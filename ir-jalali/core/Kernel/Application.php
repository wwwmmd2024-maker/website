<?php

declare(strict_types=1);

namespace IRJalali\Core\Kernel;

use IRJalali\Core\Config\Config;
use IRJalali\Core\Config\Env;

/**
 * Application kernel: bootstraps configuration, container and core services.
 */
final class Application
{
    private static ?self $instance = null;

    private function __construct(
        private readonly string $basePath,
        private readonly Container $container,
        private readonly Config $config,
    ) {
    }

    public static function boot(string $basePath): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        Env::load($basePath . '/.env');

        $config = new Config($basePath . '/config');
        $container = new Container();

        $app = new self($basePath, $container, $config);

        $container->instance(self::class, $app);
        $container->instance(Container::class, $container);
        $container->instance(Config::class, $config);

        ServiceProviders::register($container, $config, $basePath);

        date_default_timezone_set($config->get('app.timezone', 'Asia/Tehran'));

        self::$instance = $app;

        return $app;
    }

    /** Only for isolated CLI tests. */
    public static function reset(): void
    {
        self::$instance = null;
    }

    public static function get(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Application is not booted.');
        }

        return self::$instance;
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function config(): Config
    {
        return $this->config;
    }

    /** @template T @param class-string<T> $abstract @return T */
    public function make(string $abstract): mixed
    {
        return $this->container->make($abstract);
    }

    public function isInstalled(): bool
    {
        return is_file($this->basePath('storage/install.lock'));
    }

    public function version(): string
    {
        return $this->config->get('app.version', '1.0.0');
    }
}
