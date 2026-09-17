-- Email verification flags for login gating.
-- Database: ironforge_gym
-- Safe to re-run (checks information_schema before ALTER).
--
-- One-time note: existing real demo accounts will get email_verified=0
-- on members/trainers after this migration. An admin can set
-- email_verified=1 manually in phpMyAdmin so those accounts can still log in.
-- Admins default to verified (1).

-- members.email_verified (default 0)
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'members'
    AND COLUMN_NAME = 'email_verified'
);

SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE `members` ADD COLUMN `email_verified` TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT ''members.email_verified already exists — skip ALTER'' AS notice'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- trainers.email_verified (default 0)
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'trainers'
    AND COLUMN_NAME = 'email_verified'
);

SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE `trainers` ADD COLUMN `email_verified` TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT ''trainers.email_verified already exists — skip ALTER'' AS notice'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- admins.email_verified (default 1 — admins are treated as verified)
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'admins'
    AND COLUMN_NAME = 'email_verified'
);

SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE `admins` ADD COLUMN `email_verified` TINYINT(1) NOT NULL DEFAULT 1',
  'SELECT ''admins.email_verified already exists — skip ALTER'' AS notice'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
