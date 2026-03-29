<?php

declare(strict_types=1);

namespace HistoryIngestion\Tests\Unit;

use HistoryIngestion\Normalizers\HistoryNormalizer;
use HistoryIngestion\Schema\DeveloperProfile;
use HistoryIngestion\Schema\ProjectSnapshot;
use PHPUnit\Framework\TestCase;

final class HistoryNormalizerTest extends TestCase
{
    private HistoryNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new HistoryNormalizer;
    }

    public function test_aggregates_total_commits_across_projects(): void
    {
        $snapshots = [
            $this->makeSnapshot(totalCommits: 50),
            $this->makeSnapshot(totalCommits: 30),
        ];

        $dataset = $this->normalizer->normalize($this->developer(), $snapshots);

        $this->assertSame(80, $dataset->totalCommits);
    }

    public function test_counts_active_repositories(): void
    {
        $snapshots = [
            $this->makeSnapshot(totalCommits: 10, gitAvailable: true),
            $this->makeSnapshot(totalCommits: 0, gitAvailable: false),
            $this->makeSnapshot(totalCommits: 5, gitAvailable: true),
        ];

        $dataset = $this->normalizer->normalize($this->developer(), $snapshots);

        $this->assertSame(2, $dataset->activeRepositories);
    }

    public function test_merges_language_stats(): void
    {
        $snapshots = [
            $this->makeSnapshot(languageStats: ['PHP' => 100, 'Vue' => 20]),
            $this->makeSnapshot(languageStats: ['PHP' => 50,  'TypeScript' => 30]),
        ];

        $dataset = $this->normalizer->normalize($this->developer(), $snapshots);

        $this->assertSame(150, $dataset->languages['PHP']);
        $this->assertSame(20, $dataset->languages['Vue']);
        $this->assertSame(30, $dataset->languages['TypeScript']);
    }

    public function test_extracts_frameworks_from_stack(): void
    {
        $snapshots = [
            $this->makeSnapshot(stack: ['Laravel', 'Vue 3', 'PostgreSQL']),
            $this->makeSnapshot(stack: ['Next.js', 'TypeScript']),
        ];

        $dataset = $this->normalizer->normalize($this->developer(), $snapshots);

        $this->assertContains('Laravel', $dataset->frameworks);
        $this->assertContains('Vue 3', $dataset->frameworks);
        $this->assertContains('Next.js', $dataset->frameworks);
    }

    public function test_extracts_tools_from_stack(): void
    {
        $snapshots = [
            $this->makeSnapshot(stack: ['Laravel', 'PostgreSQL', 'Plivo']),
        ];

        $dataset = $this->normalizer->normalize($this->developer(), $snapshots);

        $this->assertContains('PostgreSQL', $dataset->tools);
        $this->assertContains('Plivo', $dataset->tools);
        $this->assertNotContains('Laravel', $dataset->tools);
    }

    public function test_determines_activity_date_range(): void
    {
        $early = new \DateTimeImmutable('2022-01-01');
        $mid = new \DateTimeImmutable('2023-06-15');
        $late = new \DateTimeImmutable('2024-12-31');

        $snapshots = [
            $this->makeSnapshot(firstCommitAt: $mid, lastCommitAt: $late),
            $this->makeSnapshot(firstCommitAt: $early, lastCommitAt: $mid),
        ];

        $dataset = $this->normalizer->normalize($this->developer(), $snapshots);

        $this->assertEquals($early, $dataset->activityFrom);
        $this->assertEquals($late, $dataset->activityTo);
    }

    public function test_schema_version_is_set(): void
    {
        $dataset = $this->normalizer->normalize($this->developer(), []);

        $this->assertSame('1.0.0', $dataset->schemaVersion);
    }

    public function test_to_array_produces_valid_structure(): void
    {
        $dataset = $this->normalizer->normalize($this->developer(), [
            $this->makeSnapshot(totalCommits: 5),
        ]);

        $array = $dataset->toArray();

        $this->assertArrayHasKey('schema_version', $array);
        $this->assertArrayHasKey('generated_at', $array);
        $this->assertArrayHasKey('developer', $array);
        $this->assertArrayHasKey('activity', $array);
        $this->assertArrayHasKey('skills', $array);
        $this->assertArrayHasKey('projects', $array);
        $this->assertArrayHasKey('total_commits', $array['activity']);
        $this->assertArrayHasKey('languages', $array['skills']);
        $this->assertArrayHasKey('frameworks', $array['skills']);
        $this->assertArrayHasKey('tools', $array['skills']);
    }

    // -------------------------------------------------------------------------

    private function developer(): DeveloperProfile
    {
        return new DeveloperProfile('Test Dev', 'testdev', null);
    }

    private function makeSnapshot(
        int $totalCommits = 0,
        bool $gitAvailable = true,
        array $languageStats = [],
        array $stack = [],
        ?\DateTimeImmutable $firstCommitAt = null,
        ?\DateTimeImmutable $lastCommitAt = null,
    ): ProjectSnapshot {
        return new ProjectSnapshot(
            name: 'Proj',
            repo: 'org/proj',
            description: 'desc',
            url: 'https://github.com/org/proj',
            stack: $stack,
            commits: [],
            totalCommits: $totalCommits,
            contributors: [],
            languageStats: $languageStats,
            firstCommitAt: $firstCommitAt,
            lastCommitAt: $lastCommitAt,
            gitAvailable: $gitAvailable,
        );
    }
}
