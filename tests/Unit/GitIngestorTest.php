<?php

declare(strict_types=1);

namespace HistoryIngestion\Tests\Unit;

use HistoryIngestion\Ingestors\GitIngestor;
use PHPUnit\Framework\TestCase;

final class GitIngestorTest extends TestCase
{
    private GitIngestor $ingestor;

    protected function setUp(): void
    {
        $this->ingestor = new GitIngestor;
    }

    public function test_supports_returns_false_for_missing_local_path(): void
    {
        $config = [
            'local_path' => '/path/that/does/not/exist',
            'repo' => 'org/repo',
        ];

        $this->assertFalse($this->ingestor->supports($config));
    }

    public function test_supports_returns_false_for_empty_path(): void
    {
        $config = ['local_path' => '', 'repo' => 'org/repo'];

        $this->assertFalse($this->ingestor->supports($config));
    }

    public function test_ingest_returns_empty_snapshot_when_path_missing(): void
    {
        $config = [
            'name' => 'Missing',
            'local_path' => '/path/that/does/not/exist',
            'repo' => 'org/missing',
            'description' => 'desc',
            'url' => 'https://github.com/org/missing',
            'stack' => ['PHP'],
        ];

        $snapshot = $this->ingestor->ingest($config);

        $this->assertFalse($snapshot->gitAvailable);
        $this->assertSame(0, $snapshot->totalCommits);
        $this->assertSame([], $snapshot->commits);
        $this->assertNull($snapshot->firstCommitAt);
        $this->assertNull($snapshot->lastCommitAt);
    }

    public function test_ingest_populates_metadata_from_config(): void
    {
        $config = [
            'name' => 'MyProject',
            'local_path' => '/nonexistent',
            'repo' => 'org/my-project',
            'description' => 'Great project',
            'url' => 'https://github.com/org/my-project',
            'stack' => ['Laravel', 'Vue 3'],
        ];

        $snapshot = $this->ingestor->ingest($config);

        $this->assertSame('MyProject', $snapshot->name);
        $this->assertSame('org/my-project', $snapshot->repo);
        $this->assertSame('Great project', $snapshot->description);
        $this->assertSame(['Laravel', 'Vue 3'], $snapshot->stack);
    }

    /**
     * Integration smoke test: ingests the pipeline's own git repo.
     * Only runs if the current directory is a git repo.
     */
    public function test_ingest_real_git_repo_if_available(): void
    {
        $path = dirname(__DIR__, 2);

        if (! is_dir($path.'/.git')) {
            $this->markTestSkipped('Not a git repo yet — skipping real git integration test.');
        }

        $config = [
            'name' => 'Self',
            'local_path' => $path,
            'repo' => 'paperclip/history-ingestion',
            'description' => 'Self-test',
            'url' => '',
            'stack' => ['PHP'],
        ];

        $snapshot = $this->ingestor->ingest($config);

        $this->assertTrue($snapshot->gitAvailable);
        $this->assertGreaterThan(0, $snapshot->totalCommits);
        $this->assertNotEmpty($snapshot->contributors);
    }
}
