<?php

declare(strict_types=1);

namespace HistoryIngestion\Tests\Unit;

use HistoryIngestion\Renderer\CssBuilder;
use HistoryIngestion\Renderer\HtmlBuilder;
use PHPUnit\Framework\TestCase;

final class HtmlBuilderTest extends TestCase
{
    private HtmlBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new HtmlBuilder(new CssBuilder);
    }

    public function test_build_returns_valid_html_document(): void
    {
        $html = $this->builder->build($this->makeSpec());

        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<html lang="en">', $html);
        $this->assertStringContainsString('</html>', $html);
    }

    public function test_head_contains_developer_name_in_title(): void
    {
        $html = $this->builder->build($this->makeSpec());

        $this->assertStringContainsString('<title>Jane Doe — Developer Portfolio</title>', $html);
    }

    public function test_head_includes_google_fonts(): void
    {
        $html = $this->builder->build($this->makeSpec());

        $this->assertStringContainsString('fonts.googleapis.com', $html);
        $this->assertStringContainsString('DM+Sans', $html);
    }

    public function test_hero_section_renders_content(): void
    {
        $html = $this->builder->build($this->makeSpec());

        $this->assertStringContainsString('Jane Doe — Full-Stack Developer', $html);
        $this->assertStringContainsString('Building with Laravel', $html);
        $this->assertStringContainsString('class="bio"', $html);
    }

    public function test_top_bar_navigation_rendered_when_specified(): void
    {
        $spec = $this->makeSpec(navStyle: 'top-bar');
        $html = $this->builder->build($spec);

        $this->assertStringContainsString('<nav>', $html);
        $this->assertStringContainsString('class="brand"', $html);
        $this->assertStringContainsString('href="#projects"', $html);
    }

    public function test_minimal_navigation_omits_nav_element(): void
    {
        $spec = $this->makeSpec(navStyle: 'minimal');
        $html = $this->builder->build($spec);

        $this->assertStringNotContainsString('<nav>', $html);
    }

    public function test_project_cards_render_with_tech_tags(): void
    {
        $html = $this->builder->build($this->makeSpec());

        $this->assertStringContainsString('class="project-card"', $html);
        $this->assertStringContainsString('Test Project', $html);
        $this->assertStringContainsString('class="tech-tag"', $html);
        $this->assertStringContainsString('Laravel', $html);
    }

    public function test_skills_section_groups_by_category(): void
    {
        $html = $this->builder->build($this->makeSpec());

        $this->assertStringContainsString('Languages', $html);
        $this->assertStringContainsString('Frameworks', $html);
        $this->assertStringContainsString('class="skill-bar-fill"', $html);
    }

    public function test_timeline_renders_commit_entries(): void
    {
        $html = $this->builder->build($this->makeSpec());

        $this->assertStringContainsString('class="timeline-item"', $html);
        $this->assertStringContainsString('Fix important bug', $html);
        $this->assertStringContainsString('abc1234', $html);
    }

    public function test_contact_section_renders_github_link(): void
    {
        $html = $this->builder->build($this->makeSpec());

        $this->assertStringContainsString('id="contact"', $html);
        $this->assertStringContainsString('https://github.com/janedoe', $html);
    }

    public function test_contact_section_renders_email_when_provided(): void
    {
        $spec = $this->makeSpec();
        $spec['content']['cta']['email'] = 'jane@example.com';
        $html = $this->builder->build($spec);

        $this->assertStringContainsString('mailto:jane@example.com', $html);
    }

    public function test_footer_contains_developer_name(): void
    {
        $html = $this->builder->build($this->makeSpec());

        $this->assertStringContainsString('Jane Doe', $html);
        $this->assertStringContainsString('<footer', $html);
    }

    public function test_html_escapes_special_characters(): void
    {
        $spec = $this->makeSpec();
        $spec['content']['hero']['headline'] = 'A & B <script>';
        $html = $this->builder->build($spec);

        $this->assertStringContainsString('A &amp; B &lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_timeline_section_excluded_when_not_in_structure(): void
    {
        $spec = $this->makeSpec();
        $spec['structure']['pages'][0]['sections'] = ['hero', 'projects', 'skills', 'contact'];
        $html = $this->builder->build($spec);

        $this->assertStringNotContainsString('class="timeline', $html);
    }

    // -------------------------------------------------------------------------

    private function makeSpec(string $navStyle = 'top-bar'): array
    {
        return [
            'schema_version' => '1.0.0',
            'generated_at' => '2026-03-29T00:00:00+00:00',
            'source_dataset_version' => '1.0.0',
            'developer' => [
                'name' => 'Jane Doe',
                'github' => 'janedoe',
                'email' => null,
            ],
            'structure' => [
                'layout_type' => 'single-page',
                'navigation_style' => $navStyle,
                'pages' => [
                    [
                        'key' => 'home',
                        'title' => 'Home',
                        'slug' => '/',
                        'sections' => ['hero', 'projects', 'skills', 'timeline', 'contact'],
                    ],
                ],
            ],
            'content' => [
                'hero' => [
                    'headline' => 'Jane Doe — Full-Stack Developer',
                    'subtitle' => 'Building with Laravel',
                    'bio' => 'A developer who ships.',
                ],
                'cta' => [
                    'email' => null,
                    'github' => 'https://github.com/janedoe',
                ],
                'projects' => [
                    [
                        'name' => 'Test Project',
                        'description' => 'A test project',
                        'url' => 'https://github.com/janedoe/test',
                        'tech_stack' => ['Laravel', 'Vue 3'],
                        'highlights' => ['Built feature X', 'Fixed bug Y'],
                        'commit_count' => 42,
                        'activity_from' => '2026-01-01T00:00:00+00:00',
                        'activity_to' => '2026-03-01T00:00:00+00:00',
                    ],
                ],
                'skills' => [
                    ['name' => 'PHP', 'category' => 'language', 'weight' => 100],
                    ['name' => 'Vue', 'category' => 'language', 'weight' => 40],
                    ['name' => 'Laravel', 'category' => 'framework', 'weight' => 60],
                ],
                'timeline' => [
                    [
                        'date' => '2026-03-01T12:00:00+00:00',
                        'project_name' => 'Test Project',
                        'commit_message' => 'Fix important bug',
                        'short_hash' => 'abc1234',
                        'project_url' => 'https://github.com/janedoe/test',
                    ],
                ],
            ],
            'theme' => [
                'colors' => [
                    'primary' => '#4F46E5',
                    'secondary' => '#6366F1',
                    'accent' => '#A78BFA',
                    'background' => '#FFFFFF',
                    'text' => '#111827',
                ],
                'typography' => [
                    'heading_font' => "'DM Sans', sans-serif",
                    'body_font' => "'DM Sans', sans-serif",
                ],
                'style' => 'minimal',
                'theme_source' => 'PHP',
            ],
        ];
    }
}
