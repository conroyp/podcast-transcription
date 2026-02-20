<?php

namespace Tests\Feature;

use App\Services\ThemeService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class ThemeServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['theme.default' => 'base']);
        config(['theme.colors' => [
            'brand_primary' => null,
            'brand_secondary' => null,
            'brand_accent' => null,
            'header_gradient_from' => null,
            'header_gradient_to' => null,
        ]]);
    }

    protected function tearDown(): void
    {
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

    public function test_theme_variables_are_shared_with_views(): void
    {
        $sharedData = View::getShared();

        $this->assertArrayHasKey('currentTheme', $sharedData);
        $this->assertArrayHasKey('branding', $sharedData);
        $this->assertArrayHasKey('themeConfig', $sharedData);
        $this->assertArrayHasKey('searchConfig', $sharedData);
    }

    public function test_theme_fonts_directive_renders_font_links(): void
    {
        config(['theme.default' => 'wdydy']);
        $this->app->forgetInstance(ThemeService::class);

        $rendered = Blade::render('@themeFonts');

        $this->assertStringContainsString('lilita-one', $rendered);
        $this->assertStringContainsString('<link rel="stylesheet"', $rendered);
        $this->assertStringContainsString('<link rel="preconnect"', $rendered);
    }

    public function test_theme_fonts_directive_renders_nothing_for_base_theme(): void
    {
        config(['theme.default' => 'base']);
        $this->app->forgetInstance(ThemeService::class);

        $rendered = Blade::render('@themeFonts');

        $this->assertEquals('', trim($rendered));
    }

    public function test_theme_data_attribute_directive_renders_correctly(): void
    {
        config(['theme.default' => 'base']);
        $this->app->forgetInstance(ThemeService::class);

        $rendered = Blade::render('@themeDataAttribute');
        $this->assertStringContainsString('data-theme="base"', $rendered);

        config(['theme.default' => 'wdydy']);
        $this->app->forgetInstance(ThemeService::class);

        $rendered = Blade::render('@themeDataAttribute');
        $this->assertStringContainsString('data-theme="wdydy"', $rendered);
    }

    public function test_theme_color_overrides_directive_renders_style_tag(): void
    {
        config(['theme.colors.brand_primary' => '#ff6600']);
        $this->app->forgetInstance(ThemeService::class);

        $rendered = Blade::render('@themeColorOverrides');

        $this->assertStringContainsString('<style>', $rendered);
        $this->assertStringContainsString('--brand-primary: #ff6600', $rendered);
    }

    public function test_theme_color_overrides_directive_renders_nothing_when_no_overrides(): void
    {
        config(['theme.colors' => [
            'brand_primary' => null,
            'brand_secondary' => null,
            'brand_accent' => null,
            'header_gradient_from' => null,
            'header_gradient_to' => null,
        ]]);
        $this->app->forgetInstance(ThemeService::class);

        $rendered = Blade::render('@themeColorOverrides');

        $this->assertEquals('', trim($rendered));
    }

    public function test_theme_service_is_registered_as_singleton(): void
    {
        $instance1 = app(ThemeService::class);
        $instance2 = app(ThemeService::class);

        $this->assertSame($instance1, $instance2);
    }
}
