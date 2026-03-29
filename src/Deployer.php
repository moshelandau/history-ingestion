<?php

declare(strict_types=1);

namespace HistoryIngestion;

use HistoryIngestion\Deployment\DeploymentDriverInterface;
use HistoryIngestion\Deployment\DeploymentResult;

/**
 * Orchestrates the deployment pipeline:
 *
 *   1. Pick the first driver that supports the given config.
 *   2. Run deployment.
 *   3. Write a deployment manifest (deploy.json) to the output path.
 */
final class Deployer
{
    /** @var DeploymentDriverInterface[] */
    private array $drivers;

    /** @param DeploymentDriverInterface[] $drivers Priority-ordered list. */
    public function __construct(array $drivers)
    {
        $this->drivers = $drivers;
    }

    /**
     * Deploy a generated site directory.
     *
     * @param  string  $siteDir  Absolute path to the directory to deploy.
     * @param  array<string,mixed>  $config  Deployment config (from config/deploy.php).
     * @param  string  $outputPath  Where to write deploy.json.
     */
    public function run(string $siteDir, array $config, string $outputPath): DeploymentResult
    {
        $driver = $this->resolveDriver($config);

        if ($driver === null) {
            throw new \RuntimeException(
                'No deployment driver supports the configured driver: '.($config['driver'] ?? '(none)'),
            );
        }

        $this->log('  DEPLOY via '.$driver::class);
        $result = $driver->deploy($siteDir, $config);
        $this->log("    → live at {$result->liveUrl}");

        $this->writeManifest($result, $outputPath);

        return $result;
    }

    // -------------------------------------------------------------------------

    private function resolveDriver(array $config): ?DeploymentDriverInterface
    {
        foreach ($this->drivers as $driver) {
            if ($driver->supports($config)) {
                return $driver;
            }
        }

        return null;
    }

    private function writeManifest(DeploymentResult $result, string $outputPath): void
    {
        $dir = dirname($outputPath);
        if (! is_dir($dir)) {
            mkdir($dir, recursive: true);
        }

        $json = json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new \RuntimeException('Failed to JSON-encode deployment result: '.json_last_error_msg());
        }

        file_put_contents($outputPath, $json);
    }

    private function log(string $message): void
    {
        echo $message.PHP_EOL;
    }
}
