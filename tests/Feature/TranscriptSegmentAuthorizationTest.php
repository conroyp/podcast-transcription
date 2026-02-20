<?php

use App\Models\Episode;
use App\Models\TranscriptSegment;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    $episode = Episode::factory()->create();

    $this->segment = TranscriptSegment::create([
        'episode_id' => $episode->id,
        'start_time' => 0.0,
        'end_time' => 5.0,
        'text' => 'Hello world',
        'confidence' => 0.9,
        'segment_index' => 0,
        'word_count' => 2,
        'embedding_status' => 'pending',
    ]);
});

// -----------------------------------------------------------------------
// updateTranscript – authorized user
// -----------------------------------------------------------------------

test('authenticated user can update a transcript segment', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson(route('transcript.update', $this->segment), ['text' => 'Updated text'])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($this->segment->fresh()->text)->toBe('Updated text');
});

// -----------------------------------------------------------------------
// updateTranscript – unauthenticated user
// -----------------------------------------------------------------------

test('unauthenticated user cannot update a transcript segment', function (): void {
    $this->putJson(route('transcript.update', $this->segment), ['text' => 'Updated text'])
        ->assertUnauthorized();
});

// -----------------------------------------------------------------------
// deleteTranscript – authorized user
// -----------------------------------------------------------------------

test('authenticated user can delete a transcript segment', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('transcript.delete', $this->segment))
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(TranscriptSegment::find($this->segment->id))->toBeNull();
});

// -----------------------------------------------------------------------
// deleteTranscript – unauthenticated user
// -----------------------------------------------------------------------

test('unauthenticated user cannot delete a transcript segment', function (): void {
    $this->deleteJson(route('transcript.delete', $this->segment))
        ->assertUnauthorized();
});
