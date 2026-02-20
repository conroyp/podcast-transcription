<?php

namespace App\Contracts;

/**
 * Interface for embedding generation providers.
 *
 * This interface allows for swappable embedding providers, making it easy
 * to switch between OpenAI, Cohere, local models, or other providers.
 */
interface EmbeddingProviderInterface
{
    /**
     * Generate embedding vector for text.
     *
     * @param  string  $text  The text to generate an embedding for
     * @return array<int, float> The embedding vector as an array of floats
     *
     * @throws \Exception When embedding generation fails
     */
    public function generateEmbedding(string $text): array;

    /**
     * Generate embeddings for multiple texts in batch.
     *
     * @param  array<int, string>  $texts  Array of texts to generate embeddings for
     * @return array<int, array<int, float>> Array of embedding vectors in same order as input
     *
     * @throws \Exception When batch embedding generation fails
     */
    public function generateBatchEmbeddings(array $texts): array;

    /**
     * Get the dimensionality of embeddings produced by this provider.
     *
     * @return int The number of dimensions in the embedding vectors
     */
    public function getDimensions(): int;

    /**
     * Get the model name/identifier used by this provider.
     *
     * @return string The model identifier
     */
    public function getModel(): string;
}
