<?php

namespace Tests\Feature;

use App\Providers\ThemeConfigServiceProvider;
use Tests\TestCase;

class ThemeConfigServiceProviderTest extends TestCase
{
    // =================================================================
    // Config Merging Tests
    // =================================================================

    public function test_merge_method_skips_when_base_theme()
    {
        // Reset config to base theme
        config(['theme.default' => 'base']);

        // Clear any existing replacements
        config(['transcript_cleaning.text_replacements' => []]);

        // Manually call the merge method
        $provider = new ThemeConfigServiceProvider($this->app);
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('mergeThemeConfigs');
        $method->setAccessible(true);
        $method->invoke($provider);

        // Should NOT have theme-specific replacements
        $replacements = config('transcript_cleaning.text_replacements', []);
        $this->assertArrayNotHasKey('max Rushton', $replacements);
    }

    public function test_merge_method_loads_theme_configs_when_theme_active()
    {
        // Set theme to wdydy
        config(['theme.default' => 'wdydy']);

        // Clear any existing replacements
        config(['transcript_cleaning.text_replacements' => []]);

        // Manually call the merge method
        $provider = new ThemeConfigServiceProvider($this->app);
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('mergeThemeConfigs');
        $method->setAccessible(true);
        $method->invoke($provider);

        // Should have theme-specific replacements
        $replacements = config('transcript_cleaning.text_replacements', []);
        $this->assertArrayHasKey('max Rushton', $replacements);
        $this->assertEquals('Max Rushden', $replacements['max Rushton']);
    }

    public function test_theme_config_directory_structure()
    {
        // Verify the expected directory structure exists
        $this->assertDirectoryExists(config_path('wdydy'));
        $this->assertFileExists(config_path('wdydy/transcript_cleaning.php'));
        $this->assertFileExists(config_path('wdydy/ad_removal.php'));
    }

    public function test_theme_ad_removal_config_structure()
    {
        // Verify theme ad_removal config has expected structure
        $themeConfig = require config_path('wdydy/ad_removal.php');

        $this->assertIsArray($themeConfig);
        $this->assertArrayHasKey('enabled', $themeConfig);
        $this->assertArrayHasKey('end_patterns', $themeConfig);
        $this->assertArrayHasKey('start_patterns', $themeConfig);
        $this->assertTrue($themeConfig['enabled']);
        $this->assertNotEmpty($themeConfig['end_patterns']);
    }

    public function test_theme_transcript_cleaning_config_structure()
    {
        // Verify theme transcript_cleaning config has expected structure
        $themeConfig = require config_path('wdydy/transcript_cleaning.php');

        $this->assertIsArray($themeConfig);
        $this->assertArrayHasKey('text_replacements', $themeConfig);
        $this->assertNotEmpty($themeConfig['text_replacements']);

        // Check for some expected replacements
        $this->assertArrayHasKey('max Rushton', $themeConfig['text_replacements']);
        $this->assertArrayHasKey('Rushton', $themeConfig['text_replacements']);
    }

    // =================================================================
    // Array Merge Behavior Tests
    // =================================================================

    public function test_theme_values_override_base_values()
    {
        // Set up a scenario where both base and theme have the same key
        config([
            'theme.default' => 'wdydy',
            'test_config' => ['shared_key' => 'base_value'],
        ]);

        // Create a temporary theme config file
        $themePath = config_path('wdydy/test_config.php');
        $originalExists = file_exists($themePath);
        $originalContent = $originalExists ? file_get_contents($themePath) : null;

        file_put_contents($themePath, '<?php return ["shared_key" => "theme_value"];');

        try {
            // Re-run the provider
            $provider = new \App\Providers\ThemeConfigServiceProvider($this->app);
            $reflection = new \ReflectionClass($provider);
            $method = $reflection->getMethod('mergeThemeConfigs');
            $method->setAccessible(true);
            $method->invoke($provider);

            // Theme value should override base value
            $this->assertEquals('theme_value', config('test_config.shared_key'));
        } finally {
            // Clean up
            if ($originalExists) {
                file_put_contents($themePath, $originalContent);
            } else {
                @unlink($themePath);
            }
        }
    }

    public function test_theme_podcast_config_structure()
    {
        // Verify theme podcast config has expected structure
        $themeConfig = require config_path('wdydy/podcast.php');

        $this->assertIsArray($themeConfig);
        $this->assertArrayHasKey('default_rss_url', $themeConfig);
        $this->assertArrayHasKey('name', $themeConfig);
        $this->assertArrayHasKey('hosts', $themeConfig);
        $this->assertNotEmpty($themeConfig['default_rss_url']);
    }

    public function test_podcast_rss_url_available_when_theme_active()
    {
        // Set theme to wdydy
        config(['theme.default' => 'wdydy']);

        // Clear podcast config to simulate fresh state
        config(['podcast.default_rss_url' => '']);

        // Manually call the merge method
        $provider = new ThemeConfigServiceProvider($this->app);
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('mergeThemeConfigs');
        $method->setAccessible(true);
        $method->invoke($provider);

        // Should have the RSS URL from theme config
        $rssUrl = config('podcast.default_rss_url');
        $this->assertNotEmpty($rssUrl);
        $this->assertStringContainsString('megaphone.fm', $rssUrl);
    }

    public function test_deep_merge_preserves_base_keys_not_in_theme()
    {
        config([
            'theme.default' => 'wdydy',
            'test_deep' => [
                'base_only' => 'should_remain',
                'nested' => ['base_key' => 'base_value'],
            ],
        ]);

        $themePath = config_path('wdydy/test_deep.php');
        $originalExists = file_exists($themePath);
        $originalContent = $originalExists ? file_get_contents($themePath) : null;

        file_put_contents($themePath, '<?php return ["nested" => ["theme_key" => "theme_value"]];');

        try {
            $provider = new \App\Providers\ThemeConfigServiceProvider($this->app);
            $reflection = new \ReflectionClass($provider);
            $method = $reflection->getMethod('mergeThemeConfigs');
            $method->setAccessible(true);
            $method->invoke($provider);

            // Base-only key should remain
            $this->assertEquals('should_remain', config('test_deep.base_only'));

            // Nested should have both keys
            $this->assertEquals('base_value', config('test_deep.nested.base_key'));
            $this->assertEquals('theme_value', config('test_deep.nested.theme_key'));
        } finally {
            if ($originalExists) {
                file_put_contents($themePath, $originalContent);
            } else {
                @unlink($themePath);
            }
        }
    }
}
