<?php

declare(strict_types=1);

namespace IRJalali\Core\Kernel;

/**
 * Zero-dependency PSR-4 style autoloader (no Composer needed at runtime —
 * shared-hosting friendly).
 */
final class Autoloader
{
    /** @var array<string, string> prefix => relative dir */
    private static array $map = [
        'IRJalali\\Core\\' => 'core/',
        'IRJalali\\App\\' => 'app/',
        'IRJalali\\Database\\' => 'database/',
        'IRJalali\\Themes\\' => 'themes/',
        'IRJalali\\Plugins\\' => 'plugins/',
        'IRJalali\\Modules\\' => 'modules/',
    ];

    public static function register(string $basePath): void
    {
        spl_autoload_register(function (string $class) use ($basePath): void {
            foreach (self::$map as $prefix => $dir) {
                if (str_starts_with($class, $prefix)) {
                    $relative = substr($class, strlen($prefix));
                    $file = $basePath . '/' . $dir . str_replace('\\', '/', $relative) . '.php';
                    if (is_file($file)) {
                        require $file;
                    }

                    return;
                }
            }
        });
    }
}
