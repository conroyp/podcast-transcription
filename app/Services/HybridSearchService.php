<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class HybridSearchService
{
    public function __construct(private EmbeddingService $embeddingService) {}

    /**
     * Perform hybrid search combining keyword and semantic scoring in a single query.
     *
     * Keyword matches receive a bonus added to their semantic similarity score,
     * ensuring exact text matches rank above semantic-only results. Uses PostgreSQL
     * word-boundary regex (~* with \m/\M) so "nish" matches the name but not "finish".
     *
     * Uses a CTE to compute cosine distance once per row (not 2-3 times) and
     * COUNT(*) OVER() to eliminate the separate count query.
     *
     * @param  string  $queryText  The search query
     * @param  int  $limit  Maximum results per page
     * @param  float  $threshold  Minimum similarity threshold for semantic-only results
     * @param  string|null  $episodeType  Filter by episode type or null for all
     * @param  string  $sort  Sort order: 'relevance', 'newest', or 'oldest'
     * @param  int  $page  Page number (1-indexed)
     * @return array{results: array, total: int}
     */
    public function search(string $queryText, int $limit = 10, float $threshold = 0.8, ?string $episodeType = null, string $sort = 'relevance', int $page = 1): array
    {
        $queryEmbedding = $this->embeddingService->getOrCreateQueryEmbedding($queryText);
        $queryVector = '['.implode(',', $queryEmbedding).']';

        $keywordWeight = (float) config('embeddings.hybrid.keyword_weight', 1.0);
        $offset = max(0, ($page - 1) * $limit);

        // Build word-boundary regex pattern: \m escapes word-start, \M escapes word-end
        // This ensures "nish" matches the name Nish but not "finish" or "furnishing"
        $wordPattern = '\m'.self::escapePostgresRegex($queryText).'\M';

        $orderByClause = match ($sort) {
            'oldest' => 'published_at ASC, start_time ASC',
            'newest' => 'published_at DESC, start_time ASC',
            default => 'score DESC, start_time ASC',
        };

        // CTE params: queryVector (similarity), wordPattern (has_keyword_match)
        // Outer query params: keywordWeight (score calc), threshold (filter), wordPattern (filter)
        // optional: episodeType
        // LIMIT, OFFSET
        $params = [
            $queryVector,
            $wordPattern,
        ];

        $episodeTypeClause = '';
        if ($episodeType && $episodeType !== 'all') {
            $episodeTypeClause = 'AND e.episode_type = ?';
            $params[] = $episodeType;
        }

        $params[] = $keywordWeight;
        $params[] = $threshold;
        $params[] = $limit;
        $params[] = $offset;

        $results = DB::select("
            WITH scored AS (
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
                    1 - (ts.embedding_vector <=> ?::vector) as similarity,
                    ts.text ~* ? as has_keyword_match
                FROM transcript_segments ts
                JOIN episodes e ON ts.episode_id = e.id
                JOIN podcasts p ON e.podcast_id = p.id
                WHERE ts.embedding_status = 'completed'
                  {$episodeTypeClause}
            )
            SELECT
                id, episode_id, text, start_time, end_time,
                episode_title, episode_type, published_at, podcast_title,
                CASE WHEN has_keyword_match THEN ? ELSE 0 END + similarity as score,
                similarity,
                COUNT(*) OVER() as total_count
            FROM scored
            WHERE similarity >= ? OR has_keyword_match
            ORDER BY {$orderByClause}
            LIMIT ?
            OFFSET ?
        ", $params);

        $total = count($results) > 0 ? (int) $results[0]->total_count : 0;

        return [
            'results' => $this->embeddingService->formatSearchResults($results),
            'total' => $total,
        ];
    }

    /**
     * Escape special characters for PostgreSQL POSIX regular expressions.
     */
    private static function escapePostgresRegex(string $text): string
    {
        return preg_replace('/([.\\\\*+?^${}()|[\]])/', '\\\\$1', $text);
    }
}
