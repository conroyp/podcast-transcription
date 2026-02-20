<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class SearchCacheService
{
    private const CACHE_PREFIX = 'search_results:';

    private const EPISODE_CACHE_PREFIX = 'episode_results:';

    private const SEGMENT_CACHE_PREFIX = 'episode_segments:';

    private const CACHE_TAG = 'search_cache';

    private const CACHE_TTL_HOURS = 24;

    /**
     * Generate cache key for search parameters
     */
    public function generateCacheKey(string $query, int $page, string $episodeType, string $sort): string
    {
        // Normalize parameters for consistent caching
        $normalizedQuery = trim(strtolower($query));
        $normalizedEpisodeType = $episodeType ?: 'all';
        $normalizedSort = $sort ?: 'relevance';

        // Create a hash of the parameters for a clean cache key
        $parameters = [
            'query' => $normalizedQuery,
            'page' => $page,
            'episode_type' => $normalizedEpisodeType,
            'sort' => $normalizedSort,
        ];

        $hash = hash('sha256', json_encode($parameters, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE));

        return self::CACHE_PREFIX.$hash;
    }

    /**
     * Get cached search results
     */
    public function getCachedResults(string $query, int $page, string $episodeType, string $sort): ?array
    {
        $cacheKey = $this->generateCacheKey($query, $page, $episodeType, $sort);

        return Cache::tags([self::CACHE_TAG])->get($cacheKey);
    }

    /**
     * Cache search results indefinitely
     */
    public function cacheResults(string $query, int $page, string $episodeType, string $sort, array $results): void
    {
        $cacheKey = $this->generateCacheKey($query, $page, $episodeType, $sort);

        Cache::tags([self::CACHE_TAG])->put($cacheKey, $results, now()->addHours(self::CACHE_TTL_HOURS));
    }

    /**
     * Clear all search cache
     */
    public function clearAllCache(): void
    {
        Cache::tags([self::CACHE_TAG])->flush();
    }

    /**
     * Generate cache key for episode listing parameters
     */
    public function generateEpisodeCacheKey(string $episodeType, string $sort, int $page, string $searchFilter = ''): string
    {
        // Normalize parameters for consistent caching
        $normalizedEpisodeType = $episodeType ?: 'all';
        $normalizedSort = $sort ?: 'newest';
        $normalizedSearchFilter = trim(strtolower($searchFilter));

        // Create a hash of the parameters for a clean cache key
        $parameters = [
            'episode_type' => $normalizedEpisodeType,
            'sort' => $normalizedSort,
            'page' => $page,
            'search_filter' => $normalizedSearchFilter,
        ];

        $hash = hash('sha256', json_encode($parameters, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE));

        return self::EPISODE_CACHE_PREFIX.$hash;
    }

    /**
     * Get cached episode results
     */
    public function getCachedEpisodeResults(string $episodeType, string $sort, int $page, string $searchFilter = ''): ?array
    {
        $cacheKey = $this->generateEpisodeCacheKey($episodeType, $sort, $page, $searchFilter);

        return Cache::tags([self::CACHE_TAG])->get($cacheKey);
    }

    /**
     * Cache episode results indefinitely
     */
    public function cacheEpisodeResults(string $episodeType, string $sort, int $page, string $searchFilter, array $results): void
    {
        $cacheKey = $this->generateEpisodeCacheKey($episodeType, $sort, $page, $searchFilter);

        Cache::tags([self::CACHE_TAG])->put($cacheKey, $results, now()->addHours(self::CACHE_TTL_HOURS));
    }

    /**
     * Generate cache key for episode segments
     */
    public function generateSegmentCacheKey(int $episodeId): string
    {
        return self::SEGMENT_CACHE_PREFIX.$episodeId;
    }

    /**
     * Get cached episode segments
     */
    public function getCachedSegments(int $episodeId): ?array
    {
        $cacheKey = $this->generateSegmentCacheKey($episodeId);

        return Cache::tags([self::CACHE_TAG])->get($cacheKey);
    }

    /**
     * Cache episode segments indefinitely
     */
    public function cacheSegments(int $episodeId, array $segments): void
    {
        $cacheKey = $this->generateSegmentCacheKey($episodeId);

        Cache::tags([self::CACHE_TAG])->put($cacheKey, $segments, now()->addHours(self::CACHE_TTL_HOURS));
    }
}
