<?php

use App\Models\Episode;
use App\Models\Podcast;
use App\Models\TranscriptSegment;
use App\Services\PodcastIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('selects only episodes that still need processing when force flags are disabled', function () {
    $podcast = Podcast::factory()->create();

    $needsTranscription = Episode::factory()->create([
        'podcast_id' => $podcast->id,
        'title' => 'Needs Transcription',
        'published_at' => now()->subDays(1),
        'transcription_status' => 'pending',
    ]);

    $needsEmbeddings = Episode::factory()->create([
        'podcast_id' => $podcast->id,
        'title' => 'Needs Embeddings',
        'published_at' => now()->subDays(2),
        'transcription_status' => 'completed',
    ]);

    $fullyProcessed = Episode::factory()->create([
        'podcast_id' => $podcast->id,
        'title' => 'Fully Processed',
        'published_at' => now()->subDays(3),
        'transcription_status' => 'completed',
    ]);

    TranscriptSegment::query()->create([
        'episode_id' => $fullyProcessed->id,
        'start_time' => 0.0,
        'end_time' => 5.0,
        'text' => 'Completed embedding segment',
        'segment_index' => 1,
        'embedding_status' => 'completed',
    ]);

    $service = app(PodcastIngestionService::class);

    $episodes = $service->getEpisodesToProcess(
        $podcast,
        forceDownload: false,
        forceTranscription: false,
        oldestFirst: false,
        limit: null
    );

    expect($episodes->pluck('id')->all())->toBe([
        $needsTranscription->id,
        $needsEmbeddings->id,
    ]);
});

it('includes all episodes when force flags are enabled and applies oldest-first order with limit', function () {
    $podcast = Podcast::factory()->create();

    $oldest = Episode::factory()->create([
        'podcast_id' => $podcast->id,
        'published_at' => now()->subDays(10),
        'transcription_status' => 'completed',
    ]);
    $middle = Episode::factory()->create([
        'podcast_id' => $podcast->id,
        'published_at' => now()->subDays(5),
        'transcription_status' => 'completed',
    ]);
    $newest = Episode::factory()->create([
        'podcast_id' => $podcast->id,
        'published_at' => now()->subDays(1),
        'transcription_status' => 'completed',
    ]);

    $service = app(PodcastIngestionService::class);

    $episodes = $service->getEpisodesToProcess(
        $podcast,
        forceDownload: true,
        forceTranscription: false,
        oldestFirst: true,
        limit: 2
    );

    expect($episodes->pluck('id')->all())->toBe([
        $oldest->id,
        $middle->id,
    ]);
    expect($episodes->pluck('id')->all())->not->toContain($newest->id);
});

it('returns clear processing reasons', function () {
    $podcast = Podcast::factory()->create();

    $needsTranscription = Episode::factory()->create([
        'podcast_id' => $podcast->id,
        'transcription_status' => 'pending',
    ]);

    $needsEmbeddings = Episode::factory()->create([
        'podcast_id' => $podcast->id,
        'transcription_status' => 'completed',
    ]);

    $service = app(PodcastIngestionService::class);

    expect($service->determineProcessingReason($needsTranscription))->toBe('Needs transcription');
    expect($service->determineProcessingReason($needsEmbeddings))->toBe('Needs embeddings');
});
