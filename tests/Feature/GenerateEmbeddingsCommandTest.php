<?php

use App\Models\Episode;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('fails gracefully when a specific episode id is not found', function () {
    config()->set('services.openai.api_key', 'test-openai-key');

    $this->artisan('podcast:generate-embeddings', [
        'episode' => 999_999,
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('Episode 999999 not found')
        ->assertExitCode(1);
});

it('succeeds when a valid episode has no pending segments to process', function () {
    config()->set('services.openai.api_key', 'test-openai-key');

    $episode = Episode::factory()->create([
        'title' => 'No Segments Episode',
    ]);

    $this->artisan('podcast:generate-embeddings', [
        'episode' => $episode->id,
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('Processing episode: No Segments Episode')
        ->expectsOutputToContain('No segments need processing')
        ->assertSuccessful();
});
