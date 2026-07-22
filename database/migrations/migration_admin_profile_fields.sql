-- =================================================================
-- Fix: "Database Error: SQLSTATE[42S22]... Unknown column 'avatar'"
--      on admin/profile/index.php (avatar upload + save profile form)
-- Cause: admin/profile/index.php, upload_avatar.php, update_profile.php
--        were built against columns that were never added to admin_users
--        (avatar, full_name, phone, email, bio).
-- Run this ONCE per environment (local Docker + production via phpMyAdmin).
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

CALL _add_col_if_missing('admin_users', 'avatar',    '`avatar` varchar(255) DEFAULT NULL AFTER `role`');
CALL _add_col_if_missing('admin_users', 'full_name', '`full_name` varchar(150) DEFAULT NULL AFTER `avatar`');
CALL _add_col_if_missing('admin_users', 'phone',     '`phone` varchar(30) DEFAULT NULL AFTER `full_name`');
CALL _add_col_if_missing('admin_users', 'email',     '`email` varchar(150) DEFAULT NULL AFTER `phone`');
CALL _add_col_if_missing('admin_users', 'bio',       '`bio` varchar(500) DEFAULT NULL AFTER `email`');

DROP PROCEDURE IF EXISTS _add_col_if_missing;
