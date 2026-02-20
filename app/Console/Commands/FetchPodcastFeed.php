<?php

namespace App\Console\Commands;

use App\Jobs\ProcessEpisodeCompleteJob;
use App\Models\Podcast;
use App\Services\RssFeedService;
use Illuminate\Console\Command;

class FetchPodcastFeed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'podcast:fetch {url? : RSS feed URL (optional if podcast exists)} {--episodes=5 : Number of episodes to fetch} {--process : Also dispatch processing jobs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch a podcast RSS feed and create episodes';

    /**
     * Execute the console command.
     */
    public function handle(RssFeedService $rssService): int
    {
        $url = $this->argument('url');
        $episodeLimit = (int) $this->option('episodes');
        $shouldProcess = $this->option('process');

        // If no URL provided, try to find an existing active podcast
        if (! $url) {
            $podcast = Podcast::where('is_active', true)->first();

            if (! $podcast || ! $podcast->rss_url) {
                $this->error('No URL provided and no active podcast with RSS URL found.');
                $this->info('Either provide a URL or ensure you have an active podcast with an RSS URL.');

                return 1;
            }

            $url = $podcast->rss_url;
            $this->info("Using RSS URL from existing podcast: {$podcast->title}");
        }

        $this->info("Fetching podcast feed: {$url}");

        // Create/update podcast
        $podcast = $rssService->fetchAndCreatePodcast($url);

        if (! $podcast) {
            $this->error('Failed to fetch or create podcast from RSS feed');

            return 1;
        }

        $this->info("✓ Podcast: {$podcast->title}");
        $this->info('  Description: '.substr($podcast->description, 0, 100).'...');
        $this->info("  Author: {$podcast->author}");

        // Fetch episodes
        $this->info("\nFetching up to {$episodeLimit} episodes...");
        $newEpisodes = $rssService->fetchEpisodes($podcast, $episodeLimit);

        if ($newEpisodes === 0) {
            $this->warn('No new episodes found');

            return 0;
        }

        $this->info("✓ Created {$newEpisodes} new episodes");

        // Display episode details
        $episodes = $podcast->episodes()->orderBy('published_at', 'desc')->limit($episodeLimit)->get();

        $this->newLine();
        $this->info('Episodes:');
        foreach ($episodes as $episode) {
            $this->line("  • {$episode->title}");
            $this->line("    Published: {$episode->published_at->format('Y-m-d H:i')}");
            $this->line("    Audio: {$episode->audio_url}");
            $this->line("    Status: {$episode->download_status}/{$episode->transcription_status}");
            $this->newLine();
        }

        // Optionally dispatch processing jobs
        if ($shouldProcess) {
            $this->info('Dispatching processing jobs...');
            foreach ($episodes as $episode) {
                ProcessEpisodeCompleteJob::dispatch($episode);
                $this->line("  • Dispatched job for: {$episode->title}");
            }
        } else {
            $this->info('Use --process flag to also dispatch processing jobs');
        }

        return 0;
    }
}
