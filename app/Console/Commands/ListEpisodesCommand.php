<?php

namespace App\Console\Commands;

use App\Models\Episode;
use Illuminate\Console\Command;

class ListEpisodesCommand extends Command
{
    protected $signature = 'podcast:list
                            {--podcast= : Filter by podcast ID or title}
                            {--status= : Filter by download status (pending, downloading, completed, failed)}
                            {--transcribed : Show only transcribed episodes}
                            {--limit=20 : Limit number of results}';

    protected $description = 'List podcast episodes with their processing status';

    public function handle(): int
    {
        $query = Episode::with('podcast')->withCount('transcriptSegments');

        // Apply filters
        if ($podcastFilter = $this->option('podcast')) {
            if (is_numeric($podcastFilter)) {
                $query->where('podcast_id', $podcastFilter);
            } else {
                $query->whereHas('podcast', function ($q) use ($podcastFilter) {
                    $q->where('title', 'like', "%{$podcastFilter}%");
                });
            }
        }

        if ($status = $this->option('status')) {
            $query->where('download_status', $status);
        }

        if ($this->option('transcribed')) {
            $query->where('transcription_status', 'completed');
        }

        $limit = (int) $this->option('limit');
        $episodes = $query->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();

        if ($episodes->isEmpty()) {
            $this->info('No episodes found matching the criteria');

            return self::SUCCESS;
        }

        $this->displayEpisodesTable($episodes);

        return self::SUCCESS;
    }

    private function displayEpisodesTable($episodes): void
    {
        $headers = ['ID', 'Podcast', 'Title', 'Published', 'Download', 'Transcribe', 'Segments'];

        $rows = $episodes->map(function (Episode $episode) {
            $segmentCount = $episode->transcript_segments_count;

            return [
                $episode->id,
                $this->truncate($episode->podcast->title, 15),
                $this->truncate($episode->title, 30),
                $episode->published_at->format('Y-m-d'),
                $this->getStatusIcon($episode->download_status),
                $this->getStatusIcon($episode->transcription_status),
                $segmentCount > 0 ? $segmentCount : '-',
            ];
        })->toArray();

        $this->table($headers, $rows);

        // Show summary
        $totalEpisodes = $episodes->count();
        $downloaded = $episodes->where('download_status', 'completed')->count();
        $transcribed = $episodes->where('transcription_status', 'completed')->count();

        $this->info("\nSummary:");
        $this->line("Total episodes: {$totalEpisodes}");
        $this->line("Downloaded: {$downloaded}");
        $this->line("Transcribed: {$transcribed}");

        if ($totalEpisodes >= $this->option('limit')) {
            $this->line("\n(Showing first {$this->option('limit')} results. Use --limit to see more)");
        }
    }

    private function getStatusIcon(string $status): string
    {
        return match ($status) {
            'completed' => '✅',
            'processing', 'downloading' => '⏳',
            'failed' => '❌',
            'pending' => '⏸️',
            default => '❓',
        };
    }

    private function truncate(string $text, int $length): string
    {
        return strlen($text) > $length ? substr($text, 0, $length - 3).'...' : $text;
    }
}
