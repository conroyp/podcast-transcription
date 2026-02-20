<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\TranscriptSegment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TranscriptChunkingService
{
    private int $minWords;

    private int $maxWords;

    private int $idealWords;

    private float $naturalBreakPause;

    public function __construct()
    {
        $this->minWords = config('transcript_chunking.min_words', 50);
        $this->maxWords = config('transcript_chunking.max_words', 200);
        $this->idealWords = config('transcript_chunking.ideal_words', 100);
        $this->naturalBreakPause = config('transcript_chunking.natural_break_pause', 3.0);
    }

    /**
     * Chunk segments for a specific episode
     *
     * @return array ['original_count' => int, 'chunked_count' => int, 'deleted_count' => int]
     */
    public function chunkEpisodeSegments(Episode $episode): array
    {
        $segments = $episode->transcriptSegments()
            ->orderBy('start_time')
            ->get();

        if ($segments->isEmpty()) {
            return ['original_count' => 0, 'chunked_count' => 0, 'deleted_count' => 0];
        }

        $originalCount = $segments->count();

        Log::info("Starting chunking for episode {$episode->id}", [
            'episode_title' => $episode->title,
            'original_segment_count' => $originalCount,
        ]);

        $chunkedCount = $this->combineSegments($segments);
        $deletedCount = $originalCount - $chunkedCount;

        Log::info("Completed chunking for episode {$episode->id}", [
            'original_count' => $originalCount,
            'chunked_count' => $chunkedCount,
            'deleted_count' => $deletedCount,
        ]);

        return [
            'original_count' => $originalCount,
            'chunked_count' => $chunkedCount,
            'deleted_count' => $deletedCount,
        ];
    }

    /**
     * Combine segments into chunks using in-place replacement
     *
     * @return int Number of chunks created
     */
    private function combineSegments(Collection $segments): int
    {
        $chunks = $this->groupSegmentsIntoChunks($segments);
        $chunkCount = 0;

        DB::beginTransaction();

        try {
            foreach ($chunks as $chunk) {
                if (count($chunk) === 1) {
                    // Single segment, no changes needed
                    $chunkCount++;

                    continue;
                }

                // Combine multiple segments
                $this->mergeSegmentsInPlace($chunk);
                $chunkCount++;
            }

            DB::commit();

            return $chunkCount;
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error during segment chunking', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Group segments into logical chunks based on word count and natural breaks
     *
     * @return array Array of segment arrays (chunks)
     */
    private function groupSegmentsIntoChunks(Collection $segments): array
    {
        $chunks = [];
        $currentChunk = [];
        $currentWordCount = 0;

        foreach ($segments as $segment) {
            $segmentWords = str_word_count($segment->text);
            $potentialWordCount = $currentWordCount + $segmentWords;

            // Check if we should start a new chunk
            if ($this->shouldStartNewChunk($currentChunk, $segment, $currentWordCount, $segmentWords)) {
                if (! empty($currentChunk)) {
                    $chunks[] = $currentChunk;
                }
                $currentChunk = [$segment];
                $currentWordCount = $segmentWords;
            } else {
                $currentChunk[] = $segment;
                $currentWordCount = $potentialWordCount;
            }
        }

        // Add the final chunk
        if (! empty($currentChunk)) {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }

    /**
     * Determine if we should start a new chunk
     */
    private function shouldStartNewChunk(array $currentChunk, TranscriptSegment $nextSegment, int $currentWordCount, int $nextSegmentWords): bool
    {
        // If current chunk is empty, don't start new
        if (empty($currentChunk)) {
            return false;
        }

        $potentialWordCount = $currentWordCount + $nextSegmentWords;

        // Hard limit - must break if we'd exceed max words
        if ($potentialWordCount > $this->maxWords) {
            return true;
        }

        // If we haven't reached minimum, keep adding
        if ($currentWordCount < $this->minWords) {
            return false;
        }

        // Check for natural breaks if we're in the ideal range
        if ($currentWordCount >= $this->minWords && $currentWordCount <= $this->idealWords) {
            return $this->hasNaturalBreak($currentChunk, $nextSegment);
        }

        // If we're over ideal words, look for any reasonable break point
        if ($currentWordCount > $this->idealWords) {
            return $this->hasReasonableBreak($currentChunk, $nextSegment);
        }

        return false;
    }

    /**
     * Check for natural break points (speaker changes, long pauses, sentence endings)
     */
    private function hasNaturalBreak(array $currentChunk, TranscriptSegment $nextSegment): bool
    {
        $lastSegment = end($currentChunk);

        // Check for long pause between segments
        $timeDiff = $nextSegment->start_time - $lastSegment->end_time;
        if ($timeDiff >= $this->naturalBreakPause) {
            return true;
        }

        // Check if last segment ends with sentence-ending punctuation
        $lastText = trim($lastSegment->text);
        if (preg_match('/[.!?]$/', $lastText)) {
            return true;
        }

        // Check for speaker change (if we had speaker detection)
        // This could be enhanced later with actual speaker detection

        return false;
    }

    /**
     * Check for reasonable break points when over ideal word count
     */
    private function hasReasonableBreak(array $currentChunk, TranscriptSegment $nextSegment): bool
    {
        $lastSegment = end($currentChunk);
        $lastText = trim($lastSegment->text);

        // Any punctuation ending is good enough when over ideal
        if (preg_match('/[.!?,:;]$/', $lastText)) {
            return true;
        }

        // Look for natural speech boundaries
        if (preg_match('/\b(and|but|so|then|now|well|right|okay)\s*$/i', $lastText)) {
            return true;
        }

        return false;
    }

    /**
     * Merge multiple segments in place - update first segment, delete others
     */
    private function mergeSegmentsInPlace(array $segmentsToMerge): void
    {
        if (count($segmentsToMerge) < 2) {
            return;
        }

        $firstSegment = $segmentsToMerge[0];
        $otherSegments = array_slice($segmentsToMerge, 1);

        // Collect data for merged segment
        $combinedText = collect($segmentsToMerge)->pluck('text')->implode(' ');
        $startTime = $firstSegment->start_time;
        $endTime = collect($segmentsToMerge)->max('end_time');
        $weightedConfidence = $this->calculateWeightedConfidence($segmentsToMerge);

        // Update the first segment with combined data
        $firstSegment->update([
            'text' => $combinedText,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'confidence' => $weightedConfidence,
        ]);

        // Delete the other segments
        $otherIds = collect($otherSegments)->pluck('id')->toArray();
        TranscriptSegment::whereIn('id', $otherIds)->delete();
    }

    /**
     * Calculate weighted confidence based on word count
     */
    private function calculateWeightedConfidence(array $segments): float
    {
        $totalWords = 0;
        $weightedSum = 0;

        foreach ($segments as $segment) {
            $words = str_word_count($segment->text);
            $confidence = $segment->confidence ?? 0;

            $totalWords += $words;
            $weightedSum += $confidence * $words;
        }

        return $totalWords > 0 ? round($weightedSum / $totalWords, 3) : 0;
    }

    /**
     * Get current configuration
     */
    public function getConfig(): array
    {
        return [
            'min_words' => $this->minWords,
            'max_words' => $this->maxWords,
            'ideal_words' => $this->idealWords,
            'natural_break_pause' => $this->naturalBreakPause,
        ];
    }
}
