<?php

declare(strict_types=1);

namespace HistoryIngestion;

use HistoryIngestion\Ingestors\IngestorInterface;
use HistoryIngestion\Normalizers\HistoryNormalizer;
use HistoryIngestion\Schema\DeveloperProfile;
use HistoryIngestion\Schema\NormalizedDataset;

/**
 * Orchestrates the full ingestion pipeline:
 *
 *   1. For each configured project, pick the best available ingestor.
 *   2. Run ingestion → ProjectSnapshot.
 *   3. Normalize all snapshots into a NormalizedDataset.
 *   4. Serialize and write to the output path.
 */
final class Pipeline
{
    /** @var IngestorInterface[] Priority-ordered list of ingestors (first wins). */
    private array $ingestors;

    private HistoryNormalizer $normalizer;

    /** @param IngestorInterface[] $ingestors */
    public function __construct(array $ingestors, ?HistoryNormalizer $normalizer = null)
    {
        $this->ingestors = $ingestors;
        $this->normalizer = $normalizer ?? new HistoryNormalizer;
    }

    /**
     * Run the full pipeline.
     *
     * @param  array<string,mixed>  $config  Parsed config/projects.php
     * @param  string  $outputPath  Where to write history.json
     */
    public function run(array $config, string $outputPath): NormalizedDataset
    {
        $developer = new DeveloperProfile(
            name: $config['developer']['name'],
            github: $config['developer']['github'],
            email: $config['developer']['email'],
        );

        $snapshots = [];

        foreach ($config['projects'] as $projectConfig) {
            $ingestor = $this->resolveIngestor($projectConfig);

            if ($ingestor === null) {
                $this->log("  SKIP {$projectConfig['name']} — no suitable ingestor");

                continue;
            }

            $this->log("  INGEST {$projectConfig['name']} via ".$ingestor::class);
            $snapshot = $ingestor->ingest($projectConfig);
            $this->log("    → {$snapshot->totalCommits} commits, git_available=".($snapshot->gitAvailable ? 'yes' : 'no'));
            $snapshots[] = $snapshot;
        }

        $dataset = $this->normalizer->normalize($developer, $snapshots);

        $this->writeOutput($dataset, $outputPath);

        return $dataset;
    }

    // -------------------------------------------------------------------------

    private function resolveIngestor(array $projectConfig): ?IngestorInterface
    {
        foreach ($this->ingestors as $ingestor) {
            if ($ingestor->supports($projectConfig)) {
                return $ingestor;
            }
        }

        return null;
    }

    private function writeOutput(NormalizedDataset $dataset, string $outputPath): void
    {
        $dir = dirname($outputPath);
        if (! is_dir($dir)) {
            mkdir($dir, recursive: true);
        }

        $json = json_encode($dataset->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new \RuntimeException('Failed to JSON-encode dataset: '.json_last_error_msg());
        }

        file_put_contents($outputPath, $json);
    }

    private function log(string $message): void
    {
        echo $message.PHP_EOL;
    }
}
