<?php

namespace App\Console\Commands;

use App\Jobs\ProcessEpisodeCompleteJob;
use App\Services\RssFeedService;
use Illuminate\Console\Command;

class IngestPodcastComplete extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'podcast:ingest-complete
                           {--rss-url= : RSS feed URL}
                           {--limit= : Limit number of episodes to process}
                           {--oldest-first : Process episodes from oldest to newest}
                           {--force-download : Force re-download even if already downloaded}
                           {--force-transcription : Force re-transcription even if already transcribed}
                           {--skip-ad-removal : Skip ad removal step}
                           {--skip-chunking : Skip transcript chunking step}
                           {--skip-embedding : Skip embedding generation step}
                           {--sync : Run jobs synchronously instead of queuing them}
                           {--dry-run : Show what would be processed without doing it}';

    /**
     * The console command description.
     */
    protected $description = 'Complete podcast ingestion pipeline using unified job system';

    /**
     * Execute the console command.
     */
    public function handle(RssFeedService $rssService)
    {
        $rssUrl = $this->option('rss-url') ?? config('podcast.default_rss_url');

        if (empty($rssUrl)) {
            $this->error('No RSS URL provided. Set default_rss_url in config/podcast.php (or theme config) or use --rss-url option.');

            return 1;
        }

        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $oldestFirst = $this->option('oldest-first');
        $dryRun = $this->option('dry-run');
        $sync = $this->option('sync');

        $jobOptions = [
            'force_download' => $this->option('force-download'),
            'force_transcription' => $this->option('force-transcription'),
            'skip_ad_removal' => $this->option('skip-ad-removal'),
            'skip_chunking' => $this->option('skip-chunking'),
            'skip_embedding' => $this->option('skip-embedding'),
        ];

        $this->info('Complete Podcast Ingestion Pipeline');
        $this->line("RSS URL: {$rssUrl}");
        $this->line('Order: '.($oldestFirst ? 'Oldest first' : 'Newest first'));
        if ($limit) {
            $this->line("Limit: {$limit} episodes");
        }
        $this->line('Mode: '.($sync ? 'Synchronous' : 'Queued'));
        if ($dryRun) {
            $this->warn('DRY RUN: No actual processing will occur');
        }
        $this->newLine();

        try {
            // Step 1: Ingest RSS feed
            $this->line('📡 Ingesting RSS feed...');
            $podcast = $rssService->fetchAndCreatePodcast($rssUrl);

            if (! $podcast) {
                $this->error('❌ Failed to ingest RSS feed');

                return 1;
            }

            $this->info("✅ Podcast: {$podcast->title}");

            // Step 2: Fetch all episodes
            $this->line('📥 Fetching all episodes from RSS...');
            $episodeCount = $rssService->fetchAllEpisodes($podcast);
            $this->info("✅ Fetched {$episodeCount} episodes from RSS");

            // Step 3: Get episodes that need processing, in desired order, then apply limit
            $episodesQuery = $podcast->episodes();

            // Only process episodes that haven't been fully processed yet
            // unless force options are specified
            if (! $this->option('force-download') && ! $this->option('force-transcription')) {
                // Find episodes that either:
                // 1. Haven't been transcribed, OR
                // 2. Are transcribed but don't have embeddings yet
                $episodesQuery->where(function ($query) {
                    $query->where('transcription_status', '!=', 'completed')
                        ->orWhereDoesntHave('transcriptSegments', function ($subQuery) {
                            $subQuery->where('embedding_status', 'completed');
                        });
                });
            }

            if ($oldestFirst) {
                $episodesQuery->orderBy('published_at', 'asc');
            } else {
                $episodesQuery->orderBy('published_at', 'desc');
            }

            if ($limit) {
                $episodesQuery->limit($limit);
            }

            $episodesQuery->withCount([
                'transcriptSegments',
                'transcriptSegments as embeddings_count' => fn ($q) => $q->where('embedding_status', 'completed'),
            ]);

            $episodes = $episodesQuery->get();
            $totalEpisodes = $episodes->count();
            $totalInDb = $podcast->episodes()->count();

            $this->info("📊 Found {$totalEpisodes} episodes to process (out of {$totalInDb} total episodes)");
            $this->newLine();

            if ($dryRun) {
                $this->showDryRunInfo($episodes, $jobOptions);

                return 0;
            }

            // Step 4: Process episodes
            $processed = 0;
            $failed = 0;

            $progressBar = $this->output->createProgressBar($totalEpisodes);
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');

            foreach ($episodes as $episode) {
                $progressBar->setMessage('Processing: '.substr($episode->title, 0, 40).'...');

                try {
                    if ($sync) {
                        // Run synchronously
                        $job = new ProcessEpisodeCompleteJob($episode, $jobOptions);
                        $job->handle();
                    } else {
                        // Queue the job
                        ProcessEpisodeCompleteJob::dispatch($episode, $jobOptions);
                    }

                    $processed++;

                } catch (\Exception $e) {
                    $failed++;
                    $this->newLine();
                    $this->error("❌ Failed to process episode {$episode->id}: {$e->getMessage()}");
                }

                $progressBar->advance();

                // Brief pause for synchronous processing
                if ($sync) {
                    usleep(100000); // 0.1 second
                }
            }

            $progressBar->finish();
            $this->newLine(2);

            // Final summary
            $this->info('🎉 Ingestion completed!');
            $this->newLine();

            $this->table(['Metric', 'Value'], [
                ['Total Episodes', $totalEpisodes],
                ['Jobs '.($sync ? 'Executed' : 'Queued'), $processed],
                ['Failed', $failed],
                ['Success Rate', round(($processed / max($totalEpisodes, 1)) * 100, 1).'%'],
            ]);

            if (! $sync && $processed > 0) {
                $this->newLine();
                $this->info('💡 Jobs have been queued. Monitor progress with: php artisan queue:work');
            }

            return $failed > 0 ? 1 : 0;

        } catch (\Exception $e) {
            $this->error("❌ Pipeline failed: {$e->getMessage()}");

            return 1;
        }
    }

    private function showDryRunInfo($episodes, $jobOptions): void
    {
        $this->info('📋 Episodes that would be processed:');
        $this->newLine();

        $tableData = [];
        foreach ($episodes->take(10) as $episode) {
            // Use eager-loaded counts to avoid N+1 queries
            $hasTranscripts = ($episode->transcript_segments_count ?? 0) > 0;
            $hasEmbeddings = ($episode->embeddings_count ?? 0) > 0;

            $reason = 'New episode';
            if ($episode->transcription_status !== 'completed') {
                $reason = 'Needs transcription';
            } elseif (! $hasEmbeddings) {
                $reason = 'Needs embeddings';
            }

            $tableData[] = [
                $episode->id,
                substr($episode->title, 0, 40).(strlen($episode->title) > 40 ? '...' : ''),
                $episode->published_at->format('Y-m-d'),
                $episode->download_status,
                $episode->transcription_status,
                $reason,
            ];
        }

        $this->table([
            'ID',
            'Title',
            'Published',
            'Download',
            'Transcription',
            'Reason',
        ], $tableData);

        if ($episodes->count() > 10) {
            $this->line('... and '.($episodes->count() - 10).' more episodes');
        }

        $this->newLine();
        $this->info('🔧 Job Options:');
        foreach ($jobOptions as $key => $value) {
            $this->line("  {$key}: ".($value ? 'true' : 'false'));
        }

        $this->newLine();
        $this->line('💡 Note: Episodes with completed transcription and embeddings are automatically skipped');
        $this->line('    Use --force-transcription to reprocess all episodes regardless of status');
    }
}
