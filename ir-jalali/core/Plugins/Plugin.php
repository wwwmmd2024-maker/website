<?php

declare(strict_types=1);

namespace IRJalali\Core\Plugins;

/**
 * Immutable plugin value object hydrated from plugin.json + DB status.
 */
final class Plugin
{
    /** @param array<string, mixed> $manifest @param list<string> $dependencies @param list<string> $capabilities */
    private function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $version,
        public readonly string $author,
        public readonly string $description,
        public readonly array $requires,
        public readonly array $dependencies,
        public readonly array $capabilities,
        public readonly string $api,
        public readonly string $path,
        public readonly string $status,
        public readonly array $manifest,
    ) {
    }

    /** @param array<string, mixed> $manifest */
    public static function fromManifest(string $slug, string $path, array $manifest, string $status = 'inactive'): self
    {
        $deps = [];
        foreach ((array) ($manifest['dependencies'] ?? []) as $dep => $constraint) {
            // Supports ["slug"] and {"slug": ">=1.0"} forms.
            if (is_int($dep)) {
                $deps[(string) $constraint] = '*';
            } else {
                $deps[(string) $dep] = (string) $constraint;
            }
        }

        return new self(
            $slug,
            (string) ($manifest['name'] ?? $slug),
            (string) ($manifest['version'] ?? '1.0.0'),
            (string) ($manifest['author'] ?? ''),
            (string) ($manifest['description'] ?? ''),
            (array) ($manifest['requires'] ?? []),
            $deps,
            array_values(array_intersect((array) ($manifest['capabilities'] ?? []), Capabilities::ALL)),
            (string) ($manifest['api'] ?? 'v1'),
            $path,
            $status,
            $manifest,
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** Fully-qualified Plugin class name, e.g. IRJalali\Plugins\IrHello\Plugin */
    public function className(): string
    {
        $studly = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $this->slug)));

        return 'IRJalali\\Plugins\\' . $studly . '\\Plugin';
    }

    public function classFile(): string
    {
        return $this->path . '/Plugin.php';
    }
}
