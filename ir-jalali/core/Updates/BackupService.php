<?php

declare(strict_types=1);

namespace IRJalali\Core\Updates;

use IRJalali\Core\Plugins\Plugin;

/**
 * Pre-update snapshots for plugins and themes. Keeps the last N backups.
 */
final class BackupService
{
    public function __construct(
        private readonly string $backupsPath,
        private readonly int $keep = 5,
    ) {
    }

    public function backupPlugin(Plugin $plugin): string
    {
        return $this->snapshot('plugins', $plugin->slug, $plugin->version, $plugin->path);
    }

    public function restorePlugin(Plugin $plugin, string $backupPath): void
    {
        $this->restore($backupPath, $plugin->path);
    }

    public function backupTheme(string $slug, string $version, string $path): string
    {
        return $this->snapshot('themes', $slug, $version, $path);
    }

    public function restoreTheme(string $path, string $backupPath): void
    {
        $this->restore($backupPath, $path);
    }

    /** @return list<array{file: string, size: int, created: string}> */
    public function list(string $kind, string $slug): array
    {
        $dir = $this->dir($kind);
        if (!is_dir($dir)) {
            return [];
        }
        $result = [];
        foreach (scandir($dir) ?: [] as $file) {
            if (!str_starts_with($file, $slug . '-')) {
                continue;
            }
            $full = $dir . '/' . $file;
            $result[] = ['file' => $full, 'size' => filesize($full) ?: 0, 'created' => date('Y-m-d H:i:s', filemtime($full) ?: time())];
        }
        usort($result, fn ($a, $b): int => strcmp($b['created'], $a['created']));

        return $result;
    }

    private function snapshot(string $kind, string $slug, string $version, string $sourcePath): string
    {
        $dir = $this->dir($kind);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $safe = preg_replace('/[^a-z0-9_\-.]/i', '', $slug . '-' . $version . '-' . date('Ymd-His') . '.zip');
        $zipPath = $dir . '/' . $safe;

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create backup archive.');
        }
        $base = rtrim($sourcePath, '/') . '/';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $file) {
            $zip->addFile($file->getPathname(), substr($file->getPathname(), strlen($base)));
        }
        $zip->close();

        foreach (array_slice($this->list($kind, $slug), $this->keep) as $old) {
            @unlink($old['file']);
        }

        return $zipPath;
    }

    private function restore(string $backupPath, string $targetPath): void
    {
        if (!is_file($backupPath)) {
            throw new \RuntimeException('Backup file missing.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($backupPath) !== true) {
            throw new \RuntimeException('Cannot open backup archive.');
        }
        $tmp = $targetPath . '.restore-' . bin2hex(random_bytes(4));
        mkdir($tmp, 0755, true);
        if (!$zip->extractTo($tmp)) {
            $zip->close();
            throw new \RuntimeException('Backup extraction failed.');
        }
        $zip->close();

        // Swap: move current aside, move restored in, drop the aside copy.
        $aside = $targetPath . '.broken-' . bin2hex(random_bytes(4));
        rename($targetPath, $aside);
        try {
            rename($tmp, $targetPath);
        } catch (\Throwable $e) {
            rename($aside, $targetPath); // put the broken copy back
            throw $e;
        }
        $this->removeDir($aside);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }

    private function dir(string $kind): string
    {
        return rtrim($this->backupsPath, '/') . '/' . $kind;
    }
}
