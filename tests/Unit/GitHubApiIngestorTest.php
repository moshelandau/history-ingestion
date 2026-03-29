<?php

declare(strict_types=1);

namespace HistoryIngestion\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use HistoryIngestion\Ingestors\GitHubApiIngestor;
use PHPUnit\Framework\TestCase;

final class GitHubApiIngestorTest extends TestCase
{
    private function baseConfig(array $overrides = []): array
    {
        return array_merge([
            'name' => 'TestProject',
            'repo' => 'org/test-project',
            'description' => 'A test project',
            'url' => 'https://github.com/org/test-project',
            'stack' => ['PHP', 'Laravel'],
            'local_path' => '',
        ], $overrides);
    }

    private function makeIngestor(MockHandler $mock, int $maxCommits = 300): GitHubApiIngestor
    {
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        return new GitHubApiIngestor($client, $maxCommits);
    }

    private function fakeCommitPayload(string $sha, string $message, string $author = 'Dev', string $email = 'dev@example.com', string $date = '2026-01-15T10:00:00Z'): array
    {
        return [
            'sha' => $sha,
            'commit' => [
                'message' => $message,
                'author' => [
                    'name' => $author,
                    'email' => $email,
                    'date' => $date,
                ],
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // supports()
    // -----------------------------------------------------------------------

    public function test_supports_returns_true_when_repo_is_set(): void
    {
        $ingestor = new GitHubApiIngestor;

        $this->assertTrue($ingestor->supports(['repo' => 'org/repo']));
    }

    public function test_supports_returns_false_when_repo_is_empty(): void
    {
        $ingestor = new GitHubApiIngestor;

        $this->assertFalse($ingestor->supports(['repo' => '']));
    }

    public function test_supports_returns_false_when_repo_key_is_missing(): void
    {
        $ingestor = new GitHubApiIngestor;

        $this->assertFalse($ingestor->supports(['name' => 'foo']));
    }

    // -----------------------------------------------------------------------
    // 1. Successful API fetch with pagination
    // -----------------------------------------------------------------------

    public function test_ingest_fetches_commits_and_languages_successfully(): void
    {
        $commits = [
            $this->fakeCommitPayload('abc1234567890', 'feat: add login', 'Alice', 'alice@example.com', '2026-03-01T12:00:00Z'),
            $this->fakeCommitPayload('def7890123456', 'fix: typo', 'Bob', 'bob@example.com', '2026-02-28T08:00:00Z'),
        ];
        $languages = ['PHP' => 5000, 'JavaScript' => 3000];

        $mock = new MockHandler([
            new Response(200, [], json_encode($commits)),
            new Response(200, [], json_encode($languages)),
        ]);

        $ingestor = $this->makeIngestor($mock);
        $snapshot = $ingestor->ingest($this->baseConfig());

        $this->assertTrue($snapshot->gitAvailable);
        $this->assertSame(2, $snapshot->totalCommits);
        $this->assertCount(2, $snapshot->commits);
        $this->assertSame('abc1234', $snapshot->commits[0]->shortHash);
        $this->assertSame('feat: add login', $snapshot->commits[0]->message);
        $this->assertSame('Alice', $snapshot->commits[0]->author);
        $this->assertSame('fix: typo', $snapshot->commits[1]->message);
        $this->assertSame(['PHP' => 5000, 'JavaScript' => 3000], $snapshot->languageStats);
        $this->assertContains('Alice', $snapshot->contributors);
        $this->assertContains('Bob', $snapshot->contributors);
    }

    public function test_ingest_paginates_across_multiple_pages(): void
    {
        // Page 1: 100 commits (full page triggers next page fetch)
        $page1 = [];
        for ($i = 0; $i < 100; $i++) {
            $page1[] = $this->fakeCommitPayload(
                str_pad((string) $i, 40, '0'),
                "commit {$i}",
                'Dev',
                'dev@example.com',
                '2026-01-15T10:00:00Z',
            );
        }

        // Page 2: 50 commits (partial page = no more pages)
        $page2 = [];
        for ($i = 100; $i < 150; $i++) {
            $page2[] = $this->fakeCommitPayload(
                str_pad((string) $i, 40, '0'),
                "commit {$i}",
                'Dev',
                'dev@example.com',
                '2026-01-15T10:00:00Z',
            );
        }

        $mock = new MockHandler([
            new Response(200, [], json_encode($page1)),
            new Response(200, [], json_encode($page2)),
            new Response(200, [], json_encode(['PHP' => 1000])), // languages
        ]);

        $ingestor = $this->makeIngestor($mock);
        $snapshot = $ingestor->ingest($this->baseConfig());

        $this->assertTrue($snapshot->gitAvailable);
        $this->assertSame(150, $snapshot->totalCommits);
    }

    public function test_ingest_respects_max_commits_limit(): void
    {
        // Build 100 commits per page; with maxCommits=5 we should stop early
        $page = [];
        for ($i = 0; $i < 100; $i++) {
            $page[] = $this->fakeCommitPayload(
                str_pad((string) $i, 40, '0'),
                "commit {$i}",
            );
        }

        $mock = new MockHandler([
            new Response(200, [], json_encode($page)),
            new Response(200, [], json_encode([])), // languages
        ]);

        $ingestor = $this->makeIngestor($mock, maxCommits: 5);
        $snapshot = $ingestor->ingest($this->baseConfig());

        $this->assertSame(5, $snapshot->totalCommits);
        $this->assertCount(5, $snapshot->commits);
    }

    // -----------------------------------------------------------------------
    // 2. API 403 (rate limited) — graceful degradation
    // -----------------------------------------------------------------------

    public function test_ingest_returns_empty_snapshot_on_rate_limit_403(): void
    {
        $mock = new MockHandler([
            new Response(403, [], json_encode(['message' => 'API rate limit exceeded'])),
        ]);

        $ingestor = $this->makeIngestor($mock);
        $snapshot = $ingestor->ingest($this->baseConfig());

        $this->assertFalse($snapshot->gitAvailable);
        $this->assertSame(0, $snapshot->totalCommits);
        $this->assertSame([], $snapshot->commits);
        $this->assertNull($snapshot->firstCommitAt);
        $this->assertNull($snapshot->lastCommitAt);
        $this->assertSame('TestProject', $snapshot->name);
    }

    // -----------------------------------------------------------------------
    // 3. API 404 (repo not found)
    // -----------------------------------------------------------------------

    public function test_ingest_returns_empty_snapshot_on_404(): void
    {
        $mock = new MockHandler([
            new Response(404, [], json_encode(['message' => 'Not Found'])),
        ]);

        $ingestor = $this->makeIngestor($mock);
        $snapshot = $ingestor->ingest($this->baseConfig());

        $this->assertFalse($snapshot->gitAvailable);
        $this->assertSame(0, $snapshot->totalCommits);
    }

    // -----------------------------------------------------------------------
    // 4. Missing GITHUB_TOKEN — fallback (constructor still works)
    // -----------------------------------------------------------------------

    public function test_constructor_works_without_github_token(): void
    {
        // Ensure env var is unset for this test
        $original = getenv('GITHUB_TOKEN');
        putenv('GITHUB_TOKEN');

        $ingestor = new GitHubApiIngestor;

        // Restore
        if ($original !== false) {
            putenv("GITHUB_TOKEN={$original}");
        }

        $this->assertInstanceOf(GitHubApiIngestor::class, $ingestor);
    }

    // -----------------------------------------------------------------------
    // 5. Malformed API response
    // -----------------------------------------------------------------------

    public function test_ingest_handles_empty_commits_response(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([])), // empty commits
            new Response(200, [], json_encode([])), // empty languages
        ]);

        $ingestor = $this->makeIngestor($mock);
        $snapshot = $ingestor->ingest($this->baseConfig());

        $this->assertTrue($snapshot->gitAvailable);
        $this->assertSame(0, $snapshot->totalCommits);
        $this->assertSame([], $snapshot->commits);
        $this->assertNull($snapshot->firstCommitAt);
        $this->assertNull($snapshot->lastCommitAt);
    }

    public function test_ingest_handles_commit_with_missing_author_fields(): void
    {
        $commits = [
            [
                'sha' => 'abc1234567890abcdef1234567890abcdef123456',
                'commit' => [
                    'message' => 'mysterious commit',
                    'author' => [
                        'date' => '2026-01-01T00:00:00Z',
                        // name and email missing
                    ],
                ],
            ],
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($commits)),
            new Response(200, [], json_encode([])),
        ]);

        $ingestor = $this->makeIngestor($mock);
        $snapshot = $ingestor->ingest($this->baseConfig());

        $this->assertSame(1, $snapshot->totalCommits);
        $this->assertSame('Unknown', $snapshot->commits[0]->author);
        $this->assertSame('', $snapshot->commits[0]->authorEmail);
    }

    public function test_ingest_handles_multiline_commit_message(): void
    {
        $commits = [
            $this->fakeCommitPayload('aaa1234567890abcdef1234567890abcdef123456', "feat: first line\n\nExtended description here"),
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($commits)),
            new Response(200, [], json_encode([])),
        ]);

        $ingestor = $this->makeIngestor($mock);
        $snapshot = $ingestor->ingest($this->baseConfig());

        $this->assertSame('feat: first line', $snapshot->commits[0]->message);
    }

    // -----------------------------------------------------------------------
    // Metadata passthrough
    // -----------------------------------------------------------------------

    public function test_ingest_passes_through_project_config_metadata(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([])),
            new Response(200, [], json_encode([])),
        ]);

        $config = $this->baseConfig([
            'name' => 'CustomName',
            'description' => 'Custom description',
            'url' => 'https://custom.example.com',
            'stack' => ['Go', 'React'],
        ]);

        $ingestor = $this->makeIngestor($mock);
        $snapshot = $ingestor->ingest($config);

        $this->assertSame('CustomName', $snapshot->name);
        $this->assertSame('Custom description', $snapshot->description);
        $this->assertSame('https://custom.example.com', $snapshot->url);
        $this->assertSame(['Go', 'React'], $snapshot->stack);
    }

    public function test_ingest_sets_first_and_last_commit_dates(): void
    {
        $commits = [
            $this->fakeCommitPayload('aaa0000000000000000000000000000000000000', 'latest', 'Dev', 'dev@example.com', '2026-03-15T12:00:00Z'),
            $this->fakeCommitPayload('bbb0000000000000000000000000000000000000', 'oldest', 'Dev', 'dev@example.com', '2025-01-01T00:00:00Z'),
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($commits)),
            new Response(200, [], json_encode([])),
        ]);

        $ingestor = $this->makeIngestor($mock);
        $snapshot = $ingestor->ingest($this->baseConfig());

        // firstCommitAt = last item (oldest), lastCommitAt = first item (newest)
        $this->assertSame('2025-01-01T00:00:00+00:00', $snapshot->firstCommitAt->format(\DateTimeInterface::ATOM));
        $this->assertSame('2026-03-15T12:00:00+00:00', $snapshot->lastCommitAt->format(\DateTimeInterface::ATOM));
    }

    public function test_ingest_deduplicates_contributors(): void
    {
        $commits = [
            $this->fakeCommitPayload('aaa0000000000000000000000000000000000000', 'c1', 'Alice', 'alice@example.com'),
            $this->fakeCommitPayload('bbb0000000000000000000000000000000000000', 'c2', 'Alice', 'alice@example.com'),
            $this->fakeCommitPayload('ccc0000000000000000000000000000000000000', 'c3', 'Bob', 'bob@example.com'),
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($commits)),
            new Response(200, [], json_encode([])),
        ]);

        $ingestor = $this->makeIngestor($mock);
        $snapshot = $ingestor->ingest($this->baseConfig());

        $this->assertCount(2, $snapshot->contributors);
        $this->assertContains('Alice', $snapshot->contributors);
        $this->assertContains('Bob', $snapshot->contributors);
    }
}
