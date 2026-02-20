<?php

namespace Tests\Support;

use App\Services\EmbeddingService;

class DeterministicFakeEmbeddingService extends EmbeddingService
{
    public function __construct()
    {
        // Override parent constructor to avoid OpenAI client initialization
    }

    public function getOrCreateQueryEmbedding(string $queryText): array
    {
        return $this->generateEmbedding($queryText);
    }

    public function generateEmbedding(string $text): array
    {
        // Check for keywords to ensure high similarity for tests
        $keywords = ['vector', 'Laravel', 'search'];
        $seedText = $text;

        foreach ($keywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                $seedText = $keyword;
                break;
            }
        }

        // Use a hash of the text to seed a random number generator
        // This ensures the same text always produces the same vector
        $seed = crc32($seedText);
        mt_srand($seed);

        $vector = [];
        for ($i = 0; $i < 1536; $i++) {
            // Generate random float between -1 and 1
            $vector[] = (mt_rand() / mt_getrandmax()) * 2 - 1;
        }

        // Normalize the vector (optional, but good for cosine similarity)
        $magnitude = sqrt(array_sum(array_map(fn ($x) => $x * $x, $vector)));

        if ($magnitude > 0) {
            $vector = array_map(fn ($x) => $x / $magnitude, $vector);
        }

        return $vector;
    }
}
