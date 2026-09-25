<?php

declare(strict_types=1);

namespace IRJalali\App\Repositories;

use IRJalali\Core\Database\Database;

final class MenuRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function createMenu(string $slug, string $name, string $location = 'primary'): int
    {
        $existing = $this->db->table('menus')->where('slug', $slug)->first();
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        $now = date('Y-m-d H:i:s');

        return (int) $this->db->insert('menus', [
            'slug' => $slug,
            'name' => $name,
            'location' => $location,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @param array<string, mixed> $item */
    public function addItem(int $menuId, array $item): int
    {
        $now = date('Y-m-d H:i:s');

        return (int) $this->db->insert('menu_items', [
            'menu_id' => $menuId,
            'parent_id' => $item['parent_id'] ?? null,
            'title' => $item['title'],
            'type' => $item['type'] ?? 'custom',
            'url' => $item['url'] ?? null,
            'reference_type' => $item['reference_type'] ?? null,
            'reference_id' => $item['reference_id'] ?? null,
            'ordering' => $item['ordering'] ?? 0,
            'target' => $item['target'] ?? '_self',
            'css_class' => $item['css_class'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function findByLocation(string $location): ?array
    {
        return $this->db->table('menus')->where('location', $location)->orderBy('id')->first();
    }

    /** @return list<array<string, mixed>> nested tree */
    /** @return list<array<string, mixed>> */
    public function listMenus(): array
    {
        return $this->db->table('menus')->orderBy('id')->get();
    }

    /** @return array<string, mixed>|null */
    public function findMenu(int $id): ?array
    {
        return $this->db->table('menus')->where('id', $id)->first();
    }

    public function updateMenu(int $id, string $name, string $location): bool
    {
        return $this->db->table('menus')->where('id', $id)->update([
            'name' => $name,
            'location' => $location,
            'updated_at' => date('Y-m-d H:i:s'),
        ]) > 0;
    }

    public function deleteMenu(int $id): bool
    {
        $this->db->table('menu_items')->where('menu_id', $id)->delete();

        return $this->db->table('menus')->where('id', $id)->delete() > 0;
    }

    /** @return array<string, mixed>|null */
    public function findItem(int $id): ?array
    {
        return $this->db->table('menu_items')->where('id', $id)->first();
    }

    /** @param array<string, mixed> $item */
    public function updateItem(int $id, array $item): bool
    {
        $allowed = ['parent_id', 'title', 'type', 'url', 'reference_type', 'reference_id', 'ordering', 'target', 'css_class'];
        $update = ['updated_at' => date('Y-m-d H:i:s')];
        foreach ($allowed as $column) {
            if (array_key_exists($column, $item)) {
                $update[$column] = $item[$column];
            }
        }

        return $this->db->table('menu_items')->where('id', $id)->update($update) > 0;
    }

    public function deleteItem(int $id): bool
    {
        $this->db->table('menu_items')->where('parent_id', $id)->update(['parent_id' => null]);

        return $this->db->table('menu_items')->where('id', $id)->delete() > 0;
    }

    /** @return list<array<string, mixed>> */
    public function flatItems(int $menuId): array
    {
        return $this->db->table('menu_items')
            ->where('menu_id', $menuId)
            ->orderBy('ordering')
            ->orderBy('id')
            ->get();
    }

    public function nextOrdering(int $menuId): int
    {
        $row = $this->db->select(
            'SELECT COALESCE(MAX(ordering), 0) AS m FROM menu_items WHERE menu_id = :id',
            ['id' => $menuId]
        );

        return ((int) ($row[0]['m'] ?? 0)) + 1;
    }

    public function tree(int $menuId): array
    {
        $rows = $this->db->table('menu_items')
            ->where('menu_id', $menuId)
            ->orderBy('ordering')
            ->orderBy('id')
            ->get();
        $byId = [];
        foreach ($rows as $row) {
            $row['children'] = [];
            $byId[$row['id']] = $row;
        }
        $tree = [];
        foreach ($byId as $id => $item) {
            if (!empty($item['parent_id']) && isset($byId[$item['parent_id']])) {
                $byId[$item['parent_id']]['children'][] = &$byId[$id];
            } else {
                $tree[] = &$byId[$id];
            }
        }

        return $tree;
    }
}
