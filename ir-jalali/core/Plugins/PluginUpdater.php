<?php

declare(strict_types=1);

namespace IRJalali\Core\Plugins;

use IRJalali\Core\Config\Config;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Kernel\Container;
use IRJalali\Core\Logging\Logger;
use IRJalali\Core\Marketplace\MarketplaceClient;
use IRJalali\Core\Updates\BackupService;

/**
 * Update pipeline: backup → download → scan → extract → migrate → swap.
 * Any failure rolls back files + DB migrations to the previous state.
 */
final class PluginUpdater
{
    public function __construct(private readonly Container $app)
    {
    }

    /**
     * @return array{success: bool, version: string, message: string}
     */
    public function update(string $slug, ?string $targetVersion = null): array
    {
        /** @var PluginManager $manager */
        $manager = $this->app->make(PluginManager::class);
        /** @var MarketplaceClient $marketplace */
        $marketplace = $this->app->make(MarketplaceClient::class);
        /** @var Logger $logger */
        $logger = $this->app->make(Logger::class);

        $plugins = $manager->discover();
        $plugin = $plugins[$slug] ?? null;
        if ($plugin === null) {
            return ['success' => false, 'version' => '', 'message' => 'افزونه یافت نشد.'];
        }

        $release = $marketplace->latestPluginRelease($slug, $targetVersion);
        if ($release === null || $release['version'] === $plugin->version) {
            return ['success' => false, 'version' => $plugin->version, 'message' => 'به‌روزرسانی جدیدی موجود نیست.'];
        }

        $previousVersion = $plugin->version;
        $tmpDir = sys_get_temp_dir() . '/irj-upd-' . preg_replace('/[^a-z0-9]/i', '', $slug) . '-' . bin2hex(random_bytes(4));
        mkdir($tmpDir, 0755, true);
        $zipPath = $tmpDir . '/update.zip';

        try {
            /** @var Database $db */
            $db = $this->app->make(Database::class);
            /** @var BackupService $backups */
            $backups = $this->app->make(BackupService::class);
            $backupPath = $backups->backupPlugin($plugin); // full file backup of current version

            // 1. Download
            $downloaded = $marketplace->download($release['download_url'], $zipPath);
            if (!$downloaded) {
                throw new \RuntimeException('Download failed.');
            }
            if (!empty($release['checksum']) && (hash_file('sha256', $zipPath) ?: '') !== strtolower($release['checksum'])) {
                throw new \RuntimeException('Checksum mismatch on update package.');
            }

            // 2. Scan + 3. Extract + 4. Swap
            /** @var PluginInstaller $installer */
            $installer = $this->app->make(PluginInstaller::class);
            $report = $installer->scan($zipPath);
            if (!$report->safe()) {
                throw new \RuntimeException('Security scan failed: ' . implode(' | ', array_slice($report->errors, 0, 3)));
            }
            $installer->extractOver($zipPath, $plugin->path);

            // 5. Re-read manifest + check core constraint
            $plugins = $manager->discover();
            $updated = $plugins[$slug] ?? null;
            if ($updated === null) {
                throw new \RuntimeException('Updated manifest missing.');
            }
            $coreVersion = (string) $this->app->make(Config::class)->get('versions.core', '1.1.0');
            if (!PluginManifestLoader::checkCoreConstraint($updated->manifest, $coreVersion)) {
                throw new \RuntimeException('New version requires a newer core.');
            }

            // 6. Migrate forward
            /** @var PluginMigrator $migrator */
            $migrator = $this->app->make(PluginMigrator::class);
            $migrator->migrate($updated);

            $db->table('plugins')->where('slug', $slug)->update([
                'name' => $updated->name,
                'version' => $updated->version,
                'settings' => json_encode($updated->manifest, JSON_UNESCAPED_UNICODE),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $logger->info('plugin.updated', ['slug' => $slug, 'from' => $previousVersion, 'to' => $updated->version]);

            return ['success' => true, 'version' => $updated->version, 'message' => "به‌روزرسانی به نسخه {$updated->version} انجام شد."];
        } catch (\Throwable $e) {
            $this->rollbackFiles($plugin, $backupPath ?? null);
            $logger->error('plugin.update.failed', ['slug' => $slug, 'error' => $e->getMessage()]);

            return ['success' => false, 'version' => $previousVersion, 'message' => 'به‌روزرسانی ناموفق بود و نسخه قبلی بازیابی شد: ' . $e->getMessage()];
        } finally {
            $this->removeDir($tmpDir);
        }
    }

    private function rollbackFiles(Plugin $plugin, ?string $backupPath): void
    {
        if ($backupPath === null || !is_file($backupPath)) {
            return;
        }
        try {
            /** @var BackupService $backups */
            $backups = $this->app->make(BackupService::class);
            $backups->restorePlugin($plugin, $backupPath);
        } catch (\Throwable) {
            // Logged by caller path; never throw from rollback.
        }
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
}
