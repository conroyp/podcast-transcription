<?php

use App\Services\SearchCacheService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class);

it('clears only tagged search cache entries', function () {
    $taggedCache = \Mockery::mock();
    $taggedCache->shouldReceive('flush')->once();

    Cache::shouldReceive('tags')
        ->once()
        ->with(['search_cache'])
        ->andReturn($taggedCache);

    Cache::shouldReceive('flush')->never();

    $service = new SearchCacheService;
    $service->clearAllCache();
});
