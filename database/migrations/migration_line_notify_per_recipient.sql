-- ============================================================
-- Per-recipient LINE alert toggle (Notification Center)
--   admin_users.line_notify_enabled: 1 = รับแจ้งเตือน 1:1, 0 = ปิดรับ
--   (กลุ่มมี toggle อยู่แล้วผ่าน line_groups.is_active)
-- Run on prod manually via phpMyAdmin.
-- ============================================================

ALTER TABLE admin_users
    ADD COLUMN line_notify_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER line_display_name;
