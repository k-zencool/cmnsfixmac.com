-- =================================================================
-- Feature: admin/user/ online status, device tracking, force-logout
-- Adds:
--   admin_sessions table  (one row per login — online status, device,
--                           revocation for force-logout)
--   admin_users.is_active, admin_users.deleted_at (soft-delete)
-- Run this ONCE per environment (local Docker + production via phpMyAdmin).
-- Deploy never runs this automatically — must be applied manually.
--
-- MySQL 8.0 has NO "ADD COLUMN IF NOT EXISTS", so we check
-- information_schema per column and add only what's missing.
-- Safe to run multiple times.
-- =================================================================

CREATE TABLE IF NOT EXISTS admin_sessions (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id     INT NOT NULL,
    session_hash CHAR(64) NOT NULL,
    ip           VARCHAR(45) DEFAULT NULL,
    user_agent   VARCHAR(255) DEFAULT NULL,
    device_label VARCHAR(120) DEFAULT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at   DATETIME DEFAULT NULL,
    revoked_by   INT DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_session_hash (session_hash),
    KEY idx_admin_active (admin_id, revoked_at, last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CALL _add_col_if_missing('admin_users', 'is_active',  '`is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `role`');
CALL _add_col_if_missing('admin_users', 'deleted_at', '`deleted_at` DATETIME DEFAULT NULL AFTER `bio`');

DROP PROCEDURE IF EXISTS _add_col_if_missing;
