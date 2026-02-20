<?php

namespace Tests\Unit\Services;

use App\Models\Episode;
use App\Models\Podcast;
use App\Models\TranscriptSegment;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmbeddingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openai.api_key' => 'test-api-key',
            'embeddings.model' => 'text-embedding-3-small',
            'embeddings.dimensions' => 1536,
        ]);
    }

    public function test_get_embedding_stats_returns_correct_structure()
    {
        $podcast = Podcast::factory()->create();
        $episode = Episode::factory()->create(['podcast_id' => $podcast->id]);

        // Create segments with different statuses
        $this->createSegment($episode, 0, 10, 'Pending segment', 'pending');
        $this->createSegment($episode, 10, 20, 'Completed segment', 'completed');
        $this->createSegment($episode, 20, 30, 'Failed segment', 'failed');

        // Resolve via container to inject dependencies
        $service = app(EmbeddingService::class);
        $stats = $service->getEmbeddingStats();

        $this->assertArrayHasKey('total_segments', $stats);
        $this->assertArrayHasKey('completion_rate', $stats);
        $this->assertArrayHasKey('status_breakdown', $stats);
        $this->assertArrayHasKey('model', $stats);
        $this->assertArrayHasKey('dimensions', $stats);

        $this->assertEquals(3, $stats['total_segments']);
        $this->assertEquals('text-embedding-3-small', $stats['model']);
        $this->assertEquals(1536, $stats['dimensions']);
    }

    public function test_get_embedding_stats_calculates_completion_rate()
    {
        $podcast = Podcast::factory()->create();
        $episode = Episode::factory()->create(['podcast_id' => $podcast->id]);

        // Create 4 segments: 2 completed, 2 pending
        $this->createSegment($episode, 0, 10, 'Completed 1', 'completed');
        $this->createSegment($episode, 10, 20, 'Completed 2', 'completed');
        $this->createSegment($episode, 20, 30, 'Pending 1', 'pending');
        $this->createSegment($episode, 30, 40, 'Pending 2', 'pending');

        // Resolve via container to inject dependencies
        $service = app(EmbeddingService::class);
        $stats = $service->getEmbeddingStats();

        // 2/4 = 50%
        $this->assertEquals(50.0, $stats['completion_rate']);
    }

    public function test_get_embedding_stats_returns_zero_rate_for_no_segments()
    {
        // Resolve via container to inject dependencies
        $service = app(EmbeddingService::class);
        $stats = $service->getEmbeddingStats();

        $this->assertEquals(0, $stats['total_segments']);
        $this->assertEquals(0, $stats['completion_rate']);
    }

    public function test_get_embedding_stats_status_breakdown_includes_all_statuses()
    {
        $podcast = Podcast::factory()->create();
        $episode = Episode::factory()->create(['podcast_id' => $podcast->id]);

        $this->createSegment($episode, 0, 10, 'Pending', 'pending');
        $this->createSegment($episode, 10, 20, 'Processing', 'processing');
        $this->createSegment($episode, 20, 30, 'Completed', 'completed');
        $this->createSegment($episode, 30, 40, 'Failed', 'failed');

        // Resolve via container to inject dependencies
        $service = app(EmbeddingService::class);
        $stats = $service->getEmbeddingStats();

        $this->assertArrayHasKey('pending', $stats['status_breakdown']);
        $this->assertArrayHasKey('processing', $stats['status_breakdown']);
        $this->assertArrayHasKey('completed', $stats['status_breakdown']);
        $this->assertArrayHasKey('failed', $stats['status_breakdown']);
        $this->assertEquals(1, $stats['status_breakdown']['pending']);
        $this->assertEquals(1, $stats['status_breakdown']['processing']);
        $this->assertEquals(1, $stats['status_breakdown']['completed']);
        $this->assertEquals(1, $stats['status_breakdown']['failed']);
    }

    /**
     * Helper to create a transcript segment with specific status
     */
    private function createSegment(Episode $episode, float $start, float $end, string $text, string $embeddingStatus = 'pending'): TranscriptSegment
    {
        return TranscriptSegment::create([
            'episode_id' => $episode->id,
            'start_time' => $start,
            'end_time' => $end,
            'text' => $text,
            'confidence' => 0.95,
            'segment_index' => (int) $start,
            'word_count' => str_word_count($text),
            'embedding_status' => $embeddingStatus,
        ]);
    }
}
