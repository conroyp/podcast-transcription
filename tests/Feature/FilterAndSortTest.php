<?php

namespace Tests\Feature;

use App\Models\Episode;
use App\Models\Podcast;
use App\Models\TranscriptSegment;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DeterministicFakeEmbeddingService;
use Tests\TestCase;

class FilterAndSortTest extends TestCase
{
    use RefreshDatabase;

    private Podcast $podcast;

    private Episode $interviewEpisode;

    private Episode $midweekEpisode;

    private Episode $olderEpisode;

    private Episode $newerEpisode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(EmbeddingService::class, DeterministicFakeEmbeddingService::class);
        $this->seedTestData();
    }

    private function seedTestData(): void
    {
        $embeddingService = new DeterministicFakeEmbeddingService;

        $this->podcast = Podcast::create([
            'title' => 'Test Podcast',
            'rss_url' => 'http://example.com/feed.xml',
        ]);

        $this->interviewEpisode = Episode::create([
            'podcast_id' => $this->podcast->id,
            'title' => 'Interview Episode',
            'description' => 'An interview about testing',
            'episode_type' => 'interview',
            'published_at' => now()->subDays(10),
            'audio_url' => 'http://example.com/interview.mp3',
            'guid' => 'guid-interview',
            'transcription_status' => 'completed',
            'embedding_status' => 'completed',
        ]);
        $this->olderEpisode = $this->interviewEpisode;

        $this->midweekEpisode = Episode::create([
            'podcast_id' => $this->podcast->id,
            'title' => 'Midweek Mayhem Episode',
            'description' => 'A midweek mayhem show',
            'episode_type' => 'midweek_mayhem',
            'published_at' => now()->subDays(1),
            'audio_url' => 'http://example.com/midweek.mp3',
            'guid' => 'guid-midweek',
            'transcription_status' => 'completed',
            'embedding_status' => 'completed',
        ]);
        $this->newerEpisode = $this->midweekEpisode;

        foreach ([$this->interviewEpisode, $this->midweekEpisode] as $episode) {
            $text = 'Testing content for '.$episode->title;
            $embedding = $embeddingService->generateEmbedding($text);
            $vectorString = '['.implode(',', $embedding).']';

            TranscriptSegment::create([
                'episode_id' => $episode->id,
                'start_time' => 0.0,
                'end_time' => 10.0,
                'text' => $text,
                'segment_index' => 0,
                'word_count' => str_word_count($text),
                'embedding_vector' => $vectorString,
                'embedding_status' => 'completed',
            ]);
        }
    }

    public function test_search_filters_by_episode_type_interview(): void
    {
        $response = $this->getJson('/?q=testing&episode_type=interview&ajax=1');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'episode_type' => 'interview',
        ]);

        // Verify all returned results are from interview episodes
        $data = $response->json();
        foreach ($data['results'] as $result) {
            $this->assertEquals('interview', $result['episode_type']);
        }
    }

    public function test_search_filters_by_episode_type_midweek_mayhem(): void
    {
        $response = $this->getJson('/?q=testing&episode_type=midweek_mayhem&ajax=1');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'episode_type' => 'midweek_mayhem',
        ]);

        // Verify all returned results are from midweek episodes
        $data = $response->json();
        foreach ($data['results'] as $result) {
            $this->assertEquals('midweek_mayhem', $result['episode_type']);
        }
    }

    public function test_search_sorts_by_newest(): void
    {
        $response = $this->getJson('/?q=testing&sort=newest&ajax=1');

        $response->assertStatus(200);
        $data = $response->json();

        if (count($data['results']) >= 2) {
            $firstResultEpisodeId = $data['results'][0]['episode_id'];
            $this->assertEquals($this->newerEpisode->id, $firstResultEpisodeId);
        }
    }

    public function test_search_sorts_by_oldest(): void
    {
        $response = $this->getJson('/?q=testing&sort=oldest&ajax=1');

        $response->assertStatus(200);
        $data = $response->json();

        if (count($data['results']) >= 2) {
            $firstResultEpisodeId = $data['results'][0]['episode_id'];
            $this->assertEquals($this->olderEpisode->id, $firstResultEpisodeId);
        }
    }

    public function test_search_sorts_by_relevance_default(): void
    {
        $response = $this->getJson('/?q=testing&ajax=1');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'sort' => 'relevance',
        ]);
    }

    public function test_search_pagination_returns_correct_page(): void
    {
        $response = $this->getJson('/?q=testing&page=1&ajax=1');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'page' => 1,
        ]);
    }

    public function test_search_pagination_metadata_is_correct(): void
    {
        $response = $this->getJson('/?q=testing&ajax=1');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('limit', $data);
        $this->assertIsInt($data['total']);
        $this->assertIsInt($data['page']);
        $this->assertIsInt($data['limit']);
    }

    public function test_episode_list_filters_by_episode_type(): void
    {
        $response = $this->getJson('/?view=episodes&episode_type=interview&ajax=1');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertNotEmpty($data['episodes']);

        foreach ($data['episodes'] as $episode) {
            $this->assertEquals('interview', $episode['episode_type']);
        }
    }

    public function test_episode_list_sorts_by_oldest(): void
    {
        $response = $this->getJson('/?view=episodes&sort=oldest&ajax=1');

        $response->assertStatus(200);

        $data = $response->json();
        $episodes = $data['episodes'];

        if (count($episodes) >= 2) {
            $this->assertEquals($this->olderEpisode->id, $episodes[0]['id']);
        }
    }

    public function test_episode_list_sorts_by_newest_default(): void
    {
        $response = $this->getJson('/?view=episodes&ajax=1');

        $response->assertStatus(200);

        $data = $response->json();
        $episodes = $data['episodes'];

        if (count($episodes) >= 2) {
            $this->assertEquals($this->newerEpisode->id, $episodes[0]['id']);
        }
    }

    public function test_episode_list_pagination_works(): void
    {
        $response = $this->getJson('/?view=episodes&page=1&ajax=1');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertEquals(1, $data['pagination']['current_page']);
    }

    public function test_episode_list_search_filters_by_title(): void
    {
        $response = $this->getJson('/?view=episodes&q=Interview&ajax=1');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertNotEmpty($data['episodes']);
        $this->assertStringContainsString('Interview', $data['episodes'][0]['title']);
    }

    public function test_episode_type_filter_with_invalid_type_returns_validation_error(): void
    {
        $response = $this->getJson('/?view=episodes&episode_type=invalid_type&ajax=1');

        $response->assertStatus(422);
    }

    public function test_sort_filter_with_invalid_sort_returns_validation_error(): void
    {
        $response = $this->getJson('/?view=episodes&sort=invalid_sort&ajax=1');

        $response->assertStatus(422);
    }

    public function test_hybrid_search_returns_results_for_a_single_word_query(): void
    {
        $this->seed(\Database\Seeders\SearchTestSeeder::class);

        $response = $this->getJson('/?q=vector&ajax=1');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $data = $response->json();
        $this->assertNotEmpty($data['results']);
    }

    public function test_quoted_queries_still_use_ilike_exclusively(): void
    {
        $this->seed(\Database\Seeders\SearchTestSeeder::class);

        $response = $this->getJson('/?q="vector databases"&ajax=1');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'is_exact_match' => true,
        ]);
    }

    public function test_hybrid_search_results_are_deduplicated(): void
    {
        $this->seed(\Database\Seeders\SearchTestSeeder::class);

        $response = $this->getJson('/?q=vector+databases&ajax=1');

        $response->assertStatus(200);
        $data = $response->json();

        if (! empty($data['results'])) {
            $ids = array_column($data['results'], 'id');
            $this->assertCount(count(array_unique($ids)), $ids);
        }
    }

    public function test_hybrid_search_can_be_disabled_via_config(): void
    {
        $this->seed(\Database\Seeders\SearchTestSeeder::class);
        config(['embeddings.hybrid.enabled' => false]);

        $response = $this->getJson('/?q=vector&ajax=1');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_hybrid_search_supports_sort_parameters(): void
    {
        $this->seed(\Database\Seeders\SearchTestSeeder::class);

        $response = $this->getJson('/?q=vector&sort=newest&ajax=1');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'sort' => 'newest',
        ]);
    }

    public function test_hybrid_search_supports_episode_type_filter(): void
    {
        $this->seed(\Database\Seeders\SearchTestSeeder::class);

        $response = $this->getJson('/?q=vector&episode_type=interview&ajax=1');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'episode_type' => 'interview',
        ]);
    }
}
