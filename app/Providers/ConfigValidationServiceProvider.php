<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Validates required configuration values at application boot time.
 *
 * This provider ensures that critical environment variables are set before
 * the application attempts to use them, providing clear error messages
 * instead of confusing runtime failures.
 */
class ConfigValidationServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Only validate in production to avoid breaking development/testing
        if (app()->environment('production')) {
            $this->validateRequiredConfig();
        }
    }

    /**
     * Validate that required configuration values are set.
     *
     * @throws \RuntimeException If a required config value is missing
     */
    private function validateRequiredConfig(): void
    {
        $required = [
            // Database configuration
            'database.connections.pgsql.database' => 'DB_DATABASE',
            'database.connections.pgsql.host' => 'DB_HOST',

            // OpenAI API (required for embeddings)
            'services.openai.api_key' => 'OPENAI_API_KEY',
        ];

        $missing = [];

        foreach ($required as $config => $env) {
            if (empty(config($config))) {
                $missing[] = $env;
            }
        }

        if (! empty($missing)) {
            throw new \RuntimeException(
                'Missing required environment variables: '.implode(', ', $missing).
                '. Please check your .env file.'
            );
        }
    }
}
