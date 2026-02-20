<?php

use App\Jobs\SyncEpisodeJob;
use App\Models\Episode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

it('sets sync_status to completed and records last_synced_at on success', function () {
    $episode = Episode::factory()->create(['last_synced_at' => null]);

    Artisan::shouldReceive('call')
        ->once()
        ->with('sync:export', [
            'type' => 'episode',
            'id' => $episode->id,
            '--endpoint' => 'https://example.com/api/import-sync',
        ])
        ->andReturn(0);

    Artisan::shouldReceive('output')->andReturn('');

    $job = new SyncEpisodeJob($episode->id, 'https://example.com/api/import-sync');
    $job->handle();

    $episode->refresh();

    expect($episode->sync_status)->toBe('completed');
    expect($episode->last_synced_at)->not->toBeNull();
    expect($episode->sync_progress['phase'])->toBe('completed');
    expect($episode->sync_progress)->toHaveKey('completed_at');
});

it('sets sync_status to failed when artisan command returns non-zero exit code', function () {
    $episode = Episode::factory()->create();

    Artisan::shouldReceive('call')->once()->andReturn(1);
    Artisan::shouldReceive('output')->andReturn('Connection refused');

    $job = new SyncEpisodeJob($episode->id, 'https://example.com/api/import-sync');

    expect(fn () => $job->handle())->toThrow(\Exception::class, 'Sync failed with exit code 1');

    $episode->refresh();

    expect($episode->sync_status)->toBe('failed');
    expect($episode->sync_progress['phase'])->toBe('failed');
    expect($episode->sync_progress['error'])->toBe('Connection refused');
    expect($episode->sync_progress)->toHaveKey('failed_at');
});

it('stores artisan output as the error when sync fails', function () {
    $episode = Episode::factory()->create();

    Artisan::shouldReceive('call')->once()->andReturn(2);
    Artisan::shouldReceive('output')->andReturn('Timeout connecting to remote host');

    $job = new SyncEpisodeJob($episode->id, 'https://example.com/api/import-sync');

    expect(fn () => $job->handle())->toThrow(\Exception::class);

    $episode->refresh();
    expect($episode->sync_progress['error'])->toBe('Timeout connecting to remote host');
});

it('throws an exception that includes the exit code when sync fails', function () {
    $episode = Episode::factory()->create();

    Artisan::shouldReceive('call')->once()->andReturn(5);
    Artisan::shouldReceive('output')->andReturn('');

    $job = new SyncEpisodeJob($episode->id, 'https://example.com/api/import-sync');

    expect(fn () => $job->handle())->toThrow(\Exception::class, 'Sync failed with exit code 5');
});

it('throws a ModelNotFoundException when the episode does not exist', function () {
    $job = new SyncEpisodeJob(99999999, 'https://example.com/api/import-sync');

    expect(fn () => $job->handle())->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('does not update last_synced_at when sync fails', function () {
    $episode = Episode::factory()->create(['last_synced_at' => null]);

    Artisan::shouldReceive('call')->once()->andReturn(1);
    Artisan::shouldReceive('output')->andReturn('error');

    $job = new SyncEpisodeJob($episode->id, 'https://example.com/api/import-sync');

    expect(fn () => $job->handle())->toThrow(\Exception::class);

    $episode->refresh();
    expect($episode->last_synced_at)->toBeNull();
});

it('passes the correct endpoint to the artisan sync command', function () {
    $episode = Episode::factory()->create();
    $endpoint = 'https://production.example.com/api/import-sync';

    Artisan::shouldReceive('call')
        ->once()
        ->with('sync:export', [
            'type' => 'episode',
            'id' => $episode->id,
            '--endpoint' => $endpoint,
        ])
        ->andReturn(0);

    Artisan::shouldReceive('output')->andReturn('');

    $job = new SyncEpisodeJob($episode->id, $endpoint);
    $job->handle();

    expect(true)->toBeTrue();
});

it('logs success after a completed sync', function () {
    Log::spy();

    $episode = Episode::factory()->create();

    Artisan::shouldReceive('call')->once()->andReturn(0);
    Artisan::shouldReceive('output')->andReturn('');

    $job = new SyncEpisodeJob($episode->id, 'https://example.com/api/import-sync');
    $job->handle();

    Log::shouldHaveReceived('info')
        ->withArgs(fn ($message) => str_contains($message, 'Sync job completed successfully') || str_contains($message, 'completed'));
});

it('logs failure after a failed sync', function () {
    Log::spy();

    $episode = Episode::factory()->create();

    Artisan::shouldReceive('call')->once()->andReturn(1);
    Artisan::shouldReceive('output')->andReturn('some error output');

    $job = new SyncEpisodeJob($episode->id, 'https://example.com/api/import-sync');

    expect(fn () => $job->handle())->toThrow(\Exception::class);

    Log::shouldHaveReceived('error')
        ->withArgs(fn ($message) => str_contains($message, 'Sync job failed') || str_contains($message, 'failed'));
});
