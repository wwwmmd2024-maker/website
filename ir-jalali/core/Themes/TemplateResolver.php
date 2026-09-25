<?php

declare(strict_types=1);

namespace IRJalali\Core\Themes;

/**
 * Template hierarchy (theme file wins, core views are the final fallback):
 *
 * page:   templates/page-{slug}.php → page.php → singular.php → index.php
 * single: templates/single-{slug}.php → single.php → singular.php → index.php
 * archive: templates/archive.php → index.php
 * 404:    templates/404.php (else core 404)
 */
final class TemplateResolver
{
    public function __construct(private readonly ThemeManager $themes)
    {
    }

    /** @return list<string> candidate files, first hit wins */
    public function candidates(string $kind, string $slug = ''): array
    {
        $slug = preg_replace('/[^a-z0-9_\-]/i', '', $slug) ?? '';

        return match ($kind) {
            'page' => array_filter([
                $slug !== '' ? "templates/page-{$slug}.php" : null,
                'templates/page.php', 'templates/singular.php', 'templates/index.php',
            ]),
            'single' => array_filter([
                $slug !== '' ? "templates/single-{$slug}.php" : null,
                'templates/single.php', 'templates/singular.php', 'templates/index.php',
            ]),
            'archive' => ['templates/archive.php', 'templates/index.php'],
            '404' => ['templates/404.php'],
            'search' => ['templates/search.php', 'templates/archive.php', 'templates/index.php'],
            default => ['templates/index.php'],
        };
    }

    public function resolve(string $themeSlug, string $kind, string $slug = ''): ?string
    {
        foreach ($this->candidates($kind, $slug) as $candidate) {
            $file = $this->themes->file($themeSlug, $candidate);
            if ($file !== null) {
                return $file;
            }
        }

        return null;
    }
}
