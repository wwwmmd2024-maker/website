<?php

declare(strict_types=1);

namespace IRJalali\Plugins\IrCommerce\Migrations;

use IRJalali\Core\Database\Database;

final class Version_1_0_0_CreateCommerceTables
{
    public function up(Database $db): void
    {
        if ($db->driver() === 'sqlite') {
            $db->query('CREATE TABLE IF NOT EXISTS ir_commerce_orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_no VARCHAR(40) NOT NULL,
                customer_name VARCHAR(120) NOT NULL,
                customer_phone VARCHAR(40) NOT NULL,
                customer_email VARCHAR(191) NULL,
                address VARCHAR(500) NOT NULL,
                note VARCHAR(500) NULL,
                payment_method VARCHAR(40) NOT NULL DEFAULT "manual",
                payment_status VARCHAR(20) NOT NULL DEFAULT "unpaid",
                order_status VARCHAR(20) NOT NULL DEFAULT "pending",
                items_total INTEGER NOT NULL DEFAULT 0,
                shipping_total INTEGER NOT NULL DEFAULT 0,
                grand_total INTEGER NOT NULL DEFAULT 0,
                created_at VARCHAR(19) NOT NULL,
                updated_at VARCHAR(19) NULL
            )');
            $db->query('CREATE UNIQUE INDEX IF NOT EXISTS idx_ir_orders_no ON ir_commerce_orders (order_no)');
            $db->query('CREATE INDEX IF NOT EXISTS idx_ir_orders_status ON ir_commerce_orders (order_status)');
            $db->query('CREATE TABLE IF NOT EXISTS ir_commerce_order_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                title VARCHAR(255) NOT NULL,
                price INTEGER NOT NULL,
                qty INTEGER NOT NULL,
                line_total INTEGER NOT NULL
            )');
            $db->query('CREATE INDEX IF NOT EXISTS idx_ir_order_items_order ON ir_commerce_order_items (order_id)');
        } else {
            $db->query('CREATE TABLE IF NOT EXISTS `ir_commerce_orders` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `order_no` VARCHAR(40) NOT NULL,
                `customer_name` VARCHAR(120) NOT NULL,
                `customer_phone` VARCHAR(40) NOT NULL,
                `customer_email` VARCHAR(191) NULL,
                `address` VARCHAR(500) NOT NULL,
                `note` VARCHAR(500) NULL,
                `payment_method` VARCHAR(40) NOT NULL DEFAULT "manual",
                `payment_status` VARCHAR(20) NOT NULL DEFAULT "unpaid",
                `order_status` VARCHAR(20) NOT NULL DEFAULT "pending",
                `items_total` BIGINT NOT NULL DEFAULT 0,
                `shipping_total` BIGINT NOT NULL DEFAULT 0,
                `grand_total` BIGINT NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NULL,
                UNIQUE KEY `uniq_order_no` (`order_no`),
                INDEX `idx_order_status` (`order_status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $db->query('CREATE TABLE IF NOT EXISTS `ir_commerce_order_items` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `order_id` BIGINT UNSIGNED NOT NULL,
                `product_id` BIGINT UNSIGNED NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `price` BIGINT NOT NULL,
                `qty` INT NOT NULL,
                `line_total` BIGINT NOT NULL,
                INDEX `idx_order_id` (`order_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down(Database $db): void
    {
        $db->query('DROP TABLE IF EXISTS ir_commerce_order_items');
        $db->query('DROP TABLE IF EXISTS ir_commerce_orders');
    }
}
