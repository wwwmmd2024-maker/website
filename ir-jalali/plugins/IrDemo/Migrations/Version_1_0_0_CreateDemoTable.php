<?php

declare(strict_types=1);

namespace IRJalali\Plugins\IrDemo\Migrations;

use IRJalali\Core\Database\Database;

final class Version_1_0_0_CreateDemoTable
{
    public function up(Database $db): void
    {
        if ($db->driver() === 'sqlite') {
            $db->query('CREATE TABLE IF NOT EXISTS ir_demo_stats (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event VARCHAR(60) NOT NULL,
                created_at VARCHAR(19) NOT NULL
            )');
            $db->query('CREATE INDEX IF NOT EXISTS idx_ir_demo_stats_event ON ir_demo_stats (event)');
        } else {
            $db->query('CREATE TABLE IF NOT EXISTS `ir_demo_stats` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `event` VARCHAR(60) NOT NULL,
                `created_at` DATETIME NOT NULL,
                INDEX `idx_event` (`event`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down(Database $db): void
    {
        $db->query('DROP TABLE IF EXISTS ir_demo_stats');
    }
}
