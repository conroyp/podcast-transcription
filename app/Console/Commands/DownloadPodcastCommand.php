<?php

namespace App\Console\Commands;

use App\Models\Episode;
use App\Models\Podcast;
use App\Services\PodcastDownloadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DownloadPodcastCommand extends Command
{
    protected $signature = 'podcast:download
                            {url : The URL to the audio file}
                            {--title= : Episode title}
                            {--description= : Episode description}
                            {--podcast= : Podcast title (will create if not exists)}
                            {--guid= : Episode GUID for deduplication}
                            {--published-at= : Publication date (Y-m-d H:i:s format)}';

    protected $description = 'Download a podcast episode from a URL';

    public function handle(PodcastDownloadService $downloadService): int
    {
        $url = $this->argument('url');

        // Validate URL
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            $this->error('Invalid URL provided');

            return self::FAILURE;
        }

        try {
            // Get or create podcast
            $podcast = $this->getOrCreatePodcast();

            // Create episode record
            $episode = $this->createEpisode($podcast, $url);

            $this->info("Created episode: {$episode->title}");
            $this->info('Starting download...');

            // Download the audio file
            if ($downloadService->download($episode)) {
                $this->info('✅ Successfully downloaded episode');
                $this->info("Episode ID: {$episode->id}");
                $this->info("Local path: {$episode->local_audio_path}");
                $this->info('File size: '.$this->formatBytes($episode->file_size_bytes));
                $this->info("Duration: {$episode->formatted_duration}");

                // Automatically start transcription in headless mode
                $this->info('🎯 Starting automatic transcription...');
                $exitCode = $this->call('podcast:transcribe', [
                    'episode_id' => $episode->id,
                    '--headless' => true,
                ]);

                if ($exitCode === 0) {
                    $this->info('✅ Transcription completed successfully');
                } else {
                    $this->error('❌ Transcription failed');
                }

                return self::SUCCESS;
            } else {
                $this->error('❌ Failed to download episode');

                return self::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error('Error: '.$e->getMessage());
            Log::error('Download command failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }

    private function getOrCreatePodcast(): Podcast
    {
        $podcastTitle = $this->option('podcast') ?: 'Unknown Podcast';

        $podcast = Podcast::where('title', $podcastTitle)->first();

        if (! $podcast) {
            $podcast = Podcast::create([
                'title' => $podcastTitle,
                'description' => 'Podcast created via download command',
                'is_active' => true,
            ]);

            $this->info("Created new podcast: {$podcastTitle}");
        } else {
            $this->info("Using existing podcast: {$podcastTitle}");
        }

        return $podcast;
    }

    private function createEpisode(Podcast $podcast, string $url): Episode
    {
        $title = $this->option('title') ?: $this->generateTitleFromUrl($url);
        $description = $this->option('description');
        $guid = $this->option('guid') ?: hash('sha256', $url);
        $publishedAt = $this->option('published-at') ?
            \Carbon\Carbon::parse($this->option('published-at')) :
            now();

        // Check if episode already exists
        $existingEpisode = Episode::where('guid', $guid)->first();
        if ($existingEpisode) {
            $this->warn("Episode with GUID {$guid} already exists (ID: {$existingEpisode->id})");

            if (! $this->confirm('Do you want to re-download this episode?')) {
                throw new \Exception('Episode already exists and user chose not to re-download');
            }

            // Reset download status for re-download
            $existingEpisode->update([
                'download_status' => 'pending',
                'transcription_status' => 'pending',
                'diarization_status' => 'pending',
            ]);

            return $existingEpisode;
        }

        return Episode::create([
            'podcast_id' => $podcast->id,
            'title' => $title,
            'description' => $description,
            'audio_url' => $url,
            'guid' => $guid,
            'published_at' => $publishedAt,
            'download_status' => 'pending',
            'transcription_status' => 'pending',
            'diarization_status' => 'pending',
        ]);
    }

    private function generateTitleFromUrl(string $url): string
    {
        $filename = basename(parse_url($url, PHP_URL_PATH));
        $title = pathinfo($filename, PATHINFO_FILENAME);

        // Clean up the filename to make a reasonable title
        $title = str_replace(['-', '_'], ' ', $title);
        $title = Str::title($title);

        return $title ?: 'Episode '.date('Y-m-d H:i:s');
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = floor(log($bytes, 1024));

        return round($bytes / pow(1024, $power), 2).' '.$units[$power];
    }
}
