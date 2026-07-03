-- Migration: tracking fields present on prod but missing from full_dump.sql
-- (device_series / accessories / pickup_date / updated_by)
-- Applied via zz_apply_migrations.sh with --force; safe to re-run.
ALTER TABLE `tracking`
  ADD COLUMN `device_series` VARCHAR(120) NULL AFTER `device_model`,
  ADD COLUMN `accessories`   TEXT NULL AFTER `problem_details`,
  ADD COLUMN `pickup_date`   DATE NULL AFTER `appointment_date`,
  ADD COLUMN `updated_by`    INT UNSIGNED NULL AFTER `updated_at`;
