<?php

declare(strict_types=1);

namespace IRJalali\Core\Database\Schema;

/**
 * Development/test compiler. Production uses MySQL.
 */
final class SqliteCompiler
{
    /** @return list<string> CREATE TABLE + CREATE INDEX statements */
    public function compileCreate(Blueprint $blueprint): array
    {
        $lines = [];
        foreach ($blueprint->columns as $column) {
            $lines[] = $this->compileColumn($column);
        }
        foreach ($blueprint->indexes as $index) {
            if ($index['type'] === 'unique') {
                $cols = implode('", "', $index['columns']);
                $lines[] = "UNIQUE (\"{$cols}\")";
            }
        }
        foreach ($blueprint->foreignKeys as $fk) {
            $lines[] = sprintf(
                'FOREIGN KEY ("%s") REFERENCES "%s" ("%s") ON DELETE %s',
                $fk['column'],
                $fk['on'],
                $fk['references'],
                $fk['onDelete']
            );
        }

        $statements = [sprintf(
            "CREATE TABLE IF NOT EXISTS \"%s\" (\n%s\n)",
            $blueprint->table,
            implode(",\n", array_map(fn($l) => '  ' . $l, $lines))
        )];

        foreach ($blueprint->indexes as $index) {
            if ($index['type'] !== 'index') {
                continue;
            }
            $name = 'idx_' . $blueprint->table . '_' . implode('_', $index['columns']);
            $cols = implode('", "', $index['columns']);
            $statements[] = "CREATE INDEX IF NOT EXISTS \"{$name}\" ON \"{$blueprint->table}\" (\"{$cols}\")";
        }

        return $statements;
    }

    /** @param array<string, mixed> $column */
    private function compileColumn(array $column): string
    {
        $name = $column['name'];
        return match ($column['type']) {
            'id' => "\"{$name}\" INTEGER PRIMARY KEY AUTOINCREMENT",
            'uuid' => "\"{$name}\" TEXT NOT NULL",
            'string' => "\"{$name}\" TEXT" . $this->nullDefault($column),
            'text', 'longtext', 'json' => "\"{$name}\" TEXT" . $this->nullOnly($column),
            'integer', 'biginteger', 'boolean' => "\"{$name}\" INTEGER" . $this->nullDefault($column, true),
            'decimal' => "\"{$name}\" NUMERIC" . $this->nullDefault($column, true),
            'datetime' => "\"{$name}\" TEXT" . $this->nullOnly($column),
            default => throw new \RuntimeException("Unknown column type [{$column['type']}]."),
        };
    }

    /** @param array<string, mixed> $column */
    private function nullOnly(array $column): string
    {
        return !empty($column['nullable']) ? '' : ' NOT NULL';
    }

    /** @param array<string, mixed> $column */
    private function nullDefault(array $column, bool $numeric = false): string
    {
        $sql = $this->nullOnly($column);
        if (array_key_exists('default', $column) && $column['default'] !== null) {
            $default = $column['default'];
            if (is_bool($default)) {
                $default = $default ? 1 : 0;
            }
            $sql .= $numeric || is_numeric($default) ? " DEFAULT {$default}" : " DEFAULT '" . addslashes((string) $default) . "'";
        }

        return $sql;
    }
}
