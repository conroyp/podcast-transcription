<?php

namespace App\Services;

use App\Models\Episode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdRemovalService
{
    private array $adEndPatterns;

    private array $adStartPatterns;

    private array $directStartPatterns;

    private array $hostNames;

    private float $searchPercentage;

    private int $minimumSegmentsForPercentageSearch;

    private int $lookbackSegments;

    public function __construct()
    {
        $this->adEndPatterns = config('ad_removal.end_patterns', []);
        $this->adStartPatterns = config('ad_removal.start_patterns', []);
        $this->directStartPatterns = config('ad_removal.direct_start_patterns', []);
        $this->hostNames = config('ad_removal.host_names', []);
        $this->searchPercentage = config('ad_removal.search_percentage', 0.3);
        $this->minimumSegmentsForPercentageSearch = config('ad_removal.minimum_segments_for_percentage_search', 10);
        $this->lookbackSegments = config('ad_removal.lookback_segments', 20);
    }

    /**
     * Check if ad removal is enabled
     */
    public function isEnabled(): bool
    {
        return config('ad_removal.enabled', true) && ! empty($this->adEndPatterns);
    }

    /**
     * Remove ad content from an episode's transcript segments
     */
    public function removeAdsFromEpisode(Episode $episode): array
    {
        if (! $this->isEnabled()) {
            return [
                'ads_found' => 0,
                'segments_removed' => 0,
                'details' => [],
                'skipped' => 'Ad removal disabled or no patterns configured',
            ];
        }

        $segments = $episode->transcriptSegments()
            ->orderBy('start_time')
            ->get();

        if ($segments->isEmpty()) {
            return [
                'ads_found' => 0,
                'segments_removed' => 0,
                'details' => [],
            ];
        }

        // Determine search area based on config
        $totalSegments = $segments->count();
        if ($totalSegments < $this->minimumSegmentsForPercentageSearch) {
            // If there are fewer than minimum segments, search the whole episode
            $searchStartIndex = 0;
        } else {
            // Search the configured percentage from the end
            $searchStartIndex = max(0, (int) ($totalSegments * (1 - $this->searchPercentage)));
        }
        $searchSegments = $segments->slice($searchStartIndex);

        Log::debug('Searching for ads in episode', [
            'episode_id' => $episode->id,
            'search_start_index' => $searchStartIndex,
            'total_segments' => $totalSegments,
        ]);

        // Find ALL ads in the search area
        $allAds = $this->findAllAds($searchSegments, $searchStartIndex);
        Log::debug('Ad search complete', [
            'episode_id' => $episode->id,
            'ads_found' => count($allAds),
        ]);
        if (empty($allAds)) {
            return [
                'ads_found' => 0,
                'segments_removed' => 0,
                'details' => [],
            ];
        }

        $segmentsRemoved = 0;
        $adDetails = [];

        DB::transaction(function () use ($episode, $segments, $allAds, &$segmentsRemoved, &$adDetails) {
            foreach ($allAds as $adInfo) {
                $actualAdStartIndex = $adInfo['start_index'];
                $adEndIndex = $adInfo['end_index'] ?? $segments->count() - 1;
                $segmentsToRemove = $segments->slice($actualAdStartIndex, $adEndIndex - $actualAdStartIndex + 1);

                $adDetails[] = [
                    'ad_start_time' => $segments[$actualAdStartIndex]->start_time,
                    'segments_removed' => $segmentsToRemove->count(),
                    'ad_start_text' => substr($segments[$actualAdStartIndex]->text, 0, 100).'...',
                    'pattern' => $adInfo['pattern'],
                ];

                // Remove this specific ad
                $this->removeSpecificAd($episode, $segments, $actualAdStartIndex, $adEndIndex);
                $segmentsRemoved += $segmentsToRemove->count();
            }
        });

        Log::info('Ad content removed from episode', [
            'episode_id' => $episode->id,
            'ads_found' => count($allAds),
            'segments_removed' => $segmentsRemoved,
            'ad_details' => $adDetails,
        ]);

        return [
            'ads_found' => count($allAds),
            'segments_removed' => $segmentsRemoved,
            'details' => $adDetails,
        ];
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
                        // Check if we already found this pattern
                        $alreadyFound = false;
                        foreach ($allAds as $existingAd) {
                            if ($existingAd['pattern_index'] >= $baseIndex + $i &&
                                $existingAd['pattern_index'] <= $baseIndex + $i + 1) {
                                $alreadyFound = true;
                                break;
                            }
                        }

                        if (! $alreadyFound) {
                            $adStartIndex = $this->findAdStartForPattern($segmentArray, $i + 1, $pattern, $baseIndex);

                            if ($adStartIndex !== null) {
                                $allAds[] = [
                                    'start_index' => $adStartIndex,
                                    'pattern' => $pattern,
                                    'pattern_index' => $baseIndex + $i + 1,
                                ];
                            }
                        }
                    }
                }
            }
        }

        // Sort ads by start index and remove duplicates
        usort($allAds, function ($a, $b) {
            return $a['start_index'] <=> $b['start_index'];
        });

        // Remove overlapping ads
        $filteredAds = [];
        $lastEndIndex = -1;

        foreach ($allAds as $ad) {
            if ($ad['start_index'] > $lastEndIndex) {
                $filteredAds[] = $ad;
                $lastEndIndex = $ad['start_index'] + 50;
            }
        }

        return $filteredAds;
    }

    private function findAdStartForPattern(array $segmentArray, int $patternIndex, string $pattern, int $baseIndex): ?int
    {
        // Check if this is a "direct start" ad pattern (where the end pattern IS the start)
        if (in_array($pattern, $this->directStartPatterns)) {
            return $baseIndex + $patternIndex;
        }

        // For patterns with intros, work backwards to find the intro
        $searchStart = max(0, $patternIndex - $this->lookbackSegments);

        // Try to find exact matches
        for ($i = $patternIndex; $i >= $searchStart; $i--) {
            $text = strtolower($segmentArray[$i]->text);

            foreach ($this->adStartPatterns as $startPattern) {
                if (stripos($text, $startPattern) !== false) {
                    return $baseIndex + $i;
                }
            }
        }

        // Look for partial matches across segments
        $adStartIndex = $this->findPartialAdStart($segmentArray, $searchStart, $patternIndex);
        if ($adStartIndex !== null) {
            return $baseIndex + $adStartIndex;
        }

        // Default to pattern location
        return $baseIndex + $patternIndex;
    }

    private function findPartialAdStart(array $segmentArray, int $searchStart, int $adEndIndex): ?int
    {
        // Skip partial matching if no host names configured
        if (empty($this->hostNames)) {
            return null;
        }

        for ($i = $searchStart; $i <= $adEndIndex; $i++) {
            $text = strtolower($segmentArray[$i]->text);

            // Check for "hello" followed by a host name in subsequent segments
            if (stripos($text, 'hello') !== false) {
                for ($j = $i; $j <= min($i + 3, $adEndIndex); $j++) {
                    $nextText = strtolower($segmentArray[$j]->text);

                    // Check each configured host name
                    foreach ($this->hostNames as $shortName => $fullNames) {
                        // Check for full names
                        foreach ($fullNames as $fullName) {
                            if (stripos($nextText, $fullName) !== false) {
                                Log::debug('Found ad start via partial match', [
                                    'segment_index' => $i,
                                    'pattern' => $text,
                                    'host' => $fullName,
                                ]);

                                return $i;
                            }
                        }

                        // Check for short name + "here" pattern
                        if (stripos($nextText, $shortName) !== false && stripos($nextText, 'here') !== false) {
                            Log::debug('Found ad start via short name match', [
                                'segment_index' => $i,
                                'pattern' => $text,
                                'host' => $shortName,
                            ]);

                            return $i;
                        }
                    }
                }
            }

            // Check for direct "[host] here" patterns
            foreach ($this->hostNames as $shortName => $fullNames) {
                foreach ($fullNames as $fullName) {
                    if (stripos($text, $fullName.' here') !== false || stripos($text, $fullName) !== false) {
                        Log::debug('Found ad start via direct host match', [
                            'segment_index' => $i,
                            'pattern' => $text,
                            'host' => $fullName,
                        ]);

                        return $i;
                    }
                }
            }
        }

        return null;
    }

    private function removeSpecificAd(Episode $episode, $segments, int $startIndex, int $endIndex): void
    {
        for ($i = $startIndex; $i <= min($endIndex, $segments->count() - 1); $i++) {
            if (isset($segments[$i])) {
                $segments[$i]->delete();
            }
        }
    }
}
