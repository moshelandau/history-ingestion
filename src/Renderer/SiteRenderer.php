<?php

declare(strict_types=1);

namespace HistoryIngestion\Renderer;

/**
 * Orchestrates the full rendering pipeline: reads a site-spec JSON file
 * and produces a self-contained HTML website in the output directory.
 */
final class SiteRenderer
{
    private HtmlBuilder $htmlBuilder;

    public function __construct(
        ?HtmlBuilder $htmlBuilder = null,
    ) {
        $this->htmlBuilder = $htmlBuilder ?? new HtmlBuilder(new CssBuilder);
    }

    /**
     * Factory for convenience.
     */
    public static function create(): self
    {
        return new self;
    }

    /**
     * Render a site-spec JSON file into an HTML file.
     *
     * @param  string  $inputPath  Path to site-spec.json
     * @param  string  $outputPath  Path to write the generated index.html
     * @return array{developer: string, style: string, sections: int, output: string}
     *
     * @throws \RuntimeException if the input file cannot be read or decoded
     */
    public function render(string $inputPath, string $outputPath): array
    {
        $spec = $this->loadSpec($inputPath);
        $html = $this->htmlBuilder->build($spec);

        $outputDir = dirname($outputPath);
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        file_put_contents($outputPath, $html);

        $sections = $spec['structure']['pages'][0]['sections'] ?? [];

        return [
            'developer' => $spec['developer']['name'].' (@'.$spec['developer']['github'].')',
            'style' => $spec['theme']['style'].' (source: '.$spec['theme']['theme_source'].')',
            'sections' => count($sections),
            'output' => realpath($outputPath) ?: $outputPath,
        ];
    }

    /**
     * Load and validate a site-spec JSON file.
     *
     * @throws \RuntimeException
     */
    private function loadSpec(string $path): array
    {
        if (! file_exists($path)) {
            throw new \RuntimeException("Site spec not found: {$path}");
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException("Failed to read site spec: {$path}");
        }

        $spec = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $required = ['developer', 'structure', 'content', 'theme'];
        foreach ($required as $key) {
            if (! isset($spec[$key])) {
                throw new \RuntimeException("Site spec missing required key: {$key}");
            }
        }

        return $spec;
    }
}
