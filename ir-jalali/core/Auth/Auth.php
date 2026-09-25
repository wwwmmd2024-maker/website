<?php

declare(strict_types=1);

namespace IRJalali\Core\Auth;

use IRJalali\Core\Database\Database;
use IRJalali\Core\Logging\Logger;
use IRJalali\Core\Security\Hasher;
use IRJalali\Core\Security\Session;

/**
 * Session authentication + RBAC permission checks.
 */
final class Auth
{
    /** @var array<string, mixed>|null */
    private ?array $user = null;
    private bool $resolved = false;

    /** @var list<string>|null */
    private ?array $permissions = null;

    public function __construct(
        private readonly Database $db,
        private readonly Hasher $hasher,
        private readonly Logger $logger,
    ) {
    }

    public function attempt(string $login, string $password): bool
    {
        $user = $this->db->first(
            'SELECT * FROM users WHERE (email = :l1 OR username = :l2) AND deleted_at IS NULL LIMIT 1',
            ['l1' => $login, 'l2' => $login]
        );
        if ($user === null) {
            return false;
        }
        if (($user['status'] ?? '') !== 'active') {
            return false;
        }
        if (!$this->hasher->verify($password, (string) $user['password_hash'])) {
            return false;
        }
        if ($this->hasher->needsRehash((string) $user['password_hash'])) {
            $this->db->table('users')->where('id', $user['id'])->update([
                'password_hash' => $this->hasher->hash($password),
            ]);
        }
        $this->login((int) $user['id']);

        return true;
    }

    public function login(int $userId): void
    {
        Session::regenerate();
        $_SESSION['irj_user_id'] = $userId;
        $this->resolved = false;
        $this->user = null;
        $this->permissions = null;
        $this->db->table('users')->where('id', $userId)->update([
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function logout(): void
    {
        $this->user = null;
        $this->resolved = false;
        $this->permissions = null;
        Session::destroy();
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        Session::start();
        if ($this->resolved) {
            return $this->user;
        }
        $this->resolved = true;
        $id = $_SESSION['irj_user_id'] ?? null;
        if (!is_numeric($id)) {
            return null;
        }
        $user = $this->db->first(
            'SELECT * FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            ['id' => (int) $id]
        );
        if ($user === null || ($user['status'] ?? '') !== 'active') {
            return null;
        }
        $this->user = $user;

        return $user;
    }

    public function id(): ?int
    {
        $user = $this->user();

        return $user !== null ? (int) $user['id'] : null;
    }

    /** @return list<string> role slugs */
    public function roles(): array
    {
        $user = $this->user();
        if ($user === null) {
            return [];
        }
        $rows = $this->db->select(
            'SELECT r.slug FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = :u',
            ['u' => $user['id']]
        );

        return array_column($rows, 'slug');
    }

    public function hasRole(string ...$slugs): bool
    {
        return count(array_intersect($slugs, $this->roles())) > 0;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /** @return list<string> permission slugs like "posts.edit" */
    public function permissions(): array
    {
        if ($this->permissions !== null) {
            return $this->permissions;
        }
        $user = $this->user();
        if ($user === null) {
            return $this->permissions = [];
        }
        if ($this->isSuperAdmin()) {
            return $this->permissions = ['*'];
        }
        $rows = $this->db->select(
            'SELECT DISTINCT p.slug FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             INNER JOIN user_roles ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = :u',
            ['u' => $user['id']]
        );
        $this->permissions = array_column($rows, 'slug');

        return $this->permissions;
    }

    public function can(string ...$permissions): bool
    {
        $granted = $this->permissions();
        if (in_array('*', $granted, true)) {
            return true;
        }

        return count(array_intersect($permissions, $granted)) > 0;
    }
}
