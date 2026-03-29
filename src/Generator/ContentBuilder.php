<?php

declare(strict_types=1);

namespace HistoryIngestion\Generator;

use HistoryIngestion\Generator\Schema\ProjectCard;
use HistoryIngestion\Generator\Schema\SiteContent;
use HistoryIngestion\Generator\Schema\SkillEntry;
use HistoryIngestion\Generator\Schema\TimelineEntry;
use HistoryIngestion\Schema\CommitRecord;
use HistoryIngestion\Schema\NormalizedDataset;
use HistoryIngestion\Schema\ProjectSnapshot;

/**
 * Generates all website content from the normalized developer history.
 *
 * Responsibilities:
 *   - Hero section: headline, subtitle, bio paragraph
 *   - Project cards: ordered by activity, with commit highlights
 *   - Skills: languages (weighted), frameworks, tools (flat weight)
 *   - Timeline: last N commits across all projects, newest first
 *   - CTA links (email, GitHub)
 */
final class ContentBuilder
{
    private const MAX_TIMELINE_ENTRIES = 15;

    private const MAX_HIGHLIGHT_COMMITS = 3;

    public function build(NormalizedDataset $dataset): SiteContent
    {
        return new SiteContent(
            heroHeadline: $this->buildHeadline($dataset),
            heroSubtitle: $this->buildSubtitle($dataset),
            heroBio: $this->buildBio($dataset),
            projectCards: $this->buildProjectCards($dataset),
            skills: $this->buildSkills($dataset),
            timeline: $this->buildTimeline($dataset),
            ctaEmail: $dataset->developer->email,
            ctaGithub: "https://github.com/{$dataset->developer->github}",
        );
    }

    // -------------------------------------------------------------------------

    private function buildHeadline(NormalizedDataset $dataset): string
    {
        return "{$dataset->developer->name} — Full-Stack Developer";
    }

    private function buildSubtitle(NormalizedDataset $dataset): string
    {
        $top = array_slice($dataset->frameworks, 0, 3);

        if (empty($top)) {
            return 'Building modern web applications';
        }

        $last = array_pop($top);
        $list = empty($top)
            ? $last
            : implode(', ', $top)." & {$last}";

        return "Building with {$list}";
    }

    private function buildBio(NormalizedDataset $dataset): string
    {
        $name = $dataset->developer->name;
        $repoCount = $dataset->activeRepositories;
        $commitCount = $dataset->totalCommits;

        $topLangs = array_keys(array_slice($dataset->languages, 0, 3, preserve_keys: true));
        $langList = implode(', ', $topLangs);

        $topFrameworks = array_slice($dataset->frameworks, 0, 3);
        $fwList = implode(', ', $topFrameworks);

        $parts = ["{$name} is a full-stack developer with hands-on experience across {$repoCount} active projects"];

        if ($commitCount > 0) {
            $parts[] = "and {$commitCount} commits of shipped code";
        }

        $sentence = implode(', ', $parts).'.';

        if ($langList !== '') {
            $sentence .= " Primarily working in {$langList}";
            if ($fwList !== '') {
                $sentence .= " with frameworks including {$fwList}";
            }
            $sentence .= '.';
        }

        return $sentence;
    }

    /**
     * @return ProjectCard[]
     */
    private function buildProjectCards(NormalizedDataset $dataset): array
    {
        // Sort projects: git-available first, then by commit count descending
        $projects = $dataset->projects;
        usort($projects, function (ProjectSnapshot $a, ProjectSnapshot $b): int {
            if ($a->gitAvailable !== $b->gitAvailable) {
                return $a->gitAvailable ? -1 : 1;
            }

            return $b->totalCommits <=> $a->totalCommits;
        });

        return array_map(fn (ProjectSnapshot $p) => $this->buildProjectCard($p), $projects);
    }

    private function buildProjectCard(ProjectSnapshot $project): ProjectCard
    {
        $highlights = $this->extractHighlights($project);

        return new ProjectCard(
            name: $project->name,
            description: $project->description,
            url: $project->url,
            techStack: $project->stack,
            highlights: $highlights,
            commitCount: $project->totalCommits,
            activityFrom: $project->firstCommitAt?->format(\DateTimeInterface::ATOM),
            activityTo: $project->lastCommitAt?->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * Pick the most descriptive commit messages as feature highlights.
     * Skips merge commits, fixup commits, and very short messages.
     *
     * @return string[]
     */
    private function extractHighlights(ProjectSnapshot $project): array
    {
        $highlights = [];

        foreach ($project->commits as $commit) {
            if (count($highlights) >= self::MAX_HIGHLIGHT_COMMITS) {
                break;
            }

            $msg = trim($commit->message);

            if ($this->isBoringCommit($msg)) {
                continue;
            }

            $highlights[] = $msg;
        }

        return $highlights;
    }

    private function isBoringCommit(string $message): bool
    {
        if (strlen($message) < 10) {
            return true;
        }

        $lower = strtolower($message);

        foreach (['merge', 'fix.', 'wip', 'update env', 'init'] as $skip) {
            if (str_starts_with($lower, $skip)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return SkillEntry[]
     */
    private function buildSkills(NormalizedDataset $dataset): array
    {
        $skills = [];

        // Languages: weight is relative to the top language (top = 100)
        $maxLangCount = max(1, max(array_values($dataset->languages) ?: [1]));
        foreach ($dataset->languages as $lang => $count) {
            $weight = (int) round(($count / $maxLangCount) * 100);
            $skills[] = new SkillEntry(name: $lang, category: 'language', weight: max(1, $weight));
        }

        // Frameworks and tools: fixed weight of 60 (they're qualitative, not quantitative)
        foreach ($dataset->frameworks as $fw) {
            $skills[] = new SkillEntry(name: $fw, category: 'framework', weight: 60);
        }

        foreach ($dataset->tools as $tool) {
            $skills[] = new SkillEntry(name: $tool, category: 'tool', weight: 60);
        }

        return $skills;
    }

    /**
     * @return TimelineEntry[]
     */
    private function buildTimeline(NormalizedDataset $dataset): array
    {
        // Collect all commits across all projects with project metadata
        $allEntries = [];

        foreach ($dataset->projects as $project) {
            foreach ($project->commits as $commit) {
                $allEntries[] = [
                    'commit' => $commit,
                    'project' => $project,
                ];
            }
        }

        // Sort newest first
        usort($allEntries, function (array $a, array $b): int {
            /** @var CommitRecord $ca */
            $ca = $a['commit'];
            /** @var CommitRecord $cb */
            $cb = $b['commit'];

            return $cb->date <=> $ca->date;
        });

        // Take the most recent entries and map to TimelineEntry
        $topEntries = array_slice($allEntries, 0, self::MAX_TIMELINE_ENTRIES);

        return array_map(function (array $entry): TimelineEntry {
            /** @var CommitRecord $commit */
            $commit = $entry['commit'];
            /** @var ProjectSnapshot $project */
            $project = $entry['project'];

            return new TimelineEntry(
                date: $commit->date->format(\DateTimeInterface::ATOM),
                projectName: $project->name,
                commitMessage: $commit->message,
                shortHash: $commit->shortHash,
                projectUrl: $project->url,
            );
        }, $topEntries);
    }
}
