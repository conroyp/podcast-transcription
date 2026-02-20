<?php

use App\Http\Controllers\SearchController;
use App\Services\EmbeddingService;
use App\Services\HybridSearchService;
use App\Services\SearchCacheService;
use App\Services\SeoService;

use function Pest\Laravel\mock;

it('escapes transcript html before highlighting', function () {
    $controller = new SearchController(
        mock(EmbeddingService::class),
        mock(HybridSearchService::class),
        mock(SearchCacheService::class),
        mock(SeoService::class)
    );

    $method = new ReflectionMethod($controller, 'highlightSearchTerms');
    $method->setAccessible(true);

    $highlighted = $method->invoke($controller, '<script>alert("x")</script> hello world', 'hello', false);

    expect($highlighted)
        ->toContain('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;')
        ->toContain('<mark class="search-highlight">hello</mark>')
        ->not->toContain('<script>');
});

it('supports exact phrase highlighting without unsafe output', function () {
    $controller = new SearchController(
        mock(EmbeddingService::class),
        mock(HybridSearchService::class),
        mock(SearchCacheService::class),
        mock(SeoService::class)
    );

    $method = new ReflectionMethod($controller, 'highlightSearchTerms');
    $method->setAccessible(true);

    $highlighted = $method->invoke($controller, 'unsafe phrase <b>still unsafe</b>', 'unsafe phrase', true);

    expect($highlighted)
        ->toContain('<mark class="search-highlight">unsafe phrase</mark>')
        ->toContain('&lt;b&gt;')
        ->not->toContain('<b>');
});
