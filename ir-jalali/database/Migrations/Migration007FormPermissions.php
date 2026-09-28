<?php

declare(strict_types=1);

namespace IRJalali\Database\Migrations;

use IRJalali\Core\Database\Migration;
use IRJalali\Core\Database\Schema\Schema;

/**
 * Adds form-builder permissions and grants them to administrator/editor roles.
 * (Fresh installs receive the same rows from CoreSeeder.)
 */
final class Migration007FormPermissions extends Migration
{
    /** @var list<array{slug: string, name: string, group: string}> */
    private const PERMISSIONS = [
        ['slug' => 'forms.view', 'name' => 'مشاهده فرم‌ها', 'group' => 'forms'],
        ['slug' => 'forms.manage', 'name' => 'مدیریت فرم‌ها', 'group' => 'forms'],
        ['slug' => 'forms.delete', 'name' => 'حذف فرم‌ها', 'group' => 'forms'],
    ];

    /** @var array<string, list<string>> */
    private const GRANTS = [
        'administrator' => ['forms.view', 'forms.manage', 'forms.delete'],
        'editor' => ['forms.view', 'forms.manage'],
    ];

    public function up(Schema $schema): void
    {
        $db = $schema->db();
        $now = date('Y-m-d H:i:s');
        $permIds = [];
        foreach (self::PERMISSIONS as $permission) {
            $existing = $db->table('permissions')->where('slug', $permission['slug'])->first();
            if ($existing !== null) {
                $permIds[$permission['slug']] = (int) $existing['id'];
                continue;
            }
            $permIds[$permission['slug']] = (int) $db->insert('permissions', [
                'slug' => $permission['slug'],
                'name' => $permission['name'],
                'group' => $permission['group'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (self::GRANTS as $roleSlug => $slugs) {
            $role = $db->table('roles')->where('slug', $roleSlug)->first();
            if ($role === null) {
                continue;
            }
            foreach ($slugs as $slug) {
                $exists = $db->table('role_permissions')
                    ->where('role_id', $role['id'])
                    ->where('permission_id', $permIds[$slug])
                    ->first();
                if ($exists === null) {
                    $db->insert('role_permissions', [
                        'role_id' => $role['id'],
                        'permission_id' => $permIds[$slug],
                    ]);
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        $db = $schema->db();
        foreach (self::PERMISSIONS as $permission) {
            $row = $db->table('permissions')->where('slug', $permission['slug'])->first();
            if ($row === null) {
                continue;
            }
            $db->table('role_permissions')->where('permission_id', $row['id'])->delete();
            $db->table('permissions')->where('id', $row['id'])->delete();
        }
    }
}
