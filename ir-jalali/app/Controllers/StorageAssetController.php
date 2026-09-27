<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers;

use IRJalali\Core\Filesystem\Filesystem;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;

/**
 * Serves uploaded media through a jailed proxy so storage/uploads can live
 * OUTSIDE the document root on shared hosting (DocumentRoot = /public).
 *
 * Security: no "..", strict charset whitelist, realpath() jail inside the
 * uploads directory only, extension whitelist, nosniff header, and forced
 * download for anything that is not an inline-renderable image.
 */
final class StorageAssetController
{
    /** @var array<string, string> */
    private const MIME = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png', 'webp' => 'image/webp',
        'avif' => 'image/avif', 'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'mp4' => 'video/mp4', 'webm' => 'video/webm',
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'zip' => 'application/zip',
    ];

    /** Extensions browsers must download, never render inline. */
    private const FORCE_DOWNLOAD = ['pdf', 'docx', 'xlsx', 'zip', 'svg'];

    public function __construct(private readonly Filesystem $fs)
    {
    }

    public function show(Request $request): Response
    {
        $relative = ltrim(str_replace('\\', '/', (string) $request->route('file', '')), '/');
        if ($relative === '' || str_contains($relative, '..') || !preg_match('#^[a-zA-Z0-9_\-./ ]+$#', $relative)) {
            return Response::text('Not found.', 404);
        }

        $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        if (!isset(self::MIME[$ext])) {
            return Response::text('Not found.', 404);
        }

        $dir = $this->fs->path('storage/uploads');
        $realDir = realpath($dir);
        if ($realDir === false) {
            return Response::text('Not found.', 404);
        }

        $real = realpath($realDir . '/' . $relative);
        if ($real === false || !is_file($real) || !str_starts_with($real, $realDir . DIRECTORY_SEPARATOR)) {
            return Response::text('Not found.', 404);
        }

        $content = @file_get_contents($real);
        if ($content === false) {
            return Response::text('Not found.', 404);
        }

        $response = new Response($content, 200, [
            'Content-Type' => self::MIME[$ext],
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->withHeader('Cache-Control', 'public, max-age=31536000, immutable');
        $response->withHeader('ETag', '"' . sha1_file($real) . '"');
        if (in_array($ext, self::FORCE_DOWNLOAD, true)) {
            $name = rawurlencode(basename($relative));
            $response->withHeader('Content-Disposition', 'attachment; filename*=UTF-8\'\'' . $name);
        }

        return $response;
    }
}
