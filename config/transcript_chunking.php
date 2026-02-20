<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Transcript Chunking Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for combining transcript segments into larger chunks
    | suitable for embedding generation and semantic search.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Word Count Limits
    |--------------------------------------------------------------------------
    |
    | Define the word count boundaries for transcript chunks.
    | These values balance semantic coherence with embedding effectiveness.
    |
    */

    // Minimum words per chunk (will try to reach this before breaking)
    'min_words' => 15,

    // Maximum words per chunk (hard limit, will force break)
    'max_words' => 50,

    // Ideal words per chunk (preferred target range)
    'ideal_words' => 30,

    /*
    |--------------------------------------------------------------------------
    | Natural Break Detection
    |--------------------------------------------------------------------------
    |
    | Settings for detecting natural boundaries between speech segments.
    |
    */

    // Minimum pause (in seconds) to consider a natural break point
    'natural_break_pause' => 2.0,

    /*
    |--------------------------------------------------------------------------
    | Break Point Detection
    |--------------------------------------------------------------------------
    |
    | Patterns and rules for identifying good places to break chunks.
    |
    */

    // Common speech transition words that indicate good break points
    'transition_words' => [
        'and', 'but', 'so', 'then', 'now', 'well', 'right', 'okay', 'anyway',
    ],

    /*
    |--------------------------------------------------------------------------
    | Quality Thresholds
    |--------------------------------------------------------------------------
    |
    | Minimum viable chunk size for edge cases (very short episodes, etc.)
    |
    */

    // Absolute minimum words (for edge cases)
    'absolute_min_words' => 20,
];
