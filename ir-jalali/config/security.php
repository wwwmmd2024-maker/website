<?php

declare(strict_types=1);

use IRJalali\Core\Config\Env;

return [
    'login_max_attempts' => (int) Env::get('LOGIN_MAX_ATTEMPTS', 5),
    'login_decay_seconds' => (int) Env::get('LOGIN_DECAY_SECONDS', 300),
    'api_max_attempts' => 60,
    'api_decay_seconds' => 60,
    'upload_max_kb' => (int) Env::get('UPLOAD_MAX_KB', 10240),
    'allowed_upload_extensions' => [
        'jpg', 'jpeg', 'png', 'webp', 'avif', 'svg', 'gif',
        'mp4', 'webm', 'pdf', 'docx', 'xlsx', 'zip',
    ],
];
