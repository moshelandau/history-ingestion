<?php

declare(strict_types=1);

namespace HistoryIngestion\Generator\Schema;

/**
 * The structural layout of the generated website: pages, navigation, layout type.
 */
final readonly class SiteStructure
{
    /**
     * @param  array<array{key:string,title:string,slug:string,sections:string[]}>  $pages
     */
    public function __construct(
        /**
         * Ordered list of pages.
         * Each page has: key, title, slug, sections (ordered list of section keys).
         *
         * @var array<array{key:string,title:string,slug:string,sections:string[]}>
         */
        public array $pages,
        /** Navigation style: top-bar | side-bar | minimal */
        public string $navigationStyle,
        /** Layout type: single-page | multi-page */
        public string $layoutType,
    ) {}

    public function toArray(): array
    {
        return [
            'layout_type' => $this->layoutType,
            'navigation_style' => $this->navigationStyle,
            'pages' => $this->pages,
        ];
    }
}
