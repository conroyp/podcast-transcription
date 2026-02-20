<?php

declare(strict_types=1);

use App\Providers\ThemeConfigServiceProvider;

test('base footer links config exists and has empty links array', function () {
    $config = require config_path('footer_links.php');

    expect($config)->toBeArray()
        ->toHaveKey('links')
        ->and($config['links'])->toBeArray()->toBeEmpty();
});

test('wdydy footer links config exists and has valid links', function () {
    $config = require config_path('wdydy/footer_links.php');

    expect($config)->toBeArray()->toHaveKey('links');
    expect($config['links'])->not->toBeEmpty();

    foreach ($config['links'] as $link) {
        expect($link)->toHaveKeys(['label', 'url']);
        expect($link['label'])->not->toBeEmpty();
        expect($link['url'])->not->toBeEmpty();
    }
});

test('wdydy footer links config contains expected project links', function () {
    $config = require config_path('wdydy/footer_links.php');
    $labels = array_column($config['links'], 'label');

    expect($labels)->toContain('Kids Sudoku')
        ->toContain('JD Captcha')
        ->toContain('Tic-Tac-GoAway')
        ->toContain('Eamon Dunphy Soundboard');
});

test('wdydy theme merges footer links config on boot', function () {
    config(['theme.default' => 'wdydy']);
    config(['footer_links.links' => []]);

    $provider = new ThemeConfigServiceProvider(app());
    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('mergeThemeConfigs');
    $method->setAccessible(true);
    $method->invoke($provider);

    $links = config('footer_links.links', []);
    $labels = array_column($links, 'label');

    expect($links)->not->toBeEmpty();
    expect($labels)->toContain('Kids Sudoku');
});

test('base theme has no footer links by default', function () {
    config(['theme.default' => 'base']);
    config(['footer_links.links' => []]);

    $provider = new ThemeConfigServiceProvider(app());
    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('mergeThemeConfigs');
    $method->setAccessible(true);
    $method->invoke($provider);

    expect(config('footer_links.links', []))->toBeEmpty();
});

test('footer links view renders links from config', function () {
    config(['footer_links.links' => [
        ['label' => 'Test Project', 'url' => 'https://example.com'],
    ]]);

    $rendered = view('themes.wdydy.partials.footer-links')->render();

    expect($rendered)->toContain('Test Project')
        ->toContain('https://example.com')
        ->toContain('Time to kill');
});

test('footer links view renders nothing when no links configured', function () {
    config(['footer_links.links' => []]);

    $rendered = view('themes.wdydy.partials.footer-links')->render();

    expect(trim($rendered))->toBeEmpty();
});

test('about content view renders social links from config', function () {
    config(['social_links.links' => [
        ['platform' => 'Bluesky', 'url' => 'https://bsky.app/profile/example', 'icon' => 'bluesky'],
        ['platform' => 'Twitter', 'url' => 'https://x.com/example', 'icon' => 'twitter'],
    ]]);

    $rendered = view('themes.wdydy.partials.about-content')->render();

    expect($rendered)
        ->toContain('https://bsky.app/profile/example')
        ->toContain('https://x.com/example')
        ->toContain('send all feedback');
});

test('about content view omits feedback sentence when no social links configured', function () {
    config(['social_links.links' => []]);

    $rendered = view('themes.wdydy.partials.about-content')->render();

    expect($rendered)
        ->not->toContain('send all feedback')
        ->toContain('Leave a review');
});
