<?php

declare(strict_types=1);

namespace HistoryIngestion\Tests\Unit;

use HistoryIngestion\Dashboard\DashboardHandler;
use PHPUnit\Framework\TestCase;

final class DashboardHandlerTest extends TestCase
{
    private string $tmpDir;

    private string $specPath;

    private string $sitePath;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/dashboard_test_'.uniqid();
        mkdir($this->tmpDir, 0755, true);
        mkdir($this->tmpDir.'/site', 0755, true);

        $this->specPath = $this->tmpDir.'/site-spec.json';
        $this->sitePath = $this->tmpDir.'/site/index.html';
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    // -------------------------------------------------------------------------
    // GET / — Dashboard page
    // -------------------------------------------------------------------------

    public function test_dashboard_page_returns_html(): void
    {
        $handler = new DashboardHandler($this->specPath, $this->sitePath);

        [$status, $headers, $body] = $handler->handle('GET', '/');

        $this->assertSame(200, $status);
        $this->assertSame('text/html', $headers['Content-Type']);
        $this->assertStringContainsString('Site Dashboard', $body);
        $this->assertStringContainsString('<iframe', $body);
    }

    // -------------------------------------------------------------------------
    // GET /api/spec
    // -------------------------------------------------------------------------

    public function test_get_spec_returns_spec_json(): void
    {
        $spec = $this->writeSampleSpec();
        $handler = new DashboardHandler($this->specPath, $this->sitePath);

        [$status, $headers, $body] = $handler->handle('GET', '/api/spec');

        $this->assertSame(200, $status);
        $this->assertSame('application/json', $headers['Content-Type']);

        $data = json_decode($body, true);
        $this->assertSame('Test User', $data['developer']['name']);
    }

    public function test_get_spec_returns404_when_missing(): void
    {
        $handler = new DashboardHandler($this->specPath, $this->sitePath);

        [$status, $headers, $body] = $handler->handle('GET', '/api/spec');

        $this->assertSame(404, $status);
        $this->assertStringContainsString('not found', $body);
    }

    // -------------------------------------------------------------------------
    // PATCH /api/spec
    // -------------------------------------------------------------------------

    public function test_update_spec_modifies_hero_fields(): void
    {
        $this->writeSampleSpec();
        $handler = new DashboardHandler($this->specPath, $this->sitePath);

        $updates = json_encode([
            'hero' => ['headline' => 'New Headline', 'subtitle' => 'New Subtitle'],
        ]);

        [$status, , $body] = $handler->handle('PATCH', '/api/spec', $updates);

        $this->assertSame(200, $status);
        $this->assertStringContainsString('updated', $body);

        // Verify the spec was actually modified
        $saved = json_decode(file_get_contents($this->specPath), true);
        $this->assertSame('New Headline', $saved['content']['hero']['headline']);
        $this->assertSame('New Subtitle', $saved['content']['hero']['subtitle']);
        // Unchanged fields should remain
        $this->assertSame('A bio.', $saved['content']['hero']['bio']);
    }

    public function test_update_spec_modifies_developer_fields(): void
    {
        $this->writeSampleSpec();
        $handler = new DashboardHandler($this->specPath, $this->sitePath);

        $updates = json_encode([
            'developer' => ['name' => 'Updated Name', 'email' => 'new@example.com'],
        ]);

        [$status] = $handler->handle('PATCH', '/api/spec', $updates);

        $this->assertSame(200, $status);

        $saved = json_decode(file_get_contents($this->specPath), true);
        $this->assertSame('Updated Name', $saved['developer']['name']);
        $this->assertSame('new@example.com', $saved['developer']['email']);
    }

    public function test_update_spec_modifies_cta_fields(): void
    {
        $this->writeSampleSpec();
        $handler = new DashboardHandler($this->specPath, $this->sitePath);

        $updates = json_encode([
            'cta' => ['github' => 'https://github.com/newuser', 'email' => 'contact@example.com'],
        ]);

        [$status] = $handler->handle('PATCH', '/api/spec', $updates);

        $this->assertSame(200, $status);

        $saved = json_decode(file_get_contents($this->specPath), true);
        $this->assertSame('https://github.com/newuser', $saved['content']['cta']['github']);
        $this->assertSame('contact@example.com', $saved['content']['cta']['email']);
    }

    public function test_update_spec_rejects404_when_missing(): void
    {
        $handler = new DashboardHandler($this->specPath, $this->sitePath);

        [$status] = $handler->handle('PATCH', '/api/spec', '{}');

        $this->assertSame(404, $status);
    }

    public function test_update_spec_rejects_invalid_json(): void
    {
        $this->writeSampleSpec();
        $handler = new DashboardHandler($this->specPath, $this->sitePath);

        [$status, , $body] = $handler->handle('PATCH', '/api/spec', 'not-json');

        $this->assertSame(400, $status);
        $this->assertStringContainsString('Invalid JSON', $body);
    }

    // -------------------------------------------------------------------------
    // POST /api/regenerate
    // -------------------------------------------------------------------------

    public function test_regenerate_renders_the_site(): void
    {
        $this->writeSampleSpec();
        $handler = new DashboardHandler($this->specPath, $this->sitePath);

        [$status, , $body] = $handler->handle('POST', '/api/regenerate');

        $this->assertSame(200, $status);
        $data = json_decode($body, true);
        $this->assertSame('regenerated', $data['status']);
        $this->assertFileExists($this->sitePath);
        $this->assertStringContainsString('Test User', file_get_contents($this->sitePath));
    }

    public function test_regenerate_returns404_when_no_spec(): void
    {
        $handler = new DashboardHandler($this->specPath, $this->sitePath);

        [$status] = $handler->handle('POST', '/api/regenerate');

        $this->assertSame(404, $status);
    }

    // -------------------------------------------------------------------------
    // GET /preview
    // -------------------------------------------------------------------------

    public function test_preview_serves_generated_site(): void
    {
        file_put_contents($this->sitePath, '<html><body>Hello</body></html>');
        $handler = new DashboardHandler($this->specPath, $this->sitePath);

        [$status, $headers, $body] = $handler->handle('GET', '/preview');

        $this->assertSame(200, $status);
        $this->assertSame('text/html', $headers['Content-Type']);
        $this->assertStringContainsString('Hello', $body);
    }

    public function test_preview_returns404_when_no_site(): void
    {
        $handler = new DashboardHandler($this->specPath, $this->sitePath);

        [$status, , $body] = $handler->handle('GET', '/preview');

        $this->assertSame(404, $status);
        $this->assertStringContainsString('No site generated yet', $body);
    }

    // -------------------------------------------------------------------------
    // Unknown routes
    // -------------------------------------------------------------------------

    public function test_unknown_route_returns404(): void
    {
        $handler = new DashboardHandler($this->specPath, $this->sitePath);

        [$status, , $body] = $handler->handle('GET', '/nonexistent');

        $this->assertSame(404, $status);
        $this->assertStringContainsString('Not found', $body);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function writeSampleSpec(): array
    {
        $spec = [
            'schema_version' => '1.0.0',
            'generated_at' => '2026-01-01T00:00:00+00:00',
            'source_dataset_version' => '1.0.0',
            'developer' => [
                'name' => 'Test User',
                'github' => 'testuser',
                'email' => 'test@example.com',
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
                    'headline' => 'Test User — Developer',
                    'subtitle' => 'Building things',
                    'bio' => 'A bio.',
                ],
                'cta' => [
                    'email' => 'test@example.com',
                    'github' => 'https://github.com/testuser',
                ],
                'projects' => [],
                'skills' => [],
                'timeline' => [],
            ],
            'theme' => [
                'style' => 'minimal',
                'theme_source' => 'auto',
                'colors' => [
                    'primary' => '#2563EB',
                    'secondary' => '#1E40AF',
                    'accent' => '#F59E0B',
                    'background' => '#FFFFFF',
                    'text' => '#1F2937',
                ],
                'typography' => [
                    'heading_font' => "'Inter', sans-serif",
                    'body_font' => "'Inter', sans-serif",
                ],
            ],
        ];

        file_put_contents($this->specPath, json_encode($spec, JSON_PRETTY_PRINT));

        return $spec;
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
