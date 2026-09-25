<?php

declare(strict_types=1);

namespace IRJalali\Plugins\IrMembership\Migrations;

use IRJalali\Core\Database\Database;

final class Version_1_0_0_CreateMembershipTables
{
    public function up(Database $db): void
    {
        if ($db->driver() === 'sqlite') {
            $db->query('CREATE TABLE IF NOT EXISTS ir_membership_plans (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(120) NOT NULL,
                slug VARCHAR(120) NOT NULL,
                description VARCHAR(500) NULL,
                price INTEGER NOT NULL DEFAULT 0,
                period_days INTEGER NOT NULL DEFAULT 30,
                is_active INTEGER NOT NULL DEFAULT 1
            )');
            $db->query('CREATE UNIQUE INDEX IF NOT EXISTS idx_ir_plans_slug ON ir_membership_plans (slug)');
            $db->query('CREATE TABLE IF NOT EXISTS ir_membership_subscriptions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                plan_id INTEGER NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT "pending_payment",
                started_at VARCHAR(19) NULL,
                expires_at VARCHAR(19) NULL,
                created_at VARCHAR(19) NOT NULL,
                updated_at VARCHAR(19) NULL
            )');
            $db->query('CREATE INDEX IF NOT EXISTS idx_ir_subs_user ON ir_membership_subscriptions (user_id, status)');
        } else {
            $db->query('CREATE TABLE IF NOT EXISTS `ir_membership_plans` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `title` VARCHAR(120) NOT NULL,
                `slug` VARCHAR(120) NOT NULL,
                `description` VARCHAR(500) NULL,
                `price` BIGINT NOT NULL DEFAULT 0,
                `period_days` INT NOT NULL DEFAULT 30,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                UNIQUE KEY `uniq_slug` (`slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $db->query('CREATE TABLE IF NOT EXISTS `ir_membership_subscriptions` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `plan_id` BIGINT UNSIGNED NOT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT "pending_payment",
                `started_at` DATETIME NULL,
                `expires_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NULL,
                INDEX `idx_user_status` (`user_id`, `status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down(Database $db): void
    {
        $db->query('DROP TABLE IF EXISTS ir_membership_subscriptions');
        $db->query('DROP TABLE IF EXISTS ir_membership_plans');
    }
}
