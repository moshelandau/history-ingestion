<?php

declare(strict_types=1);

namespace HistoryIngestion\Generator\Schema;

/**
 * A single skill item for the skills section.
 *
 * Category is one of: language | framework | tool
 * Weight is used for visual sizing (e.g. progress bar percentage).
 */
final readonly class SkillEntry
{
    public function __construct(
        public string $name,
        /** language | framework | tool */
        public string $category,
        /** Relative weight 1–100 derived from usage metrics */
        public int $weight,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'category' => $this->category,
            'weight' => $this->weight,
        ];
    }
}
