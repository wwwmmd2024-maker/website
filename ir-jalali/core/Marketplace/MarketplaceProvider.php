<?php

declare(strict_types=1);

namespace IRJalali\Core\Marketplace;

/**
 * Marketplace item provider contract. Local provider is fully real;
 * remote provider is code-ready and activates when a URL is configured.
 */
interface MarketplaceProvider
{
    /** @return list<array{slug: string, type: string, name: string, version: string, description: string, author: string, price: int, rating: float, downloads: int}> */
    public function search(string $type, string $query = '', int $page = 1, int $perPage = 20): array;

    /** @return array{slug: string, version: string, download_url: string, checksum: string, requires: array<string, string>, changelog: string}|null */
    public function latestRelease(string $type, string $slug, ?string $pinned = null): ?array;

    public function download(string $url, string $destination): bool;

    /** @return array{valid: bool, plan: ?string, expires_at: ?string}|null null = cannot verify offline */
    public function verifyLicense(string $type, string $slug, string $key): ?array;
}
