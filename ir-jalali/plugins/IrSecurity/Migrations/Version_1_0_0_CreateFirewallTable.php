<?php

declare(strict_types=1);

namespace IRJalali\Plugins\IrSecurity\Migrations;

use IRJalali\Core\Database\Database;

final class Version_1_0_0_CreateFirewallTable
{
    public function up(Database $db): void
    {
        if ($db->driver() === 'sqlite') {
            $db->query('CREATE TABLE IF NOT EXISTS ir_security_rules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                rule_type VARCHAR(10) NOT NULL,
                ip VARCHAR(45) NOT NULL,
                reason VARCHAR(255) NULL,
                expires_at VARCHAR(19) NULL,
                created_at VARCHAR(19) NOT NULL
            )');
            $db->query('CREATE UNIQUE INDEX IF NOT EXISTS idx_ir_security_rules_ip ON ir_security_rules (rule_type, ip)');
        } else {
            $db->query('CREATE TABLE IF NOT EXISTS `ir_security_rules` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `rule_type` VARCHAR(10) NOT NULL,
                `ip` VARCHAR(45) NOT NULL,
                `reason` VARCHAR(255) NULL,
                `expires_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL,
                UNIQUE KEY `uniq_type_ip` (`rule_type`, `ip`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down(Database $db): void
    {
        $db->query('DROP TABLE IF EXISTS ir_security_rules');
    }
}
