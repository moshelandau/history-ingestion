<?php

declare(strict_types=1);

namespace HistoryIngestion\Schema;

/**
 * A normalized snapshot of a single project's history and metadata.
 */
final readonly class ProjectSnapshot
{
    /**
     * @param  string[]  $stack
     * @param  CommitRecord[]  $commits
     * @param  string[]  $contributors
     * @param  array<string,int>  $languageStats  Language → line-count estimate
     */
    public function __construct(
        public string $name,
        public string $repo,
        public string $description,
        public string $url,
        public array $stack,
        public array $commits,
        public int $totalCommits,
        public array $contributors,
        public array $languageStats,
        public ?\DateTimeImmutable $firstCommitAt,
        public ?\DateTimeImmutable $lastCommitAt,
        public bool $gitAvailable,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'repo' => $this->repo,
            'description' => $this->description,
            'url' => $this->url,
            'stack' => $this->stack,
            'total_commits' => $this->totalCommits,
            'contributors' => $this->contributors,
            'language_stats' => $this->languageStats,
            'first_commit_at' => $this->firstCommitAt?->format(\DateTimeInterface::ATOM),
            'last_commit_at' => $this->lastCommitAt?->format(\DateTimeInterface::ATOM),
            'git_available' => $this->gitAvailable,
            'commits' => array_map(fn (CommitRecord $c) => $c->toArray(), $this->commits),
        ];
    }
}
