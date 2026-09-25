<?php

declare(strict_types=1);

namespace IRJalali\Core\Plugins;

use IRJalali\Core\Version\Compatibility;

/**
 * Validates plugin.json against the manifest contract.
 */
final class PluginManifestLoader
{
    /**
     * @return array<string, mixed>
     */
    public static function load(string $manifestPath): array
    {
        $raw = @file_get_contents($manifestPath);
        if ($raw === false) {
            throw new \RuntimeException("Cannot read manifest [{$manifestPath}].");
        }
        $manifest = json_decode($raw, true);
        if (!is_array($manifest)) {
            throw new \RuntimeException("Invalid JSON in [{$manifestPath}].");
        }

        foreach (['slug', 'name', 'version', 'requires'] as $field) {
            if (empty($manifest[$field])) {
                throw new \RuntimeException("Manifest [{$manifestPath}] misses [{$field}].");
            }
        }
        if (!preg_match('/^[a-z0-9][a-z0-9_\-]{1,59}$/i', (string) $manifest['slug'])) {
            throw new \RuntimeException("Invalid plugin slug [{$manifest['slug']}].");
        }
        if (!preg_match('/^\d+\.\d+\.\d+$/', (string) $manifest['version'])) {
            throw new \RuntimeException("Plugin version must be semver [{$manifest['version']}].");
        }
        if (!is_array($manifest['requires'])) {
            throw new \RuntimeException('Manifest [requires] must be an object.');
        }
        $manifest['description'] ??= '';
        $manifest['author'] ??= '';
        $manifest['license'] ??= 'proprietary';
        $manifest['capabilities'] ??= [];
        $manifest['autoload'] ??= ['psr-4' => ['IRJalali\\Plugins\\' . self::studly((string) $manifest['slug']) . '\\' => 'src/']];
        $manifest['must_use'] ??= false;

        $unknown = array_diff((array) $manifest['capabilities'], Capabilities::all());
        if ($unknown !== []) {
            throw new \RuntimeException('Unknown capabilities: ' . implode(', ', $unknown));
        }

        return $manifest;
    }

    public static function studly(string $slug): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $slug)));
    }

    /** Step-3 of install: deny when core constraint is not satisfied. */
    public static function checkCoreConstraint(array $manifest, string $coreVersion): bool
    {
        $constraint = (string) ($manifest['requires']['core'] ?? '*');
        if ($constraint === '*' || $constraint === '') {
            return true;
        }

        return Compatibility::satisfies($coreVersion, $constraint);
    }
}
