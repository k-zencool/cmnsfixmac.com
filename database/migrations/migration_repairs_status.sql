-- Migration: add status column to repairs
-- Run once: docker compose exec db mysql -u cmns_user -pcmns_password cmnsfixmac_db < db/migration_repairs_status.sql

ALTER TABLE `repairs`
  ADD COLUMN `status` ENUM('published','draft','hidden') NOT NULL DEFAULT 'published' AFTER `category`;
