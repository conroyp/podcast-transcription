<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function configureSync(?string $endpoint = null, ?string $secret = null): void
{
    config([
        'services.sync.upload_endpoint' => $endpoint,
        'services.sync.shared_secret' => $secret,
    ]);
}

it('fails when sync endpoint is not configured', function () {
    configureSync(null, 'sync-secret');

    $this->artisan('sync:healthcheck')
        ->expectsOutputToContain('No endpoint specified')
        ->assertExitCode(1);
});

it('fails when shared secret is not configured', function () {
    configureSync('https://example.com/api/import-sync', null);

    $this->artisan('sync:healthcheck')
        ->expectsOutputToContain('No shared secret configured')
        ->assertExitCode(1);
});

it('fails when endpoint format is invalid', function () {
    configureSync('not-a-valid-url', 'sync-secret');

    $this->artisan('sync:healthcheck')
        ->expectsOutputToContain('Invalid endpoint URL format')
        ->assertExitCode(1);
});

it('succeeds and sends signed headers for healthcheck request', function () {
    configureSync('https://example.com/api/import-sync', 'sync-secret');

    Http::preventStrayRequests();
    Http::fake([
        'example.com/*' => Http::response([
            'status' => 'ok',
            'message' => 'Sync endpoint is healthy and authentication successful',
            'server_time' => now()->toIso8601String(),
            'environment' => 'testing',
        ], 200),
    ]);

    $this->artisan('sync:healthcheck')
        ->expectsOutputToContain('Connection successful')
        ->assertSuccessful();

    Http::assertSent(function (Request $request): bool {
        $timestampHeader = $request->header('X-Upload-Timestamp');
        $signatureHeader = $request->header('X-Upload-Signature');

        $timestamp = is_array($timestampHeader) ? ($timestampHeader[0] ?? '') : '';
        $signature = is_array($signatureHeader) ? ($signatureHeader[0] ?? '') : '';

        $expectedSignature = hash_hmac('sha256', implode("\n", [
            'POST',
            'api/sync-healthcheck',
            $timestamp,
            hash('sha256', ''),
        ]), 'sync-secret');

        return $request->method() === 'POST'
            && $request->url() === 'https://example.com/api/sync-healthcheck'
            && $request->hasHeader('X-Upload-Secret', 'sync-secret')
            && ctype_digit($timestamp)
            && $signature === $expectedSignature;
    });
});

it('fails with an authentication message when remote returns unauthorized', function () {
    configureSync('https://example.com/api/import-sync', 'sync-secret');

    Http::preventStrayRequests();
    Http::fake([
        'example.com/*' => Http::response([
            'status' => 'unauthorized',
        ], 401),
    ]);

    $this->artisan('sync:healthcheck')
        ->expectsOutputToContain('Authentication failed')
        ->assertExitCode(1);
});

it('fails with unexpected response message for non 2xx and non 401 statuses', function () {
    configureSync('https://example.com/api/import-sync', 'sync-secret');

    Http::preventStrayRequests();
    Http::fake([
        'example.com/*' => Http::response([
            'error' => 'upstream failure',
        ], 500),
    ]);

    $this->artisan('sync:healthcheck')
        ->expectsOutputToContain('Unexpected response')
        ->expectsOutputToContain('HTTP Status: 500')
        ->assertExitCode(1);
});
