<?php

declare(strict_types=1);

namespace IRJalali\Core\Plugins;

/**
 * Capability-gated file broker. Plugins can ONLY touch their own
 * directory (read needs filesystem.read, write needs filesystem.write).
 */
final class PluginFiles
{
    private readonly string $root;

    /** @param list<string> $granted */
    public function __construct(
        string $pluginPath,
        private readonly array $granted,
    ) {
        $real = realpath($pluginPath);
        if ($real === false) {
            throw new \RuntimeException('Invalid plugin path.');
        }
        $this->root = $real;
    }

    public function get(string $relative): ?string
    {
        $this->require(Capabilities::FILESYSTEM_READ);
        $absolute = $this->resolve($relative);
        if (!is_file($absolute)) {
            return null;
        }

        return file_get_contents($absolute) ?: null;
    }

    public function put(string $relative, string $contents): bool
    {
        $this->require(Capabilities::FILESYSTEM_WRITE);
        $absolute = $this->resolve($relative);
        $dir = dirname($absolute);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents($absolute, $contents, LOCK_EX) !== false;
    }

    public function exists(string $relative): bool
    {
        $this->require(Capabilities::FILESYSTEM_READ);

        return file_exists($this->resolve($relative));
    }

    public function delete(string $relative): bool
    {
        $this->require(Capabilities::FILESYSTEM_WRITE);
        $absolute = $this->resolve($relative);

        return !file_exists($absolute) || @unlink($absolute);
    }

    private function require(string $capability): void
    {
        if (!in_array($capability, $this->granted, true)) {
            throw new CapabilityDeniedException("Plugin lacks capability [{$capability}].");
        }
    }

    private function resolve(string $relative): string
    {
        $relative = str_replace('\\', '/', ltrim($relative, '/'));
        $parts = [];
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $segment;
        }
        $absolute = $this->root . '/' . implode('/', $parts);
        $real = realpath($absolute) ?: $absolute;
        if (!str_starts_with($real, $this->root . '/') && $real !== $this->root) {
            throw new \RuntimeException('Path escapes plugin directory.');
        }

        return $absolute;
    }
}
