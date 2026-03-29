<?php

declare(strict_types=1);

namespace HistoryIngestion\Generator\Schema;

/**
 * A rendered card representing a single project in the portfolio section.
 */
final readonly class ProjectCard
{
    /**
     * @param  string[]  $techStack
     * @param  string[]  $highlights  Notable commit messages that hint at features built
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $url,
        public array $techStack,
        public array $highlights,
        public int $commitCount,
        public ?string $activityFrom,
        public ?string $activityTo,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'url' => $this->url,
            'tech_stack' => $this->techStack,
            'highlights' => $this->highlights,
            'commit_count' => $this->commitCount,
            'activity_from' => $this->activityFrom,
            'activity_to' => $this->activityTo,
        ];
    }
}
