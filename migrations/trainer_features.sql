-- Migration: Add Trainer Portal Authentication and Leave Management
-- Database: ironforge_gym

ALTER TABLE `trainers`
  ADD COLUMN `password_hash` VARCHAR(255) NULL AFTER `email`,
  MODIFY COLUMN `status` ENUM('active','inactive','on_leave') NOT NULL DEFAULT 'active',
  ADD COLUMN `leave_start` DATE NULL AFTER `status`,
  ADD COLUMN `leave_end` DATE NULL AFTER `leave_start`;

CREATE TABLE IF NOT EXISTS `trainer_leave_requests` (
  `leave_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `trainer_id` INT UNSIGNED NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `reason` TEXT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_leave_trainer` FOREIGN KEY (`trainer_id`) REFERENCES `trainers`(`trainer_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_leave_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `admins`(`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
