<?php

declare(strict_types=1);

namespace HistoryIngestion\Tests\Unit;

use HistoryIngestion\Ingestors\IngestorInterface;
use HistoryIngestion\Normalizers\HistoryNormalizer;
use HistoryIngestion\Pipeline;
use HistoryIngestion\Schema\ProjectSnapshot;
use PHPUnit\Framework\TestCase;

final class PipelineTest extends TestCase
{
    public function test_run_produces_normalized_dataset(): void
    {
        $ingestor = $this->createMock(IngestorInterface::class);
        $ingestor->method('supports')->willReturn(true);
        $ingestor->method('ingest')->willReturn(new ProjectSnapshot(
            name: 'TestProject',
            repo: 'org/test',
            description: 'desc',
            url: 'https://github.com/org/test',
            stack: ['Laravel'],
            commits: [],
            totalCommits: 42,
            contributors: ['Dev'],
            languageStats: ['PHP' => 100],
            firstCommitAt: new \DateTimeImmutable('2023-01-01'),
            lastCommitAt: new \DateTimeImmutable('2024-01-01'),
            gitAvailable: true,
        ));

        $outputPath = sys_get_temp_dir().'/history_test_'.uniqid().'.json';
        $pipeline = new Pipeline([$ingestor], new HistoryNormalizer);

        $config = [
            'developer' => ['name' => 'Dev', 'github' => 'dev', 'email' => null],
            'projects' => [
                [
                    'name' => 'TestProject',
                    'local_path' => '/tmp',
                    'repo' => 'org/test',
                    'description' => 'desc',
                    'url' => '',
                    'stack' => ['Laravel'],
                ],
            ],
        ];

        $dataset = $pipeline->run($config, $outputPath);

        $this->assertSame(42, $dataset->totalCommits);
        $this->assertCount(1, $dataset->projects);
        $this->assertFileExists($outputPath);

        $written = json_decode(file_get_contents($outputPath), associative: true);
        $this->assertSame('1.0.0', $written['schema_version']);
        $this->assertCount(1, $written['projects']);

        unlink($outputPath);
    }

    public function test_run_skips_project_when_no_ingestor_supports_it(): void
    {
        $ingestor = $this->createMock(IngestorInterface::class);
        $ingestor->method('supports')->willReturn(false);
        $ingestor->expects($this->never())->method('ingest');

        $outputPath = sys_get_temp_dir().'/history_test_'.uniqid().'.json';
        $pipeline = new Pipeline([$ingestor], new HistoryNormalizer);

        $config = [
            'developer' => ['name' => 'Dev', 'github' => 'dev', 'email' => null],
            'projects' => [
                [
                    'name' => 'Unsupported',
                    'local_path' => '',
                    'repo' => '',
                    'description' => '',
                    'url' => '',
                    'stack' => [],
                ],
            ],
        ];

        $dataset = $pipeline->run($config, $outputPath);

        $this->assertSame(0, $dataset->totalCommits);
        $this->assertCount(0, $dataset->projects);

        unlink($outputPath);
    }

    public function test_output_json_is_valid_and_complete(): void
    {
        $ingestor = $this->createMock(IngestorInterface::class);
        $ingestor->method('supports')->willReturn(true);
        $ingestor->method('ingest')->willReturn(new ProjectSnapshot(
            name: 'Proj', repo: 'x/y', description: 'd', url: 'u',
            stack: ['Laravel', 'PostgreSQL'], commits: [], totalCommits: 1,
            contributors: [], languageStats: [], firstCommitAt: null,
            lastCommitAt: null, gitAvailable: false,
        ));

        $outputPath = sys_get_temp_dir().'/history_test_'.uniqid().'.json';
        $pipeline = new Pipeline([$ingestor]);

        $config = [
            'developer' => ['name' => 'D', 'github' => 'g', 'email' => null],
            'projects' => [['name' => 'Proj', 'local_path' => '', 'repo' => 'x/y', 'description' => 'd', 'url' => 'u', 'stack' => ['Laravel', 'PostgreSQL']]],
        ];

        $pipeline->run($config, $outputPath);

        $json = file_get_contents($outputPath);
        $this->assertNotFalse($json);

        $data = json_decode($json, associative: true);
        $this->assertNull(json_last_error() === JSON_ERROR_NONE ? null : 'json error');

        $requiredKeys = ['schema_version', 'generated_at', 'developer', 'activity', 'skills', 'projects'];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $data, "Missing key: {$key}");
        }

        unlink($outputPath);
    }
}
