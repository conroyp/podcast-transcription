<?php

namespace App\Console\Commands;

use App\Models\Episode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemoveAdContent extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'transcripts:remove-ads
                            {--dry-run : Show what would be removed without making changes}
                            {--episode= : Process only specific episode ID}
                            {--show-preview : Show preview of content around the ad}';

    /**
     * The console command description.
     */
    protected $description = 'Remove advertising content from the end of podcast episodes';

    private array $adEndPatterns = [
        // Parenting Hell ads
        'episode 2 of parenting hell',
        'episode two of parenting hell',
        'series 9 episode 2 of parenting hell',
        'series nine episode two of parenting hell',
        'parenting hell',
        // Like-Minded Friends ads
        'like-minded friends',
        'like minded friends',
        'likeminded friends',
        // Jessica Napet ads
        'brand new podcast alert',
        'new podcast alert',
        'podcast alert',
        // Always Be Comedy ads (these start directly with the pattern)
        'the always be comedy podcast is where',
        'always be comedy podcast is where',
        'always be comedy podcast',
        'always be comedy',
    ];

    private array $adStartPatterns = [
        // Max Rushden ads
        'hello max rushden here',
        'hello, max rushden here',
        // Tom Allen ads
        'hello tom allen here',
        'hello, tom allen here',
        // Jessica Napet ads
        'hello, it\'s me, jessica napet',
        'hello it\'s me jessica napet',
        'hello its me jessica napet',
        'hello, its me, jessica napet',
        'jessica napet',
    ];

    private int $totalEpisodes = 0;

    private int $episodesWithAds = 0;

    private int $segmentsRemoved = 0;

    private array $adDetails = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $episodeId = $this->option('episode');
        $showPreview = $this->option('show-preview');

        $this->info('🎯 Starting ad content removal...');
        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        // Build query
        $query = Episode::query()->with('transcriptSegments');
        if ($episodeId) {
            $query->where('id', $episodeId);
            $this->info("📄 Processing episode ID: {$episodeId}");
        }

        $episodes = $query->orderBy('published_at')->get();
        $this->info("📊 Found {$episodes->count()} episodes to process");

        if ($episodes->isEmpty()) {
            $this->warn('No episodes found');

            return 0;
        }

        $this->info('');
        $progressBar = $this->output->createProgressBar($episodes->count());
        $progressBar->start();

        foreach ($episodes as $episode) {
            $this->processEpisode($episode, $isDryRun, $showPreview);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->info('');
        $this->info('');

        // Show summary
        $this->showSummary($isDryRun);

        return 0;
    }

    private function processEpisode(Episode $episode, bool $isDryRun, bool $showPreview): void
    {
        $this->totalEpisodes++;

        $segments = $episode->transcriptSegments()
            ->orderBy('start_time')
            ->get();

        if ($segments->isEmpty()) {
            return;
        }

        // Look for ad patterns in the last 10% of the episode
        $totalSegments = $segments->count();
        $searchStartIndex = max(0, (int) ($totalSegments * 0.9)); // Start searching from 90% through
        $searchSegments = $segments->slice($searchStartIndex);

        // Find ALL ads in the search area, not just the first one
        $allAds = $this->findAllAds($searchSegments, $searchStartIndex);

        if (empty($allAds)) {
            return;
        }

        $this->episodesWithAds++;

        foreach ($allAds as $adInfo) {
            $actualAdStartIndex = $adInfo['start_index'];
            $adEndIndex = $adInfo['end_index'] ?? $segments->count() - 1;
            $segmentsToRemove = $segments->slice($actualAdStartIndex, $adEndIndex - $actualAdStartIndex + 1);

            $this->adDetails[] = [
                'episode_id' => $episode->id,
                'episode_title' => $episode->title,
                'ad_start_time' => $segments[$actualAdStartIndex]->start_time,
                'segments_removed' => $segmentsToRemove->count(),
                'ad_start_text' => substr($segments[$actualAdStartIndex]->text, 0, 100).'...',
            ];

            if ($showPreview) {
                $this->showAdPreview($episode, $segments, $actualAdStartIndex);
            }

            if (! $isDryRun) {
                // Remove this specific ad
                DB::transaction(function () use ($episode, $segments, $actualAdStartIndex, $adEndIndex) {
                    $this->removeSpecificAd($episode, $segments, $actualAdStartIndex, $adEndIndex);
                });
            }

            $this->segmentsRemoved += $segmentsToRemove->count();
        }
    }

    private function findAdStart($segments, int $baseIndex): ?int
    {
        $segmentArray = $segments->values()->all();

        // Step 1: Find ad end patterns in the last segments
        $adEndIndex = null;
        $foundPattern = null;
        for ($i = count($segmentArray) - 1; $i >= 0; $i--) {
            $text = strtolower($segmentArray[$i]->text);

            foreach ($this->adEndPatterns as $pattern) {
                if (stripos($text, $pattern) !== false) {
                    $adEndIndex = $i;
                    $foundPattern = $pattern;
                    break 2; // Break out of both loops
                }
            }
        }

        if ($adEndIndex === null) {
            return null; // No ad end pattern found
        }

        // Step 2: Check if this is a "direct start" ad pattern (like "Always Be Comedy")
        // These patterns don't have a separate intro, they start with the ad content itself
        $directStartPatterns = [
            'the always be comedy podcast is where',
            'always be comedy podcast is where',
            'always be comedy podcast',
        ];

        if (in_array($foundPattern, $directStartPatterns)) {
            // For direct start patterns, the ad starts where we found the pattern
            return $baseIndex + $adEndIndex;
        }

        // Step 3: For patterns with intros, work backwards to find "Hello, Max Rushden here" etc.
        // Look back much further as the intro might be many segments before
        $searchStart = max(0, $adEndIndex - 20); // Look back up to 20 segments

        // First, try to find exact matches
        for ($i = $adEndIndex; $i >= $searchStart; $i--) {
            $text = strtolower($segmentArray[$i]->text);

            foreach ($this->adStartPatterns as $pattern) {
                if (stripos($text, $pattern) !== false) {
                    return $baseIndex + $i; // Return absolute index
                }
            }
        }

        // Step 4: If no exact match, look for partial matches across segments
        // This handles cases where "Hello" and "Max Rushden here" or "Tom Allen here" are in different segments
        $adStartIndex = $this->findPartialAdStart($segmentArray, $searchStart, $adEndIndex);
        if ($adStartIndex !== null) {
            return $baseIndex + $adStartIndex;
        }

        // If we found ad end pattern but no intro,
        // assume the ad starts at the segment with the ad end pattern
        return $baseIndex + $adEndIndex;
    }

    private function findPartialAdStart(array $segmentArray, int $searchStart, int $adEndIndex): ?int
    {
        // Look for "hello" followed by "max", "tom", or "jessica" in nearby segments
        for ($i = $searchStart; $i <= $adEndIndex; $i++) {
            $text = strtolower($segmentArray[$i]->text);

            // Check if this segment contains "hello"
            if (stripos($text, 'hello') !== false) {
                // Look in the next few segments for "max rushden", "tom allen", "jessica napet", or just "max"/"tom"/"jessica"
                for ($j = $i; $j <= min($i + 3, $adEndIndex); $j++) {
                    $nextText = strtolower($segmentArray[$j]->text);
                    if (stripos($nextText, 'max rushden') !== false ||
                        stripos($nextText, 'tom allen') !== false ||
                        stripos($nextText, 'jessica napet') !== false ||
                        (stripos($nextText, 'max') !== false && stripos($nextText, 'here') !== false) ||
                        (stripos($nextText, 'tom') !== false && stripos($nextText, 'here') !== false) ||
                        (stripos($nextText, 'jessica') !== false)) {
                        return $i; // Return the index where "hello" was found
                    }
                }
            }

            // Also check for just "max rushden here", "tom allen here", or "jessica napet" without "hello" in same segment
            if (stripos($text, 'max rushden here') !== false ||
                stripos($text, 'tom allen here') !== false ||
                stripos($text, 'jessica napet') !== false) {
                return $i;
            }
        }

        return null;
    }

    private function showAdPreview(Episode $episode, $segments, int $adStartIndex): void
    {
        $this->info("\n📺 Episode {$episode->id}: {$episode->title}");
        $this->info("🎯 Ad detected at segment {$adStartIndex}");

        // Show 2 segments before and 3 segments after the ad start
        $previewStart = max(0, $adStartIndex - 2);
        $previewEnd = min($segments->count() - 1, $adStartIndex + 3);

        for ($i = $previewStart; $i <= $previewEnd; $i++) {
            $segment = $segments[$i];
            $marker = $i === $adStartIndex ? '🚨 AD START: ' : '  ';
            $timestamp = gmdate('H:i:s', $segment->start_time);
            $this->line($marker."[{$timestamp}] ".substr($segment->text, 0, 80).'...');
        }
        $segmentsToRemoveCount = $segments->count() - $adStartIndex;
        $this->info("📊 Would remove {$segmentsToRemoveCount} segments\n");
    }

    private function showSummary(bool $isDryRun): void
    {
        $this->info('📋 AD REMOVAL SUMMARY');
        $this->info('====================');

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Episodes Processed', $this->totalEpisodes],
                ['Episodes with Ads Found', $this->episodesWithAds],
                ['Segments Removed', number_format($this->segmentsRemoved)],
                ['Episodes Without Ads', $this->totalEpisodes - $this->episodesWithAds],
            ]
        );

        if (! empty($this->adDetails)) {
            $this->info("\n📺 EPISODES WITH ADS DETECTED:");

            $tableData = [];
            foreach ($this->adDetails as $detail) {
                $tableData[] = [
                    $detail['episode_id'],
                    substr($detail['episode_title'], 0, 40).'...',
                    gmdate('H:i:s', $detail['ad_start_time']),
                    $detail['segments_removed'],
                    substr($detail['ad_start_text'], 0, 50).'...',
                ];
            }

            $this->table(
                ['Episode ID', 'Title', 'Ad Start Time', 'Segments Removed', 'Ad Start Text'],
                $tableData
            );
        }

        if ($isDryRun) {
            $this->warn("\nNote: This was a dry run - no actual changes were made");
            $this->info('Run without --dry-run to actually remove the ad content');
        } else {
            $this->info("\n✅ Ad content has been removed from {$this->episodesWithAds} episodes");
        }
    }

    private function findAllAds($segments, int $baseIndex): array
    {
        $segmentArray = $segments->values()->all();
        $allAds = [];

        // Find all ad end patterns in the search segments
        for ($i = 0; $i < count($segmentArray); $i++) {
            $text = strtolower($segmentArray[$i]->text);

            // Check for exact matches first
            foreach ($this->adEndPatterns as $pattern) {
                if (stripos($text, $pattern) !== false) {
                    // Found an ad pattern, now find where this specific ad starts
                    $adStartIndex = $this->findAdStartForPattern($segmentArray, $i, $pattern, $baseIndex);

                    if ($adStartIndex !== null) {
                        $allAds[] = [
                            'start_index' => $adStartIndex,
                            'pattern' => $pattern,
                            'pattern_index' => $baseIndex + $i,
                        ];
                    }
                }
            }

            // Check for split patterns across adjacent segments
            if ($i < count($segmentArray) - 1) {
                $combinedText = $text.' '.strtolower($segmentArray[$i + 1]->text);

                foreach ($this->adEndPatterns as $pattern) {
                    if (stripos($combinedText, $pattern) !== false) {
                        // Check if we already found this pattern in the current segment
                        $alreadyFound = false;
                        foreach ($allAds as $existingAd) {
                            if ($existingAd['pattern_index'] >= $baseIndex + $i &&
                                $existingAd['pattern_index'] <= $baseIndex + $i + 1) {
                                $alreadyFound = true;
                                break;
                            }
                        }

                        if (! $alreadyFound) {
                            // Found a split ad pattern, find where this specific ad starts
                            $adStartIndex = $this->findAdStartForPattern($segmentArray, $i + 1, $pattern, $baseIndex);

                            if ($adStartIndex !== null) {
                                $allAds[] = [
                                    'start_index' => $adStartIndex,
                                    'pattern' => $pattern,
                                    'pattern_index' => $baseIndex + $i + 1, // Use the second segment as reference
                                ];
                            }
                        }
                    }
                }
            }
        }

        // Sort ads by start index (earliest first) and remove duplicates
        usort($allAds, function ($a, $b) {
            return $a['start_index'] <=> $b['start_index'];
        });

        // Remove overlapping ads (keep the earliest start)
        $filteredAds = [];
        $lastEndIndex = -1;

        foreach ($allAds as $ad) {
            if ($ad['start_index'] > $lastEndIndex) {
                $filteredAds[] = $ad;
                $lastEndIndex = $ad['start_index'] + 50; // Assume ads are at least 50 segments apart
            }
        }

        return $filteredAds;
    }

    private function findAdStartForPattern(array $segmentArray, int $patternIndex, string $pattern, int $baseIndex): ?int
    {
        // Check if this is a "direct start" ad pattern (like "Always Be Comedy")
        $directStartPatterns = [
            'the always be comedy podcast is where',
            'always be comedy podcast is where',
            'always be comedy podcast',
        ];

        if (in_array($pattern, $directStartPatterns)) {
            // For direct start patterns, the ad starts where we found the pattern
            return $baseIndex + $patternIndex;
        }

        // For patterns with intros, work backwards to find "Hello, Max Rushden here" etc.
        $searchStart = max(0, $patternIndex - 20); // Look back up to 20 segments

        // First, try to find exact matches
        for ($i = $patternIndex; $i >= $searchStart; $i--) {
            $text = strtolower($segmentArray[$i]->text);

            foreach ($this->adStartPatterns as $startPattern) {
                if (stripos($text, $startPattern) !== false) {
                    return $baseIndex + $i; // Return absolute index
                }
            }
        }

        // If no exact match, look for partial matches across segments
        $adStartIndex = $this->findPartialAdStart($segmentArray, $searchStart, $patternIndex);
        if ($adStartIndex !== null) {
            return $baseIndex + $adStartIndex;
        }

        // If we found ad pattern but no intro, assume the ad starts at the pattern
        return $baseIndex + $patternIndex;
    }

    private function removeSpecificAd(Episode $episode, $segments, int $startIndex, int $endIndex): void
    {
        // Remove segments from startIndex to endIndex (inclusive)
        for ($i = $startIndex; $i <= min($endIndex, $segments->count() - 1); $i++) {
            if (isset($segments[$i])) {
                $segments[$i]->delete();
            }
        }
    }
}
