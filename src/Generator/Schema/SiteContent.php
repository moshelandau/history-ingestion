<?php

declare(strict_types=1);

namespace HistoryIngestion\Generator\Schema;

/**
 * All generated textual and structural content for the website.
 */
final readonly class SiteContent
{
    /**
     * @param  ProjectCard[]  $projectCards
     * @param  SkillEntry[]  $skills
     * @param  TimelineEntry[]  $timeline
     */
    public function __construct(
        /** Primary headline in the hero section, e.g. "Full-Stack Developer" */
        public string $heroHeadline,
        /** One-line subtitle below the headline */
        public string $heroSubtitle,
        /** Short bio paragraph generated from the developer's profile and skills */
        public string $heroBio,
        /** Ordered portfolio cards, most-active project first */
        public array $projectCards,
        /** Skill entries ordered: languages (by weight), then frameworks, then tools */
        public array $skills,
        /** Recent commit activity, newest first, max 15 entries */
        public array $timeline,
        /** Call-to-action: developer's email (null if not configured) */
        public ?string $ctaEmail,
        /** Call-to-action: developer's GitHub profile URL */
        public string $ctaGithub,
    ) {}

    public function toArray(): array
    {
        return [
            'hero' => [
                'headline' => $this->heroHeadline,
                'subtitle' => $this->heroSubtitle,
                'bio' => $this->heroBio,
            ],
            'cta' => [
                'email' => $this->ctaEmail,
                'github' => $this->ctaGithub,
            ],
            'projects' => array_map(fn (ProjectCard $c) => $c->toArray(), $this->projectCards),
            'skills' => array_map(fn (SkillEntry $s) => $s->toArray(), $this->skills),
            'timeline' => array_map(fn (TimelineEntry $t) => $t->toArray(), $this->timeline),
        ];
    }
}
