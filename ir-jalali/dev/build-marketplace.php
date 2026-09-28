<?php

declare(strict_types=1);

/** Temp build script: creates marketplace/packages/*.zip + catalog.json from plugins/. */

$base = dirname(__DIR__);
$pluginsDir = $base . '/plugins';
$marketDir = $base . '/marketplace';
$pkgDir = $marketDir . '/packages';

if (!is_dir($pkgDir)) {
    mkdir($pkgDir, 0775, true);
}

$catalog = [];
foreach (scandir($pluginsDir) ?: [] as $entry) {
    if ($entry === '.' || $entry === '..') {
        continue;
    }
    $manifestPath = $pluginsDir . '/' . $entry . '/plugin.json';
    if (!is_file($manifestPath)) {
        continue;
    }
    $manifest = json_decode((string) file_get_contents($manifestPath), true);
    if (!is_array($manifest) || !isset($manifest['slug'], $manifest['version'])) {
        continue;
    }
    $slug = (string) $manifest['slug'];
    $version = (string) $manifest['version'];
    $zipPath = $pkgDir . '/' . $slug . '-' . $version . '.zip';
    if (is_file($zipPath)) {
        unlink($zipPath);
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        fwrite(STDERR, "zip create failed: {$zipPath}\n");
        exit(1);
    }
    $root = $pluginsDir . '/' . $entry;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($files as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $relative = $entry . '/' . substr($file->getPathname(), strlen($root) + 1);
        $zip->addFile($file->getPathname(), $relative);
    }
    $zip->close();

    $catalog[] = [
        'type' => 'plugin',
        'slug' => $slug,
        'name' => (string) ($manifest['name'] ?? $slug),
        'description' => (string) ($manifest['description'] ?? ''),
        'version' => $version,
        'author' => (string) ($manifest['author'] ?? ''),
        'category' => (string) ($manifest['category'] ?? 'general'),
        'downloads' => 0,
        'changelog' => 'انتشار اولیه ' . $version,
        'requires' => (array) ($manifest['requires'] ?? []),
    ];
    echo "packed {$slug} v{$version} -> " . basename($zipPath) . "\n";
}

usort($catalog, fn (array $a, array $b): int => strcmp($a['slug'], $b['slug']));
file_put_contents(
    $marketDir . '/catalog.json',
    json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n"
);
echo 'catalog items: ' . count($catalog) . "\n";
