<?php

declare(strict_types=1);

namespace HistoryIngestion\Renderer;

/**
 * Generates CSS stylesheet from a site-spec theme definition.
 *
 * Supports three style modes (minimal, professional, vibrant) with
 * corresponding visual density and animation levels.
 */
final readonly class CssBuilder
{
    /**
     * Build the complete CSS stylesheet from theme data.
     *
     * @param  array{colors: array{primary: string, secondary: string, accent: string, background: string, text: string}, typography: array{heading_font: string, body_font: string}, style: string}  $theme
     */
    public function build(array $theme): string
    {
        $colors = $theme['colors'];
        $typography = $theme['typography'];
        $style = $theme['style'] ?? 'minimal';

        return implode("\n", [
            $this->reset(),
            $this->variables($colors, $typography),
            $this->baseStyles(),
            $this->navigationStyles($style),
            $this->heroStyles($style),
            $this->projectStyles($style),
            $this->skillStyles(),
            $this->timelineStyles($style),
            $this->contactStyles(),
            $this->responsiveStyles(),
        ]);
    }

    private function reset(): string
    {
        return <<<'CSS'
/* Reset */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
CSS;
    }

    private function variables(array $colors, array $typography): string
    {
        return <<<CSS
/* Variables */
:root {
  --color-primary: {$colors['primary']};
  --color-secondary: {$colors['secondary']};
  --color-accent: {$colors['accent']};
  --color-bg: {$colors['background']};
  --color-text: {$colors['text']};
  --color-muted: #6B7280;
  --color-border: #E5E7EB;
  --color-surface: #F9FAFB;
  --font-heading: {$typography['heading_font']};
  --font-body: {$typography['body_font']};
  --max-width: 1100px;
  --section-padding: 5rem 1.5rem;
}
CSS;
    }

    private function baseStyles(): string
    {
        return <<<'CSS'
/* Base */
body {
  font-family: var(--font-body);
  color: var(--color-text);
  background: var(--color-bg);
  line-height: 1.7;
  -webkit-font-smoothing: antialiased;
}
a { color: var(--color-primary); text-decoration: none; }
a:hover { color: var(--color-secondary); }
h1, h2, h3 { font-family: var(--font-heading); line-height: 1.2; }
.container { max-width: var(--max-width); margin: 0 auto; }
section { padding: var(--section-padding); }
.section-title {
  font-size: 1.75rem;
  font-weight: 700;
  margin-bottom: 2.5rem;
  color: var(--color-text);
}
.section-title::after {
  content: '';
  display: block;
  width: 3rem;
  height: 3px;
  background: var(--color-primary);
  margin-top: 0.5rem;
  border-radius: 2px;
}
CSS;
    }

    private function navigationStyles(string $style): string
    {
        $shadow = $style === 'vibrant' ? '0 2px 12px rgba(0,0,0,0.1)' : '0 1px 3px rgba(0,0,0,0.06)';

        return <<<CSS
/* Navigation */
nav {
  position: sticky;
  top: 0;
  background: var(--color-bg);
  border-bottom: 1px solid var(--color-border);
  box-shadow: {$shadow};
  z-index: 100;
  padding: 0.75rem 1.5rem;
}
nav .container {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
nav .brand {
  font-family: var(--font-heading);
  font-weight: 700;
  font-size: 1.1rem;
  color: var(--color-text);
}
nav ul { list-style: none; display: flex; gap: 1.5rem; }
nav a {
  color: var(--color-muted);
  font-size: 0.875rem;
  font-weight: 500;
  transition: color 0.2s;
}
nav a:hover { color: var(--color-primary); }
CSS;
    }

    private function heroStyles(string $style): string
    {
        $paddingTop = match ($style) {
            'vibrant' => '8rem',
            'professional' => '7rem',
            default => '6rem',
        };
        $paddingBottom = match ($style) {
            'vibrant' => '6rem',
            default => '4rem',
        };

        return <<<CSS
/* Hero */
.hero {
  padding: {$paddingTop} 1.5rem {$paddingBottom};
  text-align: center;
}
.hero h1 {
  font-size: 2.75rem;
  font-weight: 800;
  margin-bottom: 1rem;
  letter-spacing: -0.02em;
}
.hero .subtitle {
  font-size: 1.25rem;
  color: var(--color-secondary);
  margin-bottom: 1.5rem;
  font-weight: 500;
}
.hero .bio {
  max-width: 640px;
  margin: 0 auto 2rem;
  color: var(--color-muted);
  font-size: 1.05rem;
  line-height: 1.8;
}
.hero .cta-links {
  display: flex;
  gap: 1rem;
  justify-content: center;
  flex-wrap: wrap;
}
.hero .cta-links a {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.65rem 1.5rem;
  border-radius: 6px;
  font-weight: 600;
  font-size: 0.9rem;
  transition: background 0.2s, color 0.2s;
}
.cta-primary {
  background: var(--color-primary);
  color: #fff !important;
}
.cta-primary:hover { background: var(--color-secondary); }
.cta-secondary {
  border: 1.5px solid var(--color-border);
  color: var(--color-text) !important;
}
.cta-secondary:hover {
  border-color: var(--color-primary);
  color: var(--color-primary) !important;
}
CSS;
    }

    private function projectStyles(string $style): string
    {
        $hoverTransform = $style === 'minimal' ? 'none' : 'translateY(-2px)';

        return <<<CSS
/* Projects */
.projects { background: var(--color-surface); }
.projects-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 1.5rem;
}
.project-card {
  background: var(--color-bg);
  border: 1px solid var(--color-border);
  border-radius: 10px;
  padding: 1.5rem;
  transition: transform 0.2s, box-shadow 0.2s;
}
.project-card:hover {
  transform: {$hoverTransform};
  box-shadow: 0 4px 16px rgba(0,0,0,0.06);
}
.project-card h3 {
  font-size: 1.15rem;
  margin-bottom: 0.5rem;
}
.project-card h3 a { color: var(--color-text); }
.project-card h3 a:hover { color: var(--color-primary); }
.project-card .description {
  color: var(--color-muted);
  font-size: 0.9rem;
  margin-bottom: 0.75rem;
}
.project-card .tech-stack {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-bottom: 0.75rem;
}
.tech-tag {
  background: var(--color-surface);
  color: var(--color-primary);
  padding: 0.2rem 0.6rem;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 600;
}
.project-card .highlights {
  list-style: none;
  padding: 0;
  border-top: 1px solid var(--color-border);
  padding-top: 0.75rem;
  margin-top: 0.5rem;
}
.project-card .highlights li {
  font-size: 0.8rem;
  color: var(--color-muted);
  padding: 0.2rem 0;
  padding-left: 1rem;
  position: relative;
}
.project-card .highlights li::before {
  content: '›';
  position: absolute;
  left: 0;
  color: var(--color-accent);
  font-weight: 700;
}
.project-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 0.75rem;
  font-size: 0.8rem;
  color: var(--color-muted);
}
CSS;
    }

    private function skillStyles(): string
    {
        return <<<'CSS'
/* Skills */
.skills-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 2rem;
}
.skill-category h3 {
  font-size: 1rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--color-muted);
  margin-bottom: 1rem;
  font-weight: 600;
}
.skill-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}
.skill-item .name {
  min-width: 100px;
  font-size: 0.9rem;
  font-weight: 500;
}
.skill-bar {
  flex: 1;
  height: 6px;
  background: var(--color-border);
  border-radius: 3px;
  overflow: hidden;
}
.skill-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--color-primary), var(--color-accent));
  border-radius: 3px;
  transition: width 0.6s ease;
}
CSS;
    }

    private function timelineStyles(string $style): string
    {
        $lineColor = $style === 'vibrant' ? 'var(--color-primary)' : 'var(--color-border)';

        return <<<CSS
/* Timeline */
.timeline { background: var(--color-surface); }
.timeline-list {
  position: relative;
  padding-left: 2rem;
}
.timeline-list::before {
  content: '';
  position: absolute;
  left: 0.5rem;
  top: 0;
  bottom: 0;
  width: 2px;
  background: {$lineColor};
}
.timeline-item {
  position: relative;
  margin-bottom: 1.5rem;
  padding-bottom: 0.5rem;
}
.timeline-item::before {
  content: '';
  position: absolute;
  left: -1.75rem;
  top: 0.5rem;
  width: 10px;
  height: 10px;
  background: var(--color-primary);
  border-radius: 50%;
  border: 2px solid var(--color-bg);
}
.timeline-item .date {
  font-size: 0.8rem;
  color: var(--color-muted);
  margin-bottom: 0.25rem;
}
.timeline-item .commit-msg {
  font-size: 0.95rem;
  font-weight: 500;
}
.timeline-item .project-ref {
  font-size: 0.8rem;
  color: var(--color-muted);
  margin-top: 0.15rem;
}
.timeline-item .project-ref a { color: var(--color-accent); }
.timeline-item .hash {
  font-family: monospace;
  font-size: 0.75rem;
  color: var(--color-accent);
}
CSS;
    }

    private function contactStyles(): string
    {
        return <<<'CSS'
/* Contact */
.contact { text-align: center; }
.contact p { color: var(--color-muted); margin-bottom: 1.5rem; font-size: 1.05rem; }
.contact-links {
  display: flex;
  gap: 1rem;
  justify-content: center;
  flex-wrap: wrap;
}
.contact-links a {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.6rem 1.25rem;
  border: 1.5px solid var(--color-border);
  border-radius: 6px;
  font-weight: 500;
  font-size: 0.9rem;
  color: var(--color-text);
  transition: border-color 0.2s, color 0.2s;
}
.contact-links a:hover {
  border-color: var(--color-primary);
  color: var(--color-primary);
}
CSS;
    }

    private function responsiveStyles(): string
    {
        return <<<'CSS'
/* Responsive */
@media (max-width: 768px) {
  .hero h1 { font-size: 2rem; }
  .hero .subtitle { font-size: 1.05rem; }
  .projects-grid { grid-template-columns: 1fr; }
  .skills-grid { grid-template-columns: 1fr; }
  nav ul { gap: 0.75rem; }
  nav a { font-size: 0.8rem; }
  section { padding: 3rem 1rem; }
}
@media (max-width: 480px) {
  .hero h1 { font-size: 1.65rem; }
  nav .container { flex-direction: column; gap: 0.5rem; }
  .hero .cta-links { flex-direction: column; align-items: center; }
}
CSS;
    }
}
