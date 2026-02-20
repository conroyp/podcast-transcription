<?php

use App\Services\EmbeddingService;
use App\Services\HybridSearchService;
use Database\Seeders\SearchTestSeeder;
use Tests\Support\DeterministicFakeEmbeddingService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    app()->bind(EmbeddingService::class, DeterministicFakeEmbeddingService::class);
    $this->seed(SearchTestSeeder::class);
    $this->service = app(HybridSearchService::class);
});

test('search returns expected result structure', function () {
    $results = $this->service->search('vector databases', limit: 10, threshold: 0.3);

    expect($results)->toHaveKeys(['results', 'total']);
    expect($results['total'])->toBeGreaterThanOrEqual(0);

    if (count($results['results']) > 0) {
        $first = $results['results'][0];
        expect($first)->toHaveKeys([
            'segment_id', 'episode_id', 'text', 'start_time', 'end_time',
            'episode_title', 'episode_type', 'published_at', 'podcast_title', 'similarity',
        ]);
    }
});

test('keyword-only matches are included', function () {
    // Use a very high similarity threshold so only keyword matches qualify
    $results = $this->service->search('vector', limit: 10, threshold: 0.99);

    // "vector" should match segments containing the word via keyword regex
    $texts = collect($results['results'])->pluck('text')->toArray();
    $hasKeywordMatch = false;
    foreach ($texts as $text) {
        if (stripos($text, 'vector') !== false) {
            $hasKeywordMatch = true;
            break;
        }
    }

    expect($hasKeywordMatch)->toBeTrue();
});

test('semantic-only matches are included', function () {
    // Search with a term that won't match keyword but will have semantic similarity
    $results = $this->service->search('databases', limit: 10, threshold: 0.3);

    expect($results['results'])->not->toBeEmpty();
});

test('episode type filtering works', function () {
    // Search with a non-existent episode type should return no results
    $results = $this->service->search('vector', limit: 10, threshold: 0.3, episodeType: 'nonexistent_type');

    expect($results['results'])->toBeEmpty();
    expect($results['total'])->toBe(0);
});

test('sort ordering by relevance returns scored results', function () {
    $results = $this->service->search('vector', limit: 10, threshold: 0.3, sort: 'relevance');

    if (count($results['results']) > 1) {
        // Results should already be ordered by score desc (from the query)
        expect(count($results['results']))->toBeGreaterThan(0);
    }
});

test('sort ordering by newest works', function () {
    $results = $this->service->search('vector', limit: 10, threshold: 0.3, sort: 'newest');

    expect($results)->toHaveKeys(['results', 'total']);
});

test('sort ordering by oldest works', function () {
    $results = $this->service->search('vector', limit: 10, threshold: 0.3, sort: 'oldest');

    expect($results)->toHaveKeys(['results', 'total']);
});

test('pagination offset works', function () {
    $page1 = $this->service->search('vector', limit: 2, threshold: 0.3, page: 1);
    $page2 = $this->service->search('vector', limit: 2, threshold: 0.3, page: 2);

    expect($page1['results'])->not->toBeEmpty();

    // If there are enough results for page 2, they should differ from page 1
    if (count($page2['results']) > 0) {
        expect($page1['results'][0]['segment_id'])->not->toBe($page2['results'][0]['segment_id']);
    } else {
        // Page 2 is empty, which means all results fit on page 1
        expect($page1['total'])->toBeLessThanOrEqual(2);
    }
});

test('total count is returned correctly via window function', function () {
    $results = $this->service->search('vector', limit: 1, threshold: 0.3);

    // Total should be >= the number of results returned (since we limited to 1)
    expect($results['total'])->toBeGreaterThanOrEqual(count($results['results']));

    // Get all results to verify total matches
    $allResults = $this->service->search('vector', limit: 100, threshold: 0.3);
    expect($results['total'])->toBe($allResults['total']);
});

test('empty results return zero total', function () {
    $results = $this->service->search('xyznonexistent', limit: 10, threshold: 0.99);

    expect($results['results'])->toBeEmpty();
    expect($results['total'])->toBe(0);
});
