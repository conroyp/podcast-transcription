<?php

use App\Models\Episode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function configureSyncExport(?string $endpoint = null, ?string $secret = null): void
{
    config([
        'services.sync.upload_endpoint' => $endpoint,
        'services.sync.shared_secret' => $secret,
    ]);
}

it('fails when sync endpoint is not configured', function () {
    configureSyncExport(null, 'sync-secret');

    $this->artisan('sync:export', ['type' => 'episode', 'id' => 1])
        ->expectsOutputToContain('No endpoint specified')
        ->assertExitCode(1);
});

it('fails when shared secret is not configured', function () {
    configureSyncExport('https://example.com/api/import-sync', null);

    $this->artisan('sync:export', ['type' => 'episode', 'id' => 1])
        ->expectsOutputToContain('No shared secret configured')
        ->assertExitCode(1);
});

it('fails when endpoint format is invalid', function () {
    configureSyncExport('not-a-valid-url', 'sync-secret');

    $this->artisan('sync:export', ['type' => 'episode', 'id' => 1])
        ->expectsOutputToContain('Invalid endpoint URL format')
        ->assertExitCode(1);
});

it('fails when type is invalid', function () {
    configureSyncExport('https://example.com/api/import-sync', 'sync-secret');

    $this->artisan('sync:export', ['type' => 'podcast', 'id' => 1])
        ->expectsOutputToContain('Type must be "episode"')
        ->assertExitCode(1);
});

it('fails when episode id is missing', function () {
    configureSyncExport('https://example.com/api/import-sync', 'sync-secret');

    $this->artisan('sync:export', ['type' => 'episode'])
        ->expectsOutputToContain('You must provide an episode ID')
        ->assertExitCode(1);
});

it('fails when episode is not found', function () {
    configureSyncExport('https://example.com/api/import-sync', 'sync-secret');

    $this->artisan('sync:export', ['type' => 'episode', 'id' => 999_999])
        ->expectsOutputToContain('Episode 999999 not found')
        ->assertExitCode(1);
});

it('succeeds and sends signed metadata request for an episode with no transcript segments', function () {
    configureSyncExport('https://example.com/api/import-sync', 'sync-secret');

    $episode = Episode::factory()->create();

    Http::preventStrayRequests();
    Http::fake([
        'example.com/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $this->artisan('sync:export', ['type' => 'episode', 'id' => $episode->id])
        ->expectsOutputToContain('Sync completed successfully')
        ->assertSuccessful();

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request): bool {
        $timestampHeader = $request->header('X-Upload-Timestamp');
        $signatureHeader = $request->header('X-Upload-Signature');

        $timestamp = is_array($timestampHeader) ? ($timestampHeader[0] ?? '') : '';
        $signature = is_array($signatureHeader) ? ($signatureHeader[0] ?? '') : '';

        return $request->method() === 'POST'
            && $request->url() === 'https://example.com/api/import-sync'
            && $request->hasHeader('X-Upload-Secret', 'sync-secret')
            && ctype_digit($timestamp)
            && strlen($signature) === 64
            && ctype_xdigit($signature);
    });
});

it('fails when remote endpoint returns an unsuccessful response', function () {
    configureSyncExport('https://example.com/api/import-sync', 'sync-secret');

    $episode = Episode::factory()->create();

    Http::preventStrayRequests();
    Http::fake([
        'example.com/*' => Http::response(['error' => 'upstream'], 500),
    ]);

    $this->artisan('sync:export', ['type' => 'episode', 'id' => $episode->id])
        ->expectsOutputToContain('Failed to sync episode metadata')
        ->assertExitCode(1);
});

it('uses the endpoint option when provided', function () {
    configureSyncExport('https://wrong.example.com/api/import-sync', 'sync-secret');

    $episode = Episode::factory()->create();

    Http::preventStrayRequests();
    Http::fake([
        'example.com/*' => Http::response(['status' => 'ok'], 200),
    ]);

    $this->artisan('sync:export', [
        'type' => 'episode',
        'id' => $episode->id,
        '--endpoint' => 'https://example.com/api/import-sync',
    ])->assertSuccessful();

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://example.com/api/import-sync';
    });
});
