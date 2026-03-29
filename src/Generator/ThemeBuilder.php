<?php

declare(strict_types=1);

namespace HistoryIngestion\Generator;

use HistoryIngestion\Generator\Schema\SiteTheme;
use HistoryIngestion\Schema\NormalizedDataset;

/**
 * Derives a visual theme from the developer's normalized history.
 *
 * Strategy:
 *   - Top language by file count determines the base color palette.
 *   - Total commit volume determines the overall style intensity.
 *   - Typography follows the style choice.
 */
final class ThemeBuilder
{
    /**
     * Language → [primary, secondary, accent] hex color triples.
     *
     * @var array<string, array{string, string, string}>
     */
    private const LANGUAGE_PALETTES = [
        'PHP' => ['#4F46E5', '#6366F1', '#A78BFA'],   // indigo — mature, solid
        'TypeScript' => ['#0EA5E9', '#38BDF8', '#7DD3FC'],   // sky — modern, typed
        'JavaScript' => ['#F59E0B', '#FBBF24', '#FDE68A'],   // amber — energetic, dynamic
        'Vue' => ['#10B981', '#34D399', '#6EE7B7'],   // emerald — fresh, reactive
        'Python' => ['#3B82F6', '#60A5FA', '#93C5FD'],   // blue — analytical, broad
        'Go' => ['#06B6D4', '#22D3EE', '#67E8F9'],   // cyan — fast, concurrent
        'Rust' => ['#EF4444', '#F87171', '#FCA5A5'],   // red — systems, performance
        'default' => ['#6366F1', '#818CF8', '#A5B4FC'],   // violet — versatile fallback
    ];

    private const STYLE_THRESHOLDS = [
        'vibrant' => 300,
        'professional' => 100,
        // below 100 → minimal
    ];

    public function build(NormalizedDataset $dataset): SiteTheme
    {
        $topLanguage = $this->resolveTopLanguage($dataset->languages);
        [$primary, $secondary, $accent] = $this->resolvePalette($topLanguage);
        $style = $this->resolveStyle($dataset->totalCommits);
        [$headingFont, $bodyFont] = $this->resolveFonts($style);

        return new SiteTheme(
            primaryColor: $primary,
            secondaryColor: $secondary,
            accentColor: $accent,
            backgroundColor: '#FFFFFF',
            textColor: '#111827',
            headingFont: $headingFont,
            bodyFont: $bodyFont,
            style: $style,
            themeSource: $topLanguage,
        );
    }

    // -------------------------------------------------------------------------

    private function resolveTopLanguage(array $languages): string
    {
        if (empty($languages)) {
            return 'default';
        }

        // Languages are already sorted descending by file count from HistoryNormalizer
        $topKey = array_key_first($languages);

        return array_key_exists($topKey, self::LANGUAGE_PALETTES) ? $topKey : 'default';
    }

    /**
     * @return array{string, string, string}
     */
    private function resolvePalette(string $language): array
    {
        return self::LANGUAGE_PALETTES[$language] ?? self::LANGUAGE_PALETTES['default'];
    }

    private function resolveStyle(int $totalCommits): string
    {
        if ($totalCommits >= self::STYLE_THRESHOLDS['vibrant']) {
            return 'vibrant';
        }
        if ($totalCommits >= self::STYLE_THRESHOLDS['professional']) {
            return 'professional';
        }

        return 'minimal';
    }

    /**
     * @return array{string, string}
     */
    private function resolveFonts(string $style): array
    {
        return match ($style) {
            'vibrant' => ["'Inter', sans-serif", "'Inter', sans-serif"],
            'professional' => ["'Roboto', sans-serif", "'Roboto', sans-serif"],
            default => ["'DM Sans', sans-serif", "'DM Sans', sans-serif"],
        };
    }
}
