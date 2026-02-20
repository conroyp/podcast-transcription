<?php

/*
|--------------------------------------------------------------------------
| What Did You Do Yesterday - Social Links Configuration
|--------------------------------------------------------------------------
|
| Theme-specific social sharing links for the "What Did You Do Yesterday"
| podcast. These values are automatically merged with config/social_links.php
| when the wdydy theme is active.
|
*/

return [
    'share_url' => 'https://www.everythingisshowbiz.com/',

    'share_text' => 'Everything is Showbiz!',

    'links' => [
        [
            'platform' => 'Twitter',
            'url' => 'https://twitter.com/intent/tweet?text=Everything%20is%20Showbiz!&url=https%3A%2F%2Fwww.everythingisshowbiz.com%2F',
            'icon' => 'twitter',
        ],
        [
            'platform' => 'Bluesky',
            'url' => 'https://bsky.app/intent/compose?text=Everything%20is%20Showbiz!%20https%3A%2F%2Fwww.everythingisshowbiz.com%2F',
            'icon' => 'bluesky',
        ],
        [
            'platform' => 'Facebook',
            'url' => 'https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fwww.everythingisshowbiz.com%2F',
            'icon' => 'facebook',
        ],
    ],
];
