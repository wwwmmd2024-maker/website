<?php

declare(strict_types=1);

use IRJalali\Core\Config\Env;

return [
    'driver' => Env::get('CACHE_DRIVER', 'file'),
    'default_ttl' => (int) Env::get('CACHE_TTL', 3600),
];
