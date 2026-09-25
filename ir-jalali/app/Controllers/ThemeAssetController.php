<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers;

use IRJalali\Core\Config\Config;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Themes\ThemeManager;

/**
 * Serves theme assets (css/js/images/fonts) through a jailed proxy so
 * themes/ can live OUTSIDE the document root on shared hosting.
 */
final class ThemeAssetController
{
    private const array MIME = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'svg' => 'image/svg+xml',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'map' => 'application/json',
    ];

    public function __construct(
        private readonly ThemeManager $themes,
        private readonly Config $config,
    ) {
    }

    public function show(Request $request): Response
    {
        $slug = (string) $request->route('theme', '');
        $asset = (string) $request->route('file', '');
        $file = $this->themes->assetFile($slug, $asset);
        if ($file === null) {
            return Response::text('Not found.', 404);
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $content = @file_get_contents($file);
        if ($content === false) {
            return Response::text('Not found.', 404);
        }

        $cache = (int) $this->config->get('themes.asset_cache', 86400);
        $response = new Response($content, 200, ['Content-Type' => self::MIME[$ext] ?? 'application/octet-stream']);
        $response->withHeader('Cache-Control', 'public, max-age=' . $cache);
        $response->withHeader('ETag', '"' . sha1($slug . $asset . filemtime($file)) . '"');

        return $response;
    }
}
