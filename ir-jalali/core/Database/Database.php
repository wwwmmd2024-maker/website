<?php

declare(strict_types=1);

namespace IRJalali\Core\Database;

use IRJalali\Core\Logging\Logger;
use PDO;
use PDOStatement;

/**
 * Thin PDO wrapper. ALL queries use prepared statements with bound params.
 * Controllers must NOT use this directly for complex queries —
 * use Repositories (app/Repositories) instead.
 */
final class Database
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Logger $logger,
    ) {
    }

    public function pdo(): PDO
    {
        return $this->connection->pdo();
    }

    public function driver(): string
    {
        return $this->connection->driver();
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute($params);

            return $stmt;
        } catch (\PDOException $e) {
            $this->logger->channel('database')->error('Query failed', [
                'sql' => $sql,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /** @return list<array<string, mixed>> */
    public function select(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function first(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    public function value(string $sql, array $params = []): mixed
    {
        return $this->query($sql, $params)->fetchColumn();
    }

    public function insert(string $table, array $data): int|string
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $columns);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->wrap($table),
            implode(', ', array_map([$this, 'wrap'], $columns)),
            implode(', ', $placeholders)
        );
        $this->query($sql, $data);

        return $this->pdo()->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        $params = [];
        foreach ($data as $column => $value) {
            $sets[] = $this->wrap($column) . ' = :set_' . $column;
            $params['set_' . $column] = $value;
        }
        $sql = sprintf('UPDATE %s SET %s WHERE %s', $this->wrap($table), implode(', ', $sets), $where);

        return $this->query($sql, array_merge($params, $whereParams))->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        return $this->query(sprintf('DELETE FROM %s WHERE %s', $this->wrap($table), $where), $params)->rowCount();
    }

    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }

    public function transaction(callable $callback): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $result = $callback($this);
            $pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function tableExists(string $table): bool
    {
        if ($this->driver() === 'sqlite') {
            $found = $this->value(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name = :t",
                ['t' => $table]
            );

            return $found === $table;
        }

        $found = $this->value(
            'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t',
            ['t' => $table]
        );

        return $found === $table;
    }

    public function wrap(string $identifier): string
    {
        if (str_contains($identifier, '.')) {
            return implode('.', array_map([$this, 'wrap'], explode('.', $identifier)));
        }
        if ($identifier === '*') {
            return '*';
        }

        return $this->driver() === 'mysql' ? '`' . str_replace('`', '', $identifier) . '`' : '"' . str_replace('"', '', $identifier) . '"';
    }
}
