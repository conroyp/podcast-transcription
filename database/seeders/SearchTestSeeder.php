<?php

namespace Database\Seeders;

use App\Models\Episode;
use App\Models\Podcast;
use App\Models\TranscriptSegment;
use Illuminate\Database\Seeder;
use Tests\Support\DeterministicFakeEmbeddingService;

class SearchTestSeeder extends Seeder
{
    public function run(): void
    {
        $embeddingService = new DeterministicFakeEmbeddingService;

        $podcast = Podcast::create([
            'title' => 'Test Podcast',
            'description' => 'A podcast for testing search',
            'rss_url' => 'https://example.com/feed.xml',
        ]);

        $episode = Episode::create([
            'podcast_id' => $podcast->id,
            'title' => 'The One About Search',
            'description' => 'We discuss how to search for things.',
            'guid' => 'search-test-episode',
            'audio_url' => 'https://example.com/audio.mp3',
            'published_at' => now(),
            'download_status' => 'completed',
            'transcription_status' => 'completed',
            'embedding_status' => 'completed',
        ]);

        $segments = [
            [
                'start_time' => 0.0,
                'end_time' => 5.0,
                'text' => 'Welcome to the search testing episode.',
            ],
            [
                'start_time' => 5.0,
                'end_time' => 10.0,
                'text' => 'We are talking about vector databases today.',
            ],
            [
                'start_time' => 10.0,
                'end_time' => 15.0,
                'text' => 'Postgres is a great database for vectors.',
            ],
            [
                'start_time' => 15.0,
                'end_time' => 20.0,
                'text' => 'Laravel makes testing easy.',
            ],
        ];

        foreach ($segments as $index => $segmentData) {
            $embedding = $embeddingService->generateEmbedding($segmentData['text']);

            // Format vector for Postgres pgvector
            // It expects a string like "[0.1,0.2,0.3]"
            $vectorString = '['.implode(',', $embedding).']';

            TranscriptSegment::create([
                'episode_id' => $episode->id,
                'start_time' => $segmentData['start_time'],
                'end_time' => $segmentData['end_time'],
                'text' => $segmentData['text'],
                'segment_index' => $index,
                'word_count' => str_word_count($segmentData['text']),
                'embedding_vector' => $vectorString,
                'embedding_status' => 'completed',
            ]);
        }
    }
}
