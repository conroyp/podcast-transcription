<?php

namespace App\Observers;

use App\Models\Episode;
use App\Services\SearchCacheService;

class EpisodeObserver
{
    private SearchCacheService $searchCacheService;

    public function __construct(SearchCacheService $searchCacheService)
    {
        $this->searchCacheService = $searchCacheService;
    }

    /**
     * Handle the Episode "created" event.
     */
    public function created(Episode $episode): void
    {
        $this->clearSearchCache();
    }

    /**
     * Handle the Episode "updated" event.
     */
    public function updated(Episode $episode): void
    {
        $this->clearSearchCache();
    }

    /**
     * Handle the Episode "deleted" event.
     */
    public function deleted(Episode $episode): void
    {
        $this->clearSearchCache();
    }

    /**
     * Handle the Episode "restored" event.
     */
    public function restored(Episode $episode): void
    {
        $this->clearSearchCache();
    }

    /**
     * Handle the Episode "force deleted" event.
     */
    public function forceDeleted(Episode $episode): void
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
