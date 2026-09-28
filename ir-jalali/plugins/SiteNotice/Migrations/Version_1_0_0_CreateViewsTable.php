<?php

declare(strict_types=1);

namespace IRJalali\Plugins\SiteNotice\Migrations;

use IRJalali\Core\Database\Database;

final class Version_1_0_0_CreateViewsTable
{
    public function up(Database $db): void
    {
        if ($db->driver() === 'sqlite') {
            $db->query('CREATE TABLE IF NOT EXISTS site_notice_views (id INTEGER PRIMARY KEY AUTOINCREMENT, viewed_at VARCHAR(19) NOT NULL)');
            $db->query('CREATE INDEX IF NOT EXISTS idx_site_notice_views_viewed ON site_notice_views (viewed_at)');
        } else {
            $db->query('CREATE TABLE IF NOT EXISTS `site_notice_views` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, `viewed_at` DATETIME NOT NULL, INDEX `idx_viewed` (`viewed_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down(Database $db): void
    {
        $db->query('DROP TABLE IF EXISTS site_notice_views');
    }
}
