<?php

use App\Jobs\ProcessEpisodeCompleteJob;
use App\Models\Episode;
use App\Models\Podcast;
use App\Services\RssFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('fails when no rss url is configured and none is provided', function () {
    config(['podcast.default_rss_url' => null]);

    $this->artisan('podcast:ingest-complete')
        ->expectsOutputToContain('No RSS URL provided')
        ->assertExitCode(1);
});

it('fails when the rss service cannot fetch or create the podcast', function () {
    $mockRssService = $this->mock(RssFeedService::class);
    $mockRssService->shouldReceive('fetchAndCreatePodcast')
        ->once()
        ->andReturn(null);

    $this->artisan('podcast:ingest-complete', ['--rss-url' => 'https://example.com/feed.xml'])
        ->expectsOutputToContain('Failed to ingest RSS feed')
        ->assertExitCode(1);
});

it('queues processing jobs for episodes needing work', function () {
    Queue::fake();

    $podcast = Podcast::factory()->create(['title' => 'Test Podcast']);

    Episode::factory()->count(3)->create([
        'podcast_id' => $podcast->id,
        'transcription_status' => 'pending',
    ]);

    $mockRssService = $this->mock(RssFeedService::class);
    $mockRssService->shouldReceive('fetchAndCreatePodcast')
        ->once()
        ->andReturn($podcast);

    $mockRssService->shouldReceive('fetchAllEpisodes')
        ->once()
        ->andReturn(3);

    $this->artisan('podcast:ingest-complete', ['--rss-url' => 'https://example.com/feed.xml'])
        ->assertSuccessful();

    Queue::assertPushed(ProcessEpisodeCompleteJob::class, 3);
});

it('limits processing to the number specified by --limit', function () {
    Queue::fake();

    $podcast = Podcast::factory()->create(['title' => 'Test Podcast']);

    Episode::factory()->count(5)->create([
        'podcast_id' => $podcast->id,
        'transcription_status' => 'pending',
    ]);

    $mockRssService = $this->mock(RssFeedService::class);
    $mockRssService->shouldReceive('fetchAndCreatePodcast')
        ->once()
        ->andReturn($podcast);

    $mockRssService->shouldReceive('fetchAllEpisodes')
        ->once()
        ->andReturn(5);

    $this->artisan('podcast:ingest-complete', [
        '--rss-url' => 'https://example.com/feed.xml',
        '--limit' => 2,
    ])
        ->assertSuccessful();

    Queue::assertPushed(ProcessEpisodeCompleteJob::class, 2);
});

it('orders episodes oldest first when --oldest-first flag is used', function () {
    Queue::fake();

    $podcast = Podcast::factory()->create(['title' => 'Test Podcast']);

    $old = Episode::factory()->create([
        'podcast_id' => $podcast->id,
        'published_at' => now()->subDays(30),
        'transcription_status' => 'pending',
    ]);

    $new = Episode::factory()->create([
        'podcast_id' => $podcast->id,
        'published_at' => now()->subDay(),
        'transcription_status' => 'pending',
    ]);

    $mockRssService = $this->mock(RssFeedService::class);
    $mockRssService->shouldReceive('fetchAndCreatePodcast')
        ->once()
        ->andReturn($podcast);

    $mockRssService->shouldReceive('fetchAllEpisodes')
        ->once()
        ->andReturn(2);

    $this->artisan('podcast:ingest-complete', [
        '--rss-url' => 'https://example.com/feed.xml',
        '--oldest-first' => true,
        '--limit' => 1,
    ])
        ->assertSuccessful();

    // Only the oldest episode should be dispatched when limit is 1 and oldest-first is set
    Queue::assertPushed(ProcessEpisodeCompleteJob::class, 1);
    Queue::assertPushed(ProcessEpisodeCompleteJob::class, function (ProcessEpisodeCompleteJob $job) use ($old) {
        return $job->episode->id === $old->id;
    });
});

it('shows dry run output and does not dispatch jobs', function () {
    Queue::fake();

    $podcast = Podcast::factory()->create(['title' => 'Dry Run Podcast']);

    Episode::factory()->count(2)->create([
        'podcast_id' => $podcast->id,
        'transcription_status' => 'pending',
    ]);

    $mockRssService = $this->mock(RssFeedService::class);
    $mockRssService->shouldReceive('fetchAndCreatePodcast')
        ->once()
        ->andReturn($podcast);

    $mockRssService->shouldReceive('fetchAllEpisodes')
        ->once()
        ->andReturn(2);

    $this->artisan('podcast:ingest-complete', [
        '--rss-url' => 'https://example.com/feed.xml',
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('DRY RUN')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('outputs the episode count found for processing', function () {
    Queue::fake();

    $podcast = Podcast::factory()->create(['title' => 'Count Podcast']);

    Episode::factory()->count(4)->create([
        'podcast_id' => $podcast->id,
        'transcription_status' => 'pending',
    ]);

    $mockRssService = $this->mock(RssFeedService::class);
    $mockRssService->shouldReceive('fetchAndCreatePodcast')
        ->once()
        ->andReturn($podcast);

    $mockRssService->shouldReceive('fetchAllEpisodes')
        ->once()
        ->andReturn(4);

    $this->artisan('podcast:ingest-complete', ['--rss-url' => 'https://example.com/feed.xml'])
        ->expectsOutputToContain('Found 4 episodes to process')
        ->assertSuccessful();
});

it('uses the default rss url from config when --rss-url is not provided', function () {
    Queue::fake();

    config(['podcast.default_rss_url' => 'https://config-feed.example.com/feed.xml']);

    $podcast = Podcast::factory()->create(['title' => 'Config Podcast']);

    $mockRssService = $this->mock(RssFeedService::class);
    $mockRssService->shouldReceive('fetchAndCreatePodcast')
        ->once()
        ->with('https://config-feed.example.com/feed.xml')
        ->andReturn($podcast);

    $mockRssService->shouldReceive('fetchAllEpisodes')
        ->once()
        ->andReturn(0);

    $this->artisan('podcast:ingest-complete')
        ->expectsOutputToContain('https://config-feed.example.com/feed.xml')
        ->assertSuccessful();
});
