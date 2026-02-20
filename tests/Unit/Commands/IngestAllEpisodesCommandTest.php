<?php

use App\Console\Commands\IngestAllEpisodes;

it('maps legacy ingest-all options to forwarded options', function () {
    $forwarded = IngestAllEpisodes::mapLegacyOptions([
        '--rss-url' => 'https://example.com/feed.xml',
        '--limit' => '5',
        '--oldest-first' => true,
        '--dry-run' => true,
    ]);

    expect($forwarded)->toBe([
        '--rss-url' => 'https://example.com/feed.xml',
        '--limit' => '5',
        '--oldest-first' => true,
        '--dry-run' => true,
    ]);
});

it('drops null and false legacy options when forwarding', function () {
    $forwarded = IngestAllEpisodes::mapLegacyOptions([
        '--rss-url' => null,
        '--limit' => null,
        '--oldest-first' => false,
        '--dry-run' => false,
    ]);

    expect($forwarded)->toBe([]);
});
