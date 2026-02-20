<?php

use App\Services\SeoService;
use Tests\TestCase;

uses(TestCase::class);

it('uses theme and podcast configuration for seo titles', function () {
    config([
        'theme.branding.site_name' => 'Acme Search',
        'podcast.name' => 'Acme Podcast',
    ]);

    $service = new SeoService;

    expect($service->generateTitle(null, 'search'))->toBe('Acme Search - Acme Podcast')
        ->and($service->generateTitle(null, 'episodes'))->toBe('Episode Archive - Acme Search')
        ->and($service->generateTitle('routing', 'search'))->toBe('routing - Acme Search')
        ->and($service->generateTitle('routing', 'episodes'))->toBe('routing (Episodes) - Acme Search');
});

it('uses theme special case descriptions when configured', function () {
    config([
        'theme.branding.site_name' => 'Acme Search',
        'podcast.name' => 'Acme Podcast',
        'theme.seo.special_case_descriptions' => [
            'deepdive' => 'Custom deep dive description',
        ],
    ]);

    $service = new SeoService;

    expect($service->generateDescription('deep dive', 'search'))->toBe('Custom deep dive description');
});
