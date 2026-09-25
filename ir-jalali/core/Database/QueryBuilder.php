<?php

declare(strict_types=1);

namespace IRJalali\Core\Database;

/**
 * Minimal fluent query builder (SELECT/UPDATE/DELETE) with bound parameters.
 */
final class QueryBuilder
{
    /** @var list<string> */
    private array $selects = ['*'];

    /** @var list<string> */
    private array $wheres = [];

    /** @var array<string, mixed> */
    private array $params = [];

    /** @var list<string> */
    private array $orders = [];

    private ?int $limit = null;
    private ?int $offset = null;
    private int $paramIndex = 0;

    public function __construct(
        private readonly Database $db,
        private readonly string $table,
    ) {
    }

    /** @param list<string> $columns */
    public function select(array $columns): self
    {
        $this->selects = $columns;

        return $this;
    }

    public function where(string $column, mixed $value, string $operator = '='): self
    {
        $key = 'w' . ($this->paramIndex++);
        $this->wheres[] = $this->db->wrap($column) . " {$operator} :{$key}";
        $this->params[$key] = $value;

        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = $this->db->wrap($column) . ' IS NULL';

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->wheres[] = $this->db->wrap($column) . ' IS NOT NULL';

        return $this;
    }

    /** @param list<mixed> $values */
    public function whereIn(string $column, array $values): self
    {
        if ($values === []) {
            $this->wheres[] = '1 = 0';

            return $this;
        }
        $keys = [];
        foreach ($values as $value) {
            $key = 'w' . ($this->paramIndex++);
            $keys[] = ':' . $key;
            $this->params[$key] = $value;
        }
        $this->wheres[] = $this->db->wrap($column) . ' IN (' . implode(', ', $keys) . ')';

        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = $this->db->wrap($column) . ' ' . $direction;

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);

        return $this;
    }

    private function buildSelect(): string
    {
        $cols = $this->selects === ['*'] ? '*' : implode(', ', array_map([$this->db, 'wrap'], $this->selects));
        $sql = sprintf('SELECT %s FROM %s', $cols, $this->db->wrap($this->table));
        if ($this->wheres !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }
        if ($this->orders !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    /** @return list<array<string, mixed>> */
    public function get(): array
    {
        return $this->db->select($this->buildSelect(), $this->params);
    }

    /** @return array<string, mixed>|null */
    public function first(): ?array
    {
        $clone = clone $this;
        $clone->limit = 1;
        $rows = $this->db->select($clone->buildSelect(), $clone->params);

        return $rows[0] ?? null;
    }

    public function count(): int
    {
        $sql = sprintf('SELECT COUNT(*) AS c FROM %s', $this->db->wrap($this->table));
        if ($this->wheres !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }

        return (int) $this->db->value($sql, $this->params);
    }

    /** @param array<string, mixed> $data */
    public function update(array $data): int
    {
        if ($this->wheres === []) {
            throw new \RuntimeException('Refusing to run UPDATE without WHERE.');
        }
        $sets = [];
        $params = [];
        foreach ($data as $column => $value) {
            $key = 's' . ($this->paramIndex++);
            $sets[] = $this->db->wrap($column) . ' = :' . $key;
            $params[$key] = $value;
        }
        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $this->db->wrap($this->table),
            implode(', ', $sets),
            implode(' AND ', $this->wheres)
        );

        return $this->db->query($sql, array_merge($params, $this->params))->rowCount();
    }

    public function delete(): int
    {
        if ($this->wheres === []) {
            throw new \RuntimeException('Refusing to run DELETE without WHERE.');
        }
        $sql = sprintf(
            'DELETE FROM %s WHERE %s',
            $this->db->wrap($this->table),
            implode(' AND ', $this->wheres)
        );

        return $this->db->query($sql, $this->params)->rowCount();
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int|string
    {
        return $this->db->insert($this->table, $data);
    }
}
