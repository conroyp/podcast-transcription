<?php

namespace App\Providers;

use App\Contracts\EmbeddingProviderInterface;
use App\Models\Episode;
use App\Models\TranscriptSegment;
use App\Observers\EpisodeObserver;
use App\Observers\TranscriptSegmentObserver;
use App\Services\Embedding\OpenAIEmbeddingProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind the embedding provider interface to the configured implementation
        // This allows swapping providers by changing this binding (or via config)
        $this->app->bind(EmbeddingProviderInterface::class, OpenAIEmbeddingProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register model observers for cache invalidation
        Episode::observe(EpisodeObserver::class);
        TranscriptSegment::observe(TranscriptSegmentObserver::class);
    }
}
