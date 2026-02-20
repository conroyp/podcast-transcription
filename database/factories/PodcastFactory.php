<?php

namespace Database\Factories;

use App\Models\Podcast;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Podcast>
 */
class PodcastFactory extends Factory
{
    protected $model = Podcast::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'rss_url' => $this->faker->url(),
            'website_url' => $this->faker->url(),
            'author' => $this->faker->name(),
            'language' => 'en',
            'category' => $this->faker->word(),
            'image_url' => $this->faker->imageUrl(),
            'is_active' => true,
            'last_checked_at' => now(),
            'last_episode_at' => now(),
        ];
    }
}
