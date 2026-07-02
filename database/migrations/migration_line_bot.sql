-- ============================================================
-- LINE bot migration — ย้ายบอทแจ้งเตือน/สั่งงานซ่อมไป LINE (รันคู่ Telegram ได้)
-- วิธีรัน:
--   docker exec -i cmns-db mysql -ucmns_user -pcmns_password cmnsfixmac_db < database/migrations/migration_line_bot.sql
-- ============================================================

SET NAMES utf8mb4;

-- 1. whitelist: ผูก LINE userId กับ admin (กันคนนอกเห็นข้อมูล — pattern เดียวกับ telegram_chat_id เดิม)
-- NOTE: MySQL 8 ไม่รองรับ ADD COLUMN IF NOT EXISTS — รันครั้งเดียว (ถ้าซ้ำจะ error ว่ามีคอลัมน์แล้ว ข้ามได้)
ALTER TABLE admin_users
    ADD COLUMN line_user_id      VARCHAR(64)  NULL UNIQUE AFTER role,
    ADD COLUMN line_display_name VARCHAR(120) NULL        AFTER line_user_id,
    ADD COLUMN line_linked_at    DATETIME     NULL        AFTER line_display_name;

-- 2. คนที่ทักบอทมาแต่ยังไม่ได้รับอนุมัติ (รอ super_admin จับคู่กับ admin_users)
CREATE TABLE IF NOT EXISTS line_pending_links (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    line_user_id  VARCHAR(64)  NOT NULL UNIQUE,
    display_name  VARCHAR(120) NULL,
    code          VARCHAR(8)   NULL,            -- รหัสสั้นให้พนักงานบอก super_admin
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
