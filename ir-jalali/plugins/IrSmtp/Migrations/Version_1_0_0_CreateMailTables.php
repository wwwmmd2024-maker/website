<?php

declare(strict_types=1);

namespace IRJalali\Plugins\IrSmtp\Migrations;

use IRJalali\Core\Database\Database;

final class Version_1_0_0_CreateMailTables
{
    public function up(Database $db): void
    {
        if ($db->driver() === 'sqlite') {
            $db->query('CREATE TABLE IF NOT EXISTS ir_smtp_queue (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                to_email VARCHAR(191) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                body TEXT NOT NULL,
                headers TEXT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT "pending",
                last_error TEXT NULL,
                created_at VARCHAR(19) NOT NULL,
                sent_at VARCHAR(19) NULL
            )');
            $db->query('CREATE INDEX IF NOT EXISTS idx_ir_smtp_queue_status ON ir_smtp_queue (status)');
            $db->query('CREATE TABLE IF NOT EXISTS ir_smtp_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                to_email VARCHAR(191) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                status VARCHAR(20) NOT NULL,
                detail TEXT NULL,
                created_at VARCHAR(19) NOT NULL
            )');
        } else {
            $db->query('CREATE TABLE IF NOT EXISTS `ir_smtp_queue` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `to_email` VARCHAR(191) NOT NULL,
                `subject` VARCHAR(255) NOT NULL,
                `body` MEDIUMTEXT NOT NULL,
                `headers` TEXT NULL,
                `attempts` INT NOT NULL DEFAULT 0,
                `status` VARCHAR(20) NOT NULL DEFAULT "pending",
                `last_error` TEXT NULL,
                `created_at` DATETIME NOT NULL,
                `sent_at` DATETIME NULL,
                INDEX `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $db->query('CREATE TABLE IF NOT EXISTS `ir_smtp_log` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `to_email` VARCHAR(191) NOT NULL,
                `subject` VARCHAR(255) NOT NULL,
                `status` VARCHAR(20) NOT NULL,
                `detail` TEXT NULL,
                `created_at` DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down(Database $db): void
    {
        $db->query('DROP TABLE IF EXISTS ir_smtp_log');
        $db->query('DROP TABLE IF EXISTS ir_smtp_queue');
    }
}
