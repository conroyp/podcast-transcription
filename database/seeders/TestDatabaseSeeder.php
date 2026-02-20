<?php

namespace Database\Seeders;

use App\Models\Episode;
use App\Models\Podcast;
use Illuminate\Database\Seeder;

class TestDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $podcast = Podcast::create([
            'title' => 'Test Podcast',
            'description' => 'A test podcast for testing purposes.',
            'rss_url' => 'https://example.com/feed.xml',
            'author' => 'Test Author',
            'language' => 'en',
            'is_active' => true,
        ]);

        Episode::create([
            'podcast_id' => $podcast->id,
            'title' => 'Test Episode 1',
            'description' => 'Description for test episode 1',
            'guid' => 'test-guid-1',
            'audio_url' => 'https://example.com/audio1.mp3',
            'published_at' => now()->subDays(2),
            'download_status' => 'pending',
            'transcription_status' => 'pending',
        ]);

        Episode::create([
            'podcast_id' => $podcast->id,
            'title' => 'Test Episode 2',
            'description' => 'Description for test episode 2',
            'guid' => 'test-guid-2',
            'audio_url' => 'https://example.com/audio2.mp3',
            'published_at' => now()->subDays(1),
            'download_status' => 'completed',
            'transcription_status' => 'completed',
            'local_audio_path' => 'podcasts/test/audio2.mp3',
            'file_size_bytes' => 1024 * 1024 * 5, // 5MB
            'duration_seconds' => 300,
        ]);

        Episode::create([
            'podcast_id' => $podcast->id,
            'title' => 'Test Episode 3',
            'description' => 'Description for test episode 3',
            'guid' => 'test-guid-3',
            'audio_url' => 'https://example.com/audio3.mp3',
            'published_at' => now(),
            'download_status' => 'failed',
            'transcription_status' => 'pending',
        ]);
    }
}
