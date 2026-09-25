<?php

declare(strict_types=1);

namespace IRJalali\App\Repositories;

use IRJalali\App\Models\User;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Security\Hasher;

/**
 * All user persistence lives here — controllers never query users directly.
 */
final class UserRepository
{
    public function __construct(
        private readonly Database $db,
        private readonly Hasher $hasher,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): User
    {
        $now = date('Y-m-d H:i:s');
        $id = $this->db->insert('users', [
            'uuid' => $this->uuid(),
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => $this->hasher->hash($data['password']),
            'display_name' => $data['display_name'] ?? $data['username'],
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'status' => $data['status'] ?? 'active',
            'locale' => $data['locale'] ?? 'fa_IR',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->find((int) $id) ?? throw new \RuntimeException('User creation failed.');
    }

    public function find(int $id): ?User
    {
        $row = $this->db->table('users')->where('id', $id)->whereNull('deleted_at')->first();

        return $row === null ? null : User::fromRow($row, $this->roleSlugs($id));
    }

    public function findByLogin(string $login): ?User
    {
        $row = $this->db->first(
            'SELECT * FROM users WHERE (email = :l1 OR username = :l2) AND deleted_at IS NULL LIMIT 1',
            ['l1' => $login, 'l2' => $login]
        );

        return $row === null ? null : User::fromRow($row, $this->roleSlugs((int) $row['id']));
    }

    public function usernameExists(string $username): bool
    {
        return $this->db->table('users')->where('username', $username)->first() !== null;
    }

    public function emailExists(string $email): bool
    {
        return $this->db->table('users')->where('email', $email)->first() !== null;
    }

    public function assignRole(int $userId, string $roleSlug): void
    {
        $role = $this->db->table('roles')->where('slug', $roleSlug)->first();
        if ($role === null) {
            throw new \RuntimeException("Role [{$roleSlug}] not found.");
        }
        $exists = $this->db->table('user_roles')
            ->where('user_id', $userId)
            ->where('role_id', $role['id'])
            ->first();
        if ($exists === null) {
            $this->db->insert('user_roles', ['user_id' => $userId, 'role_id' => $role['id']]);
        }
    }

    /** @return list<string> */
    public function roleSlugs(int $userId): array
    {
        $rows = $this->db->select(
            'SELECT r.slug FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = :u',
            ['u' => $userId]
        );

        return array_column($rows, 'slug');
    }

    /** @return list<User> */
    public function paginate(int $page = 1, int $perPage = 20): array
    {
        $rows = $this->db->table('users')
            ->whereNull('deleted_at')
            ->orderBy('id', 'DESC')
            ->limit($perPage)
            ->offset(max(0, ($page - 1) * $perPage))
            ->get();
        $out = [];
        foreach ($rows as $row) {
            $out[] = User::fromRow($row, $this->roleSlugs((int) $row['id']));
        }

        return $out;
    }

    public function count(): int
    {
        return $this->db->table('users')->whereNull('deleted_at')->count();
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
