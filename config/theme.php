<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Theme
    |--------------------------------------------------------------------------
    |
    | The default theme to use when no user preference is set.
    | Available: 'base', 'wdydy', or custom theme names
    |
    */

    'default' => env('THEME_DEFAULT', 'base'),

    /*
    |--------------------------------------------------------------------------
    | Site Branding
    |--------------------------------------------------------------------------
    |
    | Customizable branding elements that appear throughout the site.
    |
    */

    'branding' => [
        'site_name' => env('THEME_SITE_NAME', 'Podcast Search'),
        'tagline' => env('THEME_TAGLINE', 'Search your favorite podcast transcripts'),
        'logo_path' => env('THEME_LOGO_PATH'),
        'favicon_path' => env('THEME_FAVICON_PATH'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Page Settings
    |--------------------------------------------------------------------------
    */

    'search' => [
        'placeholder' => env('THEME_SEARCH_PLACEHOLDER', 'Search episodes...'),
        'show_episode_types' => (bool) env('THEME_SHOW_EPISODE_TYPES', true),
        'default_view' => env('THEME_DEFAULT_VIEW', 'transcripts'), // transcripts or episodes
    ],

    /*
    |--------------------------------------------------------------------------
    | SEO Configuration
    |--------------------------------------------------------------------------
    |
    | Default SEO content that can be customized per deployment.
    |
    */

    'seo' => [
        'home_title' => env('THEME_SEO_HOME_TITLE', 'Podcast Search'),
        'home_description' => env('THEME_SEO_HOME_DESCRIPTION', 'Search podcast episodes by transcript content. Find exactly what was said and when.'),
        'search_title_template' => env('THEME_SEO_SEARCH_TITLE', '{query} - Search Results'),
        'episode_title_template' => env('THEME_SEO_EPISODE_TITLE', '{title}'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Color Scheme Overrides
    |--------------------------------------------------------------------------
    |
    | Override CSS custom properties via environment variables.
    | These are applied in addition to the theme CSS file.
    | Use standard CSS color values (hex, rgb, hsl).
    |
    */

    'colors' => [
        'brand_primary' => env('THEME_COLOR_PRIMARY'),
        'brand_secondary' => env('THEME_COLOR_SECONDARY'),
        'brand_accent' => env('THEME_COLOR_ACCENT'),
        'header_gradient_from' => env('THEME_HEADER_GRADIENT_FROM'),
        'header_gradient_to' => env('THEME_HEADER_GRADIENT_TO'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Available Themes
    |--------------------------------------------------------------------------
    |
    | Register available themes. Each theme should have:
    | - name: Display name for the theme
    | - css_file: Path to the theme CSS file (relative to resources/css/themes/)
    | - fonts: Array of font URLs to load (Google Fonts, Bunny Fonts, etc.)
    |
    */

    'themes' => [
        'base' => [
            'name' => 'Default',
            'css_file' => 'base.css',
            'fonts' => [],
        ],
        'wdydy' => [
            'name' => 'What Did You Do Yesterday',
            'css_file' => 'wdydy.css',
            'fonts' => [
                'https://fonts.bunny.net/css?family=lilita-one:400',
            ],
        ],
    ],
];
