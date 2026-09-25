<?php

declare(strict_types=1);

namespace IRJalali\Core\Marketplace;

/**
 * Real provider backed by the local packages directory:
 *   marketplace/catalog.json   (items index)
 *   marketplace/packages/<slug>-<version>.zip
 */
final class LocalMarketplaceProvider implements MarketplaceProvider
{
    public function __construct(private readonly string $basePath)
    {
    }

    public function search(string $type, string $query = '', int $page = 1, int $perPage = 20): array
    {
        $items = array_filter(
            $this->catalog(),
            fn (array $item): bool => ($item['type'] ?? 'plugin') === $type
                && ($query === '' || stripos(($item['name'] ?? '') . ' ' . ($item['description'] ?? ''), $query) !== false)
        );
        usort($items, fn ($a, $b): int => ($b['downloads'] ?? 0) <=> ($a['downloads'] ?? 0));
        $offset = max(0, ($page - 1) * $perPage);

        return array_values(array_slice($items, $offset, $perPage));
    }

    public function latestRelease(string $type, string $slug, ?string $pinned = null): ?array
    {
        foreach ($this->catalog() as $item) {
            if (($item['type'] ?? 'plugin') !== $type || ($item['slug'] ?? '') !== $slug) {
                continue;
            }
            $version = $pinned ?? (string) ($item['version'] ?? '');
            $file = $this->basePath . '/packages/' . $slug . '-' . $version . '.zip';
            if (!is_file($file)) {
                return null;
            }

            return [
                'slug' => $slug,
                'version' => $version,
                'download_url' => 'local://' . $slug . '-' . $version . '.zip',
                'checksum' => hash_file('sha256', $file) ?: '',
                'requires' => (array) ($item['requires'] ?? []),
                'changelog' => (string) ($item['changelog'] ?? ''),
            ];
        }

        return null;
    }

    public function download(string $url, string $destination): bool
    {
        if (!str_starts_with($url, 'local://')) {
            return false;
        }
        $file = $this->basePath . '/packages/' . basename(substr($url, 8));
        if (!is_file($file)) {
            return false;
        }

        return copy($file, $destination);
    }

    public function verifyLicense(string $type, string $slug, string $key): ?array
    {
        // Local packages are license-free; commercial verification lives in the remote provider.
        return ['valid' => true, 'plan' => 'local', 'expires_at' => null];
    }

    /** @return list<array<string, mixed>> */
    private function catalog(): array
    {
        $file = $this->basePath . '/catalog.json';
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? array_values($data) : [];
    }
}
