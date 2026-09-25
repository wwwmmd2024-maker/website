<?php

declare(strict_types=1);

namespace IRJalali\Core\Plugins;

/**
 * Plugin capability vocabulary. Declared in plugin.json, granted by the
 * administrator at activation (consent screen), enforced by PluginContext
 * brokers. Anything not granted throws — fail closed.
 */
final class Capabilities
{
    public const FILESYSTEM_READ = 'filesystem.read';
    public const FILESYSTEM_WRITE = 'filesystem.write';
    public const DATABASE_READ = 'database.read';
    public const DATABASE_WRITE = 'database.write';
    public const NETWORK_REQUEST = 'network.request';
    public const ADMIN_ACCESS = 'admin.access';
    public const USERS_READ = 'users.read';
    public const USERS_WRITE = 'users.write';
    public const SETTINGS_READ = 'settings.read';
    public const SETTINGS_WRITE = 'settings.write';

    public const ALL = [
        self::FILESYSTEM_READ,
        self::FILESYSTEM_WRITE,
        self::DATABASE_READ,
        self::DATABASE_WRITE,
        self::NETWORK_REQUEST,
        self::ADMIN_ACCESS,
        self::USERS_READ,
        self::USERS_WRITE,
        self::SETTINGS_READ,
        self::SETTINGS_WRITE,
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return self::ALL;
    }

    /** @return array<string, string> cap => Persian description */
    public static function describe(): array
    {
        return [
            self::FILESYSTEM_READ => 'خواندن فایل‌ها (محدود به پوشه افزونه)',
            self::FILESYSTEM_WRITE => 'نوشتن فایل (محدود به پوشه افزونه)',
            self::DATABASE_READ => 'خواندن دیتابیس',
            self::DATABASE_WRITE => 'نوشتن دیتابیس (فقط از API مجاز)',
            self::NETWORK_REQUEST => 'درخواست شبکه خروجی',
            self::ADMIN_ACCESS => 'افزودن صفحه مدیریتی',
            self::USERS_READ => 'خواندن کاربران',
            self::USERS_WRITE => 'مدیریت کاربران',
            self::SETTINGS_READ => 'خواندن تنظیمات',
            self::SETTINGS_WRITE => 'نوشتن تنظیمات',
        ];
    }
}
