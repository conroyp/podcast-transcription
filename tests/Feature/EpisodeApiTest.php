<?php

namespace Tests\Feature;

use App\Models\Episode;
use App\Models\TranscriptSegment;
use App\Services\EmbeddingService;
use Database\Seeders\SearchTestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DeterministicFakeEmbeddingService;
use Tests\TestCase;

class EpisodeApiTest extends TestCase
{
    use RefreshDatabase;

    private Episode $episode;

    protected function setUp(): void
    {
        parent::setUp();

        // Swap the real EmbeddingService with our deterministic fake
        $this->app->bind(EmbeddingService::class, DeterministicFakeEmbeddingService::class);

        // Seed test data
        $this->seed(SearchTestSeeder::class);

        // Get the seeded episode for testing
        $this->episode = Episode::first();
    }

    // =================================================================
    // 3.1.1 - 3.1.5: Episode segments API tests
    // =================================================================

    public function test_episode_segments_returns_json(): void
    {
        $response = $this->getJson("/episode/{$this->episode->id}/segments");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
    }

    public function test_episode_segments_returns_success_structure(): void
    {
        $response = $this->getJson("/episode/{$this->episode->id}/segments");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'episode',
            'segments',
        ]);
        $response->assertJson(['success' => true]);
    }

    public function test_episode_segments_episode_has_expected_fields(): void
    {
        $response = $this->getJson("/episode/{$this->episode->id}/segments");

        $response->assertStatus(200);

        $data = $response->json();
        $episode = $data['episode'];

        $this->assertArrayHasKey('id', $episode);
        $this->assertArrayHasKey('title', $episode);
        $this->assertArrayHasKey('description', $episode);
        $this->assertArrayHasKey('formatted_date', $episode);
        $this->assertArrayHasKey('episode_type', $episode);
        $this->assertArrayHasKey('audio_url', $episode);
    }

    public function test_episode_segments_segments_have_expected_fields(): void
    {
        $response = $this->getJson("/episode/{$this->episode->id}/segments");

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertNotEmpty($data['segments']);

        $firstSegment = $data['segments'][0];
        $this->assertArrayHasKey('id', $firstSegment);
        $this->assertArrayHasKey('start_time', $firstSegment);
        $this->assertArrayHasKey('end_time', $firstSegment);
        $this->assertArrayHasKey('text', $firstSegment);
        $this->assertArrayHasKey('formatted_time', $firstSegment);
        $this->assertArrayHasKey('formatted_end_time', $firstSegment);
    }

    public function test_episode_segments_returns_segments_in_order(): void
    {
        $response = $this->getJson("/episode/{$this->episode->id}/segments");

        $response->assertStatus(200);

        $data = $response->json();
        $segments = $data['segments'];

        // Verify segments are ordered by start_time ascending
        $previousStartTime = -1;
        foreach ($segments as $segment) {
            $this->assertGreaterThanOrEqual($previousStartTime, $segment['start_time']);
            $previousStartTime = $segment['start_time'];
        }
    }

    // =================================================================
    // 3.1.6 - 3.1.7: Invalid ID tests
    // =================================================================

    public function test_episode_segments_with_invalid_id_returns_404(): void
    {
        $response = $this->getJson('/episode/99999/segments');

        $response->assertStatus(404);
        $response->assertJson(['success' => false]);
    }

    public function test_episode_segments_with_non_numeric_id_returns_404(): void
    {
        // Route constraint requires numeric ID - non-numeric returns 404
        $response = $this->getJson('/episode/abc/segments');

        $response->assertStatus(404);
    }

    // =================================================================
    // 3.1.8 - 3.1.9: Episode audio tests
    // =================================================================

    public function test_episode_audio_returns_response(): void
    {
        // The seeded episode has an audio_url but no local_audio_path
        // So it should redirect to the external URL
        $response = $this->get("/episode/{$this->episode->id}/audio");

        // It should either return audio or redirect
        $this->assertTrue(
            $response->isRedirection() || $response->isOk(),
            "Expected redirect or OK response, got {$response->getStatusCode()}"
        );
    }

    public function test_episode_audio_with_invalid_id_returns_404(): void
    {
        $response = $this->getJson('/episode/99999/audio');

        $response->assertStatus(404);
    }

    public function test_episode_audio_with_non_numeric_id_returns_404(): void
    {
        // Route constraint requires numeric ID - non-numeric returns 404
        $response = $this->getJson('/episode/abc/audio');

        $response->assertStatus(404);
    }

    // =================================================================
    // Additional tests
    // =================================================================

    public function test_episode_segments_returns_correct_episode_data(): void
    {
        $response = $this->getJson("/episode/{$this->episode->id}/segments");

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertEquals($this->episode->id, $data['episode']['id']);
        $this->assertEquals($this->episode->title, $data['episode']['title']);
    }

    public function test_episode_segments_returns_all_segments(): void
    {
        $response = $this->getJson("/episode/{$this->episode->id}/segments");

        $response->assertStatus(200);

        $data = $response->json();
        $expectedCount = TranscriptSegment::where('episode_id', $this->episode->id)->count();

        $this->assertCount($expectedCount, $data['segments']);
    }
}
