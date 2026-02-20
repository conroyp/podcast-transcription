<?php

use App\Services\HybridSearchService;

it('outputs a warning when no results are found', function () {
    $mockSearchService = $this->mock(HybridSearchService::class);
    $mockSearchService->shouldReceive('search')
        ->once()
        ->andReturn(['results' => [], 'total' => 0]);

    $this->artisan('podcast:search', ['query' => 'something obscure'])
        ->expectsOutputToContain('No results found')
        ->assertSuccessful();
});

it('displays results in compact table format when results are found', function () {
    $mockSearchService = $this->mock(HybridSearchService::class);
    $mockSearchService->shouldReceive('search')
        ->once()
        ->andReturn([
            'results' => [
                [
                    'segment_id' => 1,
                    'episode_id' => 1,
                    'text' => 'This is a test transcript segment about PHP and Laravel.',
                    'start_time' => 120.0,
                    'end_time' => 135.0,
                    'episode_title' => 'Episode One',
                    'episode_type' => 'interview',
                    'published_at' => '2024-01-01',
                    'podcast_title' => 'Test Podcast',
                    'similarity' => 0.85,
                ],
            ],
            'total' => 1,
        ]);

    $this->artisan('podcast:search', ['query' => 'PHP Laravel'])
        ->expectsOutputToContain('Found 1 results')
        ->assertSuccessful();
});

it('respects the --limit option and passes it to the search service', function () {
    $mockSearchService = $this->mock(HybridSearchService::class);
    $mockSearchService->shouldReceive('search')
        ->once()
        ->withArgs(fn ($query, $limit) => $limit === 3)
        ->andReturn(['results' => [], 'total' => 0]);

    $this->artisan('podcast:search', [
        'query' => 'test query',
        '--limit' => 3,
    ])
        ->assertSuccessful();
});

it('shows detailed output when --detailed flag is used', function () {
    $mockSearchService = $this->mock(HybridSearchService::class);
    $mockSearchService->shouldReceive('search')
        ->once()
        ->andReturn([
            'results' => [
                [
                    'segment_id' => 1,
                    'episode_id' => 1,
                    'text' => 'A detailed segment of transcript text here.',
                    'start_time' => 60.0,
                    'end_time' => 75.0,
                    'episode_title' => 'Detailed Episode',
                    'episode_type' => 'interview',
                    'published_at' => '2024-01-01',
                    'podcast_title' => 'Test Podcast',
                    'similarity' => 0.92,
                ],
            ],
            'total' => 1,
        ]);

    $this->artisan('podcast:search', [
        'query' => 'detailed',
        '--detailed' => true,
    ])
        ->expectsOutputToContain('Result 1:')
        ->expectsOutputToContain('Detailed Episode')
        ->assertSuccessful();
});

it('outputs an error and returns failure when the search service throws an exception', function () {
    $mockSearchService = $this->mock(HybridSearchService::class);
    $mockSearchService->shouldReceive('search')
        ->once()
        ->andThrow(new \RuntimeException('Connection refused'));

    $this->artisan('podcast:search', ['query' => 'broken query'])
        ->expectsOutputToContain('Search failed: Connection refused')
        ->assertExitCode(1);
});

it('suggests checking openai api key when the exception message mentions openai', function () {
    $mockSearchService = $this->mock(HybridSearchService::class);
    $mockSearchService->shouldReceive('search')
        ->once()
        ->andThrow(new \RuntimeException('OpenAI API error: invalid key'));

    $this->artisan('podcast:search', ['query' => 'test'])
        ->expectsOutputToContain('Make sure your OpenAI API key is configured')
        ->assertExitCode(1);
});

it('outputs the query and limit in the header', function () {
    $mockSearchService = $this->mock(HybridSearchService::class);
    $mockSearchService->shouldReceive('search')
        ->once()
        ->andReturn(['results' => [], 'total' => 0]);

    $this->artisan('podcast:search', [
        'query' => 'my search term',
        '--limit' => 5,
    ])
        ->expectsOutputToContain('Searching for: "my search term"')
        ->expectsOutputToContain('Limit: 5')
        ->assertSuccessful();
});
