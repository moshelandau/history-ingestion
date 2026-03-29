<?php

declare(strict_types=1);

namespace HistoryIngestion\Tests\Unit;

use HistoryIngestion\Renderer\SiteRenderer;
use PHPUnit\Framework\TestCase;

final class SiteRendererTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = sys_get_temp_dir().'/site-renderer-test-'.uniqid();
        mkdir($this->fixtureDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->fixtureDir);
    }

    public function test_render_produces_html_file(): void
    {
        $specPath = $this->writeSpec($this->makeSpec());
        $outputPath = $this->fixtureDir.'/out/index.html';

        $renderer = SiteRenderer::create();
        $result = $renderer->render($specPath, $outputPath);

        $this->assertFileExists($outputPath);
        $this->assertStringContainsString('<!DOCTYPE html>', file_get_contents($outputPath));
    }

    public function test_render_returns_summary_array(): void
    {
        $specPath = $this->writeSpec($this->makeSpec());
        $outputPath = $this->fixtureDir.'/out/index.html';

        $result = SiteRenderer::create()->render($specPath, $outputPath);

        $this->assertArrayHasKey('developer', $result);
        $this->assertArrayHasKey('style', $result);
        $this->assertArrayHasKey('sections', $result);
        $this->assertArrayHasKey('output', $result);
        $this->assertSame('Test Dev (@testdev)', $result['developer']);
        $this->assertSame(5, $result['sections']);
    }

    public function test_render_creates_output_directory(): void
    {
        $specPath = $this->writeSpec($this->makeSpec());
        $deepOutput = $this->fixtureDir.'/deep/nested/dir/index.html';

        SiteRenderer::create()->render($specPath, $deepOutput);

        $this->assertFileExists($deepOutput);
    }

    public function test_render_throws_on_missing_input(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not found/');

        SiteRenderer::create()->render('/nonexistent/spec.json', $this->fixtureDir.'/out.html');
    }

    public function test_render_throws_on_invalid_json(): void
    {
        $badPath = $this->fixtureDir.'/bad.json';
        file_put_contents($badPath, 'not valid json!!!');

        $this->expectException(\JsonException::class);

        SiteRenderer::create()->render($badPath, $this->fixtureDir.'/out.html');
    }

    public function test_render_throws_on_missing_required_key(): void
    {
        $incomplete = ['developer' => ['name' => 'X', 'github' => 'x', 'email' => null]];
        $path = $this->fixtureDir.'/incomplete.json';
        file_put_contents($path, json_encode($incomplete));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/missing required key/');

        SiteRenderer::create()->render($path, $this->fixtureDir.'/out.html');
    }

    public function test_rendered_html_contains_theme_colors(): void
    {
        $specPath = $this->writeSpec($this->makeSpec());
        $outputPath = $this->fixtureDir.'/out/index.html';

        SiteRenderer::create()->render($specPath, $outputPath);
        $html = file_get_contents($outputPath);

        $this->assertStringContainsString('#4F46E5', $html);
        $this->assertStringContainsString('#6366F1', $html);
    }

    // -------------------------------------------------------------------------

    private function makeSpec(): array
    {
        return [
            'schema_version' => '1.0.0',
            'generated_at' => '2026-03-29T00:00:00+00:00',
            'source_dataset_version' => '1.0.0',
            'developer' => [
                'name' => 'Test Dev',
                'github' => 'testdev',
                'email' => null,
            ],
            'structure' => [
                'layout_type' => 'single-page',
                'navigation_style' => 'minimal',
                'pages' => [
                    [
                        'key' => 'home',
                        'title' => 'Home',
                        'slug' => '/',
                        'sections' => ['hero', 'projects', 'skills', 'timeline', 'contact'],
                    ],
                ],
            ],
            'content' => [
                'hero' => [
                    'headline' => 'Test Dev — Developer',
                    'subtitle' => 'Building things',
                    'bio' => 'A test bio.',
                ],
                'cta' => ['email' => null, 'github' => 'https://github.com/testdev'],
                'projects' => [
                    [
                        'name' => 'Project Alpha',
                        'description' => 'A project',
                        'url' => 'https://github.com/testdev/alpha',
                        'tech_stack' => ['PHP'],
                        'highlights' => ['Did something'],
                        'commit_count' => 10,
                        'activity_from' => '2026-01-01T00:00:00+00:00',
                        'activity_to' => '2026-03-01T00:00:00+00:00',
                    ],
                ],
                'skills' => [
                    ['name' => 'PHP', 'category' => 'language', 'weight' => 100],
                ],
                'timeline' => [
                    [
                        'date' => '2026-03-01T00:00:00+00:00',
                        'project_name' => 'Project Alpha',
                        'commit_message' => 'Initial commit',
                        'short_hash' => 'abc1234',
                        'project_url' => 'https://github.com/testdev/alpha',
                    ],
                ],
            ],
            'theme' => [
                'colors' => [
                    'primary' => '#4F46E5',
                    'secondary' => '#6366F1',
                    'accent' => '#A78BFA',
                    'background' => '#FFFFFF',
                    'text' => '#111827',
                ],
                'typography' => [
                    'heading_font' => "'DM Sans', sans-serif",
                    'body_font' => "'DM Sans', sans-serif",
                ],
                'style' => 'minimal',
                'theme_source' => 'PHP',
            ],
        ];
    }

    private function writeSpec(array $spec): string
    {
        $path = $this->fixtureDir.'/site-spec.json';
        file_put_contents($path, json_encode($spec, JSON_PRETTY_PRINT));

        return $path;
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.'/'.$item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
