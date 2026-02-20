<?php

namespace Database\Factories;

use App\Models\Episode;
use App\Models\Podcast;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Episode>
 */
class EpisodeFactory extends Factory
{
    protected $model = Episode::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'podcast_id' => Podcast::factory(),
            'title' => $this->faker->sentence(5),
            'description' => $this->faker->paragraph(),
            'guid' => $this->faker->uuid(),
            'audio_url' => $this->faker->url().'/episode.mp3',
            'published_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'duration_seconds' => $this->faker->numberBetween(600, 7200),
            'file_size_bytes' => $this->faker->numberBetween(10000000, 100000000),
            'audio_format' => 'mp3',
            'episode_number' => $this->faker->numberBetween(1, 100),
            'season_number' => $this->faker->numberBetween(1, 5),
            'episode_type' => 'interview',
            'download_status' => 'pending',
            'transcription_status' => 'pending',
            'diarization_status' => 'pending',
        ];
    }

    /**
     * Indicate that the episode has been downloaded.
     */
    public function downloaded(): static
    {
        return $this->state(fn (array $attributes) => [
            'download_status' => 'completed',
            'local_audio_path' => 'podcasts/audio/'.$attributes['guid'].'.mp3',
        ]);
    }

    /**
     * Indicate that the episode has been transcribed.
     */
    public function transcribed(): static
    {
        return $this->state(fn (array $attributes) => [
            'download_status' => 'completed',
            'transcription_status' => 'completed',
        ]);
    }
}
