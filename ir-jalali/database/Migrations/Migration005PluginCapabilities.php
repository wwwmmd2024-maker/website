<?php

declare(strict_types=1);

namespace IRJalali\Database\Migrations;

use IRJalali\Core\Database\Migration;
use IRJalali\Core\Database\Schema\Schema;

/**
 * Persist per-plugin granted capabilities (activation consent).
 */
final class Migration005PluginCapabilities extends Migration
{
    public function up(Schema $schema): void
    {
        $db = $schema->db();
        if ($db->driver() === 'sqlite') {
            $db->query('ALTER TABLE plugins ADD COLUMN granted_capabilities TEXT NULL');
        } else {
            $db->query('ALTER TABLE `plugins` ADD COLUMN `granted_capabilities` JSON NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $db = $schema->db();
        if ($db->driver() === 'sqlite') {
            $db->query('ALTER TABLE plugins DROP COLUMN granted_capabilities');
        } else {
            $db->query('ALTER TABLE `plugins` DROP COLUMN `granted_capabilities`');
        }
    }
}
