<?php

declare(strict_types=1);

namespace IRJalali\Plugins\IrLms\Migrations;

use IRJalali\Core\Database\Database;

final class Version_1_0_0_CreateLmsTables
{
    public function up(Database $db): void
    {
        if ($db->driver() === 'sqlite') {
            $db->query('CREATE TABLE IF NOT EXISTS ir_lms_enrollments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                course_id INTEGER NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT "pending",
                enrolled_at VARCHAR(19) NOT NULL
            )');
            $db->query('CREATE UNIQUE INDEX IF NOT EXISTS idx_ir_lms_enroll ON ir_lms_enrollments (user_id, course_id)');
            $db->query('CREATE TABLE IF NOT EXISTS ir_lms_progress (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                lesson_id INTEGER NOT NULL,
                completed_at VARCHAR(19) NOT NULL
            )');
            $db->query('CREATE UNIQUE INDEX IF NOT EXISTS idx_ir_lms_progress ON ir_lms_progress (user_id, lesson_id)');
        } else {
            $db->query('CREATE TABLE IF NOT EXISTS `ir_lms_enrollments` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `course_id` BIGINT UNSIGNED NOT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT "pending",
                `enrolled_at` DATETIME NOT NULL,
                UNIQUE KEY `uniq_user_course` (`user_id`, `course_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $db->query('CREATE TABLE IF NOT EXISTS `ir_lms_progress` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `user_id` BIGINT UNSIGNED NOT NULL,
                `lesson_id` BIGINT UNSIGNED NOT NULL,
                `completed_at` DATETIME NOT NULL,
                UNIQUE KEY `uniq_user_lesson` (`user_id`, `lesson_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        }
    }

    public function down(Database $db): void
    {
        $db->query('DROP TABLE IF EXISTS ir_lms_progress');
        $db->query('DROP TABLE IF EXISTS ir_lms_enrollments');
    }
}
