<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\Podcast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PodcastIngestionService
{
    /**
     * @return array<string, bool>
     */
    public function buildJobOptions(
        bool $forceDownload,
        bool $forceTranscription,
        bool $skipAdRemoval,
        bool $skipChunking,
        bool $skipEmbedding
    ): array {
        return [
            'force_download' => $forceDownload,
            'force_transcription' => $forceTranscription,
            'skip_ad_removal' => $skipAdRemoval,
            'skip_chunking' => $skipChunking,
            'skip_embedding' => $skipEmbedding,
        ];
    }

    /**
     * @return Collection<int, Episode>
     */
    public function getEpisodesToProcess(
        Podcast $podcast,
        bool $forceDownload,
        bool $forceTranscription,
        bool $oldestFirst,
        ?int $limit
    ): Collection {
        $episodesQuery = $podcast->episodes();

        if (! $forceDownload && ! $forceTranscription) {
            $episodesQuery->where(function (Builder $query): void {
                $query->where('transcription_status', '!=', 'completed')
                    ->orWhereDoesntHave('transcriptSegments', function (Builder $subQuery): void {
                        $subQuery->where('embedding_status', 'completed');
                    });
            });
        }

        $episodesQuery->orderBy('published_at', $oldestFirst ? 'asc' : 'desc');

        if ($limit !== null) {
            $episodesQuery->limit($limit);
        }

        /** @var Collection<int, Episode> $episodes */
        $episodes = $episodesQuery->get();

        return $episodes;
    }

    public function determineProcessingReason(Episode $episode): string
    {
        if ($episode->transcription_status !== 'completed') {
            return 'Needs transcription';
        }

        $hasEmbeddings = $episode->transcriptSegments()
            ->where('embedding_status', 'completed')
            ->exists();

        if (! $hasEmbeddings) {
            return 'Needs embeddings';
        }

        return 'New episode';
    }
}
