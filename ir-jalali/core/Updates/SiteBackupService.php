<?php

declare(strict_types=1);

namespace IRJalali\Core\Updates;

use IRJalali\Core\Database\Database;

/**
 * Full-site backups: a portable SQL dump of every table plus the user-owned
 * directories (uploads, themes, plugins) zipped together with a manifest.
 *
 * The dumper is driver-aware (MySQL / SQLite) and emits plain `CREATE TABLE`
 * + `INSERT` statements so a backup can be restored on either engine, which is
 * what keeps the "clone Website A -> Website B" workflow honest.
 */
final class SiteBackupService
{
    public function __construct(
        private readonly Database $db,
        private readonly string $basePath,
        private readonly string $backupsPath,
        private readonly int $keep = 10,
    ) {
    }

    /** Create a full backup; returns the absolute zip path. */
    public function create(string $reason = 'manual'): string
    {
        $dir = $this->dir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $stamp = date('Ymd-His');
        $zipPath = $dir . '/site-' . $stamp . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create backup archive.');
        }

        $sql = $this->dumpSql();
        $zip->addFromString('database.sql', $sql);

        $manifest = [
            'type' => 'ir-jalali-site-backup',
            'format' => 1,
            'created_at' => date('c'),
            'reason' => $reason,
            'core_version' => \IRJalali\Core\Kernel\Application::get()->version(),
            'php' => PHP_VERSION,
            'driver' => $this->db->driver(),
            'sha256_sql' => hash('sha256', $sql),
        ];

        foreach ($this->fileRoots() as $root => $label) {
            $abs = $this->basePath . '/' . $root;
            if (!is_dir($abs)) {
                continue;
            }
            $count = $this->addTree($zip, $abs, 'files/' . $label . '/');
            $manifest['files'][$label] = $count;
        }

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $zip->close();

        $this->prune();

        return $zipPath;
    }

    /** @return list<array{file: string, name: string, size: int, created: string, reason: string}> */
    public function all(): array
    {
        $dir = $this->dir();
        if (!is_dir($dir)) {
            return [];
        }
        $rows = [];
        foreach (scandir($dir) ?: [] as $file) {
            if (!str_starts_with($file, 'site-') || !str_ends_with($file, '.zip')) {
                continue;
            }
            $full = $dir . '/' . $file;
            $rows[] = [
                'file' => $full,
                'name' => $file,
                'size' => filesize($full) ?: 0,
                'created' => date('Y-m-d H:i:s', filemtime($full) ?: time()),
                'reason' => $this->peekReason($full),
            ];
        }
        usort($rows, fn ($a, $b): int => strcmp($b['created'], $a['created']));

        return $rows;
    }

    public function path(string $name): ?string
    {
        $safe = basename($name);
        if (!preg_match('/^site-[0-9\-]+\.zip$/', $safe)) {
            return null;
        }
        $full = $this->dir() . '/' . $safe;

        return is_file($full) ? $full : null;
    }

    public function delete(string $name): bool
    {
        $full = $this->path($name);
        if ($full === null) {
            return false;
        }

        return @unlink($full);
    }

    /**
     * Restore a backup: integrity-checks the archive, then loads the SQL dump
     * into the current connection and re-hydrates the file roots.
     *
     * @return array{tables: int, files: int}
     */
    public function restore(string $name): array
    {
        $full = $this->path($name);
        if ($full === null) {
            throw new \RuntimeException('Backup not found.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($full) !== true) {
            throw new \RuntimeException('Cannot open backup archive.');
        }

        $manifestRaw = $zip->getFromName('manifest.json');
        $sql = $zip->getFromName('database.sql');
        if ($manifestRaw === false || $sql === false) {
            $zip->close();
            throw new \RuntimeException('Backup is corrupted (missing manifest or SQL).');
        }
        $manifest = json_decode($manifestRaw, true);
        if (!is_array($manifest) || ($manifest['type'] ?? '') !== 'ir-jalali-site-backup') {
            $zip->close();
            throw new \RuntimeException('Not a valid IR-Jalali site backup.');
        }
        if (hash('sha256', $sql) !== ($manifest['sha256_sql'] ?? '')) {
            $zip->close();
            throw new \RuntimeException('Backup integrity check failed (SQL checksum mismatch).');
        }

        $tables = $this->loadSql($sql);

        $files = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if ($entry === false || !str_starts_with($entry, 'files/')) {
                continue;
            }
            $relative = substr($entry, strlen('files/'));
            if (str_contains($relative, '..')) {
                continue; // path traversal guard
            }
            [$root, $rest] = array_pad(explode('/', $relative, 2), 2, '');
            if ($rest === '' || !array_key_exists($root, $this->fileRoots())) {
                continue;
            }
            $target = $this->basePath . '/' . $this->fileRoots()[$root] . '/' . $rest;
            if (!is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }
            $content = $zip->getFromIndex($i);
            if ($content !== false) {
                file_put_contents($target, $content);
                $files++;
            }
        }
        $zip->close();

        return ['tables' => $tables, 'files' => $files];
    }

    /** Dump every table as portable SQL. */
    public function dumpSql(): string
    {
        $driver = $this->db->driver();
        $out = "-- IR-Jalali site backup\n-- driver: {$driver}\n-- created: " . date('c') . "\n\n";

        foreach ($this->tables() as $table) {
            if ($driver === 'sqlite') {
                $row = $this->db->first(
                    "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = :t",
                    ['t' => $table]
                );
                if ($row !== null && !empty($row['sql'])) {
                    $out .= "DROP TABLE IF EXISTS {$this->quote($table)};\n" . $row['sql'] . ";\n\n";
                }
            } else {
                $out .= "DROP TABLE IF EXISTS {$this->quote($table)};\n";
                $create = $this->db->first('SHOW CREATE TABLE ' . $this->quote($table));
                $ddl = $create['Create Table'] ?? ($create[array_key_first($create ?? [])] ?? null);
                if (is_string($ddl)) {
                    $out .= $ddl . ";\n\n";
                }
            }

            $rows = $this->db->select('SELECT * FROM ' . $this->quote($table));
            if ($rows === []) {
                continue;
            }
            foreach (array_chunk($rows, 100) as $chunk) {
                $columns = array_keys($chunk[0]);
                $colList = implode(', ', array_map(fn ($c) => $this->quote($c), $columns));
                $values = [];
                foreach ($chunk as $row) {
                    $vals = [];
                    foreach ($columns as $c) {
                        $vals[] = $this->literal($row[$c] ?? null);
                    }
                    $values[] = '(' . implode(', ', $vals) . ')';
                }
                $out .= 'INSERT INTO ' . $this->quote($table) . " ({$colList}) VALUES\n"
                    . implode(",\n", $values) . ";\n";
            }
            $out .= "\n";
        }

        return $out;
    }

    /** Execute a portable SQL dump against the current connection. Returns table count. */
    private function loadSql(string $sql): int
    {
        $pdo = $this->db->pdo();
        $driver = $this->db->driver();
        if ($driver === 'mysql') {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        } else {
            $pdo->exec('PRAGMA foreign_keys = OFF');
        }

        $tables = 0;
        foreach ($this->splitStatements($sql) as $statement) {
            $statement = trim($statement);
            if ($statement === '' || str_starts_with($statement, '--')) {
                continue;
            }
            $pdo->exec($statement);
            if (preg_match('/^\s*(DROP|CREATE)\s+TABLE/i', $statement) && preg_match('/^\s*CREATE\s+TABLE/i', $statement)) {
                $tables++;
            }
        }

        if ($driver === 'mysql') {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        } else {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }

        return $tables;
    }

    /** @return list<string> */
    private function splitStatements(string $sql): array
    {
        // Values never contain bare ";\n" because literals are escaped; splitting
        // on statement terminators at end-of-line is safe for the dumper's output.
        $parts = preg_split('/;\s*\r?\n/', $sql) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));
    }

    /** @return list<string> */
    private function tables(): array
    {
        $driver = $this->db->driver();
        if ($driver === 'sqlite') {
            $rows = $this->db->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");

            return array_map(fn ($r) => (string) $r['name'], $rows);
        }
        $rows = $this->db->select('SHOW TABLES');
        $out = [];
        foreach ($rows as $r) {
            $out[] = (string) array_values($r)[0];
        }

        return $out;
    }

    /** Directory label inside the zip => site-relative folder. */
    private function fileRoots(): array
    {
        return [
            'uploads' => 'storage/uploads',
            'themes' => 'themes',
            'plugins' => 'plugins',
        ];
    }

    private function addTree(\ZipArchive $zip, string $absRoot, string $zipPrefix): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        $baseLen = strlen(rtrim($absRoot, '/') . '/');
        foreach ($iterator as $file) {
            $relative = substr($file->getPathname(), $baseLen);
            // Never bundle other backups into a backup.
            if (str_starts_with($relative, 'storage/backups')) {
                continue;
            }
            $zip->addFile($file->getPathname(), $zipPrefix . $relative);
            $count++;
        }

        return $count;
    }

    private function peekReason(string $zipPath): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return 'unknown';
        }
        $raw = $zip->getFromName('manifest.json');
        $zip->close();
        $manifest = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($manifest) ? (string) ($manifest['reason'] ?? 'manual') : 'unknown';
    }

    private function prune(): void
    {
        foreach (array_slice($this->all(), $this->keep) as $old) {
            @unlink($old['file']);
        }
    }

    private function dir(): string
    {
        return rtrim($this->backupsPath, '/') . '/site';
    }

    private function quote(string $identifier): string
    {
        $identifier = str_replace(['`', '"'], '', $identifier);

        return $this->db->driver() === 'mysql' ? '`' . $identifier . '`' : '"' . $identifier . '"';
    }

    private function literal(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $this->db->pdo()->quote((string) $value);
    }
}
