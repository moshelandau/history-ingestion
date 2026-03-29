<?php

declare(strict_types=1);

namespace HistoryIngestion\Tests\Unit;

use HistoryIngestion\Generator\ThemeBuilder;
use HistoryIngestion\Schema\DeveloperProfile;
use HistoryIngestion\Schema\NormalizedDataset;
use PHPUnit\Framework\TestCase;

final class ThemeBuilderTest extends TestCase
{
    private ThemeBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ThemeBuilder;
    }

    public function test_php_top_language_yields_indigo_palette(): void
    {
        $dataset = $this->makeDataset(languages: ['PHP' => 1000, 'Vue' => 200]);

        $theme = $this->builder->build($dataset);

        $this->assertSame('#4F46E5', $theme->primaryColor);
        $this->assertSame('PHP', $theme->themeSource);
    }

    public function test_typescript_top_language_yields_sky_palette(): void
    {
        $dataset = $this->makeDataset(languages: ['TypeScript' => 500]);

        $theme = $this->builder->build($dataset);

        $this->assertSame('#0EA5E9', $theme->primaryColor);
        $this->assertSame('TypeScript', $theme->themeSource);
    }

    public function test_unknown_language_falls_back_to_default_palette(): void
    {
        $dataset = $this->makeDataset(languages: ['COBOL' => 999]);

        $theme = $this->builder->build($dataset);

        $this->assertSame('#6366F1', $theme->primaryColor);
        $this->assertSame('default', $theme->themeSource);
    }

    public function test_empty_languages_uses_default_palette(): void
    {
        $dataset = $this->makeDataset(languages: []);

        $theme = $this->builder->build($dataset);

        $this->assertSame('default', $theme->themeSource);
    }

    public function test_high_commits_yield_vibrant_style(): void
    {
        $dataset = $this->makeDataset(totalCommits: 350);

        $theme = $this->builder->build($dataset);

        $this->assertSame('vibrant', $theme->style);
    }

    public function test_medium_commits_yield_professional_style(): void
    {
        $dataset = $this->makeDataset(totalCommits: 150);

        $theme = $this->builder->build($dataset);

        $this->assertSame('professional', $theme->style);
    }

    public function test_low_commits_yield_minimal_style(): void
    {
        $dataset = $this->makeDataset(totalCommits: 20);

        $theme = $this->builder->build($dataset);

        $this->assertSame('minimal', $theme->style);
    }

    public function test_to_array_contains_required_keys(): void
    {
        $dataset = $this->makeDataset();

        $array = $this->builder->build($dataset)->toArray();

        $this->assertArrayHasKey('colors', $array);
        $this->assertArrayHasKey('typography', $array);
        $this->assertArrayHasKey('style', $array);
        $this->assertArrayHasKey('theme_source', $array);
        $this->assertArrayHasKey('primary', $array['colors']);
        $this->assertArrayHasKey('heading_font', $array['typography']);
    }

    // -------------------------------------------------------------------------

    private function makeDataset(
        array $languages = ['PHP' => 100],
        int $totalCommits = 50,
    ): NormalizedDataset {
        return new NormalizedDataset(
            schemaVersion: '1.0.0',
            generatedAt: new \DateTimeImmutable,
            developer: new DeveloperProfile('Test', 'test', null),
            projects: [],
            totalCommits: $totalCommits,
            activeRepositories: 0,
            languages: $languages,
            frameworks: [],
            tools: [],
            activityFrom: null,
            activityTo: null,
        );
    }
}
