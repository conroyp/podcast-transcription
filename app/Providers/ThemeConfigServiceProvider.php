<?php

namespace App\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for theme-specific configuration merging.
 *
 * This provider automatically merges theme-specific config files from
 * config/{theme}/ directories into the base configuration. This allows
 * themes to override specific config values without duplicating entire
 * config files.
 *
 * Example structure:
 *   config/ad_removal.php           - Base config
 *   config/wdydy/ad_removal.php    - Theme overrides (merged on top)
 *
 * Services can then just use config('ad_removal') and get the merged result.
 */
class ThemeConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * We use register() instead of boot() to ensure config merging happens
     * before other services try to read the config values.
     */
    public function register(): void
    {
        // Defer merging until after all configs are loaded
        $this->app->booted(function () {
            $this->mergeThemeConfigs();
        });
    }

    /**
     * Merge theme-specific configs into base configs.
     */
    protected function mergeThemeConfigs(): void
    {
        $theme = config('theme.default', 'base');

        if ($theme === 'base') {
            return;
        }

        $themePath = config_path($theme);

        if (! is_dir($themePath)) {
            Log::debug("Theme config directory not found: {$themePath}");

            return;
        }

        $mergedConfigs = [];

        foreach (glob("{$themePath}/*.php") as $file) {
            $key = basename($file, '.php');
            $themeConfig = require $file;

            if (! is_array($themeConfig)) {
                Log::warning("Theme config file did not return an array: {$file}");

                continue;
            }

            $baseConfig = config($key, []);

            // Deep merge: theme values override base values
            $merged = $this->arrayMergeRecursiveDistinct($baseConfig, $themeConfig);

            config([$key => $merged]);
            $mergedConfigs[] = $key;
        }
    }

    /**
     * Recursively merge arrays, with later values overriding earlier ones.
     *
     * Unlike array_merge_recursive, this doesn't create nested arrays for
     * duplicate keys - it replaces them. This is the expected behavior for
     * config overrides.
     */
    protected function arrayMergeRecursiveDistinct(array $base, array $override): array
    {
        $merged = $base;

        foreach ($override as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                // Both are arrays - recurse
                $merged[$key] = $this->arrayMergeRecursiveDistinct($merged[$key], $value);
            } else {
                // Override the value
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}
