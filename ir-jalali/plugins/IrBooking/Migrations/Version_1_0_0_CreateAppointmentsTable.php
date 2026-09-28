<?php

declare(strict_types=1);

namespace IRJalali\Plugins\IrBooking\Migrations;

use IRJalali\Core\Database\Database;

final class Version_1_0_0_CreateAppointmentsTable
{
    public function up(Database $db): void
    {
        if ($db->driver() === 'sqlite') {
            $db->query('CREATE TABLE IF NOT EXISTS ir_booking_appointments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                service_id INTEGER NOT NULL,
                service_title VARCHAR(255) NOT NULL,
                customer_name VARCHAR(120) NOT NULL,
                customer_phone VARCHAR(40) NOT NULL,
                customer_email VARCHAR(191) NULL,
                appoint_date VARCHAR(10) NOT NULL,
                time_slot VARCHAR(5) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT "pending",
                note VARCHAR(500) NULL,
                created_at VARCHAR(19) NOT NULL
            )');
            $db->query('CREATE INDEX IF NOT EXISTS idx_ir_booking_date ON ir_booking_appointments (appoint_date, time_slot)');
            $db->query('CREATE INDEX IF NOT EXISTS idx_ir_booking_status ON ir_booking_appointments (status)');
        } else {
            $db->query('CREATE TABLE IF NOT EXISTS `ir_booking_appointments` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `service_id` BIGINT UNSIGNED NOT NULL,
                `service_title` VARCHAR(255) NOT NULL,
                `customer_name` VARCHAR(120) NOT NULL,
                `customer_phone` VARCHAR(40) NOT NULL,
                `customer_email` VARCHAR(191) NULL,
                `appoint_date` VARCHAR(10) NOT NULL,
                `time_slot` VARCHAR(5) NOT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT "pending",
                `note` VARCHAR(500) NULL,
                `created_at` DATETIME NOT NULL,
                INDEX `idx_date_slot` (`appoint_date`, `time_slot`),
                INDEX `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down(Database $db): void
    {
        $db->query('DROP TABLE IF EXISTS ir_booking_appointments');
    }
}
