<?php

declare(strict_types=1);

/** Dev router for `php -S`: serve real files from public/, route the rest through index.php. */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = dirname(__DIR__) . '/public' . $path;
if ($path !== '/' && is_file($file)) {
    return false; // Let the built-in server stream the static file.
}
require dirname(__DIR__) . '/public/index.php';
