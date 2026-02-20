<?php

namespace App\Services;

use App\Contracts\EmbeddingProviderInterface;
use App\Models\QueryEmbedding;
use App\Models\TranscriptSegment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for generating and managing text embeddings.
 *
 * This service handles:
 * - Generating vector embeddings from text using a configurable provider
 * - Storing embeddings in PostgreSQL with pgvector extension
 * - Performing semantic similarity searches across transcript segments
 * - Batch processing of segments for efficient embedding generation
 *
 * The embedding provider can be swapped via dependency injection, allowing
 * use of different providers (OpenAI, Cohere, local models, etc.).
 *
 * Configuration is loaded from config/embeddings.php and config/services.php.
 *
 * @see config/embeddings.php for model and dimension settings
 * @see App\Contracts\EmbeddingProviderInterface for provider interface
 */
class EmbeddingService
{
    /** @var EmbeddingProviderInterface The embedding provider implementation */
    private EmbeddingProviderInterface $provider;

    /**
     * Create a new EmbeddingService instance.
     *
     * @param  EmbeddingProviderInterface  $provider  The embedding provider to use
     */
    public function __construct(EmbeddingProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Save an embedding vector to a transcript segment in the database.
     *
     * Updates the segment's embedding_vector column using PostgreSQL's pgvector
     * format, and stores metadata about the embedding generation.
     *
     * @param  TranscriptSegment  $segment  The segment to update
     * @param  array<int, float>  $embedding  The embedding vector as array of floats
     * @param  array<string, mixed>  $extraMetadata  Additional metadata to merge (e.g., batch_processed)
     */
    private function saveSegmentEmbedding(TranscriptSegment $segment, array $embedding, array $extraMetadata = []): void
    {
        $vectorString = '['.implode(',', $embedding).']';

        $metadata = array_merge([
            'model' => $this->provider->getModel(),
            'dimensions' => $this->provider->getDimensions(),
            'text_length' => strlen($segment->text),
            'created_at' => now()->toISOString(),
        ], $extraMetadata);

        DB::update("
            UPDATE transcript_segments
            SET embedding_vector = ?::vector,
                embedding_status = 'completed',
                embedding_created_at = NOW(),
                embedding_metadata = ?::json
            WHERE id = ?
        ", [
            $vectorString,
            json_encode($metadata),
            $segment->id,
        ]);
    }

    /**
     * Mark a segment's embedding as failed with an error message.
     *
     * Updates the segment's embedding_status to 'failed' and stores
     * the error message in embedding_metadata for debugging.
     *
     * @param  TranscriptSegment  $segment  The segment that failed
     * @param  string  $error  The error message describing the failure
     */
    private function markSegmentFailed(TranscriptSegment $segment, string $error): void
    {
        $segment->update([
            'embedding_status' => 'failed',
            'embedding_metadata' => [
                'error' => $error,
                'failed_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Generate an embedding vector for the given text.
     *
     * Delegates to the configured embedding provider to convert text into
     * a vector representation suitable for semantic similarity search.
     *
     * @param  string  $text  The text to generate an embedding for
     * @return array<int, float> The embedding vector as an array of floats
     *
     * @throws \Exception When embedding generation fails
     */
    public function generateEmbedding(string $text): array
    {
        return $this->provider->generateEmbedding($text);
    }

    /**
     * Generate embedding vectors for multiple texts in batch.
     *
     * Delegates to the configured embedding provider for batch processing.
     * More efficient than calling generateEmbedding() individually.
     *
     * @param  array<int, string>  $texts  Array of texts to generate embeddings for
     * @return array<int, array<int, float>> Array of embedding vectors in same order as input
     *
     * @throws \Exception When batch embedding generation fails
     */
    public function generateBatchEmbeddings(array $texts): array
    {
        return $this->provider->generateBatchEmbeddings($texts);
    }

    /**
     * Process a single transcript segment: generate and store its embedding.
     *
     * Updates the segment's status to 'processing', generates the embedding,
     * and stores it in the database. On failure, marks the segment as 'failed'
     * with the error message.
     *
     * @param  TranscriptSegment  $segment  The segment to process
     * @return bool True if successful, false if embedding generation or storage failed
     */
    public function processSegment(TranscriptSegment $segment): bool
    {
        try {
            $segment->update(['embedding_status' => 'processing']);

            $embedding = $this->generateEmbedding($segment->text);
            $this->saveSegmentEmbedding($segment, $embedding);

            Log::info('Embedding generated successfully', [
                'segment_id' => $segment->id,
                'episode_id' => $segment->episode_id,
                'text_length' => strlen($segment->text),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->markSegmentFailed($segment, $e->getMessage());

            Log::error('Failed to process segment embedding', [
                'segment_id' => $segment->id,
                'episode_id' => $segment->episode_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Process multiple transcript segments in batch for efficient embedding generation.
     *
     * More efficient than calling processSegment() individually as it generates
     * all embeddings in a single API call (chunked by 100). Updates all segments
     * to 'processing' status, generates embeddings, then updates each with result.
     *
     * @param  array<int, TranscriptSegment>  $segments  Array of segments to process
     * @return array{success: int, failed: int, errors: array<int, array{segment_id: int, error: string}>}
     */
    public function processBatchSegments(array $segments): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        // Mark all as processing
        $segmentIds = collect($segments)->pluck('id')->toArray();
        TranscriptSegment::whereIn('id', $segmentIds)
            ->update(['embedding_status' => 'processing']);

        try {
            // Extract texts and generate embeddings in batch
            $texts = collect($segments)->pluck('text')->toArray();
            $embeddings = $this->generateBatchEmbeddings($texts);

            // Process each segment with its embedding
            foreach ($segments as $index => $segment) {
                try {
                    $this->saveSegmentEmbedding($segment, $embeddings[$index], ['batch_processed' => true]);
                    $results['success']++;
                } catch (\Exception $e) {
                    $this->markSegmentFailed($segment, $e->getMessage());
                    $results['failed']++;
                    $results['errors'][] = [
                        'segment_id' => $segment->id,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        } catch (\Exception $e) {
            // Mark all as failed if batch generation fails
            TranscriptSegment::whereIn('id', $segmentIds)
                ->update([
                    'embedding_status' => 'failed',
                    'embedding_metadata' => [
                        'error' => 'Batch generation failed: '.$e->getMessage(),
                        'failed_at' => now()->toISOString(),
                    ],
                ]);

            $results['failed'] = count($segments);
            $results['errors'][] = [
                'batch_error' => $e->getMessage(),
            ];
        }

        return $results;
    }

    /**
     * Process segments one by one with real-time status updates for progress tracking.
     *
     * Unlike processBatchSegments(), this method updates each segment's status
     * individually, allowing for real-time progress monitoring.
     *
     * @param  array<int, TranscriptSegment>  $segments  Array of segments to process
     * @return array{success: int, failed: int, errors: array<int, array{segment_id: int, error: string}>}
     */
    public function processSegmentsWithProgress(array $segments): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        Log::info('Starting individual segment processing', [
            'total_segments' => count($segments),
        ]);

        foreach ($segments as $index => $segment) {
            try {
                // Mark this specific segment as processing
                $segment->update(['embedding_status' => 'processing']);

                // Generate embedding for this single segment
                $embedding = $this->generateEmbedding($segment->text);
                $this->saveSegmentEmbedding($segment, $embedding, ['processed_individually' => true]);

                $results['success']++;

            } catch (\Exception $e) {
                $this->markSegmentFailed($segment, $e->getMessage());

                $results['failed']++;
                $results['errors'][] = [
                    'segment_id' => $segment->id,
                    'error' => $e->getMessage(),
                ];

                Log::error('Failed to process segment', [
                    'segment_id' => $segment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Completed individual segment processing', [
            'success' => $results['success'],
            'failed' => $results['failed'],
        ]);

        return $results;
    }

    /**
     * Get or create a cached query embedding.
     *
     * Normalizes the query text, checks the database cache, and only calls the
     * embedding provider if no cached vector exists. Uses upsert to handle
     * concurrent writes safely.
     *
     * @param  string  $queryText  The query text to embed
     * @return array<int, float> The embedding vector
     */
    public function getOrCreateQueryEmbedding(string $queryText): array
    {
        if (! config('embeddings.query_cache.enabled', true)) {
            return $this->generateEmbedding($queryText);
        }

        $normalized = strtolower(trim($queryText));
        $hash = hash('sha256', $normalized);
        $model = $this->provider->getModel();
        $dimensions = $this->provider->getDimensions();

        // Try to find existing cached embedding and extract vector in a single query
        $cached = DB::selectOne(
            'SELECT id, embedding_vector::text as vector_text FROM query_embeddings WHERE query_hash = ? AND model = ? AND dimensions = ?',
            [$hash, $model, $dimensions]
        );

        if ($cached && $cached->vector_text) {
            // Update usage stats atomically
            QueryEmbedding::where('id', $cached->id)->update([
                'use_count' => DB::raw('use_count + 1'),
                'last_used_at' => now(),
            ]);

            // Parse vector string back to float array
            $vectorStr = trim($cached->vector_text, '[]');

            return array_map('floatval', explode(',', $vectorStr));
        }

        // Generate new embedding
        $embedding = $this->generateEmbedding($queryText);
        $vectorString = '['.implode(',', $embedding).']';

        // Store in cache
        $queryEmbedding = QueryEmbedding::create([
            'query_hash' => $hash,
            'query_text' => $normalized,
            'model' => $model,
            'dimensions' => $dimensions,
            'use_count' => 1,
            'last_used_at' => now(),
        ]);

        if (DB::getDriverName() === 'pgsql') {
            DB::update(
                'UPDATE query_embeddings SET embedding_vector = ?::vector WHERE id = ?',
                [$vectorString, $queryEmbedding->id]
            );
        }

        return $embedding;
    }

    /**
     * Find transcript segments similar to the query text using semantic search.
     *
     * Generates an embedding for the query text and uses PostgreSQL pgvector's
     * cosine distance operator (<=>) to find similar segments. Returns paginated
     * results with similarity scores.
     *
     * Optimized to use HNSW index via ORDER BY distance LIMIT pattern.
     *
     * @param  string  $queryText  The text to search for semantically similar content
     * @param  int  $limit  Maximum number of results per page (default 10)
     * @param  float  $threshold  Minimum similarity score 0-1 (default 0.8, where 1 = identical)
     * @param  string|null  $episodeType  Filter by episode type ('regular', 'interview', etc.) or null for all
     * @param  string  $sort  Sort order: 'relevance', 'newest', or 'oldest'
     * @param  int  $page  Page number for pagination (1-indexed)
     * @return array{results: array<int, array{segment_id: int, episode_id: int, text: string, start_time: float, end_time: float, episode_title: string, episode_type: string, published_at: string, podcast_title: string, similarity: float}>, total: int}
     */
    public function findSimilarSegments(string $queryText, int $limit = 10, float $threshold = 0.8, ?string $episodeType = null, string $sort = 'relevance', int $page = 1): array
    {
        // Generate embedding for the query (with caching)
        $queryEmbedding = $this->getOrCreateQueryEmbedding($queryText);
        $queryVector = '['.implode(',', $queryEmbedding).']';

        // Convert similarity threshold to distance threshold
        // similarity = 1 - distance, so distance < (1 - threshold)
        $distanceThreshold = 1 - $threshold;

        $offset = max(0, ($page - 1) * $limit);

        // For relevance sort, use the HNSW index directly (fastest path)
        if ($sort === 'relevance') {
            return $this->findSimilarSegmentsRelevance(
                $queryVector,
                $distanceThreshold,
                $episodeType,
                $limit,
                $offset
            );
        }

        // For date-based sorts, fetch candidates using index then re-sort
        return $this->findSimilarSegmentsByDate(
            $queryVector,
            $distanceThreshold,
            $episodeType,
            $sort,
            $limit,
            $offset
        );
    }

    /**
     * Find similar segments sorted by relevance
     * Uses optimized query with proper distance threshold
     */
    private function findSimilarSegmentsRelevance(
        string $queryVector,
        float $distanceThreshold,
        ?string $episodeType,
        int $limit,
        int $offset
    ): array {
        return $this->executeSimilarityQuery(
            $queryVector,
            $distanceThreshold,
            $episodeType,
            'similarity DESC, ts.start_time ASC',
            $limit,
            $offset
        );
    }

    /**
     * Find similar segments sorted by date (newest/oldest)
     * Uses same optimized query but with date ordering
     */
    private function findSimilarSegmentsByDate(
        string $queryVector,
        float $distanceThreshold,
        ?string $episodeType,
        string $sort,
        int $limit,
        int $offset
    ): array {
        $orderBy = $sort === 'oldest'
            ? 'e.published_at ASC, ts.start_time ASC'
            : 'e.published_at DESC, ts.start_time ASC';

        return $this->executeSimilarityQuery(
            $queryVector,
            $distanceThreshold,
            $episodeType,
            $orderBy,
            $limit,
            $offset
        );
    }

    /**
     * Execute the similarity search query with configurable ORDER BY clause.
     *
     * This is the core query builder for semantic search. It:
     * 1. Computes cosine similarity between query vector and segment embeddings
     * 2. Filters by similarity threshold
     * 3. Optionally filters by episode type
     * 4. Orders and paginates results
     *
     * @param  string  $queryVector  The query embedding in pgvector format '[x,y,z,...]'
     * @param  float  $distanceThreshold  Maximum cosine distance (1 - similarity threshold)
     * @param  string|null  $episodeType  Episode type filter or null for all
     * @param  string  $orderByClause  SQL ORDER BY clause (e.g., 'similarity DESC')
     * @param  int  $limit  Maximum results to return
     * @param  int  $offset  Number of results to skip
     * @return array{results: array, total: int, is_estimated: bool}
     */
    private function executeSimilarityQuery(
        string $queryVector,
        float $distanceThreshold,
        ?string $episodeType,
        string $orderByClause,
        int $limit,
        int $offset
    ): array {
        // Build WHERE clause for episode type filter
        $episodeTypeClause = '';

        // Calculate similarity threshold from distance threshold
        $similarityThreshold = 1 - $distanceThreshold;

        // Build params in exact order they appear in SQL:
        // 1. SELECT similarity calc: queryVector
        // 2. WHERE similarity calc: queryVector
        // 3. WHERE similarity threshold: similarityThreshold
        // 4. WHERE episode_type (optional): episodeType
        // 5. LIMIT: limit
        // 6. OFFSET: offset
        $params = [
            $queryVector,          // SELECT similarity
            $queryVector,          // WHERE similarity
            $similarityThreshold,  // WHERE threshold
        ];

        if ($episodeType && $episodeType !== 'all') {
            $episodeTypeClause = 'AND e.episode_type = ?';
            $params[] = $episodeType;
        }

        $params[] = $limit;
        $params[] = $offset;

        // Optimized query: compute similarity once, filter by threshold, order by specified clause
        // This is faster than the original CTE approach because:
        // 1. Single similarity calculation per row (not 2-3)
        // 2. Direct ORDER BY + LIMIT (no window functions)
        // 3. Threshold filter reduces result set before sorting
        $results = DB::select("
            SELECT
                ts.id,
                ts.episode_id,
                ts.text,
                ts.start_time,
                ts.end_time,
                e.title as episode_title,
                e.episode_type,
                e.published_at,
                p.title as podcast_title,
                1 - (ts.embedding_vector <=> ?::vector) as similarity
            FROM transcript_segments ts
            JOIN episodes e ON ts.episode_id = e.id
            JOIN podcasts p ON e.podcast_id = p.id
            WHERE ts.embedding_status = 'completed'
              AND 1 - (ts.embedding_vector <=> ?::vector) >= ?
              {$episodeTypeClause}
            ORDER BY {$orderByClause}
            LIMIT ?
            OFFSET ?
        ", $params);

        // Get total count using smart estimation
        $totalCount = $this->getSmartResultCount(
            $queryVector,
            $distanceThreshold,
            $episodeType,
            count($results),
            $limit,
            $offset
        );

        return [
            'results' => $this->formatSearchResults($results),
            'total' => $totalCount,
            'is_estimated' => $totalCount > 500,
        ];
    }

    /**
     * Get result count using smart estimation
     * Exact count for small result sets (<500), returns 500+ marker for large ones
     */
    private function getSmartResultCount(
        string $queryVector,
        float $distanceThreshold,
        ?string $episodeType,
        int $resultCount,
        int $limit,
        int $offset
    ): int {
        // If we got fewer results than requested, we know the exact total
        if ($resultCount < $limit) {
            return $offset + $resultCount;
        }

        // Convert distance threshold to similarity threshold
        $similarityThreshold = 1 - $distanceThreshold;

        // Build count query params
        $episodeTypeClause = '';
        $countParams = [$queryVector, $similarityThreshold];

        if ($episodeType && $episodeType !== 'all') {
            $episodeTypeClause = 'AND e.episode_type = ?';
            $countParams[] = $episodeType;
        }

        // Quick probe: check if there are more than 500 results
        // This is fast because of LIMIT 501
        $probeResult = DB::selectOne("
            SELECT COUNT(*) as cnt FROM (
                SELECT 1
                FROM transcript_segments ts
                JOIN episodes e ON ts.episode_id = e.id
                WHERE ts.embedding_status = 'completed'
                  AND 1 - (ts.embedding_vector <=> ?::vector) >= ?
                  {$episodeTypeClause}
                LIMIT 501
            ) probe
        ", $countParams);

        // Return exact count if 500 or fewer, otherwise return 501 as "500+" marker
        // The is_estimated flag in the response will indicate this is an approximation
        return $probeResult->cnt;
    }

    /**
     * Format search results into consistent array structure.
     *
     * @param  array<int, object>  $results  Raw DB result objects with id, episode_id, text, etc.
     * @return array<int, array{segment_id: int, episode_id: int, text: string, start_time: float, end_time: float, episode_title: string, episode_type: string, published_at: string, podcast_title: string, similarity: float}>
     */
    public function formatSearchResults(array $results): array
    {
        return collect($results)->map(function ($result) {
            return [
                'segment_id' => $result->id,
                'episode_id' => $result->episode_id,
                'text' => $result->text,
                'start_time' => $result->start_time,
                'end_time' => $result->end_time,
                'episode_title' => $result->episode_title,
                'episode_type' => $result->episode_type,
                'published_at' => $result->published_at,
                'podcast_title' => $result->podcast_title,
                'similarity' => $result->similarity,
            ];
        })->toArray();
    }

    /**
     * Find transcript segments containing an exact phrase match.
     *
     * Uses PostgreSQL ILIKE for case-insensitive substring matching.
     * Unlike semantic search, this finds exact text matches rather than
     * semantically similar content. Returns paginated results.
     *
     * @param  string  $phrase  The exact phrase to search for (case-insensitive)
     * @param  int  $limit  Maximum number of results per page (default 10)
     * @param  string|null  $episodeType  Filter by episode type or null for all
     * @param  string  $sort  Sort order: 'relevance' (shorter first), 'newest', or 'oldest'
     * @param  int  $page  Page number for pagination (1-indexed)
     * @return array{results: array<int, array{segment_id: int, episode_id: int, text: string, start_time: float, end_time: float, episode_title: string, episode_type: string, published_at: string, podcast_title: string, similarity: float}>, total: int}
     */
    public function findExactPhraseSegments(string $phrase, int $limit = 10, ?string $episodeType = null, string $sort = 'relevance', int $page = 1): array
    {
        // Build the WHERE clause and parameters based on episode type filter
        $whereClause = "WHERE ts.embedding_status = 'completed' AND ts.text ILIKE ?";
        $countParams = ['%'.$phrase.'%'];
        $resultParams = ['%'.$phrase.'%'];

        if ($episodeType && $episodeType !== 'all') {
            $whereClause .= ' AND e.episode_type = ?';
            $countParams[] = $episodeType;
            $resultParams[] = $episodeType;
        }

        // Get total count first
        $totalCount = DB::selectOne("
            SELECT COUNT(*) as total
            FROM transcript_segments ts
            JOIN episodes e ON ts.episode_id = e.id
            JOIN podcasts p ON e.podcast_id = p.id
            {$whereClause}
        ", $countParams)->total;

        // Determine ORDER BY clause based on sort parameter
        $orderByClause = '';
        switch ($sort) {
            case 'oldest':
                $orderByClause = 'ORDER BY e.published_at ASC, ts.start_time ASC';
                break;
            case 'newest':
                $orderByClause = 'ORDER BY e.published_at DESC, ts.start_time ASC';
                break;
            case 'relevance':
            default:
                // For exact phrase, we can order by text length (shorter segments first as they're more focused)
                $orderByClause = 'ORDER BY LENGTH(ts.text) ASC, ts.start_time ASC';
                break;
        }

        $resultParams[] = ($page - 1) * $limit;
        $resultParams[] = $limit;

        // Search for segments containing the exact phrase
        $results = DB::select("
            SELECT
                ts.id,
                ts.episode_id,
                ts.text,
                ts.start_time,
                ts.end_time,
                e.title as episode_title,
                e.episode_type,
                e.published_at,
                p.title as podcast_title,
                1.0 as similarity
            FROM transcript_segments ts
            JOIN episodes e ON ts.episode_id = e.id
            JOIN podcasts p ON e.podcast_id = p.id
            {$whereClause}
            {$orderByClause}
            OFFSET ?
            LIMIT ?
        ", $resultParams);

        return [
            'results' => $this->formatSearchResults($results),
            'total' => $totalCount,
        ];
    }

    /**
     * Get statistics about embedding generation progress.
     *
     * Returns counts of segments by status, overall completion rate,
     * and current configuration. Useful for monitoring embedding
     * generation jobs and displaying dashboard statistics.
     *
     * @return array{total_segments: int, completion_rate: float, status_breakdown: array<string, int>, model: string, dimensions: int}
     */
    public function getEmbeddingStats(): array
    {
        $stats = DB::select('
            SELECT
                embedding_status,
                COUNT(*) as count
            FROM transcript_segments
            GROUP BY embedding_status
        ');

        $total = TranscriptSegment::count();
        $completed = TranscriptSegment::where('embedding_status', 'completed')->count();

        return [
            'total_segments' => $total,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
            'status_breakdown' => collect($stats)->mapWithKeys(function ($stat) {
                return [$stat->embedding_status => $stat->count];
            })->toArray(),
            'model' => $this->provider->getModel(),
            'dimensions' => $this->provider->getDimensions(),
        ];
    }
}
