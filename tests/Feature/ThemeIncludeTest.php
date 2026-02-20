<?php

namespace Tests\Feature;

use App\Services\ThemeService;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ThemeIncludeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['theme.default' => 'base']);
    }

    protected function tearDown(): void
    {
        config(['theme.default' => 'base']);
        parent::tearDown();
    }

    public function test_theme_include_uses_base_partial_for_base_theme(): void
    {
        config(['theme.default' => 'base']);
        $this->app->forgetInstance(ThemeService::class);

        $rendered = Blade::render('@themeInclude("header-title")', [
            'branding' => ['site_name' => 'Test Site'],
        ]);

        $this->assertStringContainsString('Test Site', $rendered);
        $this->assertStringContainsString('font-brand', $rendered);
        $this->assertStringNotContainsString('Everything Is Showbiz', $rendered);
    }

    public function test_theme_include_uses_theme_partial_when_exists(): void
    {
        config(['theme.default' => 'wdydy']);
        $this->app->forgetInstance(ThemeService::class);

        $rendered = Blade::render('@themeInclude("header-title")', [
            'branding' => ['site_name' => 'Test Site'],
        ]);

        $this->assertStringContainsString('Everything Is Showbiz', $rendered);
        $this->assertStringContainsString('font-lilita', $rendered);
        $this->assertStringNotContainsString('font-brand', $rendered);
    }

    public function test_theme_include_falls_back_to_base_when_theme_partial_missing(): void
    {
        config(['theme.default' => 'wdydy']);
        $this->app->forgetInstance(ThemeService::class);

        $rendered = Blade::render('@themeInclude("settings-heading")', [
            'title' => 'Test Title',
            'description' => 'Test Description',
        ]);

        $this->assertNotEmpty(trim($rendered));
    }

    public function test_theme_include_passes_view_variables_to_partial(): void
    {
        config(['theme.default' => 'base']);
        $this->app->forgetInstance(ThemeService::class);

        $rendered = Blade::render('@themeInclude("header-title")', [
            'branding' => ['site_name' => 'Custom Site Name Here'],
        ]);

        $this->assertStringContainsString('Custom Site Name Here', $rendered);
    }

    public function test_theme_include_renders_nothing_for_nonexistent_partial(): void
    {
        config(['theme.default' => 'base']);
        $this->app->forgetInstance(ThemeService::class);

        $rendered = Blade::render('@themeInclude("nonexistent-partial-xyz")');

        $this->assertEquals('', trim($rendered));
    }
}
