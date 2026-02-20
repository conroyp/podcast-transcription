<?php

namespace Tests\Feature;

use App\Models\Episode;
use App\Models\Podcast;
use App\Models\TranscriptSegment;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DeterministicFakeEmbeddingService;
use Tests\TestCase;

class SsrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Swap the real EmbeddingService with our deterministic fake
        $this->app->bind(EmbeddingService::class, DeterministicFakeEmbeddingService::class);
    }

    public function test_search_page_ssr_renders_results_inline()
    {
        // Seed data using the seeder that we know works
        $this->seed(\Database\Seeders\SearchTestSeeder::class);

        // Perform search
        $response = $this->get('/?q=vector');

        $response->assertStatus(200);

        // Check Title
        $response->assertSee('<title>', false);
        $response->assertSee('vector', false);

        // Check Meta Description
        $response->assertSee('<meta name="description"', false);

        // Check Inline Results Robustly
        $content = $response->getContent();
        $dom = new \DOMDocument;
        // Suppress warnings for HTML5 tags that DOMDocument might complain about
        libxml_use_internal_errors(true);
        $dom->loadHTML($content);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // 1. Verify the results list container exists
        $resultsList = $xpath->query('//div[@id="resultsList"]');
        $this->assertEquals(1, $resultsList->length, 'Results list container (#resultsList) not found in HTML');

        // 2. Verify we have at least one result card
        $resultCards = $xpath->query('//div[@id="resultsList"]//div[contains(@class, "result-card")]');
        $this->assertGreaterThan(0, $resultCards->length, 'No result cards (.result-card) found inside #resultsList');

        // 3. Verify the content exists specifically within the result card
        $firstCardContent = $resultCards->item(0)->textContent;
        $this->assertStringContainsString('We are talking about vector databases today', $firstCardContent);
        $this->assertStringContainsString('The One About Search', $firstCardContent);
    }

    public function test_episode_list_page_ssr_renders_episodes()
    {
        // Seed data
        $podcast = Podcast::create([
            'title' => 'Test Podcast',
            'rss_url' => 'http://example.com/feed.xml',
        ]);
        $episode1 = Episode::create([
            'podcast_id' => $podcast->id,
            'title' => 'First Episode',
            'published_at' => now()->subDays(1),
            'audio_url' => 'http://example.com/audio1.mp3',
            'guid' => 'guid-2',
        ]);
        $episode2 = Episode::create([
            'podcast_id' => $podcast->id,
            'title' => 'Second Episode',
            'published_at' => now(),
            'audio_url' => 'http://example.com/audio2.mp3',
            'guid' => 'guid-3',
        ]);

        // Request episode list
        $response = $this->get('/?view=episodes');

        $response->assertStatus(200);

        $content = $response->getContent();
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($content);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // 1. Verify the episode list container exists
        $episodeList = $xpath->query('//div[@id="episodeCardsList"]');
        $this->assertEquals(1, $episodeList->length, 'Episode list container (#episodeCardsList) not found');

        // 2. Verify we have the correct number of episode cards
        // The cards are <a> tags directly inside the grid
        $episodeCards = $xpath->query('//div[@id="episodeCardsList"]/a');
        $this->assertGreaterThanOrEqual(2, $episodeCards->length, 'Not enough episode cards found');

        // 3. Verify the titles are present in the cards
        $titles = [];
        foreach ($episodeCards as $card) {
            // Look for h3 inside the card
            $h3 = $xpath->query('.//h3', $card)->item(0);
            if ($h3) {
                $titles[] = trim($h3->textContent);
            }
        }

        $this->assertContains('First Episode', $titles);
        $this->assertContains('Second Episode', $titles);
    }

    public function test_search_returns_relevant_results(): void
    {
        $this->seed(\Database\Seeders\SearchTestSeeder::class);

        $response = $this->get('/?q=vector+databases');

        $response->assertStatus(200);
        $response->assertSee('We are talking about vector databases today.');
    }

    public function test_search_returns_different_results_for_different_queries(): void
    {
        $this->seed(\Database\Seeders\SearchTestSeeder::class);

        $response = $this->get('/?q=Laravel');

        $response->assertStatus(200);
        $response->assertSee('Laravel makes testing easy.');
    }

    public function test_specific_episode_page_ssr_renders_metadata()
    {
        // Seed data
        $podcast = Podcast::create([
            'title' => 'Test Podcast',
            'rss_url' => 'http://example.com/feed.xml',
        ]);
        $episode = Episode::create([
            'podcast_id' => $podcast->id,
            'title' => 'Specific Episode Title',
            'description' => 'This is a specific description for SEO.',
            'audio_url' => 'http://example.com/audio3.mp3',
            'guid' => 'guid-4',
            'published_at' => now(),
        ]);

        // Add a transcript segment to verify sidebar content
        TranscriptSegment::create([
            'episode_id' => $episode->id,
            'start_time' => 0,
            'end_time' => 10,
            'text' => 'This is the transcript text.',
            'segment_index' => 0,
            'word_count' => 5,
            'embedding_status' => 'completed',
        ]);

        // Request specific episode
        $response = $this->get('/?episode='.$episode->id);

        $response->assertStatus(200);

        // Check SEO Metadata
        $response->assertSee('<title>Specific Episode Title - Everything Is Showbiz</title>', false);
        $response->assertSee('<meta name="description" content="This is a specific description for SEO.">', false);

        $content = $response->getContent();
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($content);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // 1. Verify the sidebar exists and is populated
        $sidebar = $xpath->query('//div[@id="episodeSidebar"]');
        $this->assertEquals(1, $sidebar->length, 'Sidebar (#episodeSidebar) not found');

        // 2. Verify the title in the sidebar
        $sidebarTitle = $xpath->query('//h2[@id="sidebarEpisodeTitle"]');
        $this->assertEquals(1, $sidebarTitle->length, 'Sidebar title element not found');
        $this->assertStringContainsString('Specific Episode Title', trim($sidebarTitle->item(0)->textContent));

        // 3. Verify the transcript segment is rendered
        $segments = $xpath->query('//div[@id="transcriptSegments"]//div[contains(@class, "segment-item")]');
        $this->assertGreaterThan(0, $segments->length, 'No transcript segments found in sidebar');

        $firstSegmentText = $segments->item(0)->textContent;
        $this->assertStringContainsString('This is the transcript text.', $firstSegmentText);
    }
}
