<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Social Links Configuration
    |--------------------------------------------------------------------------
    |
    | Social sharing and profile links displayed in the site footer or sidebar.
    | Theme-specific overrides can be defined in config/{theme}/social_links.php
    | and will be deep merged with these base defaults.
    |
    | Each entry supports:
    |   'platform' - display name / aria-label
    |   'url'      - full share or profile URL
    |   'icon'     - one of: twitter, bluesky, facebook (maps to SVG in the view)
    |
    */

    'share_url' => env('APP_URL', ''),

    'share_text' => env('THEME_SITE_NAME', ''),

    'links' => [
        // Add social links here, or override in a theme-specific config file.
        // Example:
        // [
        //     'platform' => 'Twitter',
        //     'url'      => 'https://twitter.com/intent/tweet?text=Hello&url=https://example.com/',
        //     'icon'     => 'twitter',
        // ],
    ],
];
