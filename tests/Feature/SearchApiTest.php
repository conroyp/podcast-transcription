<?php

namespace Tests\Feature;

use App\Services\EmbeddingService;
use Database\Seeders\SearchTestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DeterministicFakeEmbeddingService;
use Tests\TestCase;

class SearchApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(EmbeddingService::class, DeterministicFakeEmbeddingService::class);
        $this->seed(SearchTestSeeder::class);
    }

    public function test_search_ajax_returns_json_content_type(): void
    {
        $response = $this->get('/?q=vector&ajax=1');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
    }

    public function test_search_ajax_returns_success_structure(): void
    {
        $response = $this->getJson('/?q=vector&ajax=1');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'results',
            'total',
            'page',
            'limit',
            'query',
        ]);
        $response->assertJson(['success' => true]);
    }

    public function test_search_ajax_returns_results_with_expected_fields(): void
    {
        $response = $this->getJson('/?q=vector+databases&ajax=1');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $data = $response->json();
        $this->assertNotEmpty($data['results']);

        $firstResult = $data['results'][0];
        $this->assertArrayHasKey('id', $firstResult);
        $this->assertArrayHasKey('episode_id', $firstResult);
        $this->assertArrayHasKey('text', $firstResult);
        $this->assertArrayHasKey('highlighted_text', $firstResult);
        $this->assertArrayHasKey('start_time', $firstResult);
        $this->assertArrayHasKey('end_time', $firstResult);
        $this->assertArrayHasKey('formatted_time', $firstResult);
        $this->assertArrayHasKey('formatted_end_time', $firstResult);
        $this->assertArrayHasKey('episode_title', $firstResult);
        $this->assertArrayHasKey('episode_type', $firstResult);
        $this->assertArrayHasKey('formatted_date', $firstResult);
    }

    public function test_search_ajax_respects_page_parameter(): void
    {
        $response = $this->getJson('/?q=test&page=2&ajax=1');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'page' => 2,
        ]);
    }

    public function test_search_ajax_respects_episode_type_filter(): void
    {
        $response = $this->getJson('/?q=test&episode_type=interview&ajax=1');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'episode_type' => 'interview',
        ]);
    }

    public function test_search_ajax_respects_sort_parameter(): void
    {
        $response = $this->getJson('/?q=test&sort=newest&ajax=1');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'sort' => 'newest',
        ]);
    }

    public function test_search_ajax_with_empty_query_returns_error(): void
    {
        $response = $this->getJson('/?q=&ajax=1');

        $response->assertStatus(422);
    }

    public function test_search_ajax_with_short_query_returns_error(): void
    {
        $response = $this->getJson('/?q=a&ajax=1');

        $response->assertStatus(422);
    }

    public function test_episodes_ajax_returns_json_content_type(): void
    {
        $response = $this->get('/?view=episodes&ajax=1');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
    }

    public function test_episodes_ajax_returns_success_structure(): void
    {
        $response = $this->getJson('/?view=episodes&ajax=1');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'episodes',
            'pagination',
            'filters',
        ]);
        $response->assertJson(['success' => true]);
    }

    public function test_episodes_ajax_returns_episodes_with_expected_fields(): void
    {
        $response = $this->getJson('/?view=episodes&ajax=1');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertNotEmpty($data['episodes']);

        $firstEpisode = $data['episodes'][0];
        $this->assertArrayHasKey('id', $firstEpisode);
        $this->assertArrayHasKey('title', $firstEpisode);
        $this->assertArrayHasKey('description', $firstEpisode);
        $this->assertArrayHasKey('short_description', $firstEpisode);
        $this->assertArrayHasKey('formatted_date', $firstEpisode);
        $this->assertArrayHasKey('episode_type', $firstEpisode);
        $this->assertArrayHasKey('transcription_status', $firstEpisode);
    }

    public function test_episodes_ajax_pagination_structure(): void
    {
        $response = $this->getJson('/?view=episodes&ajax=1');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'pagination' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ]);
    }

    public function test_episodes_ajax_respects_page_parameter(): void
    {
        $response = $this->getJson('/?view=episodes&page=1&ajax=1');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertEquals(1, $data['pagination']['current_page']);
    }

    public function test_episodes_ajax_respects_episode_type_filter(): void
    {
        $response = $this->getJson('/?view=episodes&episode_type=midweek_mayhem&ajax=1');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'filters' => [
                'episode_type' => 'midweek_mayhem',
            ],
        ]);
    }

    public function test_episodes_ajax_respects_sort_parameter(): void
    {
        $response = $this->getJson('/?view=episodes&sort=oldest&ajax=1');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'filters' => [
                'sort' => 'oldest',
            ],
        ]);
    }

    public function test_episodes_ajax_respects_search_query(): void
    {
        $response = $this->getJson('/?view=episodes&q=Search&ajax=1');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'filters' => [
                'q' => 'Search',
            ],
        ]);

        $data = $response->json();
        $this->assertNotEmpty($data['episodes']);
        $this->assertStringContainsString('Search', $data['episodes'][0]['title']);
    }

    public function test_search_ajax_handles_special_characters_in_query(): void
    {
        $response = $this->getJson('/?q=test%20query&ajax=1');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_search_ajax_returns_json_with_accept_header(): void
    {
        $response = $this->get('/?q=test', ['Accept' => 'application/json']);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');
    }
}
