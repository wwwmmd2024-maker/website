<?php

declare(strict_types=1);

namespace IRJalali\Core\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * PDO connection factory. MySQL is the production driver;
 * SQLite exists ONLY for local development / automated tests.
 */
final class Connection
{
    private ?PDO $pdo = null;

    /** @param array<string, mixed> $config */
    private function __construct(private readonly array $config)
    {
    }

    /** @param array<string, mixed> $config */
    public static function fromConfig(array $config): self
    {
        return new self($config);
    }

    public function driver(): string
    {
        return (string) ($this->config['driver'] ?? 'mysql');
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $driver = $this->driver();
        try {
            $this->pdo = match ($driver) {
                'mysql' => $this->connectMysql(),
                'sqlite' => $this->connectSqlite(),
                default => throw new RuntimeException("Unsupported database driver [{$driver}]."),
            };
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }

        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        if ($driver === 'sqlite') {
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->pdo->exec('PRAGMA journal_mode = WAL');
        }

        return $this->pdo;
    }

    private function connectMysql(): PDO
    {
        $host = (string) ($this->config['host'] ?? '127.0.0.1');
        $port = (int) ($this->config['port'] ?? 3306);
        $name = (string) ($this->config['database'] ?? '');
        $charset = (string) ($this->config['charset'] ?? 'utf8mb4');
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        return new PDO($dsn, (string) ($this->config['username'] ?? ''), (string) ($this->config['password'] ?? ''), [
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE "
                . ($this->config['collation'] ?? 'utf8mb4_unicode_ci'),
        ]);
    }

    private function connectSqlite(): PDO
    {
        $path = (string) ($this->config['database'] ?? ':memory:');
        if ($path !== ':memory:') {
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        return new PDO('sqlite:' . $path);
    }

    public static function test(array $config): bool
    {
        try {
            self::fromConfig($config)->pdo()->query('SELECT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
