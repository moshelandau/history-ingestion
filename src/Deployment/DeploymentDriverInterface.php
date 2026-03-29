<?php

declare(strict_types=1);

namespace HistoryIngestion\Deployment;

interface DeploymentDriverInterface
{
    /**
     * Deploy the generated site directory and return a result with a live URL.
     *
     * @param  string  $siteDir  Absolute path to the directory containing HTML/assets.
     * @param  array<string,mixed>  $config  Deployment config (from config/deploy.php).
     */
    public function deploy(string $siteDir, array $config): DeploymentResult;

    /**
     * Whether this driver can handle the given deployment config.
     *
     * @param  array<string,mixed>  $config
     */
    public function supports(array $config): bool;
}
