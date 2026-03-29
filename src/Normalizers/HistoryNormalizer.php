<?php

declare(strict_types=1);

namespace HistoryIngestion\Normalizers;

use HistoryIngestion\Schema\DeveloperProfile;
use HistoryIngestion\Schema\NormalizedDataset;
use HistoryIngestion\Schema\ProjectSnapshot;

/**
 * Aggregates per-project snapshots into a single NormalizedDataset
 * ready for consumption by the website generation engine.
 */
final class HistoryNormalizer
{
    private const SCHEMA_VERSION = '1.0.0';

    /**
     * Known framework keywords to extract from stack arrays.
     *
     * @var string[]
     */
    private const FRAMEWORK_KEYWORDS = [
        'Laravel', 'Vue', 'Vue 3', 'Next.js', 'React', 'Inertia.js',
        'Filament', 'Livewire', 'Nuxt.js', 'Tailwind CSS',
    ];

    /**
     * Known tool keywords.
     *
     * @var string[]
     */
    private const TOOL_KEYWORDS = [
        'PostgreSQL', 'MySQL', 'SQLite', 'Redis',
        'Prisma', 'Docker', 'GitHub Actions',
        'Plivo', 'Google Maps API', 'Claude AI',
    ];

    /**
     * @param  ProjectSnapshot[]  $snapshots
     */
    public function normalize(
        DeveloperProfile $developer,
        array $snapshots,
    ): NormalizedDataset {
        $totalCommits = 0;
        $activeRepos = 0;
        $allLanguages = [];
        $allFrameworks = [];
        $allTools = [];
        $activityFrom = null;
        $activityTo = null;

        foreach ($snapshots as $snapshot) {
            $totalCommits += $snapshot->totalCommits;

            if ($snapshot->gitAvailable && $snapshot->totalCommits > 0) {
                $activeRepos++;
            }

            // Merge language stats (sum file counts across repos)
            foreach ($snapshot->languageStats as $lang => $count) {
                $allLanguages[$lang] = ($allLanguages[$lang] ?? 0) + $count;
            }

            // Extract frameworks and tools from stack
            foreach ($snapshot->stack as $tech) {
                if (in_array($tech, self::FRAMEWORK_KEYWORDS, strict: true)) {
                    $allFrameworks[] = $tech;
                } elseif (in_array($tech, self::TOOL_KEYWORDS, strict: true)) {
                    $allTools[] = $tech;
                }
            }

            // Track overall activity date range
            if ($snapshot->firstCommitAt !== null) {
                if ($activityFrom === null || $snapshot->firstCommitAt < $activityFrom) {
                    $activityFrom = $snapshot->firstCommitAt;
                }
            }
            if ($snapshot->lastCommitAt !== null) {
                if ($activityTo === null || $snapshot->lastCommitAt > $activityTo) {
                    $activityTo = $snapshot->lastCommitAt;
                }
            }
        }

        arsort($allLanguages);

        $frameworks = array_values(array_unique($allFrameworks));
        $tools = array_values(array_unique($allTools));

        return new NormalizedDataset(
            schemaVersion: self::SCHEMA_VERSION,
            generatedAt: new \DateTimeImmutable,
            developer: $developer,
            projects: $snapshots,
            totalCommits: $totalCommits,
            activeRepositories: $activeRepos,
            languages: $allLanguages,
            frameworks: $frameworks,
            tools: $tools,
            activityFrom: $activityFrom,
            activityTo: $activityTo,
        );
    }
}
