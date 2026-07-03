-- ============================================================
-- LINE groups — เก็บกลุ่ม/ห้องที่บอทถูกเชิญเข้าไป เพื่อ push แจ้งเตือน
-- + ให้พนักงาน (whitelist) สั่งคำสั่งในกลุ่มได้
-- รันบน prod: cPanel → phpMyAdmin → DB → SQL → วางแล้ว Go
-- ============================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS line_groups (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id   VARCHAR(64)  NOT NULL UNIQUE,   -- groupId / roomId จาก LINE
    group_name VARCHAR(160) NULL,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1, -- 1 = ส่งแจ้งเตือนเข้ากลุ่มนี้
    added_by   VARCHAR(64)  NULL,               -- userId คนที่เชิญบอทเข้า (ถ้ารู้)
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
