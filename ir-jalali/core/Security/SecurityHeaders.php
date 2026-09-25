<?php

declare(strict_types=1);

namespace IRJalali\Core\Security;

/**
 * Applies baseline security headers to every response.
 */
final class SecurityHeaders
{
    /** @return array<string, string> */
    public static function all(bool $isAdminOrInstall = false): array
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ];
        if ($isAdminOrInstall) {
            // Strict CSP for first-party admin/installer pages (no external assets there).
            $headers['Content-Security-Policy'] = "default-src 'self'; img-src 'self' data:; "
                . "style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; "
                . "font-src 'self' data:; frame-ancestors 'self'; base-uri 'self'; form-action 'self'";
        }

        return $headers;
    }
}
