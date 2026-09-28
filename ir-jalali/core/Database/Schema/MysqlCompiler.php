<?php

declare(strict_types=1);

namespace IRJalali\Core\Database\Schema;

final class MysqlCompiler
{
    public function compileCreate(Blueprint $blueprint): string
    {
        $lines = [];
        foreach ($blueprint->columns as $column) {
            $lines[] = $this->compileColumn($column);
        }
        foreach ($blueprint->indexes as $index) {
            $cols = implode('`, `', $index['columns']);
            $name = 'idx_' . $blueprint->table . '_' . implode('_', $index['columns']);
            $name = substr(preg_replace('/[^a-z0-9_]/i', '_', $name) ?? $name, 0, 60);
            $lines[] = $index['type'] === 'unique'
                ? "UNIQUE KEY `{$name}` (`{$cols}`)"
                : "KEY `{$name}` (`{$cols}`)";
        }
        foreach ($blueprint->foreignKeys as $fk) {
            $name = substr('fk_' . $blueprint->table . '_' . $fk['column'], 0, 60);
            $lines[] = sprintf(
                'CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`) ON DELETE %s',
                $name,
                $fk['column'],
                $fk['on'],
                $fk['references'],
                $fk['onDelete']
            );
        }

        return sprintf(
            "CREATE TABLE IF NOT EXISTS `%s` (\n%s\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            $blueprint->table,
            implode(",\n", array_map(fn($l) => '  ' . $l, $lines))
        );
    }

    /** @param array<string, mixed> $column */
    private function compileColumn(array $column): string
    {
        $name = $column['name'];
        switch ($column['type']) {
            case 'id':
                return "`{$name}` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY";
            case 'uuid':
                return "`{$name}` CHAR(36) NOT NULL";
            case 'string':
                $length = (int) ($column['length'] ?? 191);
                return "`{$name}` VARCHAR({$length})" . $this->nullDefault($column);
            case 'text':
                return "`{$name}` TEXT" . $this->nullOnly($column);
            case 'longtext':
                return "`{$name}` LONGTEXT" . $this->nullOnly($column);
            case 'json':
                return "`{$name}` JSON" . $this->nullOnly($column);
            case 'integer':
                return "`{$name}` INT" . $this->nullDefault($column);
            case 'biginteger':
                // Unsigned to match `id()` (BIGINT UNSIGNED) for foreign keys.
                return "`{$name}` BIGINT UNSIGNED" . $this->nullDefault($column);
            case 'boolean':
                $default = !empty($column['default']) ? '1' : '0';
                return "`{$name}` TINYINT(1) NOT NULL DEFAULT {$default}";
            case 'decimal':
                return sprintf(
                    '`%s` DECIMAL(%d,%d)%s',
                    $name,
                    (int) $column['precision'],
                    (int) $column['scale'],
                    $this->nullDefault($column)
                );
            case 'datetime':
                return "`{$name}` DATETIME" . $this->nullOnly($column);
            default:
                throw new \RuntimeException("Unknown column type [{$column['type']}].");
        }
    }

    /** @param array<string, mixed> $column */
    private function nullOnly(array $column): string
    {
        return !empty($column['nullable']) ? ' NULL' : ' NOT NULL';
    }

    /** @param array<string, mixed> $column */
    private function nullDefault(array $column): string
    {
        $sql = $this->nullOnly($column);
        if (array_key_exists('default', $column) && $column['default'] !== null) {
            $default = $column['default'];
            $sql .= is_numeric($default) ? " DEFAULT {$default}" : " DEFAULT '" . addslashes((string) $default) . "'";
        }

        return $sql;
    }
}
