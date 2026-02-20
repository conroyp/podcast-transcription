<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ad Removal Configuration
    |--------------------------------------------------------------------------
    |
    | Enable or disable automatic ad removal from transcripts.
    |
    */

    'enabled' => env('AD_REMOVAL_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Ad End Patterns
    |--------------------------------------------------------------------------
    |
    | Phrases that indicate an advertisement is ending. When these patterns
    | are found, the service looks backwards to find where the ad started.
    |
    | These are typically promotional mentions like "check out [other podcast]"
    | or "new podcast alert" that appear at the end of ad reads.
    |
    */

    'end_patterns' => [
        // Add your ad end patterns here
        // Examples:
        // 'check out our other podcast',
        // 'new podcast alert',
        // 'brand new podcast',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ad Start Patterns
    |--------------------------------------------------------------------------
    |
    | Phrases that indicate an advertisement is starting. The service looks
    | for these when searching backwards from an end pattern.
    |
    | These are typically host introduction patterns like "hello [name] here"
    | that signal the beginning of an ad read.
    |
    */

    'start_patterns' => [
        // Add your ad start patterns here
        // Examples:
        // 'hello john smith here',
        // 'hi it\'s jane doe',
    ],

    /*
    |--------------------------------------------------------------------------
    | Direct Start Patterns
    |--------------------------------------------------------------------------
    |
    | End patterns that also serve as the start of the ad (no separate intro).
    | When these are found, the ad removal starts from the pattern itself
    | rather than searching backwards for a separate start pattern.
    |
    */

    'direct_start_patterns' => [
        // Add patterns that are both start and end
        // Examples:
        // 'the example podcast is where',
    ],

    /*
    |--------------------------------------------------------------------------
    | Host Names for Partial Matching
    |--------------------------------------------------------------------------
    |
    | Names used for partial pattern matching when "hello" is found but
    | the full start pattern is split across segments.
    |
    | Format: 'shortname' => ['full name', 'alternate spelling']
    |
    */

    'host_names' => [
        // Add host names for partial matching
        // Examples:
        // 'john' => ['john smith'],
        // 'jane' => ['jane doe', 'jane d'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Configuration
    |--------------------------------------------------------------------------
    |
    | Controls how far into the episode to search for ads.
    |
    */

    // What percentage of the episode (from the end) to search for ads
    // 0.3 = search last 30% of segments
    'search_percentage' => 0.3,

    // Minimum number of segments before limiting search area
    'minimum_segments_for_percentage_search' => 10,

    // How many segments to look back when finding ad start
    'lookback_segments' => 20,
];
