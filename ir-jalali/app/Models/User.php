<?php

declare(strict_types=1);

namespace IRJalali\App\Models;

/**
 * Immutable User value object hydrated from the users table.
 */
final class User
{
    /** @param array<string, mixed> $row @param list<string> $roles */
    private function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $email,
        public readonly string $displayName,
        public readonly string $status,
        public readonly string $locale,
        public readonly ?string $avatar,
        public readonly ?string $lastLoginAt,
        public readonly ?string $createdAt,
        public readonly array $row,
        public readonly array $roles = [],
    ) {
    }

    /** @param array<string, mixed> $row @param list<string> $roles */
    public static function fromRow(array $row, array $roles = []): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['username'],
            (string) $row['email'],
            (string) ($row['display_name'] ?: $row['username']),
            (string) $row['status'],
            (string) ($row['locale'] ?? 'fa_IR'),
            $row['avatar'] ?? null,
            $row['last_login_at'] ?? null,
            $row['created_at'] ?? null,
            $row,
            $roles,
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasRole(string $slug): bool
    {
        return in_array($slug, $this->roles, true);
    }

    public function initial(): string
    {
        return mb_substr($this->displayName, 0, 1, 'UTF-8');
    }
}
