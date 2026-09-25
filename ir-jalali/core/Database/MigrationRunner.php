<?php

declare(strict_types=1);

namespace IRJalali\Core\Database;

use IRJalali\Core\Database\Schema\Schema;
use IRJalali\Core\Logging\Logger;

/**
 * Discovers migration classes in given directories and runs pending ones.
 */
final class MigrationRunner
{
    public function __construct(
        private readonly Database $db,
        private readonly Logger $logger,
    ) {
    }

    /** @param list<string> $directories */
    public function run(array $directories): int
    {
        $this->ensureMigrationsTable();
        $applied = $this->applied();

        $migrations = [];
        foreach ($directories as $dir) {
            foreach (glob(rtrim($dir, '/') . '/*.php') ?: [] as $file) {
                $class = $this->classFromFile($file);
                if ($class === null || isset($applied[$class]) || !class_exists($class)) {
                    continue;
                }
                $migrations[$class] = $file;
            }
        }
        ksort($migrations);

        $count = 0;
        $schema = new Schema($this->db);
        foreach ($migrations as $class => $file) {
            $migration = new $class();
            if (!$migration instanceof Migration) {
                continue;
            }
            $this->logger->channel('database')->info('Running migration', ['migration' => $class]);
            $migration->up($schema);
            $this->db->insert('migrations', [
                'migration' => $class,
                'batch' => 1,
                'executed_at' => date('Y-m-d H:i:s'),
            ]);
            $count++;
        }

        return $count;
    }

    private function ensureMigrationsTable(): void
    {
        if ($this->db->tableExists('migrations')) {
            return;
        }
        (new Schema($this->db))->create('migrations', function ($table): void {
            $table->id();
            $table->string('migration', 191);
            $table->integer('batch', false, 1);
            $table->dateTime('executed_at', false);
            $table->unique('migration');
        });
    }

    /** @return array<string, true> */
    private function applied(): array
    {
        $rows = $this->db->select('SELECT migration FROM migrations');
        $out = [];
        foreach ($rows as $row) {
            $out[$row['migration']] = true;
        }

        return $out;
    }

    private function classFromFile(string $file): ?string
    {
        $src = file_get_contents($file);
        if ($src === false) {
            return null;
        }
        if (!preg_match('/namespace\s+([^;]+);/u', $src, $ns)) {
            return null;
        }
        if (!preg_match('/class\s+([A-Za-z_][A-Za-z0-9_]*)/', $src, $cls)) {
            return null;
        }
        require_once $file;

        return trim($ns[1]) . '\\' . $cls[1];
    }
}
