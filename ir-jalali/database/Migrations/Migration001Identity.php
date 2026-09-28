<?php

declare(strict_types=1);

namespace IRJalali\Database\Migrations;

use IRJalali\Core\Database\Migration;
use IRJalali\Core\Database\Schema\Schema;

/**
 * Identity, access control, sites and key/value settings.
 */
final class Migration001Identity extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->create('users', function ($table): void {
            $table->id();
            $table->uuid();
            $table->string('username', 60);
            $table->string('email', 191);
            $table->string('password_hash', 255);
            $table->string('display_name', 120, true);
            $table->string('first_name', 80, true);
            $table->string('last_name', 80, true);
            $table->string('mobile', 20, true);
            $table->string('avatar', 255, true);
            $table->string('status', 20, false, 'active');
            $table->string('locale', 10, false, 'fa_IR');
            $table->dateTime('last_login_at');
            $table->timestamps();
            $table->softDeletes();
            $table->unique('username');
            $table->unique('email');
            $table->unique('uuid');
            $table->index('status');
        });

        $schema->create('roles', function ($table): void {
            $table->id();
            $table->string('slug', 60);
            $table->string('name', 100);
            $table->string('description', 255, true);
            $table->boolean('is_system', true);
            $table->timestamps();
            $table->unique('slug');
        });

        $schema->create('permissions', function ($table): void {
            $table->id();
            $table->string('slug', 100);
            $table->string('name', 120);
            $table->string('group', 60, false, 'general');
            $table->timestamps();
            $table->unique('slug');
            $table->index('group');
        });

        $schema->create('role_permissions', function ($table): void {
            $table->id();
            $table->bigInteger('role_id');
            $table->bigInteger('permission_id');
            $table->unique('role_id', 'permission_id');
            $table->foreign('role_id', 'id', 'roles');
            $table->foreign('permission_id', 'id', 'permissions');
        });

        $schema->create('user_roles', function ($table): void {
            $table->id();
            $table->bigInteger('user_id');
            $table->bigInteger('role_id');
            $table->unique('user_id', 'role_id');
            $table->foreign('user_id', 'id', 'users');
            $table->foreign('role_id', 'id', 'roles');
        });

        $schema->create('sites', function ($table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('domain', 191, true);
            $table->string('locale', 10, false, 'fa_IR');
            $table->string('timezone', 40, false, 'Asia/Tehran');
            $table->string('calendar', 20, false, 'jalali');
            $table->boolean('is_default', true);
            $table->timestamps();
            $table->index('domain');
        });

        $schema->create('settings', function ($table): void {
            $table->id();
            $table->bigInteger('site_id', false, 0);
            $table->string('group', 60, false, 'general');
            $table->string('key', 120);
            $table->longText('value');
            $table->string('type', 20, false, 'string');
            $table->boolean('is_public', false);
            $table->timestamps();
            $table->unique('site_id', 'group', 'key');
            $table->index('group');
        });

        $schema->create('options', function ($table): void {
            $table->id();
            $table->string('key', 120);
            $table->longText('value');
            $table->boolean('autoload', true);
            $table->timestamps();
            $table->unique('key');
        });
    }

    public function down(Schema $schema): void
    {
        foreach (['options', 'settings', 'sites', 'user_roles', 'role_permissions', 'permissions', 'roles', 'users'] as $table) {
            $schema->drop($table);
        }
    }
}
