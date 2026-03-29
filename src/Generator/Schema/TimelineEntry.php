<?php

declare(strict_types=1);

namespace HistoryIngestion\Generator\Schema;

/**
 * A single activity entry in the commit timeline section.
 */
final readonly class TimelineEntry
{
    public function __construct(
        public string $date,
        public string $projectName,
        public string $commitMessage,
        public string $shortHash,
        public string $projectUrl,
    ) {}

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'project_name' => $this->projectName,
            'commit_message' => $this->commitMessage,
            'short_hash' => $this->shortHash,
            'project_url' => $this->projectUrl,
        ];
    }
}
