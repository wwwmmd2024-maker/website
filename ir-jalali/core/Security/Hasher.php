<?php

declare(strict_types=1);

namespace IRJalali\Core\Security;

/**
 * Password hashing (Argon2id preferred, Bcrypt fallback) + secure tokens.
 */
final class Hasher
{
    public function hash(string $password): string
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;

        return password_hash($password, $algo);
    }

    public function verify(string $password, string $hash): bool
    {
        return $hash !== '' && password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;

        return password_needs_rehash($hash, $algo);
    }

    public function token(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public function sha256(string $value): string
    {
        return hash('sha256', $value);
    }
}
