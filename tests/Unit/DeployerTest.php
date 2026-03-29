<?php

declare(strict_types=1);

namespace HistoryIngestion\Tests\Unit;

use HistoryIngestion\Deployer;
use HistoryIngestion\Deployment\DeploymentDriverInterface;
use HistoryIngestion\Deployment\DeploymentResult;
use PHPUnit\Framework\TestCase;

final class DeployerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/deployer_test_'.uniqid();
        mkdir($this->tempDir);
        file_put_contents($this->tempDir.'/index.html', '<h1>Hello</h1>');
    }

    protected function tearDown(): void
    {
        @unlink($this->tempDir.'/index.html');
        @rmdir($this->tempDir);
    }

    public function test_run_calls_matching_driver_and_writes_manifest(): void
    {
        $expectedResult = new DeploymentResult(
            liveUrl: 'https://owner.github.io/repo/',
            repositoryUrl: 'https://github.com/owner/repo',
            commitSha: 'deadbeef',
            driver: 'github_pages',
            deployedAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );

        $driver = $this->createMock(DeploymentDriverInterface::class);
        $driver->method('supports')->willReturn(true);
        $driver->method('deploy')->willReturn($expectedResult);

        $outputPath = sys_get_temp_dir().'/deploy_test_'.uniqid().'.json';
        $deployer = new Deployer([$driver]);

        $result = $deployer->run($this->tempDir, ['driver' => 'github_pages'], $outputPath);

        $this->assertSame('https://owner.github.io/repo/', $result->liveUrl);
        $this->assertFileExists($outputPath);

        $manifest = json_decode(file_get_contents($outputPath), associative: true);
        $this->assertSame('https://owner.github.io/repo/', $manifest['live_url']);
        $this->assertSame('deadbeef', $manifest['commit_sha']);

        unlink($outputPath);
    }

    public function test_run_throws_when_no_driver_matches(): void
    {
        $driver = $this->createMock(DeploymentDriverInterface::class);
        $driver->method('supports')->willReturn(false);

        $deployer = new Deployer([$driver]);
        $outputPath = sys_get_temp_dir().'/deploy_test_'.uniqid().'.json';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/No deployment driver/');

        $deployer->run($this->tempDir, ['driver' => 'unknown'], $outputPath);
    }

    public function test_run_uses_first_supporting_driver(): void
    {
        $result = new DeploymentResult(
            liveUrl: 'https://example.com', repositoryUrl: 'r', commitSha: 'sha',
            driver: 'd1', deployedAt: new \DateTimeImmutable,
        );

        $driver1 = $this->createMock(DeploymentDriverInterface::class);
        $driver1->method('supports')->willReturn(false);
        $driver1->expects($this->never())->method('deploy');

        $driver2 = $this->createMock(DeploymentDriverInterface::class);
        $driver2->method('supports')->willReturn(true);
        $driver2->expects($this->once())->method('deploy')->willReturn($result);

        $outputPath = sys_get_temp_dir().'/deploy_test_'.uniqid().'.json';
        $deployer = new Deployer([$driver1, $driver2]);
        $deployer->run($this->tempDir, ['driver' => 'd2'], $outputPath);

        @unlink($outputPath);
    }

    public function test_run_throws_when_site_dir_empty(): void
    {
        $emptyDir = sys_get_temp_dir().'/empty_site_'.uniqid();
        mkdir($emptyDir);

        $result = new DeploymentResult(
            liveUrl: 'x', repositoryUrl: 'r', commitSha: 's',
            driver: 'd', deployedAt: new \DateTimeImmutable,
        );

        $driver = $this->createMock(DeploymentDriverInterface::class);
        $driver->method('supports')->willReturn(true);
        $driver->method('deploy')->willThrowException(
            new \RuntimeException('No files found in site directory'),
        );

        $outputPath = sys_get_temp_dir().'/deploy_test_'.uniqid().'.json';
        $deployer = new Deployer([$driver]);

        $this->expectException(\RuntimeException::class);
        $deployer->run($emptyDir, ['driver' => 'd'], $outputPath);

        rmdir($emptyDir);
    }
}
