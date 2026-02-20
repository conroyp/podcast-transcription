<?php

use App\Http\Controllers\EpisodeController;
use App\Jobs\ProcessEpisodeCompleteJob;
use App\Models\Episode;
use Illuminate\Support\Facades\Bus;

uses(Tests\TestCase::class);

afterEach(function (): void {
    \Mockery::close();
});

it('does not leak internal exception details when re-transcription queueing fails', function () {
    Bus::fake();

    $episode = \Mockery::mock(Episode::class)->makePartial();
    $episode->id = 101;
    $episode->audio_url = 'https://example.com/audio.mp3';
    $episode->transcription_status = 'pending';
    $episode->shouldReceive('update')
        ->once()
        ->andThrow(new Exception('database failure: secret-token-123'));

    $response = app(EpisodeController::class)->reTranscribe($episode);

    expect($response->getStatusCode())->toBe(500);
    expect($response->getData(true)['error'])->toBe('Failed to queue re-transcription.');
    expect((string) $response->getContent())->not->toContain('secret-token-123');

    Bus::assertDispatched(ProcessEpisodeCompleteJob::class);
});

it('does not leak internal exception details when sync queueing fails', function () {
    config([
        'services.sync.upload_endpoint' => 'https://example.com/api/import-sync',
        'services.sync.shared_secret' => 'sync-secret',
    ]);

    $episode = \Mockery::mock(Episode::class)->makePartial();
    $episode->id = 202;
    $episode->title = 'Example Episode';
    $episode->shouldReceive('isFullyProcessed')
        ->once()
        ->andReturnTrue();
    $episode->shouldReceive('update')
        ->once()
        ->andThrow(new Exception('queue backend unavailable: internal-hostname'));

    $response = app(EpisodeController::class)->syncEpisode($episode);

    expect($response->getStatusCode())->toBe(500);
    expect($response->getData(true)['error'])->toBe('Failed to queue sync.');
    expect((string) $response->getContent())->not->toContain('internal-hostname');
});
