-- migration_notification_settings.sql
-- Run once on production DB
-- kv store for notification control (LINE + Telegram on/off, Telegram creds)

CREATE TABLE IF NOT EXISTS `notification_settings` (
  `setting_key`   VARCHAR(64) NOT NULL,
  `setting_value` TEXT,
  `updated_at`    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- defaults: ทุกช่องเปิดไว้ก่อน, chat_id = ค่าปัจจุบันที่ hardcode อยู่ (กันพังช่วงเปลี่ยนผ่าน)
-- telegram_bot_token ปล่อยว่าง → helper จะ fallback ไปใช้ค่า hardcode เดิมจนกว่าจะกรอก token ใหม่ผ่านหน้าเว็บ
INSERT IGNORE INTO `notification_settings` (`setting_key`, `setting_value`) VALUES
  ('notify_line_enabled',     '1'),
  ('notify_telegram_enabled', '1'),
  ('notify_morning_enabled',  '1'),
  ('notify_evening_enabled',  '1'),
  ('telegram_chat_id',        '-1002778708648'),
  ('telegram_bot_token',      '');
