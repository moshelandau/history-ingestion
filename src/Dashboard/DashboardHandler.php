<?php

declare(strict_types=1);

namespace HistoryIngestion\Dashboard;

use HistoryIngestion\Renderer\SiteRenderer;

/**
 * Handles HTTP requests for the customer-facing dashboard.
 *
 * Routes:
 *   GET  /                → dashboard HTML page
 *   GET  /api/spec        → current site-spec JSON
 *   PATCH /api/spec       → update editable fields in site-spec
 *   POST /api/regenerate  → re-render the site from the current spec
 *   GET  /preview         → serve the generated site HTML
 */
final class DashboardHandler
{
    private string $specPath;

    private string $sitePath;

    private SiteRenderer $renderer;

    public function __construct(
        string $specPath,
        string $sitePath,
        ?SiteRenderer $renderer = null,
    ) {
        $this->specPath = $specPath;
        $this->sitePath = $sitePath;
        $this->renderer = $renderer ?? SiteRenderer::create();
    }

    /**
     * Route the incoming request and return [statusCode, headers, body].
     *
     * @return array{0: int, 1: array<string, string>, 2: string}
     */
    public function handle(string $method, string $path, string $requestBody = ''): array
    {
        return match (true) {
            $method === 'GET' && $path === '/' => $this->dashboardPage(),
            $method === 'GET' && $path === '/api/spec' => $this->getSpec(),
            $method === 'PATCH' && $path === '/api/spec' => $this->updateSpec($requestBody),
            $method === 'POST' && $path === '/api/regenerate' => $this->regenerate(),
            $method === 'GET' && $path === '/preview' => $this->preview(),
            default => $this->notFound(),
        };
    }

    /**
     * GET /api/spec — return the current site-spec JSON.
     *
     * @return array{0: int, 1: array<string, string>, 2: string}
     */
    public function getSpec(): array
    {
        if (! file_exists($this->specPath)) {
            return $this->jsonResponse(404, ['error' => 'Site spec not found. Run bin/generate first.']);
        }

        $contents = file_get_contents($this->specPath);
        if ($contents === false) {
            return $this->jsonResponse(500, ['error' => 'Failed to read site spec.']);
        }

        return [200, ['Content-Type' => 'application/json'], $contents];
    }

    /**
     * PATCH /api/spec — update editable fields in the site-spec.
     *
     * Accepts JSON with optional keys: hero.headline, hero.subtitle, hero.bio,
     * developer.name, developer.email, cta.github, cta.email.
     *
     * @return array{0: int, 1: array<string, string>, 2: string}
     */
    public function updateSpec(string $requestBody): array
    {
        if (! file_exists($this->specPath)) {
            return $this->jsonResponse(404, ['error' => 'Site spec not found. Run bin/generate first.']);
        }

        $spec = $this->loadSpec();
        if ($spec === null) {
            return $this->jsonResponse(500, ['error' => 'Failed to read site spec.']);
        }

        $updates = json_decode($requestBody, true);
        if (! is_array($updates)) {
            return $this->jsonResponse(400, ['error' => 'Invalid JSON body.']);
        }

        $spec = $this->applyUpdates($spec, $updates);
        $result = $this->saveSpec($spec);

        if (! $result) {
            return $this->jsonResponse(500, ['error' => 'Failed to save site spec.']);
        }

        return $this->jsonResponse(200, ['status' => 'updated', 'message' => 'Site spec updated successfully.']);
    }

    /**
     * POST /api/regenerate — re-render the site HTML from the current spec.
     *
     * @return array{0: int, 1: array<string, string>, 2: string}
     */
    public function regenerate(): array
    {
        if (! file_exists($this->specPath)) {
            return $this->jsonResponse(404, ['error' => 'Site spec not found. Run bin/generate first.']);
        }

        try {
            $result = $this->renderer->render($this->specPath, $this->sitePath);
        } catch (\Throwable $e) {
            return $this->jsonResponse(500, ['error' => 'Render failed: '.$e->getMessage()]);
        }

        return $this->jsonResponse(200, [
            'status' => 'regenerated',
            'developer' => $result['developer'],
            'style' => $result['style'],
            'sections' => $result['sections'],
        ]);
    }

    /**
     * GET /preview — serve the generated site HTML.
     *
     * @return array{0: int, 1: array<string, string>, 2: string}
     */
    public function preview(): array
    {
        if (! file_exists($this->sitePath)) {
            return [404, ['Content-Type' => 'text/html'], '<h1>No site generated yet</h1><p>Click "Regenerate" in the dashboard.</p>'];
        }

        $html = file_get_contents($this->sitePath);
        if ($html === false) {
            return [500, ['Content-Type' => 'text/html'], '<h1>Error reading generated site</h1>'];
        }

        return [200, ['Content-Type' => 'text/html'], $html];
    }

    /**
     * GET / — render the dashboard HTML page.
     *
     * @return array{0: int, 1: array<string, string>, 2: string}
     */
    public function dashboardPage(): array
    {
        return [200, ['Content-Type' => 'text/html'], $this->dashboardHtml()];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Apply allowed updates to the spec array.
     *
     * @param  array<string, mixed>  $spec
     * @param  array<string, mixed>  $updates
     * @return array<string, mixed>
     */
    private function applyUpdates(array $spec, array $updates): array
    {
        // Developer fields
        if (isset($updates['developer'])) {
            foreach (['name', 'email'] as $field) {
                if (array_key_exists($field, $updates['developer'])) {
                    $spec['developer'][$field] = $updates['developer'][$field];
                }
            }
        }

        // Hero content
        if (isset($updates['hero'])) {
            foreach (['headline', 'subtitle', 'bio'] as $field) {
                if (array_key_exists($field, $updates['hero'])) {
                    $spec['content']['hero'][$field] = $updates['hero'][$field];
                }
            }
        }

        // CTA links
        if (isset($updates['cta'])) {
            foreach (['github', 'email'] as $field) {
                if (array_key_exists($field, $updates['cta'])) {
                    $spec['content']['cta'][$field] = $updates['cta'][$field];
                }
            }
        }

        return $spec;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadSpec(): ?array
    {
        $json = file_get_contents($this->specPath);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function saveSpec(array $spec): bool
    {
        $json = json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }

        return file_put_contents($this->specPath, $json) !== false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: int, 1: array<string, string>, 2: string}
     */
    private function jsonResponse(int $status, array $data): array
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return [$status, ['Content-Type' => 'application/json'], $json ?: '{}'];
    }

    private function notFound(): array
    {
        return $this->jsonResponse(404, ['error' => 'Not found.']);
    }

    private function dashboardHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Site Dashboard</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; color: #1a1a1a; }
    .dashboard { display: grid; grid-template-columns: 380px 1fr; height: 100vh; }
    .sidebar { background: #fff; border-right: 1px solid #e0e0e0; overflow-y: auto; padding: 1.5rem; }
    .preview-pane { position: relative; }
    .preview-pane iframe { width: 100%; height: 100%; border: none; }
    h1 { font-size: 1.25rem; margin-bottom: 1.5rem; color: #111; }
    h2 { font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em; color: #888; margin: 1.5rem 0 0.75rem; }
    label { display: block; font-size: 0.8rem; font-weight: 600; color: #555; margin-bottom: 0.25rem; }
    input, textarea { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.875rem; font-family: inherit; margin-bottom: 0.75rem; }
    input:focus, textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
    textarea { resize: vertical; min-height: 80px; }
    .btn-row { display: flex; gap: 0.5rem; margin-top: 1rem; }
    button { padding: 0.6rem 1.25rem; border: none; border-radius: 6px; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: background 0.15s; }
    .btn-primary { background: #3b82f6; color: #fff; }
    .btn-primary:hover { background: #2563eb; }
    .btn-secondary { background: #e5e7eb; color: #374151; }
    .btn-secondary:hover { background: #d1d5db; }
    .btn-primary:disabled, .btn-secondary:disabled { opacity: 0.6; cursor: not-allowed; }
    .toast { position: fixed; bottom: 1.5rem; right: 1.5rem; padding: 0.75rem 1.25rem; border-radius: 8px; font-size: 0.875rem; font-weight: 500; color: #fff; opacity: 0; transition: opacity 0.3s; z-index: 1000; }
    .toast.success { background: #16a34a; }
    .toast.error { background: #dc2626; }
    .toast.visible { opacity: 1; }
    .loading { text-align: center; padding: 2rem; color: #888; }
    @media (max-width: 800px) {
      .dashboard { grid-template-columns: 1fr; grid-template-rows: auto 50vh; }
    }
  </style>
</head>
<body>
  <div class="dashboard">
    <div class="sidebar">
      <h1>Site Dashboard</h1>

      <div id="loading" class="loading">Loading spec...</div>
      <div id="editor" style="display:none;">
        <h2>Developer</h2>
        <label for="dev-name">Name</label>
        <input type="text" id="dev-name">
        <label for="dev-email">Email</label>
        <input type="email" id="dev-email">

        <h2>Hero Section</h2>
        <label for="hero-headline">Headline</label>
        <input type="text" id="hero-headline">
        <label for="hero-subtitle">Subtitle</label>
        <input type="text" id="hero-subtitle">
        <label for="hero-bio">Bio</label>
        <textarea id="hero-bio"></textarea>

        <h2>Call to Action</h2>
        <label for="cta-github">GitHub URL</label>
        <input type="url" id="cta-github">
        <label for="cta-email">Contact Email</label>
        <input type="email" id="cta-email">

        <div class="btn-row">
          <button class="btn-primary" id="save-btn">Save Changes</button>
          <button class="btn-secondary" id="regen-btn">Regenerate Site</button>
        </div>
      </div>
    </div>
    <div class="preview-pane">
      <iframe id="preview" src="/preview"></iframe>
    </div>
  </div>

  <div class="toast" id="toast"></div>

  <script>
    const $ = id => document.getElementById(id);

    function toast(msg, type) {
      const el = $('toast');
      el.textContent = msg;
      el.className = 'toast ' + type + ' visible';
      setTimeout(() => el.classList.remove('visible'), 3000);
    }

    async function loadSpec() {
      try {
        const res = await fetch('/api/spec');
        if (!res.ok) throw new Error('Failed to load spec');
        const spec = await res.json();
        $('dev-name').value = spec.developer?.name || '';
        $('dev-email').value = spec.developer?.email || '';
        $('hero-headline').value = spec.content?.hero?.headline || '';
        $('hero-subtitle').value = spec.content?.hero?.subtitle || '';
        $('hero-bio').value = spec.content?.hero?.bio || '';
        $('cta-github').value = spec.content?.cta?.github || '';
        $('cta-email').value = spec.content?.cta?.email || '';
        $('loading').style.display = 'none';
        $('editor').style.display = 'block';
      } catch (e) {
        $('loading').textContent = 'No site spec found. Run bin/generate first.';
      }
    }

    $('save-btn').addEventListener('click', async () => {
      $('save-btn').disabled = true;
      try {
        const body = {
          developer: { name: $('dev-name').value, email: $('dev-email').value || null },
          hero: { headline: $('hero-headline').value, subtitle: $('hero-subtitle').value, bio: $('hero-bio').value },
          cta: { github: $('cta-github').value, email: $('cta-email').value || null }
        };
        const res = await fetch('/api/spec', { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
        if (!res.ok) throw new Error('Save failed');
        toast('Changes saved!', 'success');
      } catch (e) {
        toast('Failed to save: ' + e.message, 'error');
      } finally {
        $('save-btn').disabled = false;
      }
    });

    $('regen-btn').addEventListener('click', async () => {
      $('regen-btn').disabled = true;
      try {
        const res = await fetch('/api/regenerate', { method: 'POST' });
        if (!res.ok) throw new Error('Regeneration failed');
        toast('Site regenerated!', 'success');
        $('preview').src = '/preview?t=' + Date.now();
      } catch (e) {
        toast('Failed to regenerate: ' + e.message, 'error');
      } finally {
        $('regen-btn').disabled = false;
      }
    });

    loadSpec();
  </script>
</body>
</html>
HTML;
    }
}
