<?php

declare(strict_types=1);

namespace IRJalali\Plugins\IrAnalytics\Migrations;

use IRJalali\Core\Database\Database;

final class Version_1_0_0_CreateAnalyticsTables
{
    public function up(Database $db): void
    {
        if ($db->driver() === 'sqlite') {
            $db->query('CREATE TABLE IF NOT EXISTS ir_analytics_views (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                path VARCHAR(191) NOT NULL,
                referrer VARCHAR(255) NULL,
                ip_hash VARCHAR(64) NOT NULL,
                user_agent VARCHAR(255) NULL,
                is_bot INTEGER NOT NULL DEFAULT 0,
                viewed_at VARCHAR(19) NOT NULL
            )');
            $db->query('CREATE INDEX IF NOT EXISTS idx_ir_analytics_views_date ON ir_analytics_views (viewed_at)');
            $db->query('CREATE TABLE IF NOT EXISTS ir_analytics_daily (
                day VARCHAR(10) NOT NULL,
                path VARCHAR(191) NOT NULL,
                views INTEGER NOT NULL DEFAULT 0,
                uniq INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (day, path)
            )');
        } else {
            $db->query('CREATE TABLE IF NOT EXISTS `ir_analytics_views` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `path` VARCHAR(191) NOT NULL,
                `referrer` VARCHAR(255) NULL,
                `ip_hash` VARCHAR(64) NOT NULL,
                `user_agent` VARCHAR(255) NULL,
                `is_bot` TINYINT(1) NOT NULL DEFAULT 0,
                `viewed_at` DATETIME NOT NULL,
                INDEX `idx_viewed_at` (`viewed_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $db->query('CREATE TABLE IF NOT EXISTS `ir_analytics_daily` (
                `day` VARCHAR(10) NOT NULL,
                `path` VARCHAR(191) NOT NULL,
                `views` INT NOT NULL DEFAULT 0,
                `uniq` INT NOT NULL DEFAULT 0,
                PRIMARY KEY (`day`, `path`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down(Database $db): void
    {
        $db->query('DROP TABLE IF EXISTS ir_analytics_daily');
        $db->query('DROP TABLE IF EXISTS ir_analytics_views');
    }
}
