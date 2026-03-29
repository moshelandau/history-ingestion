<?php

declare(strict_types=1);

namespace HistoryIngestion\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use HistoryIngestion\Deployment\GitHubPagesDriver;
use PHPUnit\Framework\TestCase;

final class GitHubPagesDriverTest extends TestCase
{
    private string $siteDir;

    protected function setUp(): void
    {
        $this->siteDir = sys_get_temp_dir().'/ghpages_test_'.uniqid();
        mkdir($this->siteDir);
        file_put_contents($this->siteDir.'/index.html', '<h1>Hello</h1>');
    }

    protected function tearDown(): void
    {
        @unlink($this->siteDir.'/index.html');
        @rmdir($this->siteDir);
    }

    public function test_supports_returns_true_for_github_pages_driver(): void
    {
        $driver = new GitHubPagesDriver;
        $this->assertTrue($driver->supports(['driver' => 'github_pages']));
    }

    public function test_supports_returns_false_for_other_drivers(): void
    {
        $driver = new GitHubPagesDriver;
        $this->assertFalse($driver->supports(['driver' => 'netlify']));
        $this->assertFalse($driver->supports([]));
    }

    public function test_deploy_returns_result_with_live_url(): void
    {
        // Sequence of HTTP responses:
        // 1. GET /repos/owner/repo        → 200 (repo exists)
        // 2. POST /repos/owner/repo/pages → 409 (already enabled)
        // 3. GET /repos/owner/repo/contents/index.html → 404 (new file)
        // 4. PUT /repos/owner/repo/contents/index.html → 201 (file created)
        $mock = new MockHandler([
            new Response(200, [], json_encode(['full_name' => 'owner/repo'])),
            new Response(409, [], json_encode(['message' => 'already enabled'])),
            new Response(404, [], json_encode(['message' => 'Not Found'])),
            new Response(201, [], json_encode(['commit' => ['sha' => 'abc1234']])),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $driver = new GitHubPagesDriver($client);

        $result = $driver->deploy($this->siteDir, [
            'github_token' => 'fake-token',
            'owner' => 'owner',
            'repo' => 'repo',
        ]);

        $this->assertSame('https://owner.github.io/repo/', $result->liveUrl);
        $this->assertSame('https://github.com/owner/repo', $result->repositoryUrl);
        $this->assertSame('abc1234', $result->commitSha);
        $this->assertSame('github_pages', $result->driver);
    }

    public function test_deploy_creates_repo_when_not_found(): void
    {
        $mock = new MockHandler([
            new Response(404, [], json_encode(['message' => 'Not Found'])),  // GET repo
            new Response(201, [], json_encode(['full_name' => 'owner/new-repo'])), // POST create repo
            new Response(201, [], json_encode(['message' => 'pages enabled'])),   // POST pages
            new Response(404, [], json_encode(['message' => 'Not Found'])),  // GET file
            new Response(201, [], json_encode(['commit' => ['sha' => 'newsha']])), // PUT file
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $driver = new GitHubPagesDriver($client);

        $result = $driver->deploy($this->siteDir, [
            'github_token' => 'fake-token',
            'owner' => 'owner',
            'repo' => 'new-repo',
        ]);

        $this->assertSame('https://owner.github.io/new-repo/', $result->liveUrl);
        $this->assertSame('newsha', $result->commitSha);
    }

    public function test_deploy_throws_when_site_dir_missing(): void
    {
        $driver = new GitHubPagesDriver;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not found/i');

        $driver->deploy('/nonexistent/path', [
            'github_token' => 'tok',
            'owner' => 'o',
            'repo' => 'r',
        ]);
    }

    public function test_deploy_throws_when_token_missing(): void
    {
        $driver = new GitHubPagesDriver;

        // Unset env var to ensure no fallback.
        putenv('GITHUB_TOKEN');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/github_token/');

        $driver->deploy($this->siteDir, ['owner' => 'o', 'repo' => 'r']);
    }
}
