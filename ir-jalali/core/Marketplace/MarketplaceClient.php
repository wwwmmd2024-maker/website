<?php

declare(strict_types=1);

namespace IRJalali\Core\Marketplace;

use IRJalali\Core\Plugins\HttpClient;

/**
 * Facade over the configured provider (local by default).
 */
final class MarketplaceClient
{
    private MarketplaceProvider $provider;

    public function __construct(HttpClient $http, string $localPath, ?string $remoteUrl = null)
    {
        $this->provider = ($remoteUrl !== null && $remoteUrl !== '')
            ? new RemoteMarketplaceProvider($remoteUrl, $http)
            : new LocalMarketplaceProvider($localPath);
    }

    public function provider(): MarketplaceProvider
    {
        return $this->provider;
    }

    public function searchPlugins(string $query = '', int $page = 1, int $perPage = 20): array
    {
        return $this->provider->search('plugin', $query, $page, $perPage);
    }

    public function searchThemes(string $query = '', int $page = 1, int $perPage = 20): array
    {
        return $this->provider->search('theme', $query, $page, $perPage);
    }

    public function latestPluginRelease(string $slug, ?string $pinned = null): ?array
    {
        return $this->provider->latestRelease('plugin', $slug, $pinned);
    }

    public function latestThemeRelease(string $slug, ?string $pinned = null): ?array
    {
        return $this->provider->latestRelease('theme', $slug, $pinned);
    }

    public function download(string $url, string $destination): bool
    {
        return $this->provider->download($url, $destination);
    }
}
