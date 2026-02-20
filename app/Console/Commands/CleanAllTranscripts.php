<?php

namespace App\Console\Commands;

use App\Models\TranscriptSegment;
use App\Services\TranscriptCleaningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanAllTranscripts extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'transcripts:clean-all
                            {--dry-run : Show what would be changed without making changes}
                            {--show-stats : Show detailed statistics about cleaning rules usage}
                            {--episode= : Clean only segments from specific episode ID}';

    /**
     * The console command description.
     */
    protected $description = 'Clean all existing transcript segments using current cleaning rules';

    private TranscriptCleaningService $cleaningService;

    private array $usageStats = [];

    private array $ignoredStats = [];

    private int $totalProcessed = 0;

    private int $totalChanged = 0;

    private int $totalIgnored = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->cleaningService = app(TranscriptCleaningService::class);
        $isDryRun = $this->option('dry-run');
        $showStats = $this->option('show-stats');
        $episodeId = $this->option('episode');

        $this->info('🧹 Starting bulk transcript cleaning...');
        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        // Build query
        $query = TranscriptSegment::query();
        if ($episodeId) {
            $query->where('episode_id', $episodeId);
            $this->info("📄 Filtering to episode ID: {$episodeId}");
        }

        // Get total count first
        $totalCount = $query->count();
        $this->info("📊 Found {$totalCount} transcript segments to process");

        if ($totalCount === 0) {
            $this->warn('No transcript segments found');

            return 0;
        }

        // Debug: Show a sample segment
        $sample = $query->first();
        if ($sample) {
            $this->info('📝 Sample segment text: '.substr($sample->text, 0, 100).'...');
        }

        $this->info('');
        $progressBar = $this->output->createProgressBar($totalCount);
        $progressBar->start();

        // Process segments in chunks directly from database to avoid memory issues
        $chunkSize = 100;
        $offset = 0;
        $chunkCount = 0;

        do {
            $chunk = $query->orderBy('episode_id')->orderBy('start_time')
                ->offset($offset)
                ->limit($chunkSize)
                ->get();

            if ($chunk->isEmpty()) {
                break;
            }

            $chunkCount++;
            if ($chunkCount % 10 === 0) {
                // Progress update every 10 chunks (1000 segments)
                $this->info("\n📈 Processed {$chunkCount} chunks...");
            }

            if (! $isDryRun) {
                DB::beginTransaction();
            }

            try {
                foreach ($chunk as $segment) {
                    $this->processSegment($segment, $isDryRun);
                    $progressBar->advance();
                }

                if (! $isDryRun) {
                    DB::commit();
                }
            } catch (\Exception $e) {
                if (! $isDryRun) {
                    DB::rollback();
                }
                throw $e;
            }

            $offset += $chunkSize;

        } while ($chunk->count() === $chunkSize);

        $progressBar->finish();
        $this->info('');
        $this->info('');

        // Show summary
        $this->showSummary($isDryRun);

        // Show detailed stats if requested
        if ($showStats) {
            $this->showDetailedStats();
        }

        return 0;
    }

    private function processSegment(TranscriptSegment $segment, bool $isDryRun): void
    {
        $this->totalProcessed++;
        $originalText = $segment->text;

        $result = $this->cleaningService->cleanTranscript($originalText);
        $cleanedText = $result['text'];
        $shouldKeep = $result['keep'];

        // Track changes BEFORE checking if we should keep the segment
        $this->trackTextChanges($originalText, $cleanedText);

        if (! $shouldKeep) {
            $this->totalIgnored++;
            $this->trackIgnoredReason($originalText);

            if (! $isDryRun) {
                $segment->delete();
            }

            return;
        }

        // Track if text changed
        $textChanged = $cleanedText !== $originalText;

        if ($textChanged) {
            $this->totalChanged++;

            if (! $isDryRun) {
                $segment->update(['text' => $cleanedText]);
            }
        }
    }

    private function trackTextChanges(string $original, string $cleaned): void
    {
        $replacements = $this->cleaningService->getTextReplacements();

        foreach ($replacements as $search => $replace) {
            // Count occurrences in the original text
            $occurrences = substr_count($original, $search);
            if ($occurrences > 0) {
                if (! isset($this->usageStats[$search])) {
                    $this->usageStats[$search] = [
                        'count' => 0,
                        'replacement' => $replace,
                    ];
                }
                $this->usageStats[$search]['count'] += $occurrences;
            }
        }
    }

    private function trackIgnoredReason(string $text): void
    {
        $ignoredEntries = $this->cleaningService->getIgnoredEntries();
        $ignoredPatterns = $this->cleaningService->getIgnoredPatterns();

        // Check exact matches
        foreach ($ignoredEntries as $ignoredEntry) {
            if (strcasecmp(trim($text), $ignoredEntry) === 0) {
                $this->ignoredStats[$ignoredEntry] = ($this->ignoredStats[$ignoredEntry] ?? 0) + 1;

                return;
            }
        }

        // Check regex patterns
        foreach ($ignoredPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                $this->ignoredStats["REGEX: {$pattern}"] = ($this->ignoredStats["REGEX: {$pattern}"] ?? 0) + 1;

                return;
            }
        }

        // If we get here, it was ignored for an unknown reason
        $this->ignoredStats['OTHER'] = ($this->ignoredStats['OTHER'] ?? 0) + 1;
    }

    private function showSummary(bool $isDryRun): void
    {
        $this->info('📋 CLEANING SUMMARY');
        $this->info('==================');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Segments Processed', number_format($this->totalProcessed)],
                ['Segments Modified', number_format($this->totalChanged)],
                ['Segments Ignored/Deleted', number_format($this->totalIgnored)],
                ['Segments Unchanged', number_format($this->totalProcessed - $this->totalChanged - $this->totalIgnored)],
            ]
        );

        if ($isDryRun) {
            $this->warn('Note: This was a dry run - no actual changes were made');
        } else {
            $this->info('✅ All changes have been saved to the database');
        }
    }

    private function showDetailedStats(): void
    {
        $this->info('');
        $this->info('📊 DETAILED USAGE STATISTICS');
        $this->info('============================');

        if (! empty($this->usageStats)) {
            $this->info('');
            $this->info('🔄 TEXT REPLACEMENTS USED:');

            // Sort by usage count (descending)
            uasort($this->usageStats, fn ($a, $b) => $b['count'] <=> $a['count']);

            $tableData = [];
            foreach ($this->usageStats as $search => $data) {
                $tableData[] = [
                    $search,
                    $data['replacement'],
                    number_format($data['count']),
                ];
            }

            $this->table(['Original Text', 'Replacement', 'Times Used'], $tableData);
        } else {
            $this->info('No text replacements were used');
        }

        if (! empty($this->ignoredStats)) {
            $this->info('');
            $this->info('🚫 IGNORED PATTERNS USED:');

            // Sort by usage count (descending)
            arsort($this->ignoredStats);

            $tableData = [];
            foreach ($this->ignoredStats as $pattern => $count) {
                $tableData[] = [
                    $pattern,
                    number_format($count),
                ];
            }

            $this->table(['Pattern/Entry', 'Times Used'], $tableData);
        } else {
            $this->info('No entries were ignored');
        }
    }
}
