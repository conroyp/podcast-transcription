<?php

use App\Jobs\ReEvaluateEmbeddingsJob;
use App\Models\Episode;
use App\Models\TranscriptSegment;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('processes segments and sets episode embedding status to completed when all succeed', function () {
    $episode = Episode::factory()->create(['embedding_status' => 'pending']);
    TranscriptSegment::factory()->count(3)->create(['episode_id' => $episode->id]);

    $mock = Mockery::mock(EmbeddingService::class);
    $mock->shouldReceive('processSegmentsWithProgress')
        ->once()
        ->andReturn(['success' => 3, 'failed' => 0, 'errors' => []]);
    $this->app->instance(EmbeddingService::class, $mock);

    $job = new ReEvaluateEmbeddingsJob($episode);
    $job->handle();

    $episode->refresh();
    expect($episode->embedding_status)->toBe('completed');
});

it('sets episode embedding status to partial when some segments fail', function () {
    $episode = Episode::factory()->create(['embedding_status' => 'pending']);
    TranscriptSegment::factory()->count(3)->create(['episode_id' => $episode->id]);

    $mock = Mockery::mock(EmbeddingService::class);
    $mock->shouldReceive('processSegmentsWithProgress')
        ->once()
        ->andReturn([
            'success' => 2,
            'failed' => 1,
            'errors' => [['segment_id' => 1, 'error' => 'API error']],
        ]);
    $this->app->instance(EmbeddingService::class, $mock);

    $job = new ReEvaluateEmbeddingsJob($episode);
    $job->handle();

    $episode->refresh();
    expect($episode->embedding_status)->toBe('partial');
});

it('sets episode embedding status to failed when there are no transcript segments', function () {
    $episode = Episode::factory()->create(['embedding_status' => 'pending']);

    $mock = Mockery::mock(EmbeddingService::class);
    $mock->shouldNotReceive('processSegmentsWithProgress');
    $this->app->instance(EmbeddingService::class, $mock);

    $job = new ReEvaluateEmbeddingsJob($episode);
    $job->handle();

    $episode->refresh();
    expect($episode->embedding_status)->toBe('failed');
});

it('sets episode embedding status to failed and re-throws on unexpected exception', function () {
    $episode = Episode::factory()->create(['embedding_status' => 'pending']);
    TranscriptSegment::factory()->count(2)->create(['episode_id' => $episode->id]);

    $mock = Mockery::mock(EmbeddingService::class);
    $mock->shouldReceive('processSegmentsWithProgress')
        ->once()
        ->andThrow(new RuntimeException('Unexpected failure'));
    $this->app->instance(EmbeddingService::class, $mock);

    expect(function () use ($episode) {
        $job = new ReEvaluateEmbeddingsJob($episode);
        $job->handle();
    })->toThrow(RuntimeException::class, 'Unexpected failure');

    $episode->refresh();
    expect($episode->embedding_status)->toBe('failed');
});
