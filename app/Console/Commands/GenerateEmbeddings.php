<?php

namespace App\Console\Commands;

use App\Models\Episode;
use App\Models\TranscriptSegment;
use App\Services\EmbeddingService;
use Illuminate\Console\Command;

class GenerateEmbeddings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'podcast:generate-embeddings
                           {episode? : Process specific episode ID}
                           {--batch-size=50 : Number of segments to process in each batch}
                           {--dry-run : Show what would be processed without making changes}
                           {--force : Regenerate embeddings for already processed segments}
                           {--failed-only : Only reprocess failed segments}
                           {--stats : Show embedding statistics}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate OpenAI embeddings for transcript segments';

    private EmbeddingService $embeddingService;

    public function __construct(EmbeddingService $embeddingService)
    {
        parent::__construct();
        $this->embeddingService = $embeddingService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('stats')) {
            $this->showStats();

            return 0;
        }

        $this->info('🚀 Starting Embedding Generation');
        $this->newLine();

        // Validate OpenAI configuration
        if (! config('services.openai.api_key')) {
            $this->error('❌ OpenAI API key not configured. Please set OPENAI_API_KEY in your .env file.');

            return 1;
        }

        // Get segments to process
        $segments = $this->getSegmentsToProcess();

        if ($segments === null) {
            return 1;
        }

        if ($segments->isEmpty()) {
            $this->info('✅ No segments need processing.');

            return 0;
        }

        $totalSegments = $segments->count();
        $batchSize = (int) $this->option('batch-size');

        $this->info("📊 Found {$totalSegments} segments to process");
        $this->info("⚙️ Batch size: {$batchSize}");
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->showDryRunInfo($segments);

            return 0;
        }

        // Confirm processing
        if (! $this->option('force') && ! $this->confirm('Do you want to proceed with embedding generation?')) {
            $this->info('Operation cancelled.');

            return 0;
        }

        // Process segments in batches
        $progressBar = $this->output->createProgressBar($totalSegments);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% - %message%');
        $progressBar->setMessage('Starting...');

        $totalSuccess = 0;
        $totalFailed = 0;
        $totalCost = 0;

        foreach ($segments->chunk($batchSize) as $batch) {
            $progressBar->setMessage("Processing batch of {$batch->count()} segments...");

            try {
                $results = $this->embeddingService->processBatchSegments($batch->values()->all());

                $totalSuccess += $results['success'];
                $totalFailed += $results['failed'];

                // Estimate cost (text-embedding-3-small: $0.00002 per 1K tokens)
                $tokenCount = $batch->sum(function ($segment) {
                    return $this->estimateTokenCount($segment->text);
                });
                $totalCost += ($tokenCount / 1000) * 0.00002;

                $progressBar->advance($batch->count());

                if (! empty($results['errors'])) {
                    $this->newLine();
                    $this->warn('⚠️ Some segments in batch failed:');
                    foreach ($results['errors'] as $error) {
                        if (isset($error['segment_id'])) {
                            $this->line("   Segment {$error['segment_id']}: {$error['error']}");
                        } else {
                            $this->line("   Batch error: {$error['batch_error']}");
                        }
                    }
                }

                // Brief pause to avoid rate limiting
                usleep(100000); // 0.1 second

            } catch (\Exception $e) {
                $totalFailed += $batch->count();
                $progressBar->advance($batch->count());

                $this->newLine();
                $this->error("❌ Batch processing failed: {$e->getMessage()}");
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        // Show results
        $this->info('🎉 Embedding generation completed!');
        $this->newLine();

        $this->table(['Metric', 'Value'], [
            ['Total Processed', $totalSegments],
            ['Successful', $totalSuccess],
            ['Failed', $totalFailed],
            ['Success Rate', round(($totalSuccess / $totalSegments) * 100, 2).'%'],
            ['Estimated Cost', '$'.number_format($totalCost, 4)],
        ]);

        if ($totalFailed > 0) {
            $this->newLine();
            $this->warn("⚠️ {$totalFailed} segments failed to process. Run with --failed-only to retry them.");
        }

        return $totalFailed > 0 ? 1 : 0;
    }

    private function getSegmentsToProcess()
    {
        $query = TranscriptSegment::query();

        // Filter by episode if specified
        if ($episodeId = $this->argument('episode')) {
            $episode = Episode::find($episodeId);
            if (! $episode) {
                $this->error("Episode {$episodeId} not found.");

                return null;
            }
            $query->where('episode_id', $episodeId);
            $this->info("🎯 Processing episode: {$episode->title}");
        }

        // Filter by status
        if ($this->option('failed-only')) {
            $query->where('embedding_status', 'failed');
        } elseif ($this->option('force')) {
            // Process all segments regardless of status
        } else {
            // Only process pending segments
            $query->where('embedding_status', 'pending');
        }

        return $query->with('episode')->orderBy('episode_id', 'asc')->orderBy('segment_index', 'asc')->get();
    }

    private function showDryRunInfo($segments)
    {
        $this->info('🔍 DRY RUN - No changes will be made');
        $this->newLine();

        $episodeGroups = $segments->groupBy('episode_id');

        $this->info("Episodes to process: {$episodeGroups->count()}");
        $this->info("Total segments: {$segments->count()}");
        $this->newLine();

        // Show breakdown by episode
        $tableData = [];
        foreach ($episodeGroups as $episodeId => $episodeSegments) {
            $episode = $episodeSegments->first()->episode;
            $tokenCount = $episodeSegments->sum(function ($segment) {
                return $this->estimateTokenCount($segment->text);
            });
            $estimatedCost = ($tokenCount / 1000) * 0.00002;

            $tableData[] = [
                $episodeId,
                $episode->title ?? 'Unknown',
                $episodeSegments->count(),
                number_format($tokenCount),
                '$'.number_format($estimatedCost, 4),
            ];
        }

        $this->table(['Episode ID', 'Title', 'Segments', 'Est. Tokens', 'Est. Cost'], $tableData);

        $totalTokens = $segments->sum(function ($segment) {
            return $this->estimateTokenCount($segment->text);
        });
        $totalCost = ($totalTokens / 1000) * 0.00002;

        $this->newLine();
        $this->info('💰 Total estimated cost: $'.number_format($totalCost, 4));
    }

    private function estimateTokenCount(string $text): int
    {
        return (int) ceil(strlen($text) / 4);
    }

    private function showStats()
    {
        $this->info('📊 Embedding Statistics');
        $this->newLine();

        $stats = $this->embeddingService->getEmbeddingStats();

        $this->table(['Metric', 'Value'], [
            ['Total Segments', number_format($stats['total_segments'])],
            ['Completion Rate', $stats['completion_rate'].'%'],
            ['Model', $stats['model']],
            ['Dimensions', $stats['dimensions']],
        ]);

        $this->newLine();
        $this->info('Status Breakdown:');
        foreach ($stats['status_breakdown'] as $status => $count) {
            $percentage = round(($count / $stats['total_segments']) * 100, 2);
            $this->line("  {$status}: ".number_format($count)." ({$percentage}%)");
        }
    }
}
