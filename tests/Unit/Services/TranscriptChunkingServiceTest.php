<?php

namespace Tests\Unit\Services;

use App\Models\Episode;
use App\Models\Podcast;
use App\Models\TranscriptSegment;
use App\Services\TranscriptChunkingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranscriptChunkingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'transcript_chunking.min_words' => 50,
            'transcript_chunking.max_words' => 200,
            'transcript_chunking.ideal_words' => 100,
            'transcript_chunking.natural_break_pause' => 3.0,
        ]);
    }

    public function test_get_config_returns_configuration()
    {
        $service = new TranscriptChunkingService;

        $config = $service->getConfig();

        $this->assertEquals(50, $config['min_words']);
        $this->assertEquals(200, $config['max_words']);
        $this->assertEquals(100, $config['ideal_words']);
        $this->assertEquals(3.0, $config['natural_break_pause']);
    }

    public function test_chunk_episode_returns_empty_for_no_segments()
    {
        $podcast = Podcast::factory()->create();
        $episode = Episode::factory()->create(['podcast_id' => $podcast->id]);

        $service = new TranscriptChunkingService;
        $result = $service->chunkEpisodeSegments($episode);

        $this->assertEquals(0, $result['original_count']);
        $this->assertEquals(0, $result['chunked_count']);
        $this->assertEquals(0, $result['deleted_count']);
    }

    public function test_chunk_episode_keeps_single_short_segment()
    {
        $podcast = Podcast::factory()->create();
        $episode = Episode::factory()->create(['podcast_id' => $podcast->id]);

        $this->createSegment($episode, 0, 10, 'This is a short segment with few words');

        $service = new TranscriptChunkingService;
        $result = $service->chunkEpisodeSegments($episode);

        $this->assertEquals(1, $result['original_count']);
        $this->assertEquals(1, $result['chunked_count']);
        $this->assertEquals(0, $result['deleted_count']);
    }

    public function test_chunk_episode_combines_small_segments()
    {
        $podcast = Podcast::factory()->create();
        $episode = Episode::factory()->create(['podcast_id' => $podcast->id]);

        // Create several small segments that should be combined
        // Each segment has about 10 words, so 6 segments = 60 words which is above min_words
        $this->createSegment($episode, 0, 5, 'Welcome to the show today everyone');
        $this->createSegment($episode, 5, 10, 'We have great topics to discuss');
        $this->createSegment($episode, 10, 15, 'Let us start with the first one');
        $this->createSegment($episode, 15, 20, 'This is about coding and programming');
        $this->createSegment($episode, 20, 25, 'I find it really fascinating stuff');
        $this->createSegment($episode, 25, 30, 'Here is what I think about it.');

        $service = new TranscriptChunkingService;
        $result = $service->chunkEpisodeSegments($episode);

        // Should combine segments since they are below min_words individually
        $this->assertEquals(6, $result['original_count']);
        $this->assertLessThan(6, $result['chunked_count']);
        $this->assertGreaterThan(0, $result['deleted_count']);
    }

    public function test_chunk_episode_respects_natural_breaks_on_sentence_end()
    {
        $podcast = Podcast::factory()->create();
        $episode = Episode::factory()->create(['podcast_id' => $podcast->id]);

        // Create segments where first ends with period (natural break)
        // First segment must exceed min_words (50) for natural break detection to apply
        $longText1 = 'This is a really long segment with many many words that keeps going on and on. '.
                     'It contains enough content to meet the minimum word count requirement easily. '.
                     'We need about sixty words so I am adding more and more text here to reach it. '.
                     'This should definitely help us reach the minimum threshold that is configured.';

        $longText2 = 'This is another segment that follows after the natural break point. '.
                     'It should start a new chunk because the previous one ended with a period.';

        $this->createSegment($episode, 0, 30, $longText1);
        $this->createSegment($episode, 30, 60, $longText2);

        $service = new TranscriptChunkingService;
        $result = $service->chunkEpisodeSegments($episode);

        // With natural break (period at end of first segment which exceeds min_words), should remain as 2 chunks
        $this->assertEquals(2, $result['original_count']);
        $this->assertEquals(2, $result['chunked_count']);
        $this->assertEquals(0, $result['deleted_count']);
    }

    public function test_chunk_episode_respects_pause_based_natural_break()
    {
        $podcast = Podcast::factory()->create();
        $episode = Episode::factory()->create(['podcast_id' => $podcast->id]);

        // First segment must exceed min_words (50) for natural break detection to apply
        $longText1 = 'This is a segment with many words that ends without punctuation and continues '.
                     'on for quite a while because we need to reach the minimum word count that is '.
                     'configured in the test setup approximately sixty or more words to ensure the '.
                     'natural break detection logic kicks in properly during the test execution';

        $longText2 = 'This segment has a long pause before it starts so it should be a new chunk '.
                     'even though the previous segment did not end with proper punctuation here';

        // Note: 5 second gap between segments (>3.0 natural_break_pause)
        $this->createSegment($episode, 0, 30, $longText1);
        $this->createSegment($episode, 35, 65, $longText2); // 5 second gap

        $service = new TranscriptChunkingService;
        $result = $service->chunkEpisodeSegments($episode);

        // Should remain as 2 chunks due to pause exceeding natural_break_pause
        $this->assertEquals(2, $result['original_count']);
        $this->assertEquals(2, $result['chunked_count']);
    }

    public function test_chunk_episode_forces_break_at_max_words()
    {
        $podcast = Podcast::factory()->create();
        $episode = Episode::factory()->create(['podcast_id' => $podcast->id]);

        // Create a very long segment that would exceed max_words if combined
        $words = [];
        for ($i = 0; $i < 150; $i++) {
            $words[] = 'word'.$i;
        }
        $veryLongText = implode(' ', $words);

        $this->createSegment($episode, 0, 60, $veryLongText);
        $this->createSegment($episode, 60, 120, $veryLongText);

        $service = new TranscriptChunkingService;
        $result = $service->chunkEpisodeSegments($episode);

        // Should not combine because it would exceed max_words (200)
        $this->assertEquals(2, $result['chunked_count']);
    }

    /**
     * Helper to create a transcript segment
     */
    private function createSegment(Episode $episode, float $start, float $end, string $text): TranscriptSegment
    {
        return TranscriptSegment::create([
            'episode_id' => $episode->id,
            'start_time' => $start,
            'end_time' => $end,
            'text' => $text,
            'confidence' => 0.95,
            'segment_index' => (int) $start,
            'word_count' => str_word_count($text),
            'embedding_status' => 'pending',
        ]);
    }
}
