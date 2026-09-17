-- Migration: plans.includes_trainer + Cardio copy
-- Database: ironforge_gym
-- Safe to re-run: skips ALTER if the column already exists.

SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'plans'
    AND COLUMN_NAME = 'includes_trainer'
);

SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE plans ADD COLUMN includes_trainer TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT ''includes_trainer already exists — skip ALTER'' AS notice'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Product rules (by plan name, when those rows exist)
UPDATE plans
SET includes_trainer = 0,
    features = 'Gym access only'
WHERE plan_name = 'Basic';

UPDATE plans
SET includes_trainer = 0,
    features = 'Gym + Cardio'
WHERE plan_name = 'Standard';

UPDATE plans
SET includes_trainer = 1,
    features = 'Gym + Cardio + Personal trainer'
WHERE plan_name = 'Premium';

-- Any leftover Sauna wording in features
UPDATE plans
SET features = REPLACE(REPLACE(features, 'Sauna', 'Cardio'), 'sauna', 'Cardio')
WHERE features LIKE '%auna%';
