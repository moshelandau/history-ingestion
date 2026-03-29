<?php

declare(strict_types=1);

namespace HistoryIngestion\Renderer;

/**
 * Generates a complete HTML document from a site-spec.
 *
 * Reads the structure, content, and theme arrays from the spec JSON and
 * produces a self-contained HTML page with inline CSS (via CssBuilder).
 */
final readonly class HtmlBuilder
{
    public function __construct(
        private CssBuilder $cssBuilder,
    ) {}

    /**
     * Build the complete HTML page from a decoded site-spec array.
     */
    public function build(array $spec): string
    {
        $css = $this->cssBuilder->build($spec['theme']);
        $developer = $spec['developer'];
        $structure = $spec['structure'];
        $content = $spec['content'];
        $theme = $spec['theme'];

        $sections = $structure['pages'][0]['sections'] ?? [];
        $navStyle = $structure['navigation_style'] ?? 'minimal';

        $html = "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n";
        $html .= $this->head($developer, $theme, $css);
        $html .= "</head>\n<body>\n";

        if ($navStyle === 'top-bar') {
            $html .= $this->navigation($developer, $sections);
        }

        foreach ($sections as $section) {
            $html .= match ($section) {
                'hero' => $this->heroSection($content),
                'projects' => $this->projectsSection($content),
                'skills' => $this->skillsSection($content),
                'timeline' => $this->timelineSection($content),
                'contact' => $this->contactSection($content),
                default => '',
            };
        }

        $html .= $this->footer($developer);
        $html .= "</body>\n</html>\n";

        return $html;
    }

    private function head(array $developer, array $theme, string $css): string
    {
        $name = $this->escape($developer['name']);
        $fonts = $this->googleFontsUrl($theme['typography']);

        $html = "  <meta charset=\"UTF-8\">\n";
        $html .= "  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
        $html .= "  <title>{$name} — Developer Portfolio</title>\n";
        $html .= "  <meta name=\"description\" content=\"Portfolio site for {$name}\">\n";

        if ($fonts !== null) {
            $html .= "  <link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">\n";
            $html .= "  <link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>\n";
            $html .= "  <link rel=\"stylesheet\" href=\"{$fonts}\">\n";
        }

        $html .= "  <style>\n{$css}\n  </style>\n";

        return $html;
    }

    private function navigation(array $developer, array $sections): string
    {
        $name = $this->escape($developer['name']);

        $links = '';
        foreach ($sections as $section) {
            if ($section === 'hero') {
                continue;
            }
            $label = ucfirst($section);
            $links .= "      <li><a href=\"#{$section}\">{$label}</a></li>\n";
        }

        return <<<HTML
<nav>
  <div class="container">
    <span class="brand">{$name}</span>
    <ul>
{$links}    </ul>
  </div>
</nav>
HTML;
    }

    private function heroSection(array $content): string
    {
        $hero = $content['hero'];
        $cta = $content['cta'];

        $headline = $this->escape($hero['headline']);
        $subtitle = $this->escape($hero['subtitle']);
        $bio = $this->escape($hero['bio']);

        $ctaHtml = '';
        if (! empty($cta['github'])) {
            $github = $this->escape($cta['github']);
            $ctaHtml .= "    <a href=\"{$github}\" class=\"cta-primary\" target=\"_blank\" rel=\"noopener\">GitHub Profile</a>\n";
        }
        if (! empty($cta['email'])) {
            $email = $this->escape($cta['email']);
            $ctaHtml .= "    <a href=\"mailto:{$email}\" class=\"cta-secondary\">Email Me</a>\n";
        }

        return <<<HTML
<section id="hero" class="hero">
  <div class="container">
    <h1>{$headline}</h1>
    <p class="subtitle">{$subtitle}</p>
    <p class="bio">{$bio}</p>
    <div class="cta-links">
{$ctaHtml}    </div>
  </div>
</section>
HTML;
    }

    private function projectsSection(array $content): string
    {
        $projects = $content['projects'] ?? [];

        $cards = '';
        foreach ($projects as $project) {
            $cards .= $this->projectCard($project);
        }

        return <<<HTML
<section id="projects" class="projects">
  <div class="container">
    <h2 class="section-title">Projects</h2>
    <div class="projects-grid">
{$cards}    </div>
  </div>
</section>
HTML;
    }

    private function projectCard(array $project): string
    {
        $name = $this->escape($project['name']);
        $description = $this->escape($project['description']);
        $url = $this->escape($project['url']);
        $commitCount = $project['commit_count'];

        $techTags = '';
        foreach ($project['tech_stack'] ?? [] as $tech) {
            $techTags .= "        <span class=\"tech-tag\">{$this->escape($tech)}</span>\n";
        }

        $highlightsHtml = '';
        foreach ($project['highlights'] ?? [] as $highlight) {
            $highlightsHtml .= "        <li>{$this->escape($highlight)}</li>\n";
        }

        $activityRange = '';
        if (! empty($project['activity_from']) && ! empty($project['activity_to'])) {
            $from = $this->formatDate($project['activity_from']);
            $to = $this->formatDate($project['activity_to']);
            $activityRange = "<span>{$from} – {$to}</span>";
        }

        return <<<HTML
      <div class="project-card">
        <h3><a href="{$url}" target="_blank" rel="noopener">{$name}</a></h3>
        <p class="description">{$description}</p>
        <div class="tech-stack">
{$techTags}        </div>
        <ul class="highlights">
{$highlightsHtml}        </ul>
        <div class="project-meta">
          <span>{$commitCount} commits</span>
          {$activityRange}
        </div>
      </div>
HTML;
    }

    private function skillsSection(array $content): string
    {
        $skills = $content['skills'] ?? [];

        $categories = ['language' => [], 'framework' => [], 'tool' => []];
        foreach ($skills as $skill) {
            $cat = $skill['category'] ?? 'tool';
            $categories[$cat][] = $skill;
        }

        $groups = '';
        foreach ($categories as $category => $items) {
            if (empty($items)) {
                continue;
            }
            $label = match ($category) {
                'language' => 'Languages',
                'framework' => 'Frameworks',
                'tool' => 'Tools & Services',
                default => ucfirst($category),
            };

            $itemsHtml = '';
            foreach ($items as $skill) {
                $name = $this->escape($skill['name']);
                $weight = max(1, min(100, $skill['weight']));
                $itemsHtml .= <<<HTML
          <div class="skill-item">
            <span class="name">{$name}</span>
            <div class="skill-bar"><div class="skill-bar-fill" style="width:{$weight}%"></div></div>
          </div>
HTML;
            }

            $groups .= <<<HTML
        <div class="skill-category">
          <h3>{$label}</h3>
{$itemsHtml}
        </div>
HTML;
        }

        return <<<HTML
<section id="skills" class="skills">
  <div class="container">
    <h2 class="section-title">Skills</h2>
    <div class="skills-grid">
{$groups}    </div>
  </div>
</section>
HTML;
    }

    private function timelineSection(array $content): string
    {
        $timeline = $content['timeline'] ?? [];

        $items = '';
        foreach ($timeline as $entry) {
            $date = $this->formatDate($entry['date']);
            $message = $this->escape($entry['commit_message']);
            $hash = $this->escape($entry['short_hash']);
            $projectName = $this->escape($entry['project_name']);
            $projectUrl = $this->escape($entry['project_url']);

            $items .= <<<HTML
      <div class="timeline-item">
        <div class="date">{$date}</div>
        <div class="commit-msg">{$message} <span class="hash">{$hash}</span></div>
        <div class="project-ref"><a href="{$projectUrl}" target="_blank" rel="noopener">{$projectName}</a></div>
      </div>
HTML;
        }

        return <<<HTML
<section id="timeline" class="timeline">
  <div class="container">
    <h2 class="section-title">Recent Activity</h2>
    <div class="timeline-list">
{$items}    </div>
  </div>
</section>
HTML;
    }

    private function contactSection(array $content): string
    {
        $cta = $content['cta'];

        $links = '';
        if (! empty($cta['github'])) {
            $github = $this->escape($cta['github']);
            $links .= "      <a href=\"{$github}\" target=\"_blank\" rel=\"noopener\">GitHub</a>\n";
        }
        if (! empty($cta['email'])) {
            $email = $this->escape($cta['email']);
            $links .= "      <a href=\"mailto:{$email}\">Email</a>\n";
        }

        return <<<HTML
<section id="contact" class="contact">
  <div class="container">
    <h2 class="section-title">Get in Touch</h2>
    <p>Interested in working together? Reach out via any of the channels below.</p>
    <div class="contact-links">
{$links}    </div>
  </div>
</section>
HTML;
    }

    private function footer(array $developer): string
    {
        $name = $this->escape($developer['name']);
        $year = date('Y');

        return <<<HTML
<footer style="text-align:center; padding:2rem 1rem; color:var(--color-muted); font-size:0.8rem; border-top:1px solid var(--color-border);">
  <p>&copy; {$year} {$name}. Generated from git history.</p>
</footer>
HTML;
    }

    /**
     * Build a Google Fonts CSS URL from the typography config.
     *
     * Returns null if the font names don't appear to be Google Fonts families.
     */
    private function googleFontsUrl(array $typography): ?string
    {
        $families = [];

        foreach ([$typography['heading_font'], $typography['body_font']] as $fontStack) {
            // Extract the first font-family name (e.g. "'DM Sans', sans-serif" → "DM Sans")
            if (preg_match("/^'([^']+)'/", $fontStack, $m)) {
                $families[$m[1]] = true;
            }
        }

        if (empty($families)) {
            return null;
        }

        $params = array_map(
            fn (string $family) => 'family='.str_replace(' ', '+', $family).':wght@400;500;600;700;800',
            array_keys($families),
        );

        return 'https://fonts.googleapis.com/css2?'.implode('&', $params).'&display=swap';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function formatDate(string $isoDate): string
    {
        $dt = new \DateTimeImmutable($isoDate);

        return $dt->format('M j, Y');
    }
}
