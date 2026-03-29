<?php

declare(strict_types=1);

namespace HistoryIngestion\Generator;

use HistoryIngestion\Generator\Schema\SiteStructure;
use HistoryIngestion\Schema\NormalizedDataset;

/**
 * Derives the page/section structure of the website from the normalized dataset.
 *
 * Always included: home (hero + projects + skills), contact.
 * Conditionally included: timeline section on home if commit count is high enough.
 */
final class StructureBuilder
{
    private const TIMELINE_THRESHOLD = 30;

    public function build(NormalizedDataset $dataset): SiteStructure
    {
        $homeSections = ['hero', 'projects', 'skills'];

        if ($dataset->totalCommits >= self::TIMELINE_THRESHOLD) {
            $homeSections[] = 'timeline';
        }

        $homeSections[] = 'contact';

        $pages = [
            [
                'key' => 'home',
                'title' => 'Home',
                'slug' => '/',
                'sections' => $homeSections,
            ],
        ];

        return new SiteStructure(
            pages: $pages,
            navigationStyle: $this->resolveNavigationStyle($dataset),
            layoutType: 'single-page',
        );
    }

    // -------------------------------------------------------------------------

    private function resolveNavigationStyle(NormalizedDataset $dataset): string
    {
        // Vibrant/high-activity profiles get a top-bar nav with visible links.
        // Minimal profiles get a clean minimal nav.
        if ($dataset->totalCommits >= 100) {
            return 'top-bar';
        }

        return 'minimal';
    }
}
