<?php

namespace App\Providers;

use App\Services\ThemeService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the theming system.
 *
 * Registers the ThemeService, sets up view namespacing for theme overrides,
 * and shares theme data with all views.
 */
class ThemeServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ThemeService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $themeService = app(ThemeService::class);
        $theme = $themeService->getCurrentTheme();

        // Register theme view namespace with priority for overrides
        if ($theme !== 'base') {
            $themePath = resource_path("views/themes/{$theme}");

            if (is_dir($themePath)) {
                // Prepend theme views to take precedence over base views
                View::prependNamespace('components', "{$themePath}/components");
                View::addNamespace('theme', $themePath);
            }
        }

        // Share theme data with all views
        View::share('currentTheme', $theme);
        View::share('branding', $themeService->getBranding());
        View::share('themeConfig', $themeService->getThemeConfig());
        View::share('searchConfig', $themeService->getSearchConfig());

        // Register Blade directives for theme assets
        $this->registerBladeDirectives();
    }

    /**
     * Register custom Blade directives for theming.
     */
    private function registerBladeDirectives(): void
    {
        // Directive to output theme fonts
        Blade::directive('themeFonts', function () {
            return "<?php
                \$fonts = app(\App\Services\ThemeService::class)->getThemeFonts();
                foreach (\$fonts as \$font) {
                    echo '<link rel=\"preconnect\" href=\"' . parse_url(\$font, PHP_URL_HOST) . '\">';
                    echo '<link rel=\"stylesheet\" href=\"' . e(\$font) . '\">';
                }
            ?>";
        });

        // Directive to output CSS variable overrides
        Blade::directive('themeColorOverrides', function () {
            return "<?php
                \$overrides = app(\App\Services\ThemeService::class)->getCssVariableOverrides();
                if (\$overrides) {
                    echo '<style>' . \$overrides . '</style>';
                }
            ?>";
        });

        // Directive to set theme data attribute on root element
        Blade::directive('themeDataAttribute', function () {
            return "<?php echo 'data-theme=\"' . app(\App\Services\ThemeService::class)->getCurrentTheme() . '\"'; ?>";
        });

        // Directive to include theme-overridable partials
        // Tries theme-specific partial first, falls back to base partial
        Blade::directive('themeInclude', function ($expression) {
            return "<?php
                \$__partial = {$expression};
                \$__theme = app(\App\Services\ThemeService::class)->getCurrentTheme();
                \$__themePath = 'themes.' . \$__theme . '.partials.' . \$__partial;
                \$__basePath = 'partials.' . \$__partial;

                // Try theme-specific partial first, fall back to base
                if (\$__theme !== 'base' && view()->exists(\$__themePath)) {
                    echo \$__env->make(\$__themePath, \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path', '__partial', '__theme', '__themePath', '__basePath']))->render();
                } elseif (view()->exists(\$__basePath)) {
                    echo \$__env->make(\$__basePath, \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path', '__partial', '__theme', '__themePath', '__basePath']))->render();
                }
            ?>";
        });
    }
}
