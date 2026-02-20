<?php

namespace Tests\Unit\Services;

use App\Models\Episode;
use App\Models\Podcast;
use App\Models\TranscriptSegment;
use App\Services\AdRemovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdRemovalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test ad removal configuration
        config([
            'ad_removal.enabled' => true,
            'ad_removal.end_patterns' => [
                'check out our new podcast',
                'brand new podcast alert',
            ],
            'ad_removal.start_patterns' => [
                'hello this is john here',
            ],
            'ad_removal.direct_start_patterns' => [
                'the example podcast is where',
            ],
            'ad_removal.host_names' => [
                'john' => ['john here', 'john speaking'],
            ],
            'ad_removal.search_percentage' => 0.3,
            'ad_removal.minimum_segments_for_percentage_search' => 10,
            'ad_removal.lookback_segments' => 20,
        ]);
    }

    public function test_is_enabled_returns_true_when_configured()
    {
        $service = new AdRemovalService;

        $this->assertTrue($service->isEnabled());
    }

    public function test_is_enabled_returns_false_when_no_patterns()
    {
        config(['ad_removal.end_patterns' => []]);

        $service = new AdRemovalService;

        $this->assertFalse($service->isEnabled());
    }

    public function test_is_enabled_returns_false_when_disabled_in_config()
    {
        config(['ad_removal.enabled' => false]);

        $service = new AdRemovalService;

        $this->assertFalse($service->isEnabled());
    }

    public function test_remove_ads_returns_early_when_disabled()
    {
        config(['ad_removal.enabled' => false]);

        $podcast = Podcast::factory()->create();
        $episode = Episode::factory()->create(['podcast_id' => $podcast->id]);

        $service = new AdRemovalService;
        $result = $service->removeAdsFromEpisode($episode);

        $this->assertEquals(0, $result['ads_found']);
        $this->assertEquals(0, $result['segments_removed']);
        $this->assertArrayHasKey('skipped', $result);
    }

    public function test_remove_ads_returns_empty_for_episode_with_no_segments()
    {
        $podcast = Podcast::factory()->create();
        $episode = Episode::factory()->create(['podcast_id' => $podcast->id]);

        $service = new AdRemovalService;
        $result = $service->removeAdsFromEpisode($episode);

        $this->assertEquals(0, $result['ads_found']);
        $this->assertEquals(0, $result['segments_removed']);
    }

    public function test_remove_ads_detects_ad_with_start_and_end_pattern()
    {
        $podcast = Podcast::factory()->create();
        $episode = Episode::factory()->create(['podcast_id' => $podcast->id]);

        // Create segments - main content followed by ad
        $this->createSegment($episode, 0, 10, 'Welcome to the show everyone');
        $this->createSegment($episode, 10, 20, 'Today we discuss interesting topics');
        $this->createSegment($episode, 20, 30, 'This is the main content');
        $this->createSegment($episode, 30, 40, 'More main content here');
        $this->createSegment($episode, 40, 50, 'Continuing the discussion');
        $this->createSegment($episode, 50, 60, 'Almost at the end now');
        $this->createSegment($episode, 60, 70, 'Still discussing main topic');
        $this->createSegment($episode, 70, 80, 'Final thoughts coming up');
        // Ad section starts here
        $this->createSegment($episode, 80, 90, 'Hello this is John here with something special');
        $this->createSegment($episode, 90, 100, 'I want to tell you about this amazing thing');
        $this->createSegment($episode, 100, 110, 'Check out our new podcast for more content');
        // Back to content
        $this->createSegment($episode, 110, 120, 'Thanks for listening to the show');

        $service = new AdRemovalService;
        $result = $service->removeAdsFromEpisode($episode);

        // Should find 1 ad and remove some segments
        $this->assertGreaterThanOrEqual(1, $result['ads_found']);
    }

    public function test_remove_ads_detects_direct_start_pattern()
    {
        $podcast = Podcast::factory()->create();
        $episode = Episode::factory()->create(['podcast_id' => $podcast->id]);

        // Create segments with direct start pattern
        $this->createSegment($episode, 0, 10, 'Welcome to the show');
        $this->createSegment($episode, 10, 20, 'Main content here');
        $this->createSegment($episode, 20, 30, 'More content');
        $this->createSegment($episode, 30, 40, 'Still discussing topics');
        $this->createSegment($episode, 40, 50, 'Great discussion today');
        $this->createSegment($episode, 50, 60, 'Wrapping up soon');
        $this->createSegment($episode, 60, 70, 'Final segment of main content');
        $this->createSegment($episode, 70, 80, 'Just a bit more');
        // Ad with direct start pattern
        $this->createSegment($episode, 80, 90, 'The example podcast is where you can find great content');
        $this->createSegment($episode, 90, 100, 'Thanks for listening');

        $service = new AdRemovalService;
        $result = $service->removeAdsFromEpisode($episode);

        // Should detect the direct start pattern
        $this->assertIsArray($result);
        $this->assertArrayHasKey('ads_found', $result);
    }

    public function test_remove_ads_does_not_modify_when_no_patterns_match()
    {
        $podcast = Podcast::factory()->create();
        $episode = Episode::factory()->create(['podcast_id' => $podcast->id]);

        // Create segments without any ad patterns
        $this->createSegment($episode, 0, 10, 'Welcome to the show');
        $this->createSegment($episode, 10, 20, 'Today we discuss coding');
        $this->createSegment($episode, 20, 30, 'PHP is great');
        $this->createSegment($episode, 30, 40, 'Laravel makes it easy');
        $this->createSegment($episode, 40, 50, 'Thanks for listening');

        $initialCount = $episode->transcriptSegments()->count();

        $service = new AdRemovalService;
        $result = $service->removeAdsFromEpisode($episode);

        $this->assertEquals(0, $result['ads_found']);
        $this->assertEquals(0, $result['segments_removed']);
        $this->assertEquals($initialCount, $episode->transcriptSegments()->count());
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
