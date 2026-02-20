<?php

namespace App\Console\Commands;

use App\Services\HybridSearchService;
use Illuminate\Console\Command;

class SearchTranscripts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'podcast:search
                           {query : Search query text}
                           {--limit=10 : Maximum number of results}
                           {--threshold=0.7 : Minimum similarity threshold (0-1)}
                           {--detailed : Show detailed results}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Search transcript segments using hybrid keyword + semantic search';

    public function __construct(private HybridSearchService $searchService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = $this->argument('query');
        $limit = (int) $this->option('limit');
        $threshold = (float) $this->option('threshold');

        $this->info("Searching for: \"{$query}\"");
        $this->info("Limit: {$limit}, Threshold: {$threshold}");
        $this->newLine();

        try {
            $searchResult = $this->searchService->search($query, $limit, $threshold);
            $results = $searchResult['results'];

            if (empty($results)) {
                $this->warn('No results found. Try lowering the similarity threshold or checking if embeddings are generated.');

                return 0;
            }

            $this->info('Found '.count($results).' results (total: '.$searchResult['total'].'):');
            $this->newLine();

            if ($this->option('detailed')) {
                $this->showVerboseResults($results);
            } else {
                $this->showCompactResults($results);
            }

        } catch (\Exception $e) {
            $this->error("Search failed: {$e->getMessage()}");

            if (str_contains($e->getMessage(), 'OpenAI')) {
                $this->warn('Make sure your OpenAI API key is configured in .env');
            }

            return 1;
        }

        return 0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     */
    private function showCompactResults(array $results): void
    {
        $tableData = [];

        foreach ($results as $result) {
            $text = strlen($result['text']) > 100
                ? substr($result['text'], 0, 97).'...'
                : $result['text'];

            $tableData[] = [
                $result['similarity'],
                $result['podcast_title'],
                $result['episode_title'],
                $this->formatTime($result['start_time']),
                $text,
            ];
        }

        $this->table([
            'Similarity',
            'Podcast',
            'Episode',
            'Time',
            'Text Preview',
        ], $tableData);
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     */
    private function showVerboseResults(array $results): void
    {
        foreach ($results as $index => $result) {
            $this->info('Result '.($index + 1).':');
            $this->line("  Similarity: {$result['similarity']}");
            $this->line("  Podcast: {$result['podcast_title']}");
            $this->line("  Episode: {$result['episode_title']}");
            $this->line("  Time: {$this->formatTime($result['start_time'])} - {$this->formatTime($result['end_time'])}");
            $this->line('  Text:');

            $wrappedText = wordwrap($result['text'], 70, "\n      ");
            $this->line('      '.$wrappedText);

            if ($index < count($results) - 1) {
                $this->newLine();
                $this->line('  '.str_repeat('-', 70));
                $this->newLine();
            }
        }
    }

    private function formatTime(float $seconds): string
    {
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%d:%02d', $minutes, $remainingSeconds);
    }
}
