<?php

declare(strict_types=1);

namespace IRJalali\Core\Marketplace;

use IRJalali\Core\Plugins\HttpClient;

/**
 * Code-ready remote provider for /marketplace/api/v1/ endpoints.
 * Activates when config('marketplace.remote_url') is set.
 */
final class RemoteMarketplaceProvider implements MarketplaceProvider
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly HttpClient $http,
    ) {
    }

    public function search(string $type, string $query = '', int $page = 1, int $perPage = 20): array
    {
        $url = rtrim($this->baseUrl, '/') . '/marketplace/api/v1/items?' . http_build_query([
            'type' => $type, 'q' => $query, 'page' => $page, 'per_page' => $perPage,
        ]);
        $response = $this->http->get($url);
        if (!$response->ok()) {
            return [];
        }
        $data = $response->json();

        return is_array($data['data'] ?? null) ? $data['data'] : [];
    }

    public function latestRelease(string $type, string $slug, ?string $pinned = null): ?array
    {
        $url = rtrim($this->baseUrl, '/') . '/marketplace/api/v1/items/' . rawurlencode($type) . '/' . rawurlencode($slug) . '/release'
            . ($pinned !== null ? '?version=' . rawurlencode($pinned) : '');
        $response = $this->http->get($url);
        if (!$response->ok()) {
            return null;
        }
        $data = $response->json();
        if (!is_array($data['data'] ?? null)) {
            return null;
        }
        $release = $data['data'];
        if (empty($release['version']) || empty($release['download_url'])) {
            return null;
        }

        return [
            'slug' => $slug,
            'version' => (string) $release['version'],
            'download_url' => (string) $release['download_url'],
            'checksum' => (string) ($release['checksum'] ?? ''),
            'requires' => (array) ($release['requires'] ?? []),
            'changelog' => (string) ($release['changelog'] ?? ''),
        ];
    }

    public function download(string $url, string $destination): bool
    {
        return $this->http->download($url, $destination);
    }

    public function verifyLicense(string $type, string $slug, string $key): ?array
    {
        $url = rtrim($this->baseUrl, '/') . '/marketplace/api/v1/licenses/verify';
        try {
            $response = $this->http->post($url, ['type' => $type, 'slug' => $slug, 'key' => $key]);
        } catch (\Throwable) {
            return null; // offline → caller applies grace rules
        }
        if (!$response->ok()) {
            return null;
        }
        $data = $response->json();
        if (!is_array($data['data'] ?? null)) {
            return null;
        }

        return [
            'valid' => (bool) ($data['data']['valid'] ?? false),
            'plan' => $data['data']['plan'] ?? null,
            'expires_at' => $data['data']['expires_at'] ?? null,
        ];
    }
}
