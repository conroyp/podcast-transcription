<?php

namespace App\Observers;

use App\Jobs\RecalculateSegmentEmbeddingJob;
use App\Models\TranscriptSegment;
use App\Services\SearchCacheService;
use Illuminate\Support\Facades\Log;

class TranscriptSegmentObserver
{
    private SearchCacheService $searchCacheService;

    public function __construct(SearchCacheService $searchCacheService)
    {
        $this->searchCacheService = $searchCacheService;
    }

    /**
     * Handle the TranscriptSegment "created" event.
     */
    public function created(TranscriptSegment $transcriptSegment): void
    {
        $this->clearSearchCache();
    }

    /**
     * Handle the TranscriptSegment "updated" event.
     */
    public function updated(TranscriptSegment $transcriptSegment): void
    {
        if ($transcriptSegment->wasChanged('text')) {
            Log::debug('TranscriptSegment text changed, queuing embedding recalculation', [
                'segment_id' => $transcriptSegment->id,
                'episode_id' => $transcriptSegment->episode_id,
            ]);
            RecalculateSegmentEmbeddingJob::dispatch($transcriptSegment->id)->afterCommit();
        }

        $this->clearSearchCache();
    }

    /**
     * Handle the TranscriptSegment "deleted" event.
     */
    public function deleted(TranscriptSegment $transcriptSegment): void
    {
        $this->clearSearchCache();
    }

    /**
     * Handle the TranscriptSegment "restored" event.
     */
    public function restored(TranscriptSegment $transcriptSegment): void
    {
        $this->clearSearchCache();
    }

    /**
     * Handle the TranscriptSegment "force deleted" event.
     */
    public function forceDeleted(TranscriptSegment $transcriptSegment): void
    {
        $this->clearSearchCache();
    }

    /**
     * Clear the search cache
     */
    private function clearSearchCache(): void
    {
        $this->searchCacheService->clearAllCache();
    }
}
