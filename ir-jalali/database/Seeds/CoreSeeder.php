<?php

declare(strict_types=1);

namespace IRJalali\Database\Seeds;

use IRJalali\Core\Database\Database;

/**
 * Seeds system roles, permissions, post types, statuses and defaults.
 * Runs once during installation (and in tests for isolation).
 */
final class CoreSeeder
{
    public function __construct(private readonly Database $db)
    {
    }

    public function run(): void
    {
        $this->db->transaction(function (): void {
            $this->seedRoles();
            $this->seedPermissions();
            $this->seedPostTypes();
            $this->seedPostStatuses();
            $this->seedSite();
        });
    }

    private function seedRoles(): void
    {
        $roles = [
            ['slug' => 'super_admin', 'name' => 'مدیر ارشد', 'description' => 'دسترسی کامل به همه‌چیز', 'is_system' => 1],
            ['slug' => 'administrator', 'name' => 'مدیر', 'description' => 'مدیریت کامل سایت', 'is_system' => 1],
            ['slug' => 'editor', 'name' => 'ویراستار', 'description' => 'مدیریت محتوا', 'is_system' => 1],
            ['slug' => 'author', 'name' => 'نویسنده', 'description' => 'نوشتن و انتشار', 'is_system' => 1],
            ['slug' => 'contributor', 'name' => 'مشارکت‌کننده', 'description' => 'نوشتن بدون انتشار', 'is_system' => 1],
            ['slug' => 'subscriber', 'name' => 'مشترک', 'description' => 'کاربر عادی', 'is_system' => 1],
        ];
        foreach ($roles as $role) {
            if ($this->db->table('roles')->where('slug', $role['slug'])->first() === null) {
                $role['created_at'] = $this->now();
                $role['updated_at'] = $role['created_at'];
                $this->db->insert('roles', $role);
            }
        }
    }

    private function seedPermissions(): void
    {
        $permissions = [
            ['slug' => 'dashboard.view', 'name' => 'مشاهده پیشخوان', 'group' => 'dashboard'],
            ['slug' => 'posts.view', 'name' => 'مشاهده نوشته‌ها', 'group' => 'posts'],
            ['slug' => 'posts.create', 'name' => 'ایجاد نوشته', 'group' => 'posts'],
            ['slug' => 'posts.edit', 'name' => 'ویرایش نوشته', 'group' => 'posts'],
            ['slug' => 'posts.delete', 'name' => 'حذف نوشته', 'group' => 'posts'],
            ['slug' => 'posts.publish', 'name' => 'انتشار نوشته', 'group' => 'posts'],
            ['slug' => 'pages.view', 'name' => 'مشاهده برگه‌ها', 'group' => 'pages'],
            ['slug' => 'pages.create', 'name' => 'ایجاد برگه', 'group' => 'pages'],
            ['slug' => 'pages.edit', 'name' => 'ویرایش برگه', 'group' => 'pages'],
            ['slug' => 'pages.delete', 'name' => 'حذف برگه', 'group' => 'pages'],
            ['slug' => 'pages.publish', 'name' => 'انتشار برگه', 'group' => 'pages'],
            ['slug' => 'media.view', 'name' => 'مشاهده رسانه', 'group' => 'media'],
            ['slug' => 'media.upload', 'name' => 'بارگذاری رسانه', 'group' => 'media'],
            ['slug' => 'media.delete', 'name' => 'حذف رسانه', 'group' => 'media'],
            ['slug' => 'menus.manage', 'name' => 'مدیریت فهرست‌ها', 'group' => 'menus'],
            ['slug' => 'forms.view', 'name' => 'مشاهده فرم‌ها', 'group' => 'forms'],
            ['slug' => 'forms.manage', 'name' => 'مدیریت فرم‌ها', 'group' => 'forms'],
            ['slug' => 'forms.delete', 'name' => 'حذف فرم‌ها', 'group' => 'forms'],
            ['slug' => 'users.view', 'name' => 'مشاهده کاربران', 'group' => 'users'],
            ['slug' => 'users.create', 'name' => 'ایجاد کاربر', 'group' => 'users'],
            ['slug' => 'users.edit', 'name' => 'ویرایش کاربر', 'group' => 'users'],
            ['slug' => 'users.delete', 'name' => 'حذف کاربر', 'group' => 'users'],
            ['slug' => 'settings.manage', 'name' => 'مدیریت تنظیمات', 'group' => 'settings'],
            ['slug' => 'logs.view', 'name' => 'مشاهده گزارش‌ها', 'group' => 'logs'],
            ['slug' => 'themes.manage', 'name' => 'مدیریت قالب‌ها', 'group' => 'themes'],
            ['slug' => 'plugins.manage', 'name' => 'مدیریت افزونه‌ها', 'group' => 'plugins'],
            ['slug' => 'api.access', 'name' => 'دسترسی API', 'group' => 'api'],
        ];

        $permIds = [];
        foreach ($permissions as $permission) {
            $existing = $this->db->table('permissions')->where('slug', $permission['slug'])->first();
            if ($existing === null) {
                $permission['created_at'] = $this->now();
                $permission['updated_at'] = $permission['created_at'];
                $permIds[$permission['slug']] = $this->db->insert('permissions', $permission);
            } else {
                $permIds[$permission['slug']] = $existing['id'];
            }
        }

        $map = [
            'administrator' => array_column($permissions, 'slug'),
            'editor' => ['dashboard.view', 'posts.view', 'posts.create', 'posts.edit', 'posts.delete', 'posts.publish', 'pages.view', 'pages.create', 'pages.edit', 'pages.delete', 'pages.publish', 'media.view', 'media.upload', 'menus.manage', 'forms.view', 'forms.manage'],
            'author' => ['dashboard.view', 'posts.view', 'posts.create', 'posts.edit', 'posts.publish', 'media.view', 'media.upload'],
            'contributor' => ['dashboard.view', 'posts.view', 'posts.create', 'posts.edit', 'media.view'],
            'subscriber' => ['dashboard.view'],
        ];

        foreach ($map as $roleSlug => $slugs) {
            $role = $this->db->table('roles')->where('slug', $roleSlug)->first();
            if ($role === null) {
                continue;
            }
            foreach ($slugs as $slug) {
                $exists = $this->db->table('role_permissions')
                    ->where('role_id', $role['id'])
                    ->where('permission_id', $permIds[$slug])
                    ->first();
                if ($exists === null) {
                    $this->db->insert('role_permissions', [
                        'role_id' => $role['id'],
                        'permission_id' => $permIds[$slug],
                    ]);
                }
            }
        }
    }

    private function seedPostTypes(): void
    {
        $types = [
            ['slug' => 'page', 'name' => 'برگه', 'icon' => 'page', 'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'template', 'seo'], 'settings' => ['hierarchical' => true, 'has_archive' => false], 'is_system' => 1],
            ['slug' => 'post', 'name' => 'نوشته', 'icon' => 'post', 'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'taxonomy', 'seo'], 'settings' => ['hierarchical' => false, 'has_archive' => true], 'is_system' => 1],
            ['slug' => 'attachment', 'name' => 'پیوست', 'icon' => 'media', 'supports' => ['title'], 'settings' => [], 'is_system' => 1],
        ];
        foreach ($types as $type) {
            if ($this->db->table('post_types')->where('slug', $type['slug'])->first() === null) {
                $type['supports'] = json_encode($type['supports'], JSON_UNESCAPED_UNICODE);
                $type['settings'] = json_encode($type['settings'], JSON_UNESCAPED_UNICODE);
                $type['created_at'] = $this->now();
                $type['updated_at'] = $type['created_at'];
                $this->db->insert('post_types', $type);
            }
        }
    }

    private function seedPostStatuses(): void
    {
        $statuses = [
            'draft' => 'پیش‌نویس',
            'pending' => 'در انتظار بررسی',
            'published' => 'منتشرشده',
            'scheduled' => 'زمان‌بندی‌شده',
            'private' => 'خصوصی',
            'trash' => 'زباله‌دان',
        ];
        foreach ($statuses as $slug => $name) {
            if ($this->db->table('post_statuses')->where('slug', $slug)->first() === null) {
                $this->db->insert('post_statuses', [
                    'slug' => $slug,
                    'name' => $name,
                    'created_at' => $this->now(),
                    'updated_at' => $this->now(),
                ]);
            }
        }
    }

    private function seedSite(): void
    {
        if ($this->db->table('sites')->count() === 0) {
            $this->db->insert('sites', [
                'name' => 'سایت من',
                'domain' => null,
                'locale' => 'fa_IR',
                'timezone' => 'Asia/Tehran',
                'calendar' => 'jalali',
                'is_default' => 1,
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ]);
        }
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
