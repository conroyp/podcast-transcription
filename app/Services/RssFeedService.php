<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\Podcast;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RssFeedService
{
    public function fetchAndCreatePodcast(string $rssUrl): ?Podcast
    {
        try {
            Log::info("Fetching RSS feed: {$rssUrl}");

            $response = Http::timeout(30)->get($rssUrl);

            if (! $response->successful()) {
                Log::error("Failed to fetch RSS feed: HTTP {$response->status()}", ['url' => $rssUrl]);

                return null;
            }

            $xml = simplexml_load_string($response->body());

            if (! $xml || ! isset($xml->channel)) {
                Log::error('Invalid RSS feed format', ['url' => $rssUrl]);

                return null;
            }

            $channel = $xml->channel;

            // Create or update podcast
            $podcast = Podcast::updateOrCreate(
                ['rss_url' => $rssUrl],
                [
                    'title' => (string) $channel->title,
                    'description' => (string) $channel->description,
                    'website_url' => (string) $channel->link,
                    'author' => (string) ($channel->author ?? $channel->{'itunes:author'} ?? ''),
                    'language' => (string) ($channel->language ?? 'en'),
                    'category' => (string) ($channel->category ?? $channel->{'itunes:category'}['text'] ?? ''),
                    'image_url' => $this->extractImageUrl($channel),
                    'is_active' => true,
                    'last_checked_at' => now(),
                ]
            );

            Log::info("Created/updated podcast: {$podcast->title} (ID: {$podcast->id})");

            return $podcast;

        } catch (\Exception $e) {
            Log::error('Error fetching RSS feed: '.$e->getMessage(), [
                'url' => $rssUrl,
                'exception' => $e,
            ]);

            return null;
        }
    }

    /**
     * Fetch episodes from RSS feed.
     *
     * @param  Podcast  $podcast  The podcast to fetch episodes for
     * @param  bool  $incremental  If true, only fetch episodes newer than latest existing
     * @param  int|null  $limit  Maximum episodes to create (null = no limit)
     * @return int Number of new episodes created
     */
    public function fetchEpisodes(Podcast $podcast, bool $incremental = true, ?int $limit = 10): int
    {
        try {
            $mode = $incremental ? 'incremental' : 'full';
            Log::info("Fetching episodes ({$mode}) for podcast: {$podcast->title}");

            // Fetch and parse RSS feed
            $items = $this->fetchRssItems($podcast->rss_url);
            if ($items === null) {
                return 0;
            }

            Log::info('Found '.count($items).' episodes in RSS feed');

            // Parse and optionally sort by date
            $episodes = $this->parseEpisodeItems($items);

            // For incremental fetches, sort by date and filter
            if ($incremental) {
                // Sort episodes by publication date (oldest first)
                usort($episodes, fn ($a, $b) => $a['published_at']->timestamp <=> $b['published_at']->timestamp);

                // Filter to only new episodes
                $episodes = $this->filterNewEpisodes($podcast, $episodes, $limit);
            }

            // Create episodes
            $newEpisodes = $this->createEpisodesFromParsed($podcast, $episodes, $limit);

            // Update last checked time
            $podcast->update([
                'last_checked_at' => now(),
                'last_episode_at' => $newEpisodes > 0 ? now() : $podcast->last_episode_at,
            ]);

            Log::info("Created {$newEpisodes} new episodes for podcast: {$podcast->title}");

            return $newEpisodes;

        } catch (\Exception $e) {
            Log::error('Error fetching episodes: '.$e->getMessage(), [
                'podcast_id' => $podcast->id,
                'exception' => $e,
            ]);

            return 0;
        }
    }

    /**
     * Fetch ALL episodes from RSS feed without incremental logic.
     * Convenience method for complete ingestion/re-ingestion scenarios.
     *
     * @param  Podcast  $podcast  The podcast to fetch episodes for
     * @return int Number of new episodes created
     */
    public function fetchAllEpisodes(Podcast $podcast): int
    {
        return $this->fetchEpisodes($podcast, incremental: false, limit: null);
    }

    /**
     * Fetch and parse RSS items from a URL.
     *
     * @return array|null Array of SimpleXML items or null on failure
     */
    private function fetchRssItems(string $rssUrl): ?array
    {
        $response = Http::timeout(30)->get($rssUrl);

        if (! $response->successful()) {
            Log::error("Failed to fetch RSS feed for episodes: HTTP {$response->status()}", [
                'url' => $rssUrl,
                'status' => $response->status(),
            ]);

            return null;
        }

        $xml = simplexml_load_string($response->body());

        if (! $xml || ! isset($xml->channel->item)) {
            Log::error('No episodes found in RSS feed', [
                'url' => $rssUrl,
            ]);

            return null;
        }

        $items = [];
        foreach ($xml->channel->item as $item) {
            $items[] = $item;
        }

        return $items;
    }

    /**
     * Parse RSS items into episode data arrays.
     */
    private function parseEpisodeItems(array $items): array
    {
        $episodes = [];

        foreach ($items as $item) {
            $publishedAt = now();
            if (isset($item->pubDate)) {
                try {
                    $publishedAt = Carbon::parse((string) $item->pubDate);
                } catch (\Exception $e) {
                    Log::warning('Could not parse publication date: '.$item->pubDate);
                }
            }

            $episodes[] = [
                'item' => $item,
                'published_at' => $publishedAt,
                'guid' => (string) $item->guid,
            ];
        }

        return $episodes;
    }

    /**
     * Filter episodes to only those newer than existing episodes.
     */
    private function filterNewEpisodes(Podcast $podcast, array $episodes, ?int $limit): array
    {
        $existingGuids = $podcast->episodes()
            ->pluck('guid')
            ->flip();

        $filtered = array_filter(
            $episodes,
            fn ($episodeData) => ! $existingGuids->has($episodeData['guid'])
        );

        if (! empty($filtered)) {
            Log::info('Found '.count($filtered).' new episodes not yet in database');
        } else {
            Log::info('No new episodes found (all GUIDs already exist in database)');
        }

        return $filtered;
    }

    /**
     * Create episodes from parsed data.
     */
    private function createEpisodesFromParsed(Podcast $podcast, array $episodes, ?int $limit): int
    {
        $newEpisodes = 0;

        foreach ($episodes as $episodeData) {
            if ($this->createEpisodeFromRssItem($podcast, $episodeData['item'])) {
                $newEpisodes++;
                Log::info('Created new episode: '.$episodeData['item']->title);

                // Respect the limit
                if ($limit !== null && $newEpisodes >= $limit) {
                    Log::info("Reached limit of {$limit} new episodes");
                    break;
                }
            }
        }

        return $newEpisodes;
    }

    private function createEpisodeFromRssItem(Podcast $podcast, $item): bool
    {
        try {
            $guid = (string) $item->guid;

            // Extract audio URL from enclosure
            $audioUrl = null;
            if (isset($item->enclosure)) {
                $enclosure = $item->enclosure;
                $type = (string) $enclosure['type'];
                if (str_contains($type, 'audio')) {
                    $audioUrl = (string) $enclosure['url'];
                }
            }

            if (! $audioUrl) {
                Log::warning("No audio URL found for episode: {$item->title}");

                return false;
            }

            // Parse publication date
            $publishedAt = null;
            if (isset($item->pubDate)) {
                try {
                    $publishedAt = Carbon::parse((string) $item->pubDate);
                } catch (\Exception $e) {
                    Log::warning('Could not parse publication date: '.$item->pubDate);
                    $publishedAt = now();
                }
            } else {
                $publishedAt = now();
            }

            // Extract episode metadata
            $duration = $this->extractDuration($item);
            $fileSize = isset($item->enclosure) ? (int) $item->enclosure['length'] : null;
            $title = (string) $item->title;

            $episode = Episode::firstOrCreate(
                [
                    'podcast_id' => $podcast->id,
                    'guid' => $guid,
                ],
                [
                    'title' => $title,
                    'episode_type' => Episode::detectEpisodeType($title),
                    'description' => (string) ($item->description ?? $item->{'content:encoded'} ?? ''),
                    'audio_url' => $audioUrl,
                    'published_at' => $publishedAt,
                    'duration_seconds' => $duration,
                    'file_size_bytes' => $fileSize,
                    'audio_format' => $this->extractAudioFormat($audioUrl),
                    'episode_number' => (string) ($item->{'itunes:episode'} ?? ''),
                    'season_number' => (string) ($item->{'itunes:season'} ?? ''),
                    'download_status' => 'pending',
                    'transcription_status' => 'pending',
                    'diarization_status' => 'pending',
                ]
            );

            if (! $episode->wasRecentlyCreated) {
                Log::debug('Episode already exists, skipping: '.$title);

                return false;
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Error creating episode from RSS item: '.$e->getMessage(), [
                'item_title' => (string) $item->title ?? 'unknown',
                'exception' => $e,
            ]);

            return false;
        }
    }

    private function extractImageUrl($channel): ?string
    {
        // Try different image sources
        if (isset($channel->{'itunes:image'})) {
            return (string) $channel->{'itunes:image'}['href'];
        }

        if (isset($channel->image->url)) {
            return (string) $channel->image->url;
        }

        return null;
    }

    private function extractDuration($item): ?int
    {
        if (isset($item->{'itunes:duration'})) {
            $duration = (string) $item->{'itunes:duration'};

            // Handle different duration formats (HH:MM:SS, MM:SS, or seconds)
            if (preg_match('/^(\d+):(\d+):(\d+)$/', $duration, $matches)) {
                return ($matches[1] * 3600) + ($matches[2] * 60) + $matches[3];
            } elseif (preg_match('/^(\d+):(\d+)$/', $duration, $matches)) {
                return ($matches[1] * 60) + $matches[2];
            } elseif (is_numeric($duration)) {
                return (int) $duration;
            }
        }

        return null;
    }

    private function extractAudioFormat(string $audioUrl): string
    {
        $extension = pathinfo(parse_url($audioUrl, PHP_URL_PATH), PATHINFO_EXTENSION);

        return strtolower($extension) ?: 'mp3';
    }
}
