<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Embedding Provider
    |--------------------------------------------------------------------------
    |
    | The embedding provider to use for generating text embeddings.
    | Currently supported: 'openai'
    |
    | To add a custom provider, create a class implementing
    | App\Contracts\EmbeddingProviderInterface and update the binding
    | in App\Providers\AppServiceProvider.
    |
    */

    'provider' => env('EMBEDDING_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Embedding Model Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the embedding model used in semantic search functionality.
    | These settings apply to the OpenAI provider by default.
    |
    */

    'model' => env('EMBEDDING_MODEL', 'text-embedding-3-small'),
    'dimensions' => env('EMBEDDING_DIMENSIONS', 1536),

    /*
    |--------------------------------------------------------------------------
    | Query Embedding Cache
    |--------------------------------------------------------------------------
    |
    | Cache query embeddings in the database to avoid redundant API calls.
    | The same query text always produces the same vector, so caching is safe.
    |
    */

    'query_cache' => [
        'enabled' => env('QUERY_EMBEDDING_CACHE_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Hybrid Search
    |--------------------------------------------------------------------------
    |
    | Combine keyword and semantic (vector) search using a single query
    | with word-boundary regex matching for better results on all query types.
    |
    */

    'hybrid' => [
        'enabled' => env('HYBRID_SEARCH_ENABLED', true),
        'keyword_weight' => (float) env('HYBRID_KEYWORD_WEIGHT', 1.0),
    ],
];
