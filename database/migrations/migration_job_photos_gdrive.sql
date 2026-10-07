-- migration_job_photos_gdrive.sql
-- รูปเครื่องลูกค้าต่องานซ่อม เก็บบน Google Drive ของร้าน (ไม่มีไฟล์บน host)
-- ใช้โดย includes/gdrive_lib.php, admin/tracking/photo_api.php,
--        admin/settings/gdrive.php, admin/settings/gdrive_callback.php
--
-- Safe to run more than once.

-- one row (id = 1): OAuth app + the connected Google account
CREATE TABLE IF NOT EXISTS gdrive_config (
    id                TINYINT UNSIGNED NOT NULL,
    client_id         VARCHAR(255) DEFAULT NULL,
    client_secret     VARCHAR(255) DEFAULT NULL,
    refresh_token     VARCHAR(512) DEFAULT NULL,
    access_token      VARCHAR(2048) DEFAULT NULL,
    access_expires_at DATETIME DEFAULT NULL,
    account_email     VARCHAR(255) DEFAULT NULL,
    root_folder_id    VARCHAR(128) DEFAULT NULL,      -- "CMNS Job Photos"
    connected_at      DATETIME DEFAULT NULL,
    connected_by      INT DEFAULT NULL,
    last_error        VARCHAR(500) DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO gdrive_config (id) VALUES (1);

-- Drive folders per job, made on the first upload:
-- stage '' = the job folder, 'intake'/'repair'/'return' = its 3 sub-folders
CREATE TABLE IF NOT EXISTS tracking_drive_folders (
    tracking_id  INT NOT NULL,
    stage        VARCHAR(10) NOT NULL DEFAULT '',
    folder_id    VARCHAR(128) NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tracking_id, stage)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tracking_photos (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tracking_id    INT NOT NULL,
    stage          ENUM('intake','repair','return') NOT NULL,
    drive_file_id  VARCHAR(128) NOT NULL,
    file_name      VARCHAR(255) NOT NULL,
    caption        VARCHAR(500) DEFAULT NULL,             -- staff note, mirrored to the Drive file description
    size_bytes     INT UNSIGNED DEFAULT NULL,
    width          SMALLINT UNSIGNED DEFAULT NULL,
    height         SMALLINT UNSIGNED DEFAULT NULL,
    uploaded_by    INT DEFAULT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_job_stage (tracking_id, stage),
    UNIQUE KEY uq_drive_file (drive_file_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
