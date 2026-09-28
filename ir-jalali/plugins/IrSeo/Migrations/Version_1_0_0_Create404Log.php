<?php

declare(strict_types=1);

namespace IRJalali\Plugins\IrSeo\Migrations;

use IRJalali\Core\Database\Database;

final class Version_1_0_0_Create404Log
{
    public function up(Database $db): void
    {
        if ($db->driver() === 'sqlite') {
            $db->query('CREATE TABLE IF NOT EXISTS ir_seo_404 (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                path VARCHAR(500) NOT NULL,
                referrer VARCHAR(500) NULL,
                ip VARCHAR(45) NULL,
                hits INTEGER NOT NULL DEFAULT 1,
                created_at VARCHAR(19) NOT NULL
            )');
            $db->query('CREATE INDEX IF NOT EXISTS idx_ir_seo_404_path ON ir_seo_404 (path)');
        } else {
            $db->query('CREATE TABLE IF NOT EXISTS `ir_seo_404` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `path` VARCHAR(500) NOT NULL,
                `referrer` VARCHAR(500) NULL,
                `ip` VARCHAR(45) NULL,
                `hits` INT NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL,
                INDEX `idx_path` (`path`(191))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down(Database $db): void
    {
        $db->query('DROP TABLE IF EXISTS ir_seo_404');
    }
}
