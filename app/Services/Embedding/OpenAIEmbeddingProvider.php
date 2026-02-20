<?php

namespace App\Services\Embedding;

use App\Contracts\EmbeddingProviderInterface;
use Illuminate\Support\Facades\Log;
use OpenAI;
use OpenAI\Exceptions\ErrorException as OpenAIErrorException;
use OpenAI\Exceptions\TransporterException;
use OpenAI\Exceptions\UnserializableResponse;

/**
 * OpenAI embedding provider implementation.
 *
 * Uses OpenAI's embedding API (text-embedding-3-small by default) to generate
 * vector representations of text for semantic similarity search.
 */
class OpenAIEmbeddingProvider implements EmbeddingProviderInterface
{
    private \OpenAI\Client $client;

    private string $model;

    private int $dimensions;

    public function __construct()
    {
        $this->client = OpenAI::client(config('services.openai.api_key'));
        $this->model = config('embeddings.model', 'text-embedding-3-small');
        $this->dimensions = (int) config('embeddings.dimensions', 1536);
    }

    /**
     * {@inheritdoc}
     */
    public function generateEmbedding(string $text): array
    {
        try {
            $response = $this->client->embeddings()->create([
                'model' => $this->model,
                'input' => $text,
                'dimensions' => $this->dimensions,
            ]);

            return $response->embeddings[0]->embedding;
        } catch (OpenAIErrorException $e) {
            Log::error('OpenAI API error during embedding generation', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'text_length' => strlen($text),
                'model' => $this->model,
            ]);
            throw $e;
        } catch (TransporterException $e) {
            Log::error('OpenAI connection error during embedding generation', [
                'error' => $e->getMessage(),
                'text_length' => strlen($text),
                'model' => $this->model,
            ]);
            throw $e;
        } catch (UnserializableResponse $e) {
            Log::error('OpenAI response parsing error during embedding generation', [
                'error' => $e->getMessage(),
                'text_length' => strlen($text),
                'model' => $this->model,
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function generateBatchEmbeddings(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        // OpenAI has a limit on batch size, so we process in chunks
        $batchSize = 100;
        $allEmbeddings = [];

        foreach (array_chunk($texts, $batchSize) as $chunk) {
            try {
                $response = $this->client->embeddings()->create([
                    'model' => $this->model,
                    'input' => $chunk,
                    'dimensions' => $this->dimensions,
                ]);

                foreach ($response->embeddings as $embedding) {
                    $allEmbeddings[] = $embedding->embedding;
                }
            } catch (OpenAIErrorException $e) {
                Log::error('OpenAI API error during batch embedding generation', [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'batch_size' => count($chunk),
                    'model' => $this->model,
                ]);
                throw $e;
            } catch (TransporterException $e) {
                Log::error('OpenAI connection error during batch embedding generation', [
                    'error' => $e->getMessage(),
                    'batch_size' => count($chunk),
                    'model' => $this->model,
                ]);
                throw $e;
            } catch (UnserializableResponse $e) {
                Log::error('OpenAI response parsing error during batch embedding generation', [
                    'error' => $e->getMessage(),
                    'batch_size' => count($chunk),
                    'model' => $this->model,
                ]);
                throw $e;
            }
        }

        return $allEmbeddings;
    }

    /**
     * {@inheritdoc}
     */
    public function getDimensions(): int
    {
        return $this->dimensions;
    }

    /**
     * {@inheritdoc}
     */
    public function getModel(): string
    {
        return $this->model;
    }
}
