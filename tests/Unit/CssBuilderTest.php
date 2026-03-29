<?php

declare(strict_types=1);

namespace HistoryIngestion\Tests\Unit;

use HistoryIngestion\Renderer\CssBuilder;
use PHPUnit\Framework\TestCase;

final class CssBuilderTest extends TestCase
{
    private CssBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new CssBuilder;
    }

    public function test_build_returns_non_empty_string(): void
    {
        $css = $this->builder->build($this->makeTheme());

        $this->assertNotEmpty($css);
    }

    public function test_css_contains_custom_properties_from_theme(): void
    {
        $css = $this->builder->build($this->makeTheme());

        $this->assertStringContainsString('--color-primary: #4F46E5', $css);
        $this->assertStringContainsString('--color-secondary: #6366F1', $css);
        $this->assertStringContainsString('--color-accent: #A78BFA', $css);
        $this->assertStringContainsString('--color-bg: #FFFFFF', $css);
        $this->assertStringContainsString('--color-text: #111827', $css);
    }

    public function test_css_contains_typography_from_theme(): void
    {
        $css = $this->builder->build($this->makeTheme());

        $this->assertStringContainsString("--font-heading: 'Inter', sans-serif", $css);
        $this->assertStringContainsString("--font-body: 'Inter', sans-serif", $css);
    }

    public function test_minimal_style_has_no_hover_transform(): void
    {
        $css = $this->builder->build($this->makeTheme(style: 'minimal'));

        $this->assertStringContainsString('transform: none', $css);
    }

    public function test_vibrant_style_has_hover_transform(): void
    {
        $css = $this->builder->build($this->makeTheme(style: 'vibrant'));

        $this->assertStringContainsString('translateY(-2px)', $css);
    }

    public function test_css_includes_responsive_breakpoints(): void
    {
        $css = $this->builder->build($this->makeTheme());

        $this->assertStringContainsString('@media (max-width: 768px)', $css);
        $this->assertStringContainsString('@media (max-width: 480px)', $css);
    }

    public function test_css_includes_all_section_styles(): void
    {
        $css = $this->builder->build($this->makeTheme());

        $this->assertStringContainsString('.hero', $css);
        $this->assertStringContainsString('.projects', $css);
        $this->assertStringContainsString('.skills', $css);
        $this->assertStringContainsString('.timeline', $css);
        $this->assertStringContainsString('.contact', $css);
    }

    // -------------------------------------------------------------------------

    private function makeTheme(string $style = 'minimal'): array
    {
        return [
            'colors' => [
                'primary' => '#4F46E5',
                'secondary' => '#6366F1',
                'accent' => '#A78BFA',
                'background' => '#FFFFFF',
                'text' => '#111827',
            ],
            'typography' => [
                'heading_font' => "'Inter', sans-serif",
                'body_font' => "'Inter', sans-serif",
            ],
            'style' => $style,
            'theme_source' => 'PHP',
        ];
    }
}
