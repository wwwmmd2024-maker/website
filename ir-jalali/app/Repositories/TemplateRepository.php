<?php

declare(strict_types=1);

namespace IRJalali\App\Repositories;

use IRJalali\Core\Database\Database;

/**
 * Builder templates (page/single/archive/header/footer/search/404).
 */
final class TemplateRepository
{
    public const TYPES = ['page', 'single', 'archive', 'header', 'footer', 'search', 'error404'];

    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function list(?string $type = null): array
    {
        $query = $this->db->table('templates');
        if ($type !== null && $type !== '') {
            $query->where('type', $type);
        }

        return $query->orderBy('id', 'DESC')->get();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->table('templates')->where('id', $id)->first();
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->db->table('templates')->where('slug', $slug)->first();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        return (int) $this->db->insert('templates', [
            'slug' => $data['slug'],
            'name' => $data['name'],
            'type' => $data['type'] ?? 'page',
            'content_json' => $data['content_json'] ?? null,
            'is_default' => !empty($data['is_default']) ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $allowed = ['slug', 'name', 'type', 'is_default'];
        $update = ['updated_at' => date('Y-m-d H:i:s')];
        foreach ($allowed as $column) {
            if (array_key_exists($column, $data)) {
                $update[$column] = $column === 'is_default' ? (!empty($data[$column]) ? 1 : 0) : $data[$column];
            }
        }

        return $this->db->table('templates')->where('id', $id)->update($update) > 0;
    }

    public function delete(int $id): bool
    {
        return $this->db->table('templates')->where('id', $id)->delete() > 0;
    }

    public function slugTaken(string $slug, ?int $ignoreId = null): bool
    {
        foreach ($this->db->table('templates')->where('slug', $slug)->get() as $row) {
            if ($ignoreId === null || (int) $row['id'] !== $ignoreId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ensures the global site header/footer templates exist (idempotent).
     */
    public function ensureSiteParts(): void
    {
        $parts = [
            ['slug' => 'site-header', 'name' => 'سربرگ سایت', 'type' => 'header'],
            ['slug' => 'site-footer', 'name' => 'پانوشت سایت', 'type' => 'footer'],
        ];
        foreach ($parts as $part) {
            if ($this->findBySlug($part['slug']) === null) {
                $this->create($part + ['is_default' => 1]);
            }
        }
    }
}
