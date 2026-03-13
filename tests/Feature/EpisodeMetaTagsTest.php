<?php

use App\Models\Episode;
use App\Models\Podcast;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('strips newlines from description in meta tags', function () {
    $podcast = Podcast::factory()->create();
    $episode = Episode::factory()->for($podcast)->create([
        'title' => 'Test Episode Title',
        'description' => "Line one\nLine two\nLine three",
        'published_at' => now(),
    ]);

    $response = $this->get('/?view=episodes&episode='.$episode->id);

    $response->assertSuccessful();
    $response->assertSee('<meta name="description" content="Line one Line two Line three">', false);
});

it('strips newlines from description in og and twitter meta tags', function () {
    $podcast = Podcast::factory()->create();
    $episode = Episode::factory()->for($podcast)->create([
        'title' => 'OG Test Episode',
        'description' => "First line\nSecond line",
        'published_at' => now(),
    ]);

    $response = $this->get('/?view=episodes&episode='.$episode->id);

    $response->assertSuccessful();
    $response->assertSee('og:description" content="First line Second line"', false);
    $response->assertSee('twitter:description" content="First line Second line"', false);
});

it('renders valid json-ld without newlines in description', function () {
    $podcast = Podcast::factory()->create();
    $episode = Episode::factory()->for($podcast)->create([
        'title' => 'JSON-LD Episode',
        'description' => "Description with\nnewlines\nin it",
        'published_at' => now(),
    ]);

    $response = $this->get('/?view=episodes&episode='.$episode->id);

    $response->assertSuccessful();

    $content = $response->getContent();

    preg_match('/<script type="application\/ld\+json">\s*(.*?)\s*<\/script>/s', $content, $matches);
    expect($matches)->not->toBeEmpty('JSON-LD script tag not found');

    $jsonLd = json_decode($matches[1], true);
    expect($jsonLd)->not->toBeNull('JSON-LD is not valid JSON');
    expect($jsonLd['@type'])->toBe('PodcastEpisode');
    expect($jsonLd['name'])->toBe('JSON-LD Episode');
    expect($jsonLd['description'])->toBe('Description with newlines in it');
    expect($jsonLd['description'])->not->toContain("\n");
});

it('handles special characters in json-ld without breaking encoding', function () {
    $podcast = Podcast::factory()->create();
    $episode = Episode::factory()->for($podcast)->create([
        'title' => 'Episode with "quotes" & <angles>',
        'description' => 'Description with "double quotes" and \'single quotes\' & ampersands',
        'published_at' => now(),
    ]);

    $response = $this->get('/?view=episodes&episode='.$episode->id);

    $response->assertSuccessful();

    $content = $response->getContent();

    preg_match('/<script type="application\/ld\+json">\s*(.*?)\s*<\/script>/s', $content, $matches);
    expect($matches)->not->toBeEmpty('JSON-LD script tag not found');

    $jsonLd = json_decode($matches[1], true);
    expect($jsonLd)->not->toBeNull('JSON-LD is not valid JSON');
    expect($jsonLd['name'])->toBe('Episode with "quotes" & <angles>');
    expect($jsonLd['description'])->toBe('Description with "double quotes" and \'single quotes\' & ampersands');
});

it('includes datePublished in json-ld when episode has published_at', function () {
    $podcast = Podcast::factory()->create();
    $episode = Episode::factory()->for($podcast)->create([
        'title' => 'Dated Episode',
        'description' => 'Some description',
        'published_at' => '2025-06-15 10:00:00',
    ]);

    $response = $this->get('/?view=episodes&episode='.$episode->id);

    $content = $response->getContent();

    preg_match('/<script type="application\/ld\+json">\s*(.*?)\s*<\/script>/s', $content, $matches);
    $jsonLd = json_decode($matches[1], true);

    expect($jsonLd)->toHaveKey('datePublished');
    expect($jsonLd['datePublished'])->toContain('2025-06-15');
});

it('includes correct json-ld structure with partOfSeries and publisher', function () {
    $podcast = Podcast::factory()->create();
    $episode = Episode::factory()->for($podcast)->create([
        'title' => 'Structure Test',
        'description' => 'Testing structure',
        'published_at' => now(),
    ]);

    $response = $this->get('/?view=episodes&episode='.$episode->id);

    $content = $response->getContent();

    preg_match('/<script type="application\/ld\+json">\s*(.*?)\s*<\/script>/s', $content, $matches);
    $jsonLd = json_decode($matches[1], true);

    expect($jsonLd['@context'])->toBe('https://schema.org');
    expect($jsonLd['@type'])->toBe('PodcastEpisode');
    expect($jsonLd['partOfSeries']['@type'])->toBe('PodcastSeries');
    expect($jsonLd['associatedMedia']['@type'])->toBe('MediaObject');
    expect($jsonLd['associatedMedia']['encodingFormat'])->toBe('audio/mpeg');
    expect($jsonLd['publisher']['@type'])->toBe('Organization');
});
