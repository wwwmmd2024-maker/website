<?php

declare(strict_types=1);

namespace IRJalali\App\Services;

use IRJalali\Core\Database\Database;
use IRJalali\Core\Security\Hasher;

/**
 * Personal API tokens (SHA-256 hashed at rest, shown once at creation).
 */
final class ApiTokenService
{
    public function __construct(
        private readonly Database $db,
        private readonly Hasher $hasher,
    ) {
    }

    /** @param list<string> $abilities @return array{token: string, id: int} */
    public function create(int $userId, string $name, array $abilities = ['*'], ?string $expiresAt = null): array
    {
        $plain = $this->hasher->token(40);
        $now = date('Y-m-d H:i:s');
        $id = $this->db->insert('api_tokens', [
            'user_id' => $userId,
            'name' => mb_substr($name, 0, 90),
            'token_hash' => $this->hasher->sha256($plain),
            'abilities' => json_encode($abilities, JSON_UNESCAPED_UNICODE),
            'expires_at' => $expiresAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['token' => $plain, 'id' => (int) $id];
    }

    /** @return array<string, mixed>|null token row with user attached */
    public function authenticate(string $plainToken): ?array
    {
        $row = $this->db->table('api_tokens')
            ->where('token_hash', $this->hasher->sha256($plainToken))
            ->first();
        if ($row === null) {
            return null;
        }
        if (!empty($row['expires_at']) && $row['expires_at'] < date('Y-m-d H:i:s')) {
            return null;
        }
        $user = $this->db->table('users')->where('id', $row['user_id'])->whereNull('deleted_at')->first();
        if ($user === null || ($user['status'] ?? '') !== 'active') {
            return null;
        }
        $this->db->table('api_tokens')->where('id', $row['id'])->update([
            'last_used_at' => date('Y-m-d H:i:s'),
        ]);
        $row['user'] = $user;
        $row['abilities'] = json_decode((string) $row['abilities'], true) ?: [];

        return $row;
    }

    public function can(array $tokenRow, string $ability): bool
    {
        $abilities = $tokenRow['abilities'] ?? [];

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }
}
