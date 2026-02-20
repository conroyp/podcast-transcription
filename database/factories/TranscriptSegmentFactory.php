<?php

namespace Database\Factories;

use App\Models\Episode;
use App\Models\TranscriptSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TranscriptSegment>
 */
class TranscriptSegmentFactory extends Factory
{
    protected $model = TranscriptSegment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = $this->faker->randomFloat(2, 0, 3500);
        $duration = $this->faker->randomFloat(2, 1, 30);

        return [
            'episode_id' => Episode::factory(),
            'start_time' => $startTime,
            'end_time' => round($startTime + $duration, 2),
            'text' => $this->faker->paragraph(),
            'confidence' => $this->faker->randomFloat(3, 0.7, 1.0),
            'embedding_status' => 'pending',
            'embedding_vector' => null,
            'speaker_id' => null,
            'segment_index' => $this->faker->numberBetween(0, 100),
            'chunk_group' => $this->faker->numberBetween(0, 10),
            'embedding_metadata' => null,
            'embedding_created_at' => null,
        ];
    }

    /**
     * Indicate the segment is pending embedding generation.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'embedding_status' => 'pending',
            'embedding_vector' => null,
            'embedding_metadata' => null,
            'embedding_created_at' => null,
        ]);
    }

    /**
     * Indicate the segment has a completed embedding.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'embedding_status' => 'completed',
            'embedding_created_at' => now(),
            'embedding_metadata' => ['model' => 'text-embedding-3-small', 'dimensions' => 1536],
        ]);
    }

    /**
     * Indicate the segment has failed embedding generation.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'embedding_status' => 'failed',
            'embedding_vector' => null,
            'embedding_metadata' => null,
            'embedding_created_at' => null,
        ]);
    }
}
