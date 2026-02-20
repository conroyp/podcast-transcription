<?php

use App\Http\Controllers\SearchController;
use App\Services\EmbeddingService;
use App\Services\HybridSearchService;
use App\Services\SearchCacheService;
use App\Services\SeoService;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class);

it('returns a generic error when ajax transcript search fails', function () {
    $embeddingService = \Mockery::mock(EmbeddingService::class);
    $hybridSearchService = \Mockery::mock(HybridSearchService::class);
    $searchCacheService = \Mockery::mock(SearchCacheService::class);
    $seoService = \Mockery::mock(SeoService::class);

    $searchCacheService->shouldReceive('getCachedResults')
        ->once()
        ->andThrow(new RuntimeException('database internals should not leak'));

    $controller = new SearchController(
        $embeddingService,
        $hybridSearchService,
        $searchCacheService,
        $seoService
    );

    $request = Request::create('/', 'GET', [
        'q' => 'vector',
        'ajax' => 1,
    ], [], [], [
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
    ]);

    $response = $controller->index($request);

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getData(true))->toMatchArray([
            'success' => false,
            'error' => 'Search is temporarily unavailable. Please try again.',
        ])
        ->and($response->getContent())->not->toContain('database internals should not leak');
});

it('returns a generic error when ajax episode listing fails', function () {
    $embeddingService = \Mockery::mock(EmbeddingService::class);
    $hybridSearchService = \Mockery::mock(HybridSearchService::class);
    $searchCacheService = \Mockery::mock(SearchCacheService::class);
    $seoService = \Mockery::mock(SeoService::class);

    $searchCacheService->shouldReceive('getCachedEpisodeResults')
        ->once()
        ->andThrow(new RuntimeException('cache backend details should not leak'));

    $controller = new SearchController(
        $embeddingService,
        $hybridSearchService,
        $searchCacheService,
        $seoService
    );

    $request = Request::create('/', 'GET', [
        'view' => 'episodes',
        'ajax' => 1,
    ], [], [], [
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
    ]);

    $response = $controller->index($request);

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getData(true))->toMatchArray([
            'success' => false,
            'error' => 'Unable to load episodes right now.',
        ])
        ->and($response->getContent())->not->toContain('cache backend details should not leak');
});

it('returns a generic 500 error when loading episode segments fails unexpectedly', function () {
    $embeddingService = \Mockery::mock(EmbeddingService::class);
    $hybridSearchService = \Mockery::mock(HybridSearchService::class);
    $searchCacheService = \Mockery::mock(SearchCacheService::class);
    $seoService = \Mockery::mock(SeoService::class);

    $searchCacheService->shouldReceive('getCachedSegments')
        ->once()
        ->andThrow(new RuntimeException('low-level transport error'));

    $controller = new SearchController(
        $embeddingService,
        $hybridSearchService,
        $searchCacheService,
        $seoService
    );

    $response = $controller->getEpisodeSegments(Request::create('/episode/123/segments', 'GET'), 123);

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getData(true))->toMatchArray([
            'success' => false,
            'error' => 'Failed to load episode segments.',
        ])
        ->and($response->getContent())->not->toContain('low-level transport error');
});

it('returns a generic error when stats lookup fails', function () {
    $embeddingService = \Mockery::mock(EmbeddingService::class);
    $hybridSearchService = \Mockery::mock(HybridSearchService::class);
    $searchCacheService = \Mockery::mock(SearchCacheService::class);
    $seoService = \Mockery::mock(SeoService::class);

    $embeddingService->shouldReceive('getEmbeddingStats')
        ->once()
        ->andThrow(new RuntimeException('provider internals should not leak'));

    $controller = new SearchController(
        $embeddingService,
        $hybridSearchService,
        $searchCacheService,
        $seoService
    );

    $response = $controller->stats();

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getData(true))->toMatchArray([
            'success' => false,
            'error' => 'Failed to retrieve search statistics.',
        ])
        ->and($response->getContent())->not->toContain('provider internals should not leak');
});
