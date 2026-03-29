<?php

declare(strict_types=1);

namespace HistoryIngestion\Schema;

/**
 * The final normalized dataset output by the ingestion pipeline.
 * This is the contract between the history ingestion pipeline
 * and the downstream website generation engine.
 */
final readonly class NormalizedDataset
{
    /**
     * @param  ProjectSnapshot[]  $projects
     * @param  string[]  $frameworks
     * @param  string[]  $tools
     * @param  array<string,int>  $languages  Language → estimated total lines
     */
    public function __construct(
        public string $schemaVersion,
        public \DateTimeImmutable $generatedAt,
        public DeveloperProfile $developer,
        public array $projects,
        public int $totalCommits,
        public int $activeRepositories,
        public array $languages,
        public array $frameworks,
        public array $tools,
        public ?\DateTimeImmutable $activityFrom,
        public ?\DateTimeImmutable $activityTo,
    ) {}

    public function toArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'generated_at' => $this->generatedAt->format(\DateTimeInterface::ATOM),
            'developer' => $this->developer->toArray(),
            'activity' => [
                'total_commits' => $this->totalCommits,
                'active_repositories' => $this->activeRepositories,
                'from' => $this->activityFrom?->format(\DateTimeInterface::ATOM),
                'to' => $this->activityTo?->format(\DateTimeInterface::ATOM),
            ],
            'skills' => [
                'languages' => $this->languages,
                'frameworks' => $this->frameworks,
                'tools' => $this->tools,
            ],
            'projects' => array_map(fn (ProjectSnapshot $p) => $p->toArray(), $this->projects),
        ];
    }
}
