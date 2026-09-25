<?php

declare(strict_types=1);

namespace IRJalali\Core\Licensing;

use IRJalali\Core\Database\Database;
use IRJalali\Core\Marketplace\MarketplaceClient;

/**
 * Commercial license lifecycle: activate → verify (cached) → grace → expire.
 * Keys are stored hashed; plaintext is never persisted.
 */
final class LicenseManager
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_INVALID = 'invalid';

    public function __construct(
        private readonly Database $db,
        private readonly MarketplaceClient $marketplace,
        private readonly int $graceDays = 14,
        private readonly int $verifyCacheHours = 24,
    ) {
    }

    /**
     * @return array{success: bool, status: string, message: string}
     */
    public function activate(string $type, string $slug, string $key, ?string $domain = null): array
    {
        $key = trim($key);
        if ($key === '' || strlen($key) > 190) {
            return ['success' => false, 'status' => self::STATUS_INVALID, 'message' => 'کلید نامعتبر است.'];
        }
        $verdict = $this->marketplace->provider()->verifyLicense($type, $slug, $key);
        if ($verdict === null) {
            return ['success' => false, 'status' => self::STATUS_INVALID, 'message' => 'سرور لایسنس در دسترس نیست؛ بعداً تلاش کنید.'];
        }
        $now = date('Y-m-d H:i:s');
        $data = [
            'key_hash' => hash('sha256', $key),
            'status' => $verdict['valid'] ? self::STATUS_ACTIVE : self::STATUS_INVALID,
            'plan' => $verdict['plan'],
            'domain' => $domain !== null ? mb_substr($domain, 0, 190) : null,
            'activated_at' => $now,
            'expires_at' => $verdict['expires_at'],
            'grace_until' => null,
            'last_checked_at' => $now,
        ];
        $existing = $this->row($type, $slug);
        if ($existing === null) {
            $this->db->insert('licenses', ['item_type' => $type, 'item_slug' => $slug, ...$data]);
        } else {
            $this->db->table('licenses')->where('id', $existing['id'])->update($data);
        }

        return $verdict['valid']
            ? ['success' => true, 'status' => self::STATUS_ACTIVE, 'message' => 'لایسنس فعال شد.']
            : ['success' => false, 'status' => self::STATUS_INVALID, 'message' => 'کلید معتبر نیست.'];
    }

    public function deactivate(string $type, string $slug): void
    {
        $row = $this->row($type, $slug);
        if ($row !== null) {
            $this->db->table('licenses')->where('id', $row['id'])->delete();
        }
    }

    /**
     * Check current status with cached remote verification + grace handling.
     *
     * @return array{status: string, in_grace: bool, plan: ?string, expires_at: ?string}
     */
    public function check(string $type, string $slug): array
    {
        $row = $this->row($type, $slug);
        if ($row === null) {
            return ['status' => self::STATUS_INVALID, 'in_grace' => false, 'plan' => null, 'expires_at' => null];
        }
        if (($row['status'] ?? '') === self::STATUS_SUSPENDED) {
            return ['status' => self::STATUS_SUSPENDED, 'in_grace' => false, 'plan' => $row['plan'], 'expires_at' => $row['expires_at']];
        }

        $lastChecked = strtotime((string) ($row['last_checked_at'] ?? '2000-01-01'));
        if (time() - $lastChecked > $this->verifyCacheHours * 3600) {
            $this->reverify($type, $slug, $row);
            $row = $this->row($type, $slug) ?? $row;
        }

        $now = time();
        $expires = $row['expires_at'] !== null ? strtotime((string) $row['expires_at']) : null;
        if ($expires !== null && $expires < $now) {
            $graceUntil = $row['grace_until'] !== null
                ? strtotime((string) $row['grace_until'])
                : $expires + $this->graceDays * 86400;
            if ($row['grace_until'] === null) {
                $this->db->table('licenses')->where('id', $row['id'])->update([
                    'grace_until' => date('Y-m-d H:i:s', $graceUntil),
                ]);
            }
            if ($graceUntil >= $now) {
                return ['status' => self::STATUS_EXPIRED, 'in_grace' => true, 'plan' => $row['plan'], 'expires_at' => $row['expires_at']];
            }

            return ['status' => self::STATUS_EXPIRED, 'in_grace' => false, 'plan' => $row['plan'], 'expires_at' => $row['expires_at']];
        }

        return ['status' => self::STATUS_ACTIVE, 'in_grace' => false, 'plan' => $row['plan'], 'expires_at' => $row['expires_at']];
    }

    /** Updates stay available during grace; premium features check status only. */
    public function updatesAllowed(string $type, string $slug): bool
    {
        $check = $this->check($type, $slug);

        return $check['status'] === self::STATUS_ACTIVE || $check['in_grace'];
    }

    private function row(string $type, string $slug): ?array
    {
        try {
            return $this->db->table('licenses')->where('item_type', $type)->where('item_slug', $slug)->first();
        } catch (\Throwable) {
            return null;
        }
    }

    private function reverify(string $type, string $slug, array $row): void
    {
        // Plaintext key is not stored, so reverify pings the item endpoint
        // anonymously: unreachable server keeps the last-known status.
        try {
            $release = $this->marketplace->provider()->latestRelease($type, $slug);
            if ($release !== null) {
                $this->db->table('licenses')->where('id', $row['id'])->update(['last_checked_at' => date('Y-m-d H:i:s')]);
            }
        } catch (\Throwable) {
            // Offline: keep last-known status (grace rules apply on expiry).
        }
    }
}
