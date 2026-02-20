<?php

/*
|--------------------------------------------------------------------------
| What Did You Do Yesterday - Ad Removal Configuration
|--------------------------------------------------------------------------
|
| Theme-specific ad removal patterns for the "What Did You Do Yesterday"
| podcast. These values are automatically merged with config/ad_removal.php
| when the wdydy theme is active.
|
| These patterns detect:
| - Cross-promotional ads for other podcasts (Parenting Hell, Like-Minded
|   Friends, Jessica Napet's podcast, Always Be Comedy)
| - Ad reads by Max Rushden, Tom Allen, and Jessica Napet
|
*/

return [
    'enabled' => true,

    'end_patterns' => [
        // Parenting Hell cross-promos
        'episode 2 of parenting hell',
        'episode two of parenting hell',
        'series 9 episode 2 of parenting hell',
        'series nine episode two of parenting hell',
        'parenting hell',
        // Like-Minded Friends cross-promos
        'like-minded friends',
        'like minded friends',
        'likeminded friends',
        // Jessica Napet podcast promos
        'brand new podcast alert',
        'new podcast alert',
        'podcast alert',
        // Always Be Comedy promos (these also act as direct start patterns)
        'the always be comedy podcast is where',
        'always be comedy podcast is where',
        'always be comedy podcast',
    ],

    'start_patterns' => [
        // Max Rushden ad reads
        'hello max rushden here',
        'hello, max rushden here',
        // Tom Allen ad reads
        'hello tom allen here',
        'hello, tom allen here',
        // Jessica Napet ad reads
        'hello, it\'s me, jessica napet',
        'hello it\'s me jessica napet',
        'hello its me jessica napet',
        'hello, its me, jessica napet',
        'jessica napet',
    ],

    'direct_start_patterns' => [
        // Always Be Comedy ads start with the promo itself
        'the always be comedy podcast is where',
        'always be comedy podcast is where',
        'always be comedy podcast',
    ],

    'host_names' => [
        // Used for partial matching when "hello" is found separately
        'max' => ['max rushden'],
        'tom' => ['tom allen'],
        'jessica' => ['jessica napet'],
    ],

    'search_percentage' => 0.3,
    'minimum_segments_for_percentage_search' => 10,
    'lookback_segments' => 20,
];
