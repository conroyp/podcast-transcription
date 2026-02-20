<?php

/*
|--------------------------------------------------------------------------
| What Did You Do Yesterday - Podcast Configuration
|--------------------------------------------------------------------------
|
| Theme-specific podcast configuration for the "What Did You Do Yesterday"
| podcast by Max Rushden and David O'Doherty. These values are automatically
| merged with config/podcast.php when the wdydy theme is active.
|
*/

return [
    'default_rss_url' => 'https://feeds.megaphone.fm/GLT5518536193',

    'name' => 'What Did You Do Yesterday',

    'hosts' => 'Max Rushden, David O\'Doherty',

    'transcription_context' => 'The comedy podcast What Did You Do Yesterday is hosted by Max Rushden and David O\'Doherty. Guests include comedians, actors, and public figures. Please maintain proper capitalization, punctuation, and formatting.',

    'episode_type_keywords' => [
        'midweek_mayhem' => ['midweek mayhem', 'midweek'],
        'interview' => ['interview', 'special guest'],
        'bonus' => ['bonus', 'extra'],
        'live' => ['live', 'live show'],
    ],
];
