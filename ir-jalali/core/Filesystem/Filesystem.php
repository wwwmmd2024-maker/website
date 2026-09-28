<?php

declare(strict_types=1);

namespace IRJalali\Core\Filesystem;

use RuntimeException;

/**
 * Safe filesystem access. All paths are jailed inside the project base path
 * (path traversal protection).
 */
final class Filesystem
{
    private readonly string $baseReal;

    public function __construct(private readonly string $basePath)
    {
        $real = realpath($basePath);
        if ($real === false) {
            throw new RuntimeException('Invalid base path.');
        }
        $this->baseReal = $real;
    }

    /**
     * Resolve a project-relative path to an absolute path inside the jail.
     *
     * @throws RuntimeException on traversal attempts.
     */
    public function path(string $relative): string
    {
        $relative = str_replace('\\', '/', ltrim($relative, '/'));
        if (str_contains($relative, "\0")) {
            throw new RuntimeException('Invalid path.');
        }
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
        $absolute = $this->baseReal . '/' . implode('/', $parts);
        $real = realpath($absolute) ?: $absolute;
        if (!str_starts_with($real, $this->baseReal)) {
            throw new RuntimeException('Path traversal blocked.');
        }

        return $absolute;
    }

    public function ensureDir(string $relative): string
    {
        $absolute = $this->path($relative);
        if (!is_dir($absolute)) {
            mkdir($absolute, 0755, true);
        }

        return $absolute;
    }

    public function put(string $relative, string $contents): bool
    {
        $absolute = $this->path($relative);
        $dir = dirname($absolute);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents($absolute, $contents, LOCK_EX) !== false;
    }

    public function get(string $relative): ?string
    {
        $absolute = $this->path($relative);
        if (!is_file($absolute)) {
            return null;
        }
        $contents = file_get_contents($absolute);

        return $contents === false ? null : $contents;
    }

    public function exists(string $relative): bool
    {
        return file_exists($this->path($relative));
    }

    public function delete(string $relative): bool
    {
        $absolute = $this->path($relative);

        return !file_exists($absolute) || @unlink($absolute);
    }

    public function moveUploadedFile(string $tmpName, string $relativeDestination): bool
    {
        if (!is_uploaded_file($tmpName)) {
            return false;
        }
        $destination = $this->path($relativeDestination);
        $dir = dirname($destination);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return move_uploaded_file($tmpName, $destination);
    }
}
