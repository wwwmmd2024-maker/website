<?php

declare(strict_types=1);

namespace IRJalali\App\Console\Commands;

use IRJalali\App\Repositories\UserRepository;
use IRJalali\Core\Console\Command;
use IRJalali\Core\Console\Output;

final class UserCreateCommand extends Command
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function name(): string
    {
        return 'user:create';
    }

    public function description(): string
    {
        return 'Create a user and assign a role.';
    }

    public function usage(): string
    {
        return 'user:create [--username=..] [--email=..] [--password=..] [--role=..] [--name=..]';
    }

    /** @param list<string> $args @param array<string, string|bool> $options */
    public function handle(array $args, array $options, Output $out): int
    {
        $username = (string) ($this->option($options, 'username') ?? $out->ask('Username'));
        $email = (string) ($this->option($options, 'email') ?? $out->ask('Email'));
        $password = (string) ($this->option($options, 'password') ?? $out->secret('Password (min 8 chars)'));
        $role = (string) ($this->option($options, 'role', 'subscriber') ?? 'subscriber');
        $name = (string) ($this->option($options, 'name', $username) ?? $username);

        if (!preg_match('/^[a-z0-9_]{3,60}$/i', $username)) {
            $out->error('Username must be 3-60 chars (letters, numbers, underscore).');
            return 1;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $out->error('Invalid email address.');
            return 1;
        }
        if (strlen($password) < 8) {
            $out->error('Password must be at least 8 characters.');
            return 1;
        }
        if ($this->users->usernameExists($username)) {
            $out->error("Username [{$username}] is taken.");
            return 1;
        }
        if ($this->users->emailExists($email)) {
            $out->error("Email [{$email}] is taken.");
            return 1;
        }

        $user = $this->users->create([
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'display_name' => mb_substr($name, 0, 120),
            'status' => 'active',
        ]);
        try {
            $this->users->assignRole($user->id, $role);
        } catch (\Throwable) {
            $this->users->assignRole($user->id, 'subscriber');
            $out->warning("Role [{$role}] not found; assigned [subscriber] instead.");
        }
        $out->success("User [{$username}] created with id {$user->id}.");

        return 0;
    }
}
