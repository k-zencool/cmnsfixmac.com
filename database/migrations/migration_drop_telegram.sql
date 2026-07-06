-- migration_drop_telegram.sql
-- Run once on production DB — เลิกใช้ Telegram ทั้งหมด
-- 1) ลบคอลัมน์ที่เก็บ Telegram chat id ของ admin
-- 2) ลบ setting keys ของ Telegram ออกจาก notification_settings

ALTER TABLE `admin_users` DROP COLUMN `telegram_chat_id`;

DELETE FROM `notification_settings`
WHERE `setting_key` IN ('telegram_bot_token', 'telegram_chat_id', 'notify_telegram_enabled');
