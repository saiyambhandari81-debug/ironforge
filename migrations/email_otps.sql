-- Register / reset email OTP codes.
-- Database: ironforge_gym
-- Safe to re-run (CREATE TABLE IF NOT EXISTS).
-- Part C uses purpose='register' only.

CREATE TABLE IF NOT EXISTS `email_otps` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(150) NOT NULL,
  `purpose` ENUM('register','reset') NOT NULL DEFAULT 'register',
  `otp_hash` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `used_at` DATETIME NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email_purpose` (`email`, `purpose`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
