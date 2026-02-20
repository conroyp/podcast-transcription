<?php

namespace App\Console\Commands;

use App\Models\Episode;
use App\Services\TranscriptChunkingService;
use Illuminate\Console\Command;

class ChunkTranscripts extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'transcripts:chunk
                            {--episode= : Process only a specific episode ID}
                            {--dry-run : Show what would be changed without making changes}
                            {--stats : Show detailed statistics}';

    /**
     * The console command description.
     */
    protected $description = 'Combine transcript segments into larger chunks suitable for embeddings';

    private TranscriptChunkingService $chunkingService;

    private array $totalStats = [
        'episodes_processed' => 0,
        'total_original_segments' => 0,
        'total_chunked_segments' => 0,
        'total_deleted_segments' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->chunkingService = app(TranscriptChunkingService::class);
        $isDryRun = $this->option('dry-run');
        $showStats = $this->option('stats');
        $episodeId = $this->option('episode');

        $this->info('🔗 Starting transcript chunking...');
        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        $this->showConfig();

        // Build query
        $query = Episode::whereHas('transcriptSegments');
        if ($episodeId) {
            $query->where('id', $episodeId);
            $this->info("📄 Processing only episode ID: {$episodeId}");
        }

        $episodes = $query->orderBy('published_at')->get();
        $this->info("📊 Found {$episodes->count()} episodes with transcript segments");

        if ($episodes->isEmpty()) {
            $this->warn('No episodes with transcript segments found');

            return 0;
        }

        $this->info('');
        $progressBar = $this->output->createProgressBar($episodes->count());
        $progressBar->start();

        foreach ($episodes as $episode) {
            if (! $isDryRun) {
                $stats = $this->processEpisode($episode);
            } else {
                $stats = $this->simulateProcessEpisode($episode);
            }

            $this->updateTotalStats($stats);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->info('');
        $this->info('');

        $this->showSummary($isDryRun);

        if ($showStats) {
            $this->showDetailedStats($episodes);
        }

        return 0;
    }

    private function processEpisode(Episode $episode): array
    {
        try {
            return $this->chunkingService->chunkEpisodeSegments($episode);
        } catch (\Exception $e) {
            $this->error("Error processing episode {$episode->id}: {$e->getMessage()}");

            return ['original_count' => 0, 'chunked_count' => 0, 'deleted_count' => 0];
        }
    }

    private function simulateProcessEpisode(Episode $episode): array
    {
        $segmentCount = $episode->transcriptSegments()->count();

        // Estimate chunking results without actually doing it
        $estimatedChunks = max(1, intval($segmentCount / 5)); // Rough estimate
        $estimatedDeleted = $segmentCount - $estimatedChunks;

        return [
            'original_count' => $segmentCount,
            'chunked_count' => $estimatedChunks,
            'deleted_count' => $estimatedDeleted,
        ];
    }

    private function updateTotalStats(array $stats): void
    {
        $this->totalStats['episodes_processed']++;
        $this->totalStats['total_original_segments'] += $stats['original_count'];
        $this->totalStats['total_chunked_segments'] += $stats['chunked_count'];
        $this->totalStats['total_deleted_segments'] += $stats['deleted_count'];
    }

    private function showConfig(): void
    {
        $config = $this->chunkingService->getConfig();

        $this->info('📋 CHUNKING CONFIGURATION:');
        $this->table(
            ['Parameter', 'Value'],
            [
                ['Min Words', $config['min_words']],
                ['Max Words', $config['max_words']],
                ['Ideal Words', $config['ideal_words']],
                ['Natural Break Pause', $config['natural_break_pause'].'s'],
            ]
        );
        $this->info('');
    }

    private function showSummary(bool $isDryRun): void
    {
        $reductionPercent = $this->totalStats['total_original_segments'] > 0
            ? round(($this->totalStats['total_deleted_segments'] / $this->totalStats['total_original_segments']) * 100, 1)
            : 0;

        $this->info('📊 CHUNKING SUMMARY');
        $this->info('==================');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Episodes Processed', number_format($this->totalStats['episodes_processed'])],
                ['Original Segments', number_format($this->totalStats['total_original_segments'])],
                ['Final Chunks', number_format($this->totalStats['total_chunked_segments'])],
                ['Segments Deleted', number_format($this->totalStats['total_deleted_segments'])],
                ['Reduction', "{$reductionPercent}%"],
            ]
        );

        if ($isDryRun) {
            $this->warn('Note: This was a dry run - no actual changes were made');
        } else {
            $this->info('✅ All changes have been saved to the database');
        }
    }

    private function showDetailedStats($episodes): void
    {
        $this->info('');
        $this->info('📈 DETAILED EPISODE STATISTICS');
        $this->info('==============================');

        $tableData = [];
        foreach ($episodes as $episode) {
            $segments = $episode->transcriptSegments;
            $segmentCount = $segments->count();
            $words = $segments->sum(function ($segment) {
                return str_word_count($segment->text);
            });

            $avgWordsPerSegment = $segmentCount > 0 ? round($words / $segmentCount, 1) : 0;

            $tableData[] = [
                $episode->id,
                substr($episode->title, 0, 40).'...',
                number_format($segmentCount),
                number_format($words),
                $avgWordsPerSegment,
            ];
        }

        $this->table(
            ['Episode ID', 'Title', 'Segments', 'Total Words', 'Avg Words/Segment'],
            $tableData
        );
    }
}
