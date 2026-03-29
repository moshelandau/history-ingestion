<?php

declare(strict_types=1);

namespace HistoryIngestion\Deployment;

/**
 * Result returned after a successful deployment.
 */
final readonly class DeploymentResult
{
    public function __construct(
        /** Public URL where the site is live. */
        public string $liveUrl,
        /** URL of the backing repository. */
        public string $repositoryUrl,
        /** Git commit SHA that was deployed. */
        public string $commitSha,
        /** Driver that performed the deployment (e.g. "github_pages"). */
        public string $driver,
        public \DateTimeImmutable $deployedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'live_url' => $this->liveUrl,
            'repository_url' => $this->repositoryUrl,
            'commit_sha' => $this->commitSha,
            'driver' => $this->driver,
            'deployed_at' => $this->deployedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
