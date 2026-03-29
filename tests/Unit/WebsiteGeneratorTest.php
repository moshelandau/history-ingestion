<?php

declare(strict_types=1);

namespace HistoryIngestion\Tests\Unit;

use HistoryIngestion\Generator\Schema\WebsiteSpec;
use HistoryIngestion\Generator\WebsiteGenerator;
use HistoryIngestion\Schema\DeveloperProfile;
use HistoryIngestion\Schema\NormalizedDataset;
use PHPUnit\Framework\TestCase;

final class WebsiteGeneratorTest extends TestCase
{
    private WebsiteGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = WebsiteGenerator::create();
    }

    public function test_generate_returns_website_spec(): void
    {
        $spec = $this->generator->generate($this->makeDataset());

        $this->assertInstanceOf(WebsiteSpec::class, $spec);
    }

    public function test_schema_version_is_set(): void
    {
        $spec = $this->generator->generate($this->makeDataset());

        $this->assertSame(WebsiteSpec::SCHEMA_VERSION, $spec->schemaVersion);
    }

    public function test_developer_carried_through(): void
    {
        $dataset = $this->makeDataset(developerName: 'Jane Dev', github: 'janedev');

        $spec = $this->generator->generate($dataset);

        $this->assertSame('Jane Dev', $spec->developer->name);
        $this->assertSame('janedev', $spec->developer->github);
    }

    public function test_source_dataset_version_propagated(): void
    {
        $spec = $this->generator->generate($this->makeDataset());

        $this->assertSame('1.0.0', $spec->sourceDatasetVersion);
    }

    public function test_generated_at_is_recent(): void
    {
        $before = new \DateTimeImmutable;
        $spec = $this->generator->generate($this->makeDataset());
        $after = new \DateTimeImmutable;

        $this->assertGreaterThanOrEqual($before, $spec->generatedAt);
        $this->assertLessThanOrEqual($after, $spec->generatedAt);
    }

    public function test_to_array_contains_all_top_level_keys(): void
    {
        $array = $this->generator->generate($this->makeDataset())->toArray();

        $this->assertArrayHasKey('schema_version', $array);
        $this->assertArrayHasKey('generated_at', $array);
        $this->assertArrayHasKey('source_dataset_version', $array);
        $this->assertArrayHasKey('developer', $array);
        $this->assertArrayHasKey('structure', $array);
        $this->assertArrayHasKey('content', $array);
        $this->assertArrayHasKey('theme', $array);
    }

    public function test_to_array_is_json_serializable(): void
    {
        $array = $this->generator->generate($this->makeDataset())->toArray();
        $json = json_encode($array);

        $this->assertNotFalse($json);
        $this->assertNotEmpty($json);
    }

    // -------------------------------------------------------------------------

    private function makeDataset(
        string $developerName = 'Test Dev',
        string $github = 'testdev',
    ): NormalizedDataset {
        return new NormalizedDataset(
            schemaVersion: '1.0.0',
            generatedAt: new \DateTimeImmutable,
            developer: new DeveloperProfile($developerName, $github, null),
            projects: [],
            totalCommits: 100,
            activeRepositories: 3,
            languages: ['PHP' => 500, 'Vue' => 200],
            frameworks: ['Laravel', 'Vue 3'],
            tools: ['PostgreSQL'],
            activityFrom: new \DateTimeImmutable('2023-01-01'),
            activityTo: new \DateTimeImmutable('2024-12-31'),
        );
    }
}
