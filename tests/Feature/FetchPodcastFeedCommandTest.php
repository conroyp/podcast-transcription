<?php

use App\Jobs\ProcessEpisodeCompleteJob;
use App\Models\Episode;
use App\Models\Podcast;
use App\Services\RssFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('fails when no url is given and no active podcast exists', function () {
    $this->artisan('podcast:fetch')
        ->expectsOutputToContain('No URL provided and no active podcast with RSS URL found')
        ->assertExitCode(1);
});

it('uses rss url from existing active podcast when no url argument is given', function () {
    $podcast = Podcast::factory()->create([
        'is_active' => true,
        'rss_url' => 'https://example.com/feed.xml',
        'title' => 'Existing Podcast',
    ]);

    $mockRssService = $this->mock(RssFeedService::class);
    $mockRssService->shouldReceive('fetchAndCreatePodcast')
        ->once()
        ->with('https://example.com/feed.xml')
        ->andReturn($podcast);

    $mockRssService->shouldReceive('fetchEpisodes')
        ->once()
        ->andReturn(0);

    $this->artisan('podcast:fetch')
        ->expectsOutputToContain('Using RSS URL from existing podcast: Existing Podcast')
        ->assertSuccessful();
});

it('outputs a warning when no new episodes are found', function () {
    $podcast = Podcast::factory()->create([
        'title' => 'Test Podcast',
        'description' => 'A description for the test podcast that is long enough.',
        'author' => 'Test Author',
    ]);

    $mockRssService = $this->mock(RssFeedService::class);
    $mockRssService->shouldReceive('fetchAndCreatePodcast')
        ->once()
        ->andReturn($podcast);

    $mockRssService->shouldReceive('fetchEpisodes')
        ->once()
        ->andReturn(0);

    $this->artisan('podcast:fetch', ['url' => 'https://example.com/feed.xml'])
        ->expectsOutputToContain('No new episodes found')
        ->assertSuccessful();
});

it('outputs success when new episodes are created', function () {
    $podcast = Podcast::factory()->create([
        'title' => 'Test Podcast',
        'description' => 'A description for the test podcast that is long enough.',
        'author' => 'Test Author',
    ]);

    $mockRssService = $this->mock(RssFeedService::class);
    $mockRssService->shouldReceive('fetchAndCreatePodcast')
        ->once()
        ->andReturn($podcast);

    $mockRssService->shouldReceive('fetchEpisodes')
        ->once()
        ->andReturn(3);

    $this->artisan('podcast:fetch', ['url' => 'https://example.com/feed.xml'])
        ->expectsOutputToContain('Created 3 new episodes')
        ->assertSuccessful();
});

it('dispatches processing jobs when --process flag is used', function () {
    Queue::fake();

    $podcast = Podcast::factory()->create([
        'title' => 'Test Podcast',
        'description' => 'A description for the test podcast that is long enough.',
        'author' => 'Test Author',
    ]);

    // The command loads episodes from the DB after fetchEpisodes; create them so dispatch runs
    Episode::factory()->count(2)->create(['podcast_id' => $podcast->id]);

    $mockRssService = $this->mock(RssFeedService::class);
    $mockRssService->shouldReceive('fetchAndCreatePodcast')
        ->once()
        ->andReturn($podcast);

    $mockRssService->shouldReceive('fetchEpisodes')
        ->once()
        ->andReturn(2);

    $this->artisan('podcast:fetch', [
        'url' => 'https://example.com/feed.xml',
        '--process' => true,
    ])
        ->expectsOutputToContain('Dispatching processing jobs')
        ->assertSuccessful();

    Queue::assertPushed(ProcessEpisodeCompleteJob::class, 2);
});

it('does not dispatch processing jobs without --process flag', function () {
    Queue::fake();

    $podcast = Podcast::factory()->create([
        'title' => 'Test Podcast',
        'description' => 'A description for the test podcast that is long enough.',
        'author' => 'Test Author',
    ]);

    $mockRssService = $this->mock(RssFeedService::class);
    $mockRssService->shouldReceive('fetchAndCreatePodcast')
        ->once()
        ->andReturn($podcast);

    $mockRssService->shouldReceive('fetchEpisodes')
        ->once()
        ->andReturn(2);

    $this->artisan('podcast:fetch', ['url' => 'https://example.com/feed.xml'])
        ->expectsOutputToContain('Use --process flag to also dispatch processing jobs')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('returns an error when the service fails to fetch or create the podcast', function () {
    $mockRssService = $this->mock(RssFeedService::class);
    $mockRssService->shouldReceive('fetchAndCreatePodcast')
        ->once()
        ->andReturn(null);

    $this->artisan('podcast:fetch', ['url' => 'https://example.com/feed.xml'])
        ->expectsOutputToContain('Failed to fetch or create podcast from RSS feed')
        ->assertExitCode(1);
});
