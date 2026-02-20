<?php

use App\Jobs\RecalculateSegmentEmbeddingJob;
use App\Models\TranscriptSegment;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('calls embedding service for a pending segment', function () {
    $segment = TranscriptSegment::factory()->pending()->create();

    $mock = Mockery::mock(EmbeddingService::class);
    $mock->shouldReceive('processSegment')
        ->once()
        ->with(Mockery::on(fn ($arg) => $arg->id === $segment->id));
    $this->app->instance(EmbeddingService::class, $mock);

    $job = new RecalculateSegmentEmbeddingJob($segment->id);
    $job->handle($mock);
});

it('does not call embedding service when segment embedding status is not pending', function () {
    $segment = TranscriptSegment::factory()->completed()->create();

    $mock = Mockery::mock(EmbeddingService::class);
    $mock->shouldNotReceive('processSegment');
    $this->app->instance(EmbeddingService::class, $mock);

    $job = new RecalculateSegmentEmbeddingJob($segment->id);
    $job->handle($mock);
});

it('does not call embedding service when segment does not exist', function () {
    $mock = Mockery::mock(EmbeddingService::class);
    $mock->shouldNotReceive('processSegment');
    $this->app->instance(EmbeddingService::class, $mock);

    $job = new RecalculateSegmentEmbeddingJob(99999999);
    $job->handle($mock);

    // No exception should be thrown — missing segment is handled gracefully.
    expect(true)->toBeTrue();
});

it('does not call embedding service for a failed segment', function () {
    $segment = TranscriptSegment::factory()->failed()->create();

    $mock = Mockery::mock(EmbeddingService::class);
    $mock->shouldNotReceive('processSegment');
    $this->app->instance(EmbeddingService::class, $mock);

    $job = new RecalculateSegmentEmbeddingJob($segment->id);
    $job->handle($mock);
});
