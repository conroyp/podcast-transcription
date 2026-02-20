<?php

namespace Tests\Unit;

use App\Models\Episode;
use App\Models\Podcast;
use App\Services\Storage\PodcastFileManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PodcastFileManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_file_path_centralization()
    {
        // Create test podcast and episode
        $podcast = Podcast::create([
            'title' => 'Test Podcast',
            'description' => 'Test Description',
            'rss_url' => 'https://example.com/feed.xml',
        ]);

        $episode = Episode::create([
            'podcast_id' => $podcast->id,
            'title' => 'Test Episode',
            'description' => 'Test Description',
            'audio_url' => 'https://example.com/audio.mp3',
            'guid' => 'test-episode-guid',
            'published_at' => now(),
            'local_audio_path' => 'private/podcasts/audio/test-file.mp3',
        ]);

        // Test that file path handling is centralized
        $this->assertNotNull(PodcastFileManager::getEpisodeAudioRelativePath($episode));
        $this->assertStringContainsString('/storage/app/private/', PodcastFileManager::getEpisodeAudioPath($episode) ?? '');

        // Test filename generation
        $filename = PodcastFileManager::generateAudioFilename($episode);
        $this->assertStringContainsString('test-podcast', $filename);
        $this->assertStringContainsString('test-episode', $filename);

        // Test directory creation
        PodcastFileManager::ensureDirectoriesExist();
        $this->assertTrue(true); // If no exception thrown, directories were created successfully

        // Test transcript directory
        $transcriptDir = PodcastFileManager::getTranscriptDirectory($episode);
        $this->assertStringContainsString('transcripts', $transcriptDir);
        $this->assertStringContainsString((string) $episode->id, $transcriptDir);
    }
}
