<?php

declare(strict_types=1);

namespace HistoryIngestion\Tests\Unit;

use HistoryIngestion\Generator\StructureBuilder;
use HistoryIngestion\Schema\DeveloperProfile;
use HistoryIngestion\Schema\NormalizedDataset;
use PHPUnit\Framework\TestCase;

final class StructureBuilderTest extends TestCase
{
    private StructureBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new StructureBuilder;
    }

    public function test_always_includes_home_page(): void
    {
        $structure = $this->builder->build($this->makeDataset());

        $keys = array_column($structure->pages, 'key');
        $this->assertContains('home', $keys);
    }

    public function test_home_sections_always_include_hero_projects_skills_contact(): void
    {
        $structure = $this->builder->build($this->makeDataset(totalCommits: 5));

        $home = $this->findPage($structure->pages, 'home');
        $this->assertContains('hero', $home['sections']);
        $this->assertContains('projects', $home['sections']);
        $this->assertContains('skills', $home['sections']);
        $this->assertContains('contact', $home['sections']);
    }

    public function test_timeline_section_added_when_commits_exceed_threshold(): void
    {
        $structure = $this->builder->build($this->makeDataset(totalCommits: 50));

        $home = $this->findPage($structure->pages, 'home');
        $this->assertContains('timeline', $home['sections']);
    }

    public function test_timeline_section_absent_when_commits_below_threshold(): void
    {
        $structure = $this->builder->build($this->makeDataset(totalCommits: 10));

        $home = $this->findPage($structure->pages, 'home');
        $this->assertNotContains('timeline', $home['sections']);
    }

    public function test_high_commit_count_yields_top_bar_navigation(): void
    {
        $structure = $this->builder->build($this->makeDataset(totalCommits: 200));

        $this->assertSame('top-bar', $structure->navigationStyle);
    }

    public function test_low_commit_count_yields_minimal_navigation(): void
    {
        $structure = $this->builder->build($this->makeDataset(totalCommits: 5));

        $this->assertSame('minimal', $structure->navigationStyle);
    }

    public function test_layout_type_is_single_page(): void
    {
        $structure = $this->builder->build($this->makeDataset());

        $this->assertSame('single-page', $structure->layoutType);
    }

    public function test_to_array_contains_required_keys(): void
    {
        $array = $this->builder->build($this->makeDataset())->toArray();

        $this->assertArrayHasKey('layout_type', $array);
        $this->assertArrayHasKey('navigation_style', $array);
        $this->assertArrayHasKey('pages', $array);
        $this->assertNotEmpty($array['pages']);
    }

    // -------------------------------------------------------------------------

    private function makeDataset(int $totalCommits = 50): NormalizedDataset
    {
        return new NormalizedDataset(
            schemaVersion: '1.0.0',
            generatedAt: new \DateTimeImmutable,
            developer: new DeveloperProfile('Test', 'test', null),
            projects: [],
            totalCommits: $totalCommits,
            activeRepositories: 0,
            languages: [],
            frameworks: [],
            tools: [],
            activityFrom: null,
            activityTo: null,
        );
    }

    /**
     * @param  array<array{key:string,title:string,slug:string,sections:string[]}>  $pages
     * @return array{key:string,title:string,slug:string,sections:string[]}
     */
    private function findPage(array $pages, string $key): array
    {
        foreach ($pages as $page) {
            if ($page['key'] === $key) {
                return $page;
            }
        }
        $this->fail("Page '{$key}' not found in structure.");
    }
}
