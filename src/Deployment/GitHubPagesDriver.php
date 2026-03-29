<?php

declare(strict_types=1);

namespace HistoryIngestion\Deployment;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

/**
 * Deploys a static site to GitHub Pages via the GitHub Contents API.
 *
 * Flow:
 *   1. Ensure the target repo exists (create if absent).
 *   2. Enable GitHub Pages on the repo (source: main branch, root /).
 *   3. Walk the local site directory and upsert every file via the API.
 *   4. Return a DeploymentResult with the live Pages URL.
 *
 * Required config keys:
 *   - github_token  : personal access token with repo + pages scopes
 *   - owner         : GitHub username or org that owns the target repo
 *   - repo          : repository name  (e.g. "my-portfolio")
 *
 * Optional config keys:
 *   - branch        : branch to push to (default: "main")
 *   - commit_message: commit message template (default: "chore: deploy generated site")
 */
final class GitHubPagesDriver implements DeploymentDriverInterface
{
    private const API_BASE = 'https://api.github.com';

    private const DRIVER = 'github_pages';

    private Client $http;

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?? new Client([
            'base_uri' => self::API_BASE,
            'timeout' => 30,
            'headers' => ['User-Agent' => 'history-ingestion-deploy/1.0'],
        ]);
    }

    public function supports(array $config): bool
    {
        return ($config['driver'] ?? '') === self::DRIVER;
    }

    public function deploy(string $siteDir, array $config): DeploymentResult
    {
        $token = $config['github_token'] ?? getenv('GITHUB_TOKEN') ?: throw new \InvalidArgumentException('github_token is required');
        $owner = $config['owner'] ?? throw new \InvalidArgumentException('owner is required');
        $repo = $config['repo'] ?? throw new \InvalidArgumentException('repo is required');
        $branch = $config['branch'] ?? 'main';
        $message = $config['commit_message'] ?? 'chore: deploy generated site';

        if (! is_dir($siteDir)) {
            throw new \InvalidArgumentException("Site directory not found: {$siteDir}");
        }

        $headers = [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ];

        $this->ensureRepo($headers, $owner, $repo);
        $this->ensurePages($headers, $owner, $repo, $branch);

        $lastSha = $this->pushFiles($headers, $owner, $repo, $branch, $siteDir, $message);

        $liveUrl = "https://{$owner}.github.io/{$repo}/";

        return new DeploymentResult(
            liveUrl: $liveUrl,
            repositoryUrl: "https://github.com/{$owner}/{$repo}",
            commitSha: $lastSha,
            driver: self::DRIVER,
            deployedAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /** Create the repo if it does not exist yet. */
    private function ensureRepo(array $headers, string $owner, string $repo): void
    {
        try {
            $this->http->get("/repos/{$owner}/{$repo}", ['headers' => $headers]);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() !== 404) {
                throw $e;
            }
            // Repo not found — create it.
            $this->http->post('/user/repos', [
                'headers' => $headers,
                'json' => [
                    'name' => $repo,
                    'private' => false,
                    'description' => 'Auto-generated developer portfolio',
                    'auto_init' => true,
                ],
            ]);
        }
    }

    /** Enable GitHub Pages on the repo (idempotent). */
    private function ensurePages(array $headers, string $owner, string $repo, string $branch): void
    {
        try {
            $this->http->post("/repos/{$owner}/{$repo}/pages", [
                'headers' => $headers,
                'json' => [
                    'source' => ['branch' => $branch, 'path' => '/'],
                ],
            ]);
        } catch (ClientException $e) {
            // 409 Conflict → Pages already enabled; that's fine.
            if ($e->getResponse()->getStatusCode() !== 409) {
                throw $e;
            }
        }
    }

    /**
     * Push every file in $siteDir to the repo via the Contents API.
     * Returns the commit SHA of the last pushed file.
     */
    private function pushFiles(
        array $headers,
        string $owner,
        string $repo,
        string $branch,
        string $siteDir,
        string $message,
    ): string {
        $files = $this->collectFiles($siteDir);

        if (empty($files)) {
            throw new \RuntimeException("No files found in site directory: {$siteDir}");
        }

        $lastSha = '';

        foreach ($files as $localPath => $repoPath) {
            $content = base64_encode(file_get_contents($localPath));

            // Fetch existing file SHA if it exists (needed for updates).
            $existingSha = $this->getFileSha($headers, $owner, $repo, $repoPath, $branch);

            $payload = [
                'message' => $message,
                'content' => $content,
                'branch' => $branch,
            ];

            if ($existingSha !== null) {
                $payload['sha'] = $existingSha;
            }

            $response = $this->http->put("/repos/{$owner}/{$repo}/contents/{$repoPath}", [
                'headers' => $headers,
                'json' => $payload,
            ]);

            $data = json_decode((string) $response->getBody(), true);
            $lastSha = $data['commit']['sha'] ?? $lastSha;
        }

        return $lastSha;
    }

    /**
     * @return array<string,string> localPath => repoPath
     */
    private function collectFiles(string $dir): array
    {
        $files = [];
        $baseLen = strlen(rtrim($dir, '/\\')) + 1;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $localPath = $file->getPathname();
            $repoPath = ltrim(str_replace('\\', '/', substr($localPath, $baseLen)), '/');
            $files[$localPath] = $repoPath;
        }

        return $files;
    }

    /** Returns the blob SHA of an existing file, or null if it does not exist. */
    private function getFileSha(array $headers, string $owner, string $repo, string $path, string $branch): ?string
    {
        try {
            $response = $this->http->get(
                "/repos/{$owner}/{$repo}/contents/{$path}",
                ['headers' => $headers, 'query' => ['ref' => $branch]],
            );
            $data = json_decode((string) $response->getBody(), true);

            return $data['sha'] ?? null;
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }
}
