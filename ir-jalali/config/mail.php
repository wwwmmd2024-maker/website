<?php

declare(strict_types=1);

use IRJalali\Core\Config\Env;

return [
    'from_address' => Env::get('MAIL_FROM_ADDRESS', 'no-reply@example.com'),
    'from_name' => Env::get('MAIL_FROM_NAME', 'IR-Jalali'),
    // SMTP transport ships with the Notification module (Part 2). Null driver logs only.
    'driver' => Env::get('MAIL_DRIVER', 'log'),
    'host' => Env::get('MAIL_HOST', '127.0.0.1'),
    'port' => (int) Env::get('MAIL_PORT', 587),
    'username' => Env::get('MAIL_USERNAME', ''),
    'password' => Env::get('MAIL_PASSWORD', ''),
    'encryption' => Env::get('MAIL_ENCRYPTION', 'tls'),
];
