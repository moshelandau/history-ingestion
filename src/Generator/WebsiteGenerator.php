<?php

declare(strict_types=1);

namespace HistoryIngestion\Generator;

use HistoryIngestion\Generator\Schema\WebsiteSpec;
use HistoryIngestion\Schema\NormalizedDataset;

/**
 * Orchestrates the website generation pipeline.
 *
 * Takes a NormalizedDataset produced by the history ingestion pipeline
 * and produces a complete WebsiteSpec describing the personalized website.
 *
 * Flow:
 *   NormalizedDataset → ThemeBuilder → SiteTheme
 *                     → StructureBuilder → SiteStructure
 *                     → ContentBuilder → SiteContent
 *                     → WebsiteSpec (assembled output)
 */
final class WebsiteGenerator
{
    public function __construct(
        private readonly ThemeBuilder $themeBuilder,
        private readonly StructureBuilder $structureBuilder,
        private readonly ContentBuilder $contentBuilder,
    ) {}

    public static function create(): self
    {
        return new self(
            themeBuilder: new ThemeBuilder,
            structureBuilder: new StructureBuilder,
            contentBuilder: new ContentBuilder,
        );
    }

    public function generate(NormalizedDataset $dataset): WebsiteSpec
    {
        $theme = $this->themeBuilder->build($dataset);
        $structure = $this->structureBuilder->build($dataset);
        $content = $this->contentBuilder->build($dataset);

        return new WebsiteSpec(
            schemaVersion: WebsiteSpec::SCHEMA_VERSION,
            generatedAt: new \DateTimeImmutable,
            developer: $dataset->developer,
            structure: $structure,
            content: $content,
            theme: $theme,
            sourceDatasetVersion: $dataset->schemaVersion,
        );
    }
}
