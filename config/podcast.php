<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Podcast Configuration
    |--------------------------------------------------------------------------
    |
    | These values configure the default podcast for ingestion commands.
    | For multi-podcast setups, podcasts are stored in the database.
    |
    */

    'default_rss_url' => env('PODCAST_DEFAULT_RSS_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Podcast Metadata
    |--------------------------------------------------------------------------
    |
    | Basic information about the podcast being processed.
    |
    */

    'name' => env('PODCAST_NAME', 'My Podcast'),
    'hosts' => env('PODCAST_HOSTS', ''),

    /*
    |--------------------------------------------------------------------------
    | Transcription Context
    |--------------------------------------------------------------------------
    |
    | This context is provided to the Whisper transcription engine to improve
    | accuracy. Include podcast name, host names, common guests, and any
    | domain-specific terminology.
    |
    | Example for a comedy podcast:
    | "The comedy podcast Example Show is hosted by Jane Doe and John Smith.
    | Guests include comedians, actors, and public figures. Please maintain
    | proper capitalization, punctuation, and formatting."
    |
    */

    'transcription_context' => env('PODCAST_TRANSCRIPTION_CONTEXT',
        'This is a podcast transcription. Please maintain proper capitalization, punctuation, and formatting.'
    ),

    /*
    |--------------------------------------------------------------------------
    | Episode Type Detection
    |--------------------------------------------------------------------------
    |
    | Keywords used to detect episode types from titles.
    | Format: 'type_name' => ['keyword1', 'keyword2']
    |
    */

    'episode_type_keywords' => [
        'interview' => ['interview', 'special guest'],
        'bonus' => ['bonus', 'extra'],
        'live' => ['live', 'live show'],
    ],
];
