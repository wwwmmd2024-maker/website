<?php

declare(strict_types=1);

namespace IRJalali\Core\Database\Schema;

/**
 * Fluent table blueprint compiled to MySQL or SQLite DDL.
 */
final class Blueprint
{
    /** @var list<array<string, mixed>> */
    public array $columns = [];

    /** @var list<array<string, mixed>> */
    public array $indexes = [];

    /** @var list<array<string, mixed>> */
    public array $foreignKeys = [];

    public function __construct(public readonly string $table)
    {
    }

    public function id(string $name = 'id'): self
    {
        $this->columns[] = ['name' => $name, 'type' => 'id'];

        return $this;
    }

    public function uuid(string $name = 'uuid'): self
    {
        $this->columns[] = ['name' => $name, 'type' => 'uuid'];

        return $this;
    }

    public function string(string $name, int $length = 191, bool $nullable = false, mixed $default = null): self
    {
        $this->columns[] = compact('name', 'length', 'nullable', 'default') + ['type' => 'string'];

        return $this;
    }

    public function text(string $name, bool $nullable = true): self
    {
        $this->columns[] = ['name' => $name, 'type' => 'text', 'nullable' => $nullable];

        return $this;
    }

    public function longText(string $name, bool $nullable = true): self
    {
        $this->columns[] = ['name' => $name, 'type' => 'longtext', 'nullable' => $nullable];

        return $this;
    }

    public function json(string $name, bool $nullable = true): self
    {
        $this->columns[] = ['name' => $name, 'type' => 'json', 'nullable' => $nullable];

        return $this;
    }

    public function integer(string $name, bool $nullable = false, mixed $default = 0): self
    {
        $this->columns[] = ['name' => $name, 'type' => 'integer', 'nullable' => $nullable, 'default' => $default];

        return $this;
    }

    public function bigInteger(string $name, bool $nullable = false, mixed $default = 0): self
    {
        $this->columns[] = ['name' => $name, 'type' => 'biginteger', 'nullable' => $nullable, 'default' => $default];

        return $this;
    }

    public function boolean(string $name, bool $default = false): self
    {
        $this->columns[] = ['name' => $name, 'type' => 'boolean', 'nullable' => false, 'default' => $default];

        return $this;
    }

    public function decimal(string $name, int $precision = 12, int $scale = 2, bool $nullable = false, mixed $default = 0): self
    {
        $this->columns[] = compact('name', 'precision', 'scale', 'nullable', 'default') + ['type' => 'decimal'];

        return $this;
    }

    public function dateTime(string $name, bool $nullable = true): self
    {
        $this->columns[] = ['name' => $name, 'type' => 'datetime', 'nullable' => $nullable];

        return $this;
    }

    public function timestamps(): self
    {
        $this->columns[] = ['name' => 'created_at', 'type' => 'datetime', 'nullable' => true];
        $this->columns[] = ['name' => 'updated_at', 'type' => 'datetime', 'nullable' => true];

        return $this;
    }

    public function softDeletes(): self
    {
        $this->columns[] = ['name' => 'deleted_at', 'type' => 'datetime', 'nullable' => true];

        return $this;
    }

    public function unique(string ...$columns): self
    {
        $this->indexes[] = ['type' => 'unique', 'columns' => $columns];

        return $this;
    }

    public function index(string ...$columns): self
    {
        $this->indexes[] = ['type' => 'index', 'columns' => $columns];

        return $this;
    }

    public function foreign(string $column, string $references, string $on, string $onDelete = 'CASCADE'): self
    {
        $this->foreignKeys[] = compact('column', 'references', 'on', 'onDelete');

        return $this;
    }
}
