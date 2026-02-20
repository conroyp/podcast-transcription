<?php

namespace App\Http\Controllers;

use App\Services\EmbeddingService;
use App\Services\HybridSearchService;
use App\Services\SearchCacheService;
use App\Services\SeoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SearchController extends Controller
{
    private EmbeddingService $embeddingService;

    private HybridSearchService $hybridSearchService;

    private SearchCacheService $searchCacheService;

    private SeoService $seoService;

    // Search configuration constants
    private const DEFAULT_LIMIT = 50;

    private const DEFAULT_THRESHOLD = 0.30;

    public const SORT_LABELS = [
        'relevance' => 'Sorted by relevance',
        'newest' => 'Newest first',
        'oldest' => 'Oldest first',
    ];

    public const TYPE_LABELS = [
        'all' => 'All episode types',
        'interview' => 'Interviews only',
        'midweek_mayhem' => 'Midweek Mayhem only',
    ];

    public function __construct(
        EmbeddingService $embeddingService,
        HybridSearchService $hybridSearchService,
        SearchCacheService $searchCacheService,
        SeoService $seoService
    ) {
        $this->embeddingService = $embeddingService;
        $this->hybridSearchService = $hybridSearchService;
        $this->searchCacheService = $searchCacheService;
        $this->seoService = $seoService;
    }

    /**
     * Show the search interface and handle both regular and AJAX requests
     */
    public function index(Request $request)
    {
        // Handle AJAX requests separately
        if ($request->wantsJson() || $request->ajax() || $request->has('ajax')) {
            return $this->handleAjaxSearch($request);
        }

        // Extract request parameters
        $view = $request->get('view', 'search');
        $query = $request->get('q');
        $episodeType = $request->get('episode_type', 'all');
        $sort = $request->get('sort', 'relevance');
        $page = $request->get('page', 1);
        $episodeId = $request->get('episode');
        $segmentId = $request->get('segment');

        // Build common view data (SEO + episode metadata)
        $viewData = $this->buildBaseViewData($query, $view, $episodeId, $segmentId);

        // Route to appropriate handler based on view type and query presence
        if ($view === 'episodes') {
            return $this->handleEpisodeListingView($request, $episodeType, $sort, $page, $viewData);
        }

        if ($query && strlen(trim($query)) >= 2) {
            return $this->handleSearchView($query, $episodeType, $sort, $page, $viewData);
        }

        // Default: show welcome state
        return $this->renderSearchView(null, null, $query, $viewData);
    }

    /**
     * Build base view data including SEO metadata and episode sidebar data
     */
    private function buildBaseViewData(?string $query, string $view, ?string $episodeId, ?string $segmentId): array
    {
        $seoData = [
            'seoTitle' => $this->seoService->generateTitle($query, $view),
            'seoDescription' => $this->seoService->generateDescription($query, $view),
            'seoCanonicalUrl' => $this->seoService->generateCanonicalUrl($query, $view),
        ];

        $episodeData = $this->loadEpisodeMetadata($episodeId, $segmentId);

        return array_merge($seoData, $episodeData);
    }

    /**
     * Load episode metadata and sidebar data for a specific episode
     */
    private function loadEpisodeMetadata(?string $episodeId, ?string $segmentId): array
    {
        if (! $episodeId) {
            return ['episodeMetadata' => null, 'sidebarEpisodeData' => null];
        }

        try {
            $episode = \App\Models\Episode::with(['podcast', 'transcriptSegments' => function ($q) {
                $q->orderBy('start_time');
            }])->findOrFail($episodeId);

            return [
                'episodeMetadata' => [
                    'title' => $episode->title,
                    'description' => $episode->description ?: 'Search the What Did You Do Yesterday archives',
                    'published_at' => $episode->published_at,
                    'episode_type' => $episode->episode_type,
                    'audio_url' => $episode->audio_url,
                    'duration_seconds' => $episode->duration_seconds,
                    'segment_id' => $segmentId,
                ],
                'sidebarEpisodeData' => [
                    'episode' => [
                        'id' => $episode->id,
                        'title' => $episode->title,
                        'description' => $episode->description,
                        'published_at' => $episode->published_at,
                        'formatted_date' => $this->formatDate($episode->published_at),
                        'duration_seconds' => $episode->duration_seconds,
                        'episode_type' => $episode->episode_type,
                        'audio_url' => $episode->audio_url,
                        'podcast' => [
                            'title' => $episode->podcast->title ?? 'What Did You Do Yesterday?',
                        ],
                    ],
                    'segments' => $episode->transcriptSegments->map(fn ($segment) => [
                        'id' => $segment->id,
                        'text' => $segment->text,
                        'start_time' => $segment->start_time,
                        'end_time' => $segment->end_time,
                        'formatted_time' => \App\Models\Episode::formatDuration($segment->start_time),
                    ]),
                ],
            ];
        } catch (\Exception $e) {
            return ['episodeMetadata' => null, 'sidebarEpisodeData' => null];
        }
    }

    /**
     * Handle episode listing view requests
     */
    private function handleEpisodeListingView(Request $request, string $episodeType, string $sort, int $page, array $viewData)
    {
        $searchFilter = (string) $request->get('q', '');

        try {
            $episodeData = $this->performCachedEpisodeQuery($episodeType, $sort, $page, $searchFilter);
        } catch (\Exception $e) {
            $episodeData = ['success' => false, 'error' => 'Failed to load episodes: '.$e->getMessage()];
        }

        $episodeDataJson = json_encode($episodeData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);

        return view('search.index', array_merge($viewData, [
            'searchData' => null,
            'episodeData' => $episodeData['success'] ?? false ? $episodeData : null,
            'episodeDataJson' => $episodeDataJson,
            'view' => 'episodes',
            'query' => $searchFilter ?: null,
        ]));
    }

    /**
     * Handle search view requests with a query
     */
    private function handleSearchView(string $query, string $episodeType, string $sort, int $page, array $viewData)
    {
        // Validate parameters
        $episodeType = in_array($episodeType, ['all', 'interview', 'midweek_mayhem']) ? $episodeType : 'all';
        $sort = in_array($sort, ['relevance', 'oldest', 'newest']) ? $sort : 'relevance';

        try {
            $searchData = $this->performCachedSearch($query, $episodeType, $sort, $page);
        } catch (\Exception $e) {
            $searchData = ['success' => false, 'error' => 'Search failed: '.$e->getMessage(), 'query' => $query];
        }

        return $this->renderSearchView($searchData, null, $query, $viewData);
    }

    /**
     * Render the search view with common parameters
     */
    private function renderSearchView(?array $searchData, ?array $episodeData, ?string $query, array $viewData)
    {
        return view('search.index', array_merge($viewData, [
            'searchData' => $searchData,
            'episodeData' => $episodeData,
            'episodeDataJson' => null,
            'view' => 'search',
            'query' => $query,
        ]));
    }

    /**
     * Handle AJAX search requests
     */
    private function handleAjaxSearch(Request $request): JsonResponse
    {
        // Determine if this is a search or episode listing request
        $view = $request->input('view', 'search');

        if ($view === 'episodes') {
            return $this->handleEpisodeListingRequest($request);
        }

        $request->validate([
            'q' => 'required|string|min:2|max:500',
            'episode_type' => 'sometimes|string|in:all,interview,midweek_mayhem',
            'sort' => 'sometimes|string|in:relevance,oldest,newest',
            'view' => 'sometimes|string|in:search,episodes',
            'page' => 'sometimes|integer|min:1',
        ]);

        $query = $request->input('q');
        $episodeType = $request->input('episode_type', 'all');
        $sort = $request->input('sort', 'relevance');
        $page = (int) $request->input('page', 1);

        try {
            // Use cached search
            $searchData = $this->performCachedSearch($query, $episodeType, $sort, $page);

            return response()->json($searchData, 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);

        } catch (\Exception $e) {
            Log::error('Ajax transcript search failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Search is temporarily unavailable. Please try again.',
            ], 500, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        }
    }

    /**
     * Get search statistics
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = $this->embeddingService->getEmbeddingStats();

            return response()->json([
                'success' => true,
                'stats' => $stats,
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve search statistics', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve search statistics.',
            ], 500);
        }
    }

    /**
     * Get all transcript segments for an episode
     */
    public function getEpisodeSegments(Request $request, int $episodeId): JsonResponse
    {
        try {
            // Try to get cached segments first
            $cachedSegments = $this->searchCacheService->getCachedSegments($episodeId);
            if ($cachedSegments !== null) {
                return response()->json($cachedSegments);
            }

            $episode = \App\Models\Episode::with(['podcast', 'transcriptSegments' => function ($query) {
                $query->orderBy('start_time');
            }])->findOrFail($episodeId);

            // Format segments for the frontend
            $segments = $episode->transcriptSegments->map(function ($segment) {
                return [
                    'id' => $segment->id,
                    'text' => $segment->text,
                    'start_time' => $segment->start_time,
                    'end_time' => $segment->end_time,
                    'formatted_time' => \App\Models\Episode::formatDuration($segment->start_time),
                    'formatted_end_time' => \App\Models\Episode::formatDuration($segment->end_time),
                    'formatted_duration' => \App\Models\Episode::formatDuration($segment->end_time - $segment->start_time),
                ];
            });

            $segmentData = [
                'success' => true,
                'episode' => [
                    'id' => $episode->id,
                    'title' => $episode->title,
                    'description' => $episode->description,
                    'published_at' => $episode->published_at,
                    'formatted_date' => $this->formatDate($episode->published_at),
                    'duration_seconds' => $episode->duration_seconds,
                    'episode_type' => $episode->episode_type,
                    'audio_url' => $episode->audio_url,
                    'local_audio_path' => $episode->local_audio_path,
                    'podcast' => [
                        'title' => $episode->podcast->title,
                    ],
                ],
                'segments' => $segments,
            ];

            // Cache the results
            $this->searchCacheService->cacheSegments($episodeId, $segmentData);

            return response()->json($segmentData, 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Episode not found.',
            ], 404, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        } catch (\Exception $e) {
            Log::error('Failed to load episode segments', ['episode_id' => $episodeId, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to load episode segments.',
            ], 500, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        }
    }

    /**
     * Serve audio file for an episode
     */
    public function getEpisodeAudio(int $episodeId)
    {
        try {
            $episode = \App\Models\Episode::findOrFail($episodeId);

            // Check if we have a local audio file
            if ($episode->local_audio_path) {
                $basePath = realpath(storage_path('app/private'));
                $requestedPath = storage_path('app/private/'.$episode->local_audio_path);
                $fullPath = realpath($requestedPath);

                // Ensure the path exists and is within the base directory (prevent path traversal)
                // Also enforce .mp3 extension to prevent serving arbitrary file types
                if ($fullPath && $basePath && str_starts_with($fullPath, $basePath.DIRECTORY_SEPARATOR) && str_ends_with($fullPath, '.mp3')) {
                    return response()->file($fullPath, [
                        'Content-Type' => 'audio/mpeg',
                        'Accept-Ranges' => 'bytes',
                    ]);
                }
            }

            // Fallback to external URL (redirect)
            if ($episode->audio_url) {
                return redirect($episode->audio_url);
            }

            return response()->json([
                'success' => false,
                'error' => 'Audio file not found',
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Episode not found',
            ], 404);
        }
    }

    /**
     * Highlight search terms in text
     *
     * The input text is HTML-escaped first to prevent XSS. Only safe <mark> tags
     * are then introduced for highlighting. The returned string is safe to inject
     * via innerHTML on the frontend.
     */
    private function highlightSearchTerms(string $text, string $query, bool $isExactMatch = false): string
    {
        // Escape all HTML in the input text first to prevent XSS
        $safeText = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        if ($isExactMatch) {
            // For exact phrase matching, highlight the entire phrase
            $escapedQuery = preg_quote($query, '/');

            return preg_replace('/('.$escapedQuery.')/i', '<mark class="search-highlight">$1</mark>', $safeText);
        }

        // For semantic search, highlight individual words
        $words = preg_split('/\s+/', trim($query));
        $words = array_filter($words, function ($word) {
            return strlen($word) > 2; // Only highlight words longer than 2 characters
        });

        if (empty($words)) {
            return $safeText;
        }

        // Escape special regex characters and create pattern
        $escapedWords = array_map(fn ($w) => preg_quote($w, '/'), $words);
        $pattern = '/\b('.implode('|', $escapedWords).')\b/i';

        // Highlight matches
        return preg_replace($pattern, '<mark class="search-highlight">$1</mark>', $safeText);
    }

    /**
     * Format date for display
     */
    private function formatDate(?string $date): string
    {
        if (! $date) {
            return 'Unknown date';
        }

        try {
            return \Carbon\Carbon::parse($date)->format('M j, Y');
        } catch (\Exception $e) {
            return 'Unknown date';
        }
    }

    /**
     * Check if query is wrapped in quotes for exact phrase matching
     */
    private function isExactMatchQuery(string $query): bool
    {
        $trimmed = trim($query);

        return (str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"')) ||
               (str_starts_with($trimmed, "'") && str_ends_with($trimmed, "'"));
    }

    /**
     * Perform cached search operation
     */
    private function performCachedSearch(string $query, string $episodeType, string $sort, int $page): array
    {
        // Try to get cached results first
        $cachedResults = $this->searchCacheService->getCachedResults($query, $page, $episodeType, $sort);
        if ($cachedResults !== null) {
            return $cachedResults;
        }

        // If not cached, perform the search
        $limit = self::DEFAULT_LIMIT;
        $threshold = self::DEFAULT_THRESHOLD;

        // Check if this is an exact phrase search (wrapped in quotes)
        $isExactMatch = $this->isExactMatchQuery($query);
        $cleanQuery = $isExactMatch ? trim($query, '"\'') : $query;

        if ($isExactMatch) {
            $searchResults = $this->embeddingService->findExactPhraseSegments($cleanQuery, $limit, $episodeType, $sort, $page);
        } elseif (config('embeddings.hybrid.enabled', true)) {
            $searchResults = $this->hybridSearchService->search($cleanQuery, $limit, $threshold, $episodeType, $sort, $page);
        } else {
            $searchResults = $this->embeddingService->findSimilarSegments($cleanQuery, $limit, $threshold, $episodeType, $sort, $page);
        }

        $results = $searchResults['results'];
        $total = $searchResults['total'];

        // Format results for the frontend
        $formattedResults = collect($results)->map(function ($result) use ($cleanQuery, $isExactMatch) {
            return [
                'id' => $result['segment_id'],
                'episode_id' => $result['episode_id'],
                'text' => $result['text'],
                'highlighted_text' => $this->highlightSearchTerms($result['text'], $cleanQuery, $isExactMatch),
                'start_time' => $result['start_time'],
                'end_time' => $result['end_time'],
                'episode_title' => $result['episode_title'],
                'episode_type' => $result['episode_type'],
                'published_at' => $result['published_at'],
                'formatted_date' => $this->formatDate($result['published_at']),
                'podcast_title' => $result['podcast_title'],
                'similarity' => $result['similarity'],
                'formatted_time' => \App\Models\Episode::formatDuration($result['start_time']),
                'formatted_end_time' => \App\Models\Episode::formatDuration($result['end_time']),
                'formatted_duration' => \App\Models\Episode::formatDuration($result['end_time'] - $result['start_time']),
            ];
        });

        $searchData = [
            'success' => true,
            'results' => $formattedResults->toArray(),
            'total' => $total,
            'shown' => $formattedResults->count(),
            'query' => $query,
            'clean_query' => $cleanQuery,
            'is_exact_match' => $isExactMatch,
            'threshold' => $threshold,
            'sort' => $sort,
            'limit' => $limit,
            'page' => $page,
            'episode_type' => $episodeType,
        ];

        // Cache the results
        $this->searchCacheService->cacheResults($query, $page, $episodeType, $sort, $searchData);

        return $searchData;
    }

    /**
     * Perform cached episode query operation
     */
    private function performCachedEpisodeQuery(string $episodeType, string $sort, int $page, string $searchFilter = ''): array
    {
        // Try to get cached results first
        $cachedResults = $this->searchCacheService->getCachedEpisodeResults($episodeType, $sort, $page, $searchFilter);
        if ($cachedResults !== null) {

            return $cachedResults;
        }

        // If not cached, perform the episode query
        $episodeData = $this->getEpisodes($episodeType, $sort, $page, $searchFilter);

        // Cache the results
        $this->searchCacheService->cacheEpisodeResults($episodeType, $sort, $page, $searchFilter, $episodeData);

        return $episodeData;
    }

    /**
     * Clear search cache (administrative endpoint)
     */
    public function clearCache(): JsonResponse
    {
        try {
            $this->searchCacheService->clearAllCache();

            return response()->json([
                'success' => true,
                'message' => 'Search cache cleared successfully',
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to clear cache: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle AJAX episode listing requests
     */
    private function handleEpisodeListingRequest(Request $request): JsonResponse
    {
        $request->validate([
            'episode_type' => 'sometimes|string|in:all,interview,midweek_mayhem',
            'sort' => 'sometimes|string|in:relevance,newest,oldest',
            'page' => 'sometimes|integer|min:1',
            'view' => 'sometimes|string|in:search,episodes',
            'q' => 'sometimes|string|max:500',
        ]);

        $episodeType = $request->input('episode_type', 'all');
        $sort = $request->input('sort', 'newest');
        $page = $request->input('page', 1);
        $searchFilter = (string) $request->input('q', '');

        try {
            $episodes = $this->performCachedEpisodeQuery($episodeType, $sort, $page, $searchFilter);

            return response()->json($episodes, 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);

        } catch (\Exception $e) {
            Log::error('Ajax episode listing failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Unable to load episodes right now.',
            ], 500, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        }
    }

    /**
     * Get paginated episode listing
     */
    private function getEpisodes(string $episodeType, string $sort, int $page, string $searchFilter = ''): array
    {
        $query = \App\Models\Episode::with(['podcast']);

        // Apply episode type filter
        if ($episodeType !== 'all') {
            $query->where('episode_type', $episodeType);
        }

        // Apply search filter if provided
        if (! empty(trim($searchFilter))) {
            $searchTerm = '%'.trim($searchFilter).'%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'ILIKE', $searchTerm)
                    ->orWhere('description', 'ILIKE', $searchTerm);
            });
        }

        // Apply sorting
        switch ($sort) {
            case 'oldest':
                $query->orderBy('published_at', 'asc');
                break;
            case 'newest':
            default:
                $query->orderBy('published_at', 'desc');
                break;
        }

        // Get paginated results
        $perPage = self::DEFAULT_LIMIT;
        $episodes = $query->paginate($perPage, ['*'], 'page', $page);

        // Format episodes for frontend
        $formattedEpisodes = $episodes->getCollection()->map(function ($episode) {
            return [
                'id' => $episode->id,
                'title' => $episode->title,
                'description' => $episode->description,
                'episode_type' => $episode->episode_type,
                'published_at' => $episode->published_at?->toISOString(),
                'formatted_date' => $this->formatDate($episode->published_at?->toDateString()),
                'duration_seconds' => $episode->duration_seconds,
                'formatted_duration' => $episode->duration_seconds ? \App\Models\Episode::formatDuration($episode->duration_seconds) : null,
                'transcription_status' => $episode->transcription_status,
                'embedding_status' => $episode->embedding_status,
                'podcast' => [
                    'id' => $episode->podcast->id ?? null,
                    'title' => $episode->podcast->title ?? 'Unknown Podcast',
                ],
                // Add short description for cards
                'short_description' => $episode->description ?
                    (mb_strlen($episode->description) > 200 ?
                        mb_substr($episode->description, 0, 200).'...' :
                        $episode->description
                    ) : null,
            ];
        });

        return [
            'success' => true,
            'episodes' => $formattedEpisodes,
            'pagination' => [
                'current_page' => $episodes->currentPage(),
                'last_page' => $episodes->lastPage(),
                'per_page' => $episodes->perPage(),
                'total' => $episodes->total(),
                'from' => $episodes->firstItem(),
                'to' => $episodes->lastItem(),
            ],
            'filters' => [
                'episode_type' => $episodeType,
                'sort' => $sort,
                'q' => $searchFilter,
            ],
        ];
    }
}
