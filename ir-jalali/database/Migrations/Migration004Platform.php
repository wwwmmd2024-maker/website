<?php

declare(strict_types=1);

namespace IRJalali\Database\Migrations;

use IRJalali\Core\Database\Migration;
use IRJalali\Core\Database\Schema\Schema;

/**
 * M2 platform tables: plugin migration ledger + license storage.
 */
final class Migration004Platform extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->create('plugin_migrations', function ($table): void {
            $table->id();
            $table->string('plugin_slug', 100);
            $table->string('version', 40);
            $table->string('name', 150);
            $table->string('checksum', 64);
            $table->dateTime('executed_at', false);
            $table->unique('plugin_slug', 'version');
            $table->index('plugin_slug');
        });

        $schema->create('licenses', function ($table): void {
            $table->id();
            $table->string('item_type', 20, false, 'plugin');
            $table->string('item_slug', 100);
            $table->string('key_hash', 64, true);
            $table->string('status', 20, false, 'inactive');
            $table->string('plan', 40, true);
            $table->string('domain', 191, true);
            $table->dateTime('activated_at');
            $table->dateTime('expires_at');
            $table->dateTime('grace_until');
            $table->timestamps();
            $table->unique('item_type', 'item_slug');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('licenses');
        $schema->drop('plugin_migrations');
    }
}
