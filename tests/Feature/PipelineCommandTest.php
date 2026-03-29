<?php

declare(strict_types=1);

namespace HistoryIngestion\Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Feature tests for bin/pipeline — the end-to-end orchestration script.
 *
 * These tests exercise the CLI directly via proc_open to verify argument parsing,
 * stage sequencing, skip logic, and error surfacing.
 */
final class PipelineCommandTest extends TestCase
{
    private string $binPath;

    protected function setUp(): void
    {
        $this->binPath = realpath(__DIR__.'/../../bin/pipeline');
        $this->assertNotFalse($this->binPath, 'bin/pipeline must exist');
    }

    public function test_help_flag_shows_usage_and_exits_zero(): void
    {
        $result = $this->runPipeline(['--help']);

        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString('End-to-End Pipeline', $result['stdout']);
        $this->assertStringContainsString('--output-dir', $result['stdout']);
        $this->assertStringContainsString('--skip-deploy', $result['stdout']);
        $this->assertStringContainsString('--skip=', $result['stdout']);
    }

    public function test_skip_all_stages_runs_successfully(): void
    {
        $result = $this->runPipeline(['--skip=ingest,generate,render,deploy']);

        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString('Pipeline Complete', $result['stdout']);
        $this->assertStringContainsString('[SKIP] 1/4 Ingest', $result['stdout']);
        $this->assertStringContainsString('[SKIP] 2/4 Generate', $result['stdout']);
        $this->assertStringContainsString('[SKIP] 3/4 Render', $result['stdout']);
        $this->assertStringContainsString('[SKIP] 4/4 Deploy', $result['stdout']);
        $this->assertStringContainsString('Stages run : 0', $result['stdout']);
    }

    public function test_skip_deploy_flag_skips_deploy_stage(): void
    {
        $result = $this->runPipeline(['--skip=ingest,generate,render', '--skip-deploy']);

        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString('[SKIP] 4/4 Deploy', $result['stdout']);
    }

    public function test_generate_fails_when_history_missing(): void
    {
        $tempDir = sys_get_temp_dir().'/pipeline_test_'.uniqid();
        mkdir($tempDir, 0755, true);

        $result = $this->runPipeline([
            '--output-dir='.$tempDir,
            '--skip=ingest,render,deploy',
        ]);

        $this->assertNotSame(0, $result['exitCode']);
        $this->assertStringContainsString('[FAILED] 2/4 Generate', $result['stderr']);
        $this->assertStringContainsString('not found', $result['stderr']);

        @rmdir($tempDir);
    }

    public function test_render_fails_when_site_spec_missing(): void
    {
        $tempDir = sys_get_temp_dir().'/pipeline_test_'.uniqid();
        mkdir($tempDir, 0755, true);

        $result = $this->runPipeline([
            '--output-dir='.$tempDir,
            '--skip=ingest,generate,deploy',
        ]);

        $this->assertNotSame(0, $result['exitCode']);
        $this->assertStringContainsString('[FAILED] 3/4 Render', $result['stderr']);
        $this->assertStringContainsString('not found', $result['stderr']);

        @rmdir($tempDir);
    }

    public function test_deploy_fails_when_site_dir_missing(): void
    {
        $tempDir = sys_get_temp_dir().'/pipeline_test_'.uniqid();
        mkdir($tempDir, 0755, true);

        $result = $this->runPipeline([
            '--output-dir='.$tempDir,
            '--skip=ingest,generate,render',
        ]);

        $this->assertNotSame(0, $result['exitCode']);
        $this->assertStringContainsString('[FAILED] 4/4 Deploy', $result['stderr']);
        $this->assertStringContainsString('not found', $result['stderr']);

        @rmdir($tempDir);
    }

    public function test_output_dir_default_is_output(): void
    {
        $result = $this->runPipeline(['--skip=ingest,generate,render,deploy']);

        $this->assertSame(0, $result['exitCode']);
        // The default output dir should resolve to /output within the project
        $this->assertStringContainsString('Output dir :', $result['stdout']);
    }

    public function test_github_flag_changes_mode_display(): void
    {
        $result = $this->runPipeline(['--github', '--skip=ingest,generate,render,deploy']);

        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString('git + GitHub API', $result['stdout']);
    }

    public function test_default_mode_is_local_git(): void
    {
        $result = $this->runPipeline(['--skip=ingest,generate,render,deploy']);

        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString('git (local only)', $result['stdout']);
    }

    public function test_stages_display_shows_active_stages(): void
    {
        $result = $this->runPipeline(['--skip=deploy']);

        // Even if it fails at ingest, the header should show active stages
        $this->assertStringContainsString('Stages     : ingest', $result['stdout']);
        $this->assertStringNotContainsString('deploy', explode("\n", $result['stdout'])[4] ?? '');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param  string[]  $args
     * @return array{stdout: string, stderr: string, exitCode: int}
     */
    private function runPipeline(array $args): array
    {
        $php = PHP_BINARY;
        $command = escapeshellarg($php).' '.escapeshellarg($this->binPath);

        foreach ($args as $arg) {
            $command .= ' '.escapeshellarg($arg);
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);
        $this->assertIsResource($process);

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'stdout' => $stdout ?: '',
            'stderr' => $stderr ?: '',
            'exitCode' => $exitCode,
        ];
    }
}
