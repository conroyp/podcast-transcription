<?php

use App\Jobs\ProcessEpisodeCompleteJob;
use App\Models\Episode;
use App\Models\Podcast;
use App\Models\TranscriptSegment;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createTestEpisode(): Episode
{
    $podcast = Podcast::factory()->create();

    return Episode::factory()->create([
        'podcast_id' => $podcast->id,
        'embedding_status' => 'pending',
        'download_status' => 'completed',
        'transcription_status' => 'completed',
    ]);
}

function createSegment(Episode $episode, int $index, string $embeddingStatus = 'pending'): TranscriptSegment
{
    return TranscriptSegment::create([
        'episode_id' => $episode->id,
        'start_time' => $index * 10.0,
        'end_time' => ($index + 1) * 10.0,
        'text' => "Segment {$index} text content",
        'confidence' => 0.95,
        'segment_index' => $index,
        'word_count' => 3,
        'embedding_status' => $embeddingStatus,
    ]);
}

it('sets episode embedding status to completed when all segments are already completed', function () {
    $episode = createTestEpisode();

    createSegment($episode, 0, 'completed');
    createSegment($episode, 1, 'completed');
    createSegment($episode, 2, 'completed');

    // Mock EmbeddingService so it doesn't make real API calls
    $mock = Mockery::mock(EmbeddingService::class);
    $this->app->instance(EmbeddingService::class, $mock);

    // Use reflection to call the private generateEmbeddings method
    $job = new ProcessEpisodeCompleteJob($episode);
    $method = new ReflectionMethod($job, 'generateEmbeddings');
    $method->invoke($job);

    $episode->refresh();
    expect($episode->embedding_status)->toBe('completed');
});

it('sets episode embedding status to partial when some segments are completed and some failed', function () {
    $episode = createTestEpisode();

    createSegment($episode, 0, 'completed');
    createSegment($episode, 1, 'failed');

    $mock = Mockery::mock(EmbeddingService::class);
    $this->app->instance(EmbeddingService::class, $mock);

    $job = new ProcessEpisodeCompleteJob($episode);
    $method = new ReflectionMethod($job, 'generateEmbeddings');
    $method->invoke($job);

    $episode->refresh();
    expect($episode->embedding_status)->toBe('partial');
});

it('does not change episode embedding status when there are no segments', function () {
    $episode = createTestEpisode();

    $mock = Mockery::mock(EmbeddingService::class);
    $this->app->instance(EmbeddingService::class, $mock);

    $job = new ProcessEpisodeCompleteJob($episode);
    $method = new ReflectionMethod($job, 'generateEmbeddings');
    $method->invoke($job);

    $episode->refresh();
    expect($episode->embedding_status)->toBe('pending');
});
