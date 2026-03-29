<?php

declare(strict_types=1);

namespace HistoryIngestion\Ingestors;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use HistoryIngestion\Schema\CommitRecord;
use HistoryIngestion\Schema\ProjectSnapshot;

/**
 * Fetches commit history from the GitHub REST API.
 *
 * Used as a fallback when a local git clone is unavailable, or to supplement
 * local data with repo metadata (languages, description) from GitHub.
 *
 * Requires a GitHub personal access token for private repos and to avoid
 * rate-limiting. Set the GITHUB_TOKEN environment variable.
 */
final class GitHubApiIngestor implements IngestorInterface
{
    private const API_BASE = 'https://api.github.com';

    private Client $client;

    /** @var int Max commits to fetch per repo (GitHub API page size is 100). */
    private int $maxCommits;

    public function __construct(?Client $client = null, int $maxCommits = 300)
    {
        $this->maxCommits = $maxCommits;

        $headers = [
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'paperclip-history-ingestion/1.0',
        ];

        $token = getenv('GITHUB_TOKEN');
        if ($token !== false && $token !== '') {
            $headers['Authorization'] = "Bearer {$token}";
        }

        $this->client = $client ?? new Client([
            'base_uri' => self::API_BASE,
            'headers' => $headers,
            'timeout' => 30,
        ]);
    }

    public function supports(array $projectConfig): bool
    {
        return isset($projectConfig['repo']) && $projectConfig['repo'] !== '';
    }

    public function ingest(array $projectConfig): ProjectSnapshot
    {
        $repo = $projectConfig['repo'];

        try {
            $commits = $this->fetchCommits($repo);
            $languages = $this->fetchLanguages($repo);
        } catch (GuzzleException $e) {
            // Graceful degradation: return empty snapshot flagged as git-unavailable
            return new ProjectSnapshot(
                name: $projectConfig['name'],
                repo: $repo,
                description: $projectConfig['description'],
                url: $projectConfig['url'],
                stack: $projectConfig['stack'],
                commits: [],
                totalCommits: 0,
                contributors: [],
                languageStats: [],
                firstCommitAt: null,
                lastCommitAt: null,
                gitAvailable: false,
            );
        }

        $contributors = $this->extractContributors($commits);
        $firstCommit = $commits !== [] ? end($commits)->date : null;
        $lastCommit = $commits !== [] ? $commits[0]->date : null;

        return new ProjectSnapshot(
            name: $projectConfig['name'],
            repo: $repo,
            description: $projectConfig['description'],
            url: $projectConfig['url'],
            stack: $projectConfig['stack'],
            commits: $commits,
            totalCommits: count($commits),
            contributors: $contributors,
            languageStats: $languages,
            firstCommitAt: $firstCommit,
            lastCommitAt: $lastCommit,
            gitAvailable: true,
        );
    }

    // -------------------------------------------------------------------------

    /** @return CommitRecord[] */
    private function fetchCommits(string $repo): array
    {
        $commits = [];
        $page = 1;
        $perPage = 100;

        while (count($commits) < $this->maxCommits) {
            $response = $this->client->get("/repos/{$repo}/commits", [
                'query' => ['per_page' => $perPage, 'page' => $page],
            ]);

            /** @var array<int,array<string,mixed>> $items */
            $items = json_decode($response->getBody()->getContents(), associative: true);

            if (empty($items)) {
                break;
            }

            foreach ($items as $item) {
                $c = $item['commit'];
                $commits[] = new CommitRecord(
                    hash: $item['sha'],
                    shortHash: substr($item['sha'], 0, 7),
                    message: $this->firstLine($c['message'] ?? ''),
                    author: $c['author']['name'] ?? 'Unknown',
                    authorEmail: $c['author']['email'] ?? '',
                    date: new \DateTimeImmutable($c['author']['date']),
                    filesChanged: 0,
                    insertions: 0,
                    deletions: 0,
                );

                if (count($commits) >= $this->maxCommits) {
                    break 2;
                }
            }

            if (count($items) < $perPage) {
                break;
            }

            $page++;
        }

        return $commits;
    }

    /** @return array<string,int> */
    private function fetchLanguages(string $repo): array
    {
        $response = $this->client->get("/repos/{$repo}/languages");

        /** @var array<string,int> $data */
        $data = json_decode($response->getBody()->getContents(), associative: true);

        arsort($data);

        return $data;
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

    private function firstLine(string $message): string
    {
        return explode("\n", trim($message))[0];
    }
}
