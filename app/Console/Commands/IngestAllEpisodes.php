<?php

namespace App\Console\Commands;

use App\Jobs\ProcessEpisodeCompleteJob;
use App\Services\RssFeedService;
use Illuminate\Console\Command;

class IngestAllEpisodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'podcast:ingest-all
                           {--rss-url= : RSS feed URL}
                           {--limit= : Limit number of episodes to process}
                           {--oldest-first : Process episodes from oldest to newest}
                           {--dry-run : Show what would be processed without doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ingest all episodes from RSS feed with full processing pipeline';

    private RssFeedService $rssService;

    public function __construct(RssFeedService $rssService)
    {
        parent::__construct();
        $this->rssService = $rssService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $rssUrl = $this->option('rss-url') ?? config('podcast.default_rss_url');

        if (empty($rssUrl)) {
            $this->error('No RSS URL provided. Set default_rss_url in config/podcast.php (or theme config) or use --rss-url option.');

            return 1;
        }

        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $oldestFirst = $this->option('oldest-first');
        $dryRun = $this->option('dry-run');

        $this->info('Starting complete podcast ingestion pipeline');
        $this->line("RSS URL: {$rssUrl}");
        $this->line('Order: '.($oldestFirst ? 'Oldest first' : 'Newest first'));
        if ($limit) {
            $this->line("Limit: {$limit} episodes");
        }
        if ($dryRun) {
            $this->warn('DRY RUN: No actual processing will occur');
        }
        $this->newLine();

        try {
            // Step 1: Ingest RSS feed
            $this->line('📡 Ingesting RSS feed...');
            $podcast = $this->rssService->fetchAndCreatePodcast($rssUrl);

            if (! $podcast) {
                $this->error('❌ Failed to ingest RSS feed');

                return 1;
            }

            $this->info("✅ Podcast: {$podcast->title}");

            // Fetch ALL episodes first (no limit during fetch)
            $this->line('📥 Fetching all episodes from RSS...');
            $episodeCount = $this->rssService->fetchAllEpisodes($podcast); // Get all available episodes
            $this->info("✅ Fetched {$episodeCount} episodes from RSS");

            // Step 2: Get episodes that need processing, in desired order, then apply limit
            $episodesQuery = $podcast->episodes();

            // Only process episodes that haven't been fully processed yet
            $episodesQuery->where(function ($query) {
                $query->where('transcription_status', '!=', 'completed')
                    ->orWhereDoesntHave('transcriptSegments', function ($subQuery) {
                        $subQuery->where('embedding_status', 'completed');
                    });
            });

            if ($oldestFirst) {
                $episodesQuery->orderBy('published_at', 'asc');
            } else {
                $episodesQuery->orderBy('published_at', 'desc');
            }

            // Apply limit AFTER ordering
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
                $this->showDryRunInfo($episodes);

                return 0;
            }

            // Step 3: Process each episode through the full pipeline
            $processed = 0;
            $failed = 0;

            $progressBar = $this->output->createProgressBar($totalEpisodes);
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');

            foreach ($episodes as $episode) {
                $progressBar->setMessage('Processing: '.substr($episode->title, 0, 40).'...');

                try {
                    // Download, transcribe, clean, chunk, and embed in one pipeline
                    $success = $this->processEpisode($episode);

                    if ($success) {
                        $processed++;
                    } else {
                        $failed++;
                    }

                } catch (\Exception $e) {
                    $failed++;
                    $this->newLine();
                    $this->error("❌ Failed to process episode {$episode->id}: {$e->getMessage()}");
                }

                $progressBar->advance();

                // Brief pause to prevent overwhelming the system
                usleep(500000); // 0.5 second
            }

            $progressBar->finish();
            $this->newLine(2);

            // Final summary
            $this->info('🎉 Pipeline completed!');
            $this->newLine();

            $this->table(['Metric', 'Value'], [
                ['Total Episodes', $totalEpisodes],
                ['Successfully Processed', $processed],
                ['Failed', $failed],
                ['Success Rate', round(($processed / max($totalEpisodes, 1)) * 100, 1).'%'],
            ]);

            return $failed > 0 ? 1 : 0;

        } catch (\Exception $e) {
            $this->error("❌ Pipeline failed: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Filter out null and false values from an options array before forwarding to another command.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public static function mapLegacyOptions(array $options): array
    {
        return array_filter($options, fn ($value) => $value !== null && $value !== false);
    }

    private function processEpisode($episode): bool
    {
        try {
            $this->newLine();
            $this->line("🔄 Dispatching job for: {$episode->title}");

            // Dispatch the unified job for complete processing to the queue
            ProcessEpisodeCompleteJob::dispatch($episode);

            $this->line('✅ Job dispatched successfully');

            return true;

        } catch (\Exception $e) {
            $this->error('❌ Failed to dispatch job for episode: '.$e->getMessage());

            return false;
        }
    }

    private function showDryRunInfo($episodes): void
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
        $this->line('💡 Note: Episodes with completed transcription and embeddings are automatically skipped');
    }
}
