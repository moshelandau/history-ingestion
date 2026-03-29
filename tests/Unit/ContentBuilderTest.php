<?php

declare(strict_types=1);

namespace HistoryIngestion\Tests\Unit;

use HistoryIngestion\Generator\ContentBuilder;
use HistoryIngestion\Schema\CommitRecord;
use HistoryIngestion\Schema\DeveloperProfile;
use HistoryIngestion\Schema\NormalizedDataset;
use HistoryIngestion\Schema\ProjectSnapshot;
use PHPUnit\Framework\TestCase;

final class ContentBuilderTest extends TestCase
{
    private ContentBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ContentBuilder;
    }

    public function test_hero_headline_contains_developer_name(): void
    {
        $dataset = $this->makeDataset(developerName: 'Alice Smith');

        $content = $this->builder->build($dataset);

        $this->assertStringContainsString('Alice Smith', $content->heroHeadline);
    }

    public function test_hero_subtitle_includes_top_frameworks(): void
    {
        $dataset = $this->makeDataset(frameworks: ['Laravel', 'Vue 3', 'Next.js']);

        $content = $this->builder->build($dataset);

        $this->assertStringContainsString('Laravel', $content->heroSubtitle);
        $this->assertStringContainsString('Vue 3', $content->heroSubtitle);
    }

    public function test_hero_subtitle_default_when_no_frameworks(): void
    {
        $dataset = $this->makeDataset(frameworks: []);

        $content = $this->builder->build($dataset);

        $this->assertNotEmpty($content->heroSubtitle);
    }

    public function test_hero_bio_contains_developer_name_and_stats(): void
    {
        $dataset = $this->makeDataset(developerName: 'Bob Dev', totalCommits: 42, activeRepos: 3);

        $content = $this->builder->build($dataset);

        $this->assertStringContainsString('Bob Dev', $content->heroBio);
        $this->assertStringContainsString('42', $content->heroBio);
    }

    public function test_cta_github_url_is_correct(): void
    {
        $dataset = $this->makeDataset(githubHandle: 'myhandle');

        $content = $this->builder->build($dataset);

        $this->assertSame('https://github.com/myhandle', $content->ctaGithub);
    }

    public function test_cta_email_propagated_from_profile(): void
    {
        $dataset = $this->makeDataset(email: 'dev@example.com');

        $content = $this->builder->build($dataset);

        $this->assertSame('dev@example.com', $content->ctaEmail);
    }

    public function test_cta_email_null_when_not_configured(): void
    {
        $dataset = $this->makeDataset(email: null);

        $content = $this->builder->build($dataset);

        $this->assertNull($content->ctaEmail);
    }

    public function test_project_cards_built_from_snapshots(): void
    {
        $snapshots = [
            $this->makeSnapshot(name: 'Alpha', totalCommits: 10),
            $this->makeSnapshot(name: 'Beta', totalCommits: 5),
        ];
        $dataset = $this->makeDataset(projects: $snapshots);

        $content = $this->builder->build($dataset);

        $this->assertCount(2, $content->projectCards);
    }

    public function test_project_cards_sorted_by_git_availability_then_commits(): void
    {
        $snapshots = [
            $this->makeSnapshot(name: 'NoGit', totalCommits: 100, gitAvailable: false),
            $this->makeSnapshot(name: 'LowGit', totalCommits: 5, gitAvailable: true),
            $this->makeSnapshot(name: 'HighGit', totalCommits: 50, gitAvailable: true),
        ];
        $dataset = $this->makeDataset(projects: $snapshots);

        $content = $this->builder->build($dataset);
        $names = array_map(fn ($c) => $c->name, $content->projectCards);

        // git-available first (HighGit > LowGit), then no-git
        $this->assertSame(['HighGit', 'LowGit', 'NoGit'], $names);
    }

    public function test_skills_include_languages_frameworks_and_tools(): void
    {
        $dataset = $this->makeDataset(
            languages: ['PHP' => 1000, 'Vue' => 500],
            frameworks: ['Laravel'],
            tools: ['PostgreSQL'],
        );

        $content = $this->builder->build($dataset);
        $names = array_map(fn ($s) => $s->name, $content->skills);

        $this->assertContains('PHP', $names);
        $this->assertContains('Vue', $names);
        $this->assertContains('Laravel', $names);
        $this->assertContains('PostgreSQL', $names);
    }

    public function test_language_skill_weight_is_relative_to_top_language(): void
    {
        $dataset = $this->makeDataset(languages: ['PHP' => 1000, 'Vue' => 500]);

        $content = $this->builder->build($dataset);
        $byName = [];
        foreach ($content->skills as $s) {
            $byName[$s->name] = $s;
        }

        $this->assertSame(100, $byName['PHP']->weight);
        $this->assertSame(50, $byName['Vue']->weight);
    }

    public function test_timeline_limited_to_max_entries(): void
    {
        // Create a project with 20 commits
        $commits = [];
        for ($i = 0; $i < 20; $i++) {
            $commits[] = $this->makeCommit("commit {$i}", new \DateTimeImmutable("2024-01-{$i}"));
        }
        $snapshot = $this->makeSnapshot(commits: $commits);
        $dataset = $this->makeDataset(projects: [$snapshot]);

        $content = $this->builder->build($dataset);

        $this->assertLessThanOrEqual(15, count($content->timeline));
    }

    public function test_timeline_sorted_newest_first(): void
    {
        $old = $this->makeCommit('old commit', new \DateTimeImmutable('2023-01-01'));
        $new = $this->makeCommit('new commit', new \DateTimeImmutable('2024-06-01'));
        $snapshot = $this->makeSnapshot(commits: [$old, $new]);
        $dataset = $this->makeDataset(projects: [$snapshot]);

        $content = $this->builder->build($dataset);

        $this->assertSame('new commit', $content->timeline[0]->commitMessage);
    }

    public function test_boring_commits_excluded_from_highlights(): void
    {
        $commits = [
            $this->makeCommit('Merge branch main'),
            $this->makeCommit('Add full user authentication flow with OAuth'),
        ];
        $snapshot = $this->makeSnapshot(commits: $commits);
        $dataset = $this->makeDataset(projects: [$snapshot]);

        $content = $this->builder->build($dataset);
        $card = $content->projectCards[0];

        $this->assertNotContains('Merge branch main', $card->highlights);
        $this->assertContains('Add full user authentication flow with OAuth', $card->highlights);
    }

    public function test_to_array_contains_required_keys(): void
    {
        $dataset = $this->makeDataset();
        $array = $this->builder->build($dataset)->toArray();

        $this->assertArrayHasKey('hero', $array);
        $this->assertArrayHasKey('cta', $array);
        $this->assertArrayHasKey('projects', $array);
        $this->assertArrayHasKey('skills', $array);
        $this->assertArrayHasKey('timeline', $array);
    }

    // -------------------------------------------------------------------------

    private function makeDataset(
        string $developerName = 'Dev User',
        string $githubHandle = 'devuser',
        ?string $email = null,
        int $totalCommits = 10,
        int $activeRepos = 1,
        array $languages = ['PHP' => 100],
        array $frameworks = ['Laravel'],
        array $tools = [],
        array $projects = [],
    ): NormalizedDataset {
        return new NormalizedDataset(
            schemaVersion: '1.0.0',
            generatedAt: new \DateTimeImmutable,
            developer: new DeveloperProfile($developerName, $githubHandle, $email),
            projects: $projects,
            totalCommits: $totalCommits,
            activeRepositories: $activeRepos,
            languages: $languages,
            frameworks: $frameworks,
            tools: $tools,
            activityFrom: null,
            activityTo: null,
        );
    }

    private function makeSnapshot(
        string $name = 'TestProject',
        int $totalCommits = 0,
        bool $gitAvailable = true,
        array $commits = [],
    ): ProjectSnapshot {
        return new ProjectSnapshot(
            name: $name,
            repo: 'org/'.strtolower($name),
            description: "Description of {$name}",
            url: "https://github.com/org/{$name}",
            stack: ['PHP'],
            commits: $commits,
            totalCommits: $totalCommits,
            contributors: [],
            languageStats: [],
            firstCommitAt: null,
            lastCommitAt: null,
            gitAvailable: $gitAvailable,
        );
    }

    private function makeCommit(string $message, ?\DateTimeImmutable $date = null): CommitRecord
    {
        return new CommitRecord(
            hash: str_pad('a', 40, 'b'),
            shortHash: 'abc1234',
            message: $message,
            author: 'dev',
            authorEmail: 'dev@example.com',
            date: $date ?? new \DateTimeImmutable('2024-01-01'),
            filesChanged: 1,
            insertions: 10,
            deletions: 2,
        );
    }
}
