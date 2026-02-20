<?php

namespace Tests\Unit\Services;

use App\Models\Episode;
use App\Models\Podcast;
use App\Services\PodcastDownloadService;
use App\Services\Storage\PodcastFileManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PodcastDownloadServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_download_an_episode()
    {
        $podcast = Podcast::create([
            'title' => 'Test Podcast',
            'rss_url' => 'https://example.com/feed.xml',
        ]);

        $episode = Episode::create([
            'podcast_id' => $podcast->id,
            'title' => 'Test Episode',
            'audio_url' => 'https://example.com/audio.mp3',
            'guid' => 'test-guid',
            'published_at' => now(),
        ]);

        // Get the absolute path the service will use, so we can create the file
        // after Http::fake (which doesn't actually write via sink)
        $absolutePath = PodcastFileManager::getAudioDownloadAbsolutePath($episode);

        Http::fake([
            'example.com/*' => Http::response('fake audio content', 200),
        ]);

        // Ensure the directory exists and pre-create the file since Http::fake
        // won't actually write to disk via sink()
        $directory = dirname($absolutePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        file_put_contents($absolutePath, 'fake audio content');

        $service = new PodcastDownloadService;
        $path = $service->download($episode);

        $this->assertNotNull($path);
        $this->assertEquals('completed', $episode->fresh()->download_status);
    }

    protected function tearDown(): void
    {
        // Clean up any files created during tests
        $files = glob(storage_path('app/private/podcasts/audio/*.mp3'));
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        parent::tearDown();
    }
}
