<?php

declare(strict_types=1);

return [
    // Local packages directory (catalog.json + packages/*.zip). Always active.
    'local_path' => null,
    // Remote marketplace base URL (/marketplace/api/v1/...). Null = local only.
    'remote_url' => $_ENV['MARKETPLACE_URL'] ?? null,
    'timeout' => 25,
];
