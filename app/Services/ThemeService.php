<?php

namespace App\Services;

/**
 * Service for managing theme configuration and settings.
 *
 * Provides access to theme configuration, branding, and generates
 * CSS variable overrides from environment settings.
 */
class ThemeService
{
    /**
     * Get the currently active theme name.
     */
    public function getCurrentTheme(): string
    {
        return config('theme.default', 'base');
    }

    /**
     * Get configuration for the current theme.
     *
     * @return array{name: string, css_file: string, fonts: array}
     */
    public function getThemeConfig(): array
    {
        $theme = $this->getCurrentTheme();

        return config("theme.themes.{$theme}", config('theme.themes.base'));
    }

    /**
     * Get site branding configuration.
     *
     * @return array{site_name: string, tagline: string|null, logo_path: string|null, favicon_path: string|null}
     */
    public function getBranding(): array
    {
        return config('theme.branding');
    }

    /**
     * Get search page configuration.
     *
     * @return array{placeholder: string, show_episode_types: bool, default_view: string}
     */
    public function getSearchConfig(): array
    {
        return config('theme.search');
    }

    /**
     * Get SEO configuration.
     *
     * @return array{home_title: string, home_description: string, search_title_template: string, episode_title_template: string}
     */
    public function getSeoConfig(): array
    {
        return config('theme.seo');
    }

    /**
     * Generate CSS variable overrides from config.
     *
     * Creates a CSS string with :root variable overrides based on
     * environment color settings in config/theme.php.
     */
    public function getCssVariableOverrides(): string
    {
        $colors = array_filter(config('theme.colors', []));

        if (empty($colors)) {
            return '';
        }

        $css = ':root {';
        foreach ($colors as $key => $value) {
            // Convert snake_case to kebab-case for CSS variables
            $cssVar = '--'.str_replace('_', '-', $key);
            $css .= "{$cssVar}: {$value};";
        }
        $css .= '}';

        return $css;
    }

    /**
     * Get fonts to load for the current theme.
     *
     * @return array<string>
     */
    public function getThemeFonts(): array
    {
        $config = $this->getThemeConfig();

        return $config['fonts'] ?? [];
    }

    /**
     * Get the theme CSS file path.
     */
    public function getThemeCssFile(): string
    {
        $config = $this->getThemeConfig();

        return $config['css_file'] ?? 'base.css';
    }

    /**
     * Check if the current theme is the base theme.
     */
    public function isBaseTheme(): bool
    {
        return $this->getCurrentTheme() === 'base';
    }

    /**
     * Get SEO title for the home page.
     */
    public function getHomeTitle(): string
    {
        return config('theme.seo.home_title', 'Podcast Search');
    }

    /**
     * Get SEO description for the home page.
     */
    public function getHomeDescription(): string
    {
        return config('theme.seo.home_description', 'Search podcast episodes by transcript content.');
    }

    /**
     * Get SEO title for search results.
     */
    public function getSearchTitle(string $query): string
    {
        $template = config('theme.seo.search_title_template', '{query} - Search Results');

        return str_replace('{query}', $query, $template);
    }

    /**
     * Get SEO title for an episode page.
     */
    public function getEpisodeTitle(string $episodeTitle): string
    {
        $template = config('theme.seo.episode_title_template', '{title}');

        return str_replace('{title}', $episodeTitle, $template);
    }
}
