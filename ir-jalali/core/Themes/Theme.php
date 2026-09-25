<?php

declare(strict_types=1);

namespace IRJalali\Core\Themes;

/**
 * Immutable theme value object hydrated from theme.json.
 */
final class Theme
{
    /** @param array<string, mixed> $manifest */
    private function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $version,
        public readonly string $author,
        public readonly string $description,
        public readonly ?string $parent,
        public readonly array $supports,
        public readonly array $requires,
        public readonly array $settings,
        public readonly array $templates,
        public readonly array $templateParts,
        public readonly string $path,
        public readonly array $manifest,
    ) {
    }

    /** @param array<string, mixed> $manifest */
    public static function fromManifest(string $slug, string $path, array $manifest): self
    {
        return new self(
            $slug,
            (string) ($manifest['name'] ?? $slug),
            (string) ($manifest['version'] ?? '1.0.0'),
            (string) ($manifest['author'] ?? ''),
            (string) ($manifest['description'] ?? ''),
            isset($manifest['parent']) && is_string($manifest['parent']) ? $manifest['parent'] : null,
            (array) ($manifest['supports'] ?? []),
            (array) ($manifest['requires'] ?? []),
            (array) ($manifest['settings'] ?? []),
            array_values((array) ($manifest['templates'] ?? [])),
            array_values((array) ($manifest['templateParts'] ?? [])),
            $path,
            $manifest,
        );
    }

    public function isChild(): bool
    {
        return $this->parent !== null && $this->parent !== '';
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, $this->supports, true);
    }

    public function screenshot(): ?string
    {
        foreach (['screenshot.png', 'screenshot.jpg'] as $file) {
            if (is_file($this->path . '/' . $file)) {
                return $file;
            }
        }

        return null;
    }
}
