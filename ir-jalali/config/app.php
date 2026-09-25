<?php

declare(strict_types=1);

use IRJalali\Core\Config\Env;

return [
    'name' => Env::get('APP_NAME', 'IR-Jalali'),
    'version' => '1.0.0',
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => (bool) Env::get('APP_DEBUG', false),
    'key' => Env::get('APP_KEY', ''),
    'url' => rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/'),
    'timezone' => Env::get('APP_TIMEZONE', 'Asia/Tehran'),
    'locale' => Env::get('APP_LOCALE', 'fa_IR'),
    'fallback_locale' => 'en_US',
    'calendar' => Env::get('APP_CALENDAR', 'jalali'),
    'admin_path' => Env::get('ADMIN_PATH', 'admin'),
];
