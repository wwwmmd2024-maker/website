<?php

declare(strict_types=1);

namespace IRJalali\Core\Security;

/**
 * Synchronized-token CSRF protection backed by the hardened session.
 */
final class Csrf
{
    private const KEY = '_irj_csrf';

    public function token(): string
    {
        $this->ensureSession();
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::KEY];
    }

    public function field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($this->token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public function validate(?string $token): bool
    {
        $this->ensureSession();
        $expected = $_SESSION[self::KEY] ?? '';
        if (!is_string($expected) || $expected === '' || !is_string($token)) {
            return false;
        }

        return hash_equals($expected, $token);
    }

    public function rotate(): void
    {
        $this->ensureSession();
        $_SESSION[self::KEY] = bin2hex(random_bytes(32));
    }

    private function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            Session::start();
        }
    }
}
