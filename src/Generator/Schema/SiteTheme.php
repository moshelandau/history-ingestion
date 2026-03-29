<?php

declare(strict_types=1);

namespace HistoryIngestion\Generator\Schema;

/**
 * The visual theme for the generated website.
 *
 * Colors are CSS hex values. Style is one of: minimal, professional, vibrant.
 */
final readonly class SiteTheme
{
    public function __construct(
        /** Primary brand color (e.g. buttons, headings) */
        public string $primaryColor,
        /** Secondary color (e.g. hover states, accents) */
        public string $secondaryColor,
        /** Accent highlight color */
        public string $accentColor,
        /** Page background color */
        public string $backgroundColor,
        /** Main body text color */
        public string $textColor,
        /** CSS font-family for headings */
        public string $headingFont,
        /** CSS font-family for body text */
        public string $bodyFont,
        /** Overall visual style: minimal | professional | vibrant */
        public string $style,
        /** Which language/tech most influenced the theme choice */
        public string $themeSource,
    ) {}

    public function toArray(): array
    {
        return [
            'colors' => [
                'primary' => $this->primaryColor,
                'secondary' => $this->secondaryColor,
                'accent' => $this->accentColor,
                'background' => $this->backgroundColor,
                'text' => $this->textColor,
            ],
            'typography' => [
                'heading_font' => $this->headingFont,
                'body_font' => $this->bodyFont,
            ],
            'style' => $this->style,
            'theme_source' => $this->themeSource,
        ];
    }
}
