<?php

use App\Contracts\EmbeddingProviderInterface;
use App\Models\QueryEmbedding;
use App\Services\EmbeddingService;
use App\Services\SearchCacheService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createMockProvider(): EmbeddingProviderInterface
{
    $provider = Mockery::mock(EmbeddingProviderInterface::class);
    $provider->shouldReceive('getModel')->andReturn('text-embedding-3-small');
    $provider->shouldReceive('getDimensions')->andReturn(1536);

    return $provider;
}

function generateFakeVector(): array
{
    $vector = [];
    for ($i = 0; $i < 1536; $i++) {
        $vector[] = round(mt_rand(-1000, 1000) / 1000, 6);
    }

    return $vector;
}

test('first call stores embedding and second call returns cached', function () {
    $fakeVector = generateFakeVector();
    $provider = createMockProvider();
    $provider->shouldReceive('generateEmbedding')
        ->once() // Only one API call should be made
        ->with('hello world')
        ->andReturn($fakeVector);

    $service = new EmbeddingService($provider);

    // First call should generate and store
    $result1 = $service->getOrCreateQueryEmbedding('hello world');
    expect($result1)->toHaveCount(1536);

    // Verify it was stored in the database
    expect(QueryEmbedding::count())->toBe(1);
    $cached = QueryEmbedding::first();
    expect($cached->query_text)->toBe('hello world');
    expect($cached->use_count)->toBe(1);

    // Second call should return cached (no additional API call)
    $result2 = $service->getOrCreateQueryEmbedding('hello world');
    expect($result2)->toHaveCount(1536);

    // use_count should have incremented
    $cached->refresh();
    expect($cached->use_count)->toBe(2);
});

test('normalization ensures same cache entry for equivalent queries', function () {
    $fakeVector = generateFakeVector();
    $provider = createMockProvider();
    $provider->shouldReceive('generateEmbedding')
        ->once() // Only one API call despite different inputs
        ->andReturn($fakeVector);

    $service = new EmbeddingService($provider);

    // First call
    $service->getOrCreateQueryEmbedding('  Hello World  ');

    // Second call with different casing/whitespace should hit cache
    $service->getOrCreateQueryEmbedding('hello world');

    expect(QueryEmbedding::count())->toBe(1);
    $cached = QueryEmbedding::first();
    expect($cached->query_text)->toBe('hello world');
    expect($cached->use_count)->toBe(2);
});

test('cache survives Cache::flush', function () {
    $fakeVector = generateFakeVector();
    $provider = createMockProvider();
    $provider->shouldReceive('generateEmbedding')
        ->once()
        ->andReturn($fakeVector);

    $service = new EmbeddingService($provider);

    // Store embedding
    $service->getOrCreateQueryEmbedding('test query');
    expect(QueryEmbedding::count())->toBe(1);

    // Flush all caches (simulating clearAllCache)
    $cacheService = new SearchCacheService;
    $cacheService->clearAllCache();

    // Database cache should still exist
    expect(QueryEmbedding::count())->toBe(1);

    // Second call should still return cached (no additional API call)
    $service->getOrCreateQueryEmbedding('test query');
});

test('cache is bypassed when disabled in config', function () {
    config(['embeddings.query_cache.enabled' => false]);

    $fakeVector = generateFakeVector();
    $provider = createMockProvider();
    $provider->shouldReceive('generateEmbedding')
        ->twice() // Should call provider each time when cache disabled
        ->andReturn($fakeVector);

    $service = new EmbeddingService($provider);

    $service->getOrCreateQueryEmbedding('hello');
    $service->getOrCreateQueryEmbedding('hello');

    // Nothing should be stored
    expect(QueryEmbedding::count())->toBe(0);
});

test('different queries create separate cache entries', function () {
    $provider = createMockProvider();
    $provider->shouldReceive('generateEmbedding')
        ->twice()
        ->andReturn(generateFakeVector());

    $service = new EmbeddingService($provider);

    $service->getOrCreateQueryEmbedding('first query');
    $service->getOrCreateQueryEmbedding('second query');

    expect(QueryEmbedding::count())->toBe(2);
});

test('cached lookup uses single database query for vector extraction', function () {
    $fakeVector = generateFakeVector();
    $provider = createMockProvider();
    $provider->shouldReceive('generateEmbedding')
        ->once()
        ->andReturn($fakeVector);

    $service = new EmbeddingService($provider);

    // First call stores the embedding
    $service->getOrCreateQueryEmbedding('test query');

    // Second call should hit cache - count DB queries
    $queryCount = 0;
    \Illuminate\Support\Facades\DB::listen(function () use (&$queryCount) {
        $queryCount++;
    });

    $result = $service->getOrCreateQueryEmbedding('test query');

    // Should be exactly 2 queries: 1 SELECT with vector::text, 1 UPDATE for use_count
    expect($queryCount)->toBe(2);
    expect($result)->toHaveCount(1536);
});
