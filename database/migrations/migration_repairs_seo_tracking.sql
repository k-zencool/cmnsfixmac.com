-- =================================================================
-- Fix: admin/repairs/ returns HTTP 500 on production
-- Cause: prod was seeded from old full_dump.sql, missing the columns
--        added by the SEO-slug + tracking-link + status features.
-- Run this ONCE on the PRODUCTION database (phpMyAdmin > SQL tab).
--
-- MySQL 8.0 has NO "ADD COLUMN IF NOT EXISTS", so we check
-- information_schema per column and add only what's missing.
-- Safe to run multiple times.
-- =================================================================

DROP PROCEDURE IF EXISTS _add_col_if_missing;
DELIMITER //
CREATE PROCEDURE _add_col_if_missing(
    IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
        PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
    END IF;
END //
DELIMITER ;

CALL _add_col_if_missing('repairs', 'tracking_id', '`tracking_id` int DEFAULT NULL AFTER `admin_id`');
CALL _add_col_if_missing('repairs', 'slug',        '`slug` varchar(255) DEFAULT NULL AFTER `title`');
CALL _add_col_if_missing('repairs', 'meta_desc',   '`meta_desc` varchar(300) DEFAULT NULL AFTER `slug`');
CALL _add_col_if_missing('repairs', 'status',      "`status` enum('published','draft','hidden') NOT NULL DEFAULT 'published' AFTER `category`");

DROP PROCEDURE IF EXISTS _add_col_if_missing;
