<?php

declare(strict_types=1);

namespace HistoryIngestion\Generator\Schema;

use HistoryIngestion\Schema\DeveloperProfile;

/**
 * The complete website specification produced by the generation engine.
 *
 * This is the contract between the website generation engine and the
 * downstream rendering/deployment layer (SIM-4+).
 */
final readonly class WebsiteSpec
{
    public const SCHEMA_VERSION = '1.0.0';

    public function __construct(
        public string $schemaVersion,
        public \DateTimeImmutable $generatedAt,
        public DeveloperProfile $developer,
        public SiteStructure $structure,
        public SiteContent $content,
        public SiteTheme $theme,
        /** Schema version of the NormalizedDataset that was used as input */
        public string $sourceDatasetVersion,
    ) {}

    public function toArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'generated_at' => $this->generatedAt->format(\DateTimeInterface::ATOM),
            'source_dataset_version' => $this->sourceDatasetVersion,
            'developer' => $this->developer->toArray(),
            'structure' => $this->structure->toArray(),
            'content' => $this->content->toArray(),
            'theme' => $this->theme->toArray(),
        ];
    }
}
