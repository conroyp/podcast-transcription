<?php

use Illuminate\Http\UploadedFile;

function syncAuthHeaders(string $path, string $payload, ?string $timestamp = null): array
{
    $timestamp ??= (string) now()->timestamp;

    $signature = hash_hmac('sha256', implode("\n", [
        'POST',
        $path,
        $timestamp,
        hash('sha256', $payload),
    ]), 'test-sync-secret');

    return [
        'X-Upload-Secret' => 'test-sync-secret',
        'X-Upload-Timestamp' => $timestamp,
        'X-Upload-Signature' => $signature,
    ];
}

test('sync healthcheck accepts a valid signed request', function () {
    config()->set('services.sync.shared_secret', 'test-sync-secret');
    config()->set('services.sync.signature_ttl_seconds', 300);

    $response = $this->withHeaders(syncAuthHeaders('api/sync-healthcheck', ''))
        ->postJson('/api/sync-healthcheck');

    $response
        ->assertOk()
        ->assertJson([
            'status' => 'ok',
        ]);
});

test('sync healthcheck rejects an invalid signature', function () {
    config()->set('services.sync.shared_secret', 'test-sync-secret');
    config()->set('services.sync.signature_ttl_seconds', 300);

    $response = $this->withHeaders([
        'X-Upload-Secret' => 'test-sync-secret',
        'X-Upload-Timestamp' => (string) now()->timestamp,
        'X-Upload-Signature' => 'invalid-signature',
    ])->postJson('/api/sync-healthcheck');

    $response
        ->assertUnauthorized()
        ->assertJson([
            'status' => 'unauthorized',
        ]);
});

test('sync healthcheck rejects a stale timestamp', function () {
    config()->set('services.sync.shared_secret', 'test-sync-secret');
    config()->set('services.sync.signature_ttl_seconds', 300);

    $timestamp = (string) now()->subMinutes(10)->timestamp;

    $response = $this->withHeaders(syncAuthHeaders('api/sync-healthcheck', '', $timestamp))
        ->postJson('/api/sync-healthcheck');

    $response->assertUnauthorized();
});

test('sync import accepts valid request authentication and then validates payload', function () {
    config()->set('services.sync.shared_secret', 'test-sync-secret');
    config()->set('services.sync.signature_ttl_seconds', 300);

    $compressedPayload = 'not-a-valid-gzip';
    $file = UploadedFile::fake()->createWithContent('payload.gz', $compressedPayload);

    $response = $this->withHeaders(syncAuthHeaders('api/import-sync', $compressedPayload))->post('/api/import-sync', [
        'file' => $file,
    ]);

    $response
        ->assertBadRequest()
        ->assertJson([
            'error' => 'Failed to decompress',
        ]);
});

test('sync import does not leak internal exception messages', function () {
    config()->set('services.sync.shared_secret', 'test-sync-secret');
    config()->set('services.sync.signature_ttl_seconds', 300);

    $json = json_encode([
        'type' => 'episode',
        'episode' => ['id' => 123],
    ], JSON_THROW_ON_ERROR);
    $compressedPayload = gzencode($json, 9);

    $file = UploadedFile::fake()->createWithContent('payload.gz', $compressedPayload);

    $response = $this->withHeaders(syncAuthHeaders('api/import-sync', $compressedPayload))
        ->post('/api/import-sync', [
            'file' => $file,
        ]);

    $response
        ->assertServerError()
        ->assertJson([
            'error' => 'Import failed',
        ]);

    expect((string) $response->json('error'))->not->toContain('Undefined');
});
