<?php

declare(strict_types=1);

namespace IRJalali\Core\Plugins;

use IRJalali\Core\Database\Database;

/**
 * Per-plugin versioned migrations.
 * Files: <plugin>/Migrations/Version_1_0_0_<Name>.php
 * Class: IRJalali\Plugins\<Studly>\Migrations\Version_1_0_0_<Name>
 * with up(Database $db): void and down(Database $db): void.
 */
final class PluginMigrator
{
    public function __construct(private readonly Database $db)
    {
    }

    /** Run all pending migrations for a plugin (activation/update). */
    public function migrate(Plugin $plugin): int
    {
        $ran = 0;
        foreach ($this->pending($plugin) as $migration) {
            $instance = new ($migration['class'])();
            $instance->up($this->db);
            $this->db->insert('plugin_migrations', [
                'plugin_slug' => $plugin->slug,
                'version' => $migration['version'],
                'name' => $migration['name'],
                'checksum' => $migration['checksum'],
                'executed_at' => date('Y-m-d H:i:s'),
            ]);
            $ran++;
        }
        $this->verifyChecksums($plugin);

        return $ran;
    }

    /** Roll back the last $steps batches (update rollback path). */
    public function rollback(Plugin $plugin, int $steps = 1): int
    {
        $applied = $this->applied($plugin);
        usort($applied, fn ($a, $b): int => version_compare($b['version'], $a['version']));
        $rolledBack = 0;
        foreach (array_slice($applied, 0, max(1, $steps)) as $row) {
            $migration = $this->find($plugin, $row['version']);
            if ($migration !== null) {
                $instance = new ($migration['class'])();
                $instance->down($this->db);
            }
            $this->db->delete(
                'plugin_migrations',
                'plugin_slug = :s AND version = :v',
                ['s' => $plugin->slug, 'v' => $row['version']]
            );
            $rolledBack++;
        }

        return $rolledBack;
    }

    /** @return list<array{version: string, name: string, class: string, checksum: string}> */
    private function pending(Plugin $plugin): array
    {
        $appliedVersions = array_column($this->applied($plugin), 'version');
        $pending = array_filter(
            $this->all($plugin),
            fn (array $m): bool => !in_array($m['version'], $appliedVersions, true)
        );
        usort($pending, fn ($a, $b): int => version_compare($a['version'], $b['version']));

        return array_values($pending);
    }

    /** @return list<array{version: string, name: string, class: string, checksum: string}> */
    private function all(Plugin $plugin): array
    {
        $dir = $plugin->path . '/Migrations';
        if (!is_dir($dir)) {
            return [];
        }
        $result = [];
        foreach (scandir($dir) ?: [] as $file) {
            if (!preg_match('/^Version_(\d+_\d+_\d+)_([A-Za-z0-9_]+)\.php$/', $file, $m)) {
                continue;
            }
            $class = 'IRJalali\\Plugins\\' . PluginManifestLoader::studly($plugin->slug) . '\\Migrations\\' . basename($file, '.php');
            require_once $dir . '/' . $file;
            if (!class_exists($class)) {
                throw new \RuntimeException("Migration class [{$class}] missing in [{$file}].");
            }
            if (!method_exists($class, 'up') || !method_exists($class, 'down')) {
                throw new \RuntimeException("Migration [{$class}] must define up() and down().");
            }
            $result[] = [
                'version' => str_replace('_', '.', $m[1]),
                'name' => $m[2],
                'class' => $class,
                'checksum' => hash_file('sha256', $dir . '/' . $file) ?: '',
            ];
        }

        return $result;
    }

    private function find(Plugin $plugin, string $version): ?array
    {
        foreach ($this->all($plugin) as $migration) {
            if ($migration['version'] === $version) {
                return $migration;
            }
        }

        return null;
    }

    /** @return list<array{version: string, checksum: string}> */
    private function applied(Plugin $plugin): array
    {
        try {
            return $this->db->table('plugin_migrations')
                ->where('plugin_slug', $plugin->slug)
                ->orderBy('version')
                ->select(['version', 'checksum'])
                ->get();
        } catch (\Throwable) {
            return [];
        }
    }

    /** Detect tampering: applied migration files must not change. */
    private function verifyChecksums(Plugin $plugin): void
    {
        foreach ($this->applied($plugin) as $row) {
            $migration = $this->find($plugin, $row['version']);
            if ($migration !== null && $migration['checksum'] !== $row['checksum']) {
                throw new \RuntimeException("Migration [{$plugin->slug} @ {$row['version']}] was modified after execution (checksum mismatch).");
            }
        }
    }
}
