<?php

namespace Database\Factories;

use App\Models\QueryEmbedding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QueryEmbedding>
 */
class QueryEmbeddingFactory extends Factory
{
    protected $model = QueryEmbedding::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $queryText = $this->faker->sentence();

        return [
            'query_text' => $queryText,
            'query_hash' => hash('sha256', $queryText),
            'embedding_vector' => null,
            'model' => 'text-embedding-3-small',
            'dimensions' => 1536,
            'use_count' => $this->faker->numberBetween(1, 50),
            'last_used_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Indicate this query embedding has never been used.
     */
    public function unused(): static
    {
        return $this->state(fn (array $attributes) => [
            'use_count' => 0,
            'last_used_at' => null,
        ]);
    }
}
