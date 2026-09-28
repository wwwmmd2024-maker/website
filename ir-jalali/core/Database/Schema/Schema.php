<?php

declare(strict_types=1);

namespace IRJalali\Core\Database\Schema;

use IRJalali\Core\Database\Database;

/**
 * Entry point for DDL operations used by migrations.
 */
final class Schema
{
    public function __construct(private readonly Database $db)
    {
    }

    /** Raw database access for migrations that need DDL beyond create(). */
    public function db(): Database
    {
        return $this->db;
    }

    public function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        if ($this->db->driver() === 'sqlite') {
            foreach ((new SqliteCompiler())->compileCreate($blueprint) as $sql) {
                $this->db->pdo()->exec($sql);
            }

            return;
        }

        $this->db->pdo()->exec((new MysqlCompiler())->compileCreate($blueprint));
    }

    public function drop(string $table): void
    {
        $this->db->pdo()->exec('DROP TABLE IF EXISTS ' . $this->db->wrap($table));
    }

    public function hasTable(string $table): bool
    {
        return $this->db->tableExists($table);
    }
}
