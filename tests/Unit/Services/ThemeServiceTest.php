<?php

namespace Tests\Unit\Services;

use App\Services\ThemeService;
use Tests\TestCase;

class ThemeServiceTest extends TestCase
{
    private ThemeService $themeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->themeService = new ThemeService;
    }

    protected function tearDown(): void
    {
        // Reset config to defaults after each test
        config(['theme.default' => 'base']);
        config(['theme.colors' => [
            'brand_primary' => null,
            'brand_secondary' => null,
            'brand_accent' => null,
            'header_gradient_from' => null,
            'header_gradient_to' => null,
        ]]);
        parent::tearDown();
    }

    // =================================================================
    // 1.1.1 - 1.1.2: getCurrentTheme tests
    // =================================================================

    public function test_get_current_theme_returns_default_base_theme(): void
    {
        config(['theme.default' => 'base']);

        $theme = $this->themeService->getCurrentTheme();

        $this->assertEquals('base', $theme);
    }

    public function test_get_current_theme_returns_configured_theme(): void
    {
        config(['theme.default' => 'wdydy']);

        $theme = $this->themeService->getCurrentTheme();

        $this->assertEquals('wdydy', $theme);
    }

    // =================================================================
    // 1.1.3 - 1.1.4: getBranding tests
    // =================================================================

    public function test_get_branding_returns_expected_structure(): void
    {
        $branding = $this->themeService->getBranding();

        $this->assertIsArray($branding);
        $this->assertArrayHasKey('site_name', $branding);
        $this->assertArrayHasKey('tagline', $branding);
        $this->assertArrayHasKey('logo_path', $branding);
        $this->assertArrayHasKey('favicon_path', $branding);
    }

    public function test_get_branding_uses_config_values(): void
    {
        config([
            'theme.branding.site_name' => 'Test Site',
            'theme.branding.tagline' => 'Test Tagline',
            'theme.branding.logo_path' => '/test/logo.png',
            'theme.branding.favicon_path' => '/test/favicon.ico',
        ]);

        $branding = $this->themeService->getBranding();

        $this->assertEquals('Test Site', $branding['site_name']);
        $this->assertEquals('Test Tagline', $branding['tagline']);
        $this->assertEquals('/test/logo.png', $branding['logo_path']);
        $this->assertEquals('/test/favicon.ico', $branding['favicon_path']);
    }

    // =================================================================
    // 1.1.5: getSearchConfig test
    // =================================================================

    public function test_get_search_config_returns_expected_structure(): void
    {
        $searchConfig = $this->themeService->getSearchConfig();

        $this->assertIsArray($searchConfig);
        $this->assertArrayHasKey('placeholder', $searchConfig);
        $this->assertArrayHasKey('show_episode_types', $searchConfig);
        $this->assertArrayHasKey('default_view', $searchConfig);
    }

    // =================================================================
    // 1.1.6: getSeoConfig test
    // =================================================================

    public function test_get_seo_config_returns_expected_structure(): void
    {
        $seoConfig = $this->themeService->getSeoConfig();

        $this->assertIsArray($seoConfig);
        $this->assertArrayHasKey('home_title', $seoConfig);
        $this->assertArrayHasKey('home_description', $seoConfig);
        $this->assertArrayHasKey('search_title_template', $seoConfig);
        $this->assertArrayHasKey('episode_title_template', $seoConfig);
    }

    // =================================================================
    // 1.1.7 - 1.1.8: getColors tests (via getCssVariableOverrides)
    // =================================================================

    public function test_get_colors_returns_expected_structure(): void
    {
        $colors = config('theme.colors');

        $this->assertIsArray($colors);
        $this->assertArrayHasKey('brand_primary', $colors);
        $this->assertArrayHasKey('brand_secondary', $colors);
        $this->assertArrayHasKey('brand_accent', $colors);
        $this->assertArrayHasKey('header_gradient_from', $colors);
        $this->assertArrayHasKey('header_gradient_to', $colors);
    }

    public function test_get_colors_applies_env_overrides(): void
    {
        config(['theme.colors.brand_primary' => '#ff0000']);

        $css = $this->themeService->getCssVariableOverrides();

        $this->assertStringContainsString('--brand-primary: #ff0000', $css);
    }

    // =================================================================
    // 1.1.9 - 1.1.10: getThemeConfig tests
    // =================================================================

    public function test_get_theme_config_returns_config_for_current_theme(): void
    {
        config(['theme.default' => 'wdydy']);

        $themeConfig = $this->themeService->getThemeConfig();

        $this->assertIsArray($themeConfig);
        $this->assertEquals('What Did You Do Yesterday', $themeConfig['name']);
        $this->assertEquals('wdydy.css', $themeConfig['css_file']);
        $this->assertArrayHasKey('fonts', $themeConfig);
    }

    public function test_get_theme_config_returns_base_for_unknown_theme(): void
    {
        config(['theme.default' => 'nonexistent_theme']);

        $themeConfig = $this->themeService->getThemeConfig();

        $this->assertEquals('Default', $themeConfig['name']);
        $this->assertEquals('base.css', $themeConfig['css_file']);
    }

    // =================================================================
    // 1.1.11 - 1.1.12: getThemeFonts tests
    // =================================================================

    public function test_get_theme_fonts_returns_empty_for_base_theme(): void
    {
        config(['theme.default' => 'base']);

        $fonts = $this->themeService->getThemeFonts();

        $this->assertIsArray($fonts);
        $this->assertEmpty($fonts);
    }

    public function test_get_theme_fonts_returns_font_urls_for_wdydy_theme(): void
    {
        config(['theme.default' => 'wdydy']);

        $fonts = $this->themeService->getThemeFonts();

        $this->assertIsArray($fonts);
        $this->assertNotEmpty($fonts);
        $this->assertStringContainsString('lilita-one', $fonts[0]);
    }

    // =================================================================
    // 1.1.13 - 1.1.14: getCssVariableOverrides tests
    // =================================================================

    public function test_get_css_variable_overrides_returns_empty_when_no_env_colors(): void
    {
        config(['theme.colors' => [
            'brand_primary' => null,
            'brand_secondary' => null,
            'brand_accent' => null,
            'header_gradient_from' => null,
            'header_gradient_to' => null,
        ]]);

        $css = $this->themeService->getCssVariableOverrides();

        $this->assertEquals('', $css);
    }

    public function test_get_css_variable_overrides_returns_style_content_with_env_colors(): void
    {
        config([
            'theme.colors.brand_primary' => '#3b82f6',
            'theme.colors.brand_secondary' => '#10b981',
        ]);

        $css = $this->themeService->getCssVariableOverrides();

        $this->assertStringContainsString(':root {', $css);
        $this->assertStringContainsString('--brand-primary: #3b82f6', $css);
        $this->assertStringContainsString('--brand-secondary: #10b981', $css);
    }

    // =================================================================
    // Additional tests for complete coverage
    // =================================================================

    public function test_is_base_theme_returns_true_for_base(): void
    {
        config(['theme.default' => 'base']);

        $this->assertTrue($this->themeService->isBaseTheme());
    }

    public function test_is_base_theme_returns_false_for_other_themes(): void
    {
        config(['theme.default' => 'wdydy']);

        $this->assertFalse($this->themeService->isBaseTheme());
    }

    public function test_get_theme_css_file_returns_correct_file(): void
    {
        config(['theme.default' => 'wdydy']);

        $cssFile = $this->themeService->getThemeCssFile();

        $this->assertEquals('wdydy.css', $cssFile);
    }

    public function test_get_home_title_returns_seo_title(): void
    {
        config(['theme.seo.home_title' => 'Custom Home Title']);

        $title = $this->themeService->getHomeTitle();

        $this->assertEquals('Custom Home Title', $title);
    }

    public function test_get_search_title_replaces_query_placeholder(): void
    {
        config(['theme.seo.search_title_template' => 'Results for: {query}']);

        $title = $this->themeService->getSearchTitle('test query');

        $this->assertEquals('Results for: test query', $title);
    }

    public function test_get_episode_title_replaces_title_placeholder(): void
    {
        config(['theme.seo.episode_title_template' => 'Episode: {title}']);

        $title = $this->themeService->getEpisodeTitle('My Episode');

        $this->assertEquals('Episode: My Episode', $title);
    }
}
