<?php

declare(strict_types=1);

namespace IRJalali\Core\Security;

/**
 * Hardened native PHP session handling.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (headers_sent()) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_name('IRJSESSION');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');

        session_start();

        // Session fixation defence: rotate ID at most every 5 minutes.
        $now = time();
        if (empty($_SESSION['_irj_rotated_at']) || $now - (int) $_SESSION['_irj_rotated_at'] > 300) {
            session_regenerate_id(true);
            $_SESSION['_irj_rotated_at'] = $now;
        }
    }

    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['_irj_rotated_at'] = time();
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        if (!headers_sent() && ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
