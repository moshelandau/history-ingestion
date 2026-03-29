<?php

declare(strict_types=1);

namespace HistoryIngestion\Tests\Unit;

use HistoryIngestion\Schema\CommitRecord;
use HistoryIngestion\Schema\ProjectSnapshot;
use PHPUnit\Framework\TestCase;

final class ProjectSnapshotTest extends TestCase
{
    public function test_to_array_includes_all_fields(): void
    {
        $commit = new CommitRecord(
            hash: 'aaabbbccc',
            shortHash: 'aaabbb',
            message: 'initial commit',
            author: 'Dev',
            authorEmail: 'dev@example.com',
            date: new \DateTimeImmutable('2024-06-01T12:00:00+00:00'),
            filesChanged: 3,
            insertions: 50,
            deletions: 0,
        );

        $snapshot = new ProjectSnapshot(
            name: 'TestProject',
            repo: 'org/test-project',
            description: 'A test project',
            url: 'https://github.com/org/test-project',
            stack: ['Laravel', 'Vue 3'],
            commits: [$commit],
            totalCommits: 1,
            contributors: ['Dev'],
            languageStats: ['PHP' => 10, 'Vue' => 3],
            firstCommitAt: new \DateTimeImmutable('2024-06-01'),
            lastCommitAt: new \DateTimeImmutable('2024-06-01'),
            gitAvailable: true,
        );

        $array = $snapshot->toArray();

        $this->assertSame('TestProject', $array['name']);
        $this->assertSame('org/test-project', $array['repo']);
        $this->assertSame(1, $array['total_commits']);
        $this->assertSame(['Dev'], $array['contributors']);
        $this->assertSame(['PHP' => 10, 'Vue' => 3], $array['language_stats']);
        $this->assertTrue($array['git_available']);
        $this->assertCount(1, $array['commits']);
        $this->assertSame('initial commit', $array['commits'][0]['message']);
    }

    public function test_empty_snapshot_when_git_unavailable(): void
    {
        $snapshot = new ProjectSnapshot(
            name: 'Offline',
            repo: 'org/offline',
            description: 'Not cloned locally',
            url: 'https://github.com/org/offline',
            stack: ['PHP'],
            commits: [],
            totalCommits: 0,
            contributors: [],
            languageStats: [],
            firstCommitAt: null,
            lastCommitAt: null,
            gitAvailable: false,
        );

        $array = $snapshot->toArray();

        $this->assertFalse($array['git_available']);
        $this->assertSame(0, $array['total_commits']);
        $this->assertNull($array['first_commit_at']);
        $this->assertNull($array['last_commit_at']);
        $this->assertCount(0, $array['commits']);
    }
}
