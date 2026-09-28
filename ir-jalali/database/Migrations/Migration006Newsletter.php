<?php

declare(strict_types=1);

namespace IRJalali\Database\Migrations;

use IRJalali\Core\Database\Migration;
use IRJalali\Core\Database\Schema\Schema;

/**
 * Newsletter subscribers table (public /newsletter/subscribe endpoint).
 */
final class Migration006Newsletter extends Migration
{
    public function up(Schema $schema): void
    {
        $schema->create('newsletter_subscribers', function ($table): void {
            $table->id();
            $table->string('email', 190);
            $table->string('name', 120, true);
            $table->string('status', 20, false, 'subscribed');
            $table->string('token', 64, true);
            $table->string('ip', 45, true);
            $table->timestamps();
            $table->index('email');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('newsletter_subscribers');
    }
}
