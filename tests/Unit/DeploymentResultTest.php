<?php

declare(strict_types=1);

namespace HistoryIngestion\Tests\Unit;

use HistoryIngestion\Deployment\DeploymentResult;
use PHPUnit\Framework\TestCase;

final class DeploymentResultTest extends TestCase
{
    public function test_to_array_contains_all_keys(): void
    {
        $result = new DeploymentResult(
            liveUrl: 'https://owner.github.io/repo/',
            repositoryUrl: 'https://github.com/owner/repo',
            commitSha: 'abc123',
            driver: 'github_pages',
            deployedAt: new \DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        );

        $array = $result->toArray();

        $this->assertSame('https://owner.github.io/repo/', $array['live_url']);
        $this->assertSame('https://github.com/owner/repo', $array['repository_url']);
        $this->assertSame('abc123', $array['commit_sha']);
        $this->assertSame('github_pages', $array['driver']);
        $this->assertStringContainsString('2026-01-01', $array['deployed_at']);
    }
}
