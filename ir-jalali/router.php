<?php

declare(strict_types=1);

/**
 * Router for PHP's built-in server (development/preview only):
 *   php -S 127.0.0.1:8000 router.php
 */

$uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$publicFile = __DIR__ . '/public' . $uri;
if ($uri !== '/' && is_file($publicFile)) {
    // Serve directly: `return false` would make php -S look in CWD, not public/.
    $ext = strtolower(pathinfo($publicFile, PATHINFO_EXTENSION));
    $mime = [
        'css' => 'text/css; charset=utf-8', 'js' => 'application/javascript; charset=utf-8',
        'json' => 'application/json', 'png' => 'image/png', 'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp',
        'svg' => 'image/svg+xml', 'woff' => 'font/woff', 'woff2' => 'font/woff2',
        'html' => 'text/html; charset=utf-8', 'ico' => 'image/x-icon',
    ][$ext] ?? (mime_content_type($publicFile) ?: 'application/octet-stream');
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($publicFile));
    readfile($publicFile);

    return true;
}

// Uploaded media is served from storage in M1 (Part 2 adds a media proxy).
$storageFile = __DIR__ . $uri;
if (str_starts_with($uri, '/storage/') && is_file($storageFile)) {
    $mime = mime_content_type($storageFile) ?: 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($storageFile));
    readfile($storageFile);

    return true;
}

require __DIR__ . '/public/index.php';
