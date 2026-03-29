<?php

declare(strict_types=1);

namespace HistoryIngestion\Ingestors;

use HistoryIngestion\Schema\CommitRecord;
use HistoryIngestion\Schema\ProjectSnapshot;

/**
 * Reads commit history from a local git repository using `git log`.
 *
 * Falls back gracefully when the path does not exist or has no git history.
 */
final class GitIngestor implements IngestorInterface
{
    private const LOG_FORMAT = '%H%x1F%h%x1F%s%x1F%an%x1F%ae%x1F%aI%x1F%cd';

    /** Null-device redirect compatible with the current OS. */
    private const NULL_REDIRECT = PHP_OS_FAMILY === 'Windows' ? '2>NUL' : '2>/dev/null';

    /** @var int Maximum commits to ingest per repo (keeps output manageable). */
    private int $maxCommits;

    public function __construct(int $maxCommits = 500)
    {
        $this->maxCommits = $maxCommits;
    }

    public function supports(array $projectConfig): bool
    {
        $path = $projectConfig['local_path'] ?? '';

        return $path !== '' && is_dir($path) && is_dir($path.'/.git');
    }

    public function ingest(array $projectConfig): ProjectSnapshot
    {
        $path = $projectConfig['local_path'];

        if (! $this->supports($projectConfig)) {
            return $this->emptySnapshot($projectConfig, gitAvailable: false);
        }

        $commits = $this->readCommits($path);
        [$insertionMap, $deletionMap, $fileMap] = $this->readDiffStats($path);

        $normalized = [];
        foreach ($commits as $raw) {
            $hash = $raw['hash'];
            $normalized[] = new CommitRecord(
                hash: $hash,
                shortHash: $raw['short_hash'],
                message: $raw['message'],
                author: $raw['author'],
                authorEmail: $raw['author_email'],
                date: new \DateTimeImmutable($raw['date']),
                filesChanged: $fileMap[$hash] ?? 0,
                insertions: $insertionMap[$hash] ?? 0,
                deletions: $deletionMap[$hash] ?? 0,
            );
        }

        $contributors = $this->extractContributors($normalized);
        $languageStats = $this->estimateLanguageStats($path);
        $firstCommit = $normalized !== [] ? end($normalized)->date : null;
        $lastCommit = $normalized !== [] ? $normalized[0]->date : null;

        return new ProjectSnapshot(
            name: $projectConfig['name'],
            repo: $projectConfig['repo'],
            description: $projectConfig['description'],
            url: $projectConfig['url'],
            stack: $projectConfig['stack'],
            commits: $normalized,
            totalCommits: count($normalized),
            contributors: $contributors,
            languageStats: $languageStats,
            firstCommitAt: $firstCommit,
            lastCommitAt: $lastCommit,
            gitAvailable: true,
        );
    }

    // -------------------------------------------------------------------------

    /** @return array<array{hash:string,short_hash:string,message:string,author:string,author_email:string,date:string}> */
    private function readCommits(string $path): array
    {
        // Use proc_open to bypass shell %‑expansion on Windows.
        $output = $this->execArgs([
            'git', '-C', $path,
            'log', '--format='.self::LOG_FORMAT, '-n', (string) $this->maxCommits,
        ]);

        if ($output === '') {
            return [];
        }

        $commits = [];
        foreach (explode("\n", trim($output)) as $line) {
            if ($line === '') {
                continue;
            }
            $parts = explode("\x1F", $line);
            if (count($parts) < 6) {
                continue;
            }
            $commits[] = [
                'hash' => $parts[0],
                'short_hash' => $parts[1],
                'message' => $parts[2],
                'author' => $parts[3],
                'author_email' => $parts[4],
                'date' => $parts[5],
            ];
        }

        return $commits;
    }

    /**
     * Returns [insertionMap, deletionMap, fileChangedMap] keyed by commit hash.
     *
     * @return array{array<string,int>, array<string,int>, array<string,int>}
     */
    private function readDiffStats(string $path): array
    {
        $output = $this->execArgs([
            'git', '-C', $path,
            'log', '--format=%H', '--shortstat', '-n', (string) $this->maxCommits,
        ]);

        $insertions = [];
        $deletions = [];
        $files = [];
        $currentHash = null;

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);
            if (preg_match('/^[0-9a-f]{40}$/', $line)) {
                $currentHash = $line;

                continue;
            }
            if ($currentHash === null) {
                continue;
            }
            // " 3 files changed, 120 insertions(+), 5 deletions(-)"
            if (preg_match('/(\d+) files? changed/', $line, $m)) {
                $files[$currentHash] = (int) $m[1];
            }
            if (preg_match('/(\d+) insertions?\(\+\)/', $line, $m)) {
                $insertions[$currentHash] = (int) $m[1];
            }
            if (preg_match('/(\d+) deletions?\(-\)/', $line, $m)) {
                $deletions[$currentHash] = (int) $m[1];
            }
        }

        return [$insertions, $deletions, $files];
    }

    /** @return string[] */
    private function extractContributors(array $commits): array
    {
        $seen = [];
        foreach ($commits as $commit) {
            $seen[$commit->authorEmail] = $commit->author;
        }

        return array_values(array_unique(array_values($seen)));
    }

    /** @return array<string,int> */
    private function estimateLanguageStats(string $path): array
    {
        $extMap = [
            'php' => 'PHP',
            'vue' => 'Vue',
            'ts' => 'TypeScript',
            'tsx' => 'TypeScript',
            'js' => 'JavaScript',
            'jsx' => 'JavaScript',
            'blade' => 'Blade',
            'css' => 'CSS',
            'scss' => 'SCSS',
            'json' => 'JSON',
            'md' => 'Markdown',
            'sql' => 'SQL',
            'py' => 'Python',
            'go' => 'Go',
        ];

        $counts = [];

        $excludes = ['vendor', 'node_modules', '.git', 'storage', 'bootstrap/cache'];
        $excludePattern = implode('|', array_map('preg_quote', $excludes));

        $cmd = 'git -C '.escapeshellarg($path)
            .' ls-files '.self::NULL_REDIRECT;
        $files = $this->exec($cmd);

        foreach (explode("\n", $files) as $file) {
            $file = trim($file);
            if ($file === '') {
                continue;
            }
            // skip excluded directories
            if (preg_match('#('.$excludePattern.')/#i', $file)) {
                continue;
            }
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (isset($extMap[$ext])) {
                $lang = $extMap[$ext];
                $counts[$lang] = ($counts[$lang] ?? 0) + 1;
            }
        }

        arsort($counts);

        return $counts;
    }

    private function emptySnapshot(array $projectConfig, bool $gitAvailable): ProjectSnapshot
    {
        return new ProjectSnapshot(
            name: $projectConfig['name'],
            repo: $projectConfig['repo'],
            description: $projectConfig['description'],
            url: $projectConfig['url'],
            stack: $projectConfig['stack'],
            commits: [],
            totalCommits: 0,
            contributors: [],
            languageStats: [],
            firstCommitAt: null,
            lastCommitAt: null,
            gitAvailable: $gitAvailable,
        );
    }

    /**
     * Run a git command via proc_open, bypassing the shell.
     * This avoids Windows CMD expanding `%` placeholders in format strings.
     *
     * @param  string[]  $args
     */
    private function execArgs(array $args): string
    {
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($args, $descriptor, $pipes);

        if (! is_resource($process)) {
            return '';
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return $output !== false ? trim($output) : '';
    }

    private function exec(string $cmd): string
    {
        $output = shell_exec($cmd);

        return $output !== null ? trim($output) : '';
    }
}
