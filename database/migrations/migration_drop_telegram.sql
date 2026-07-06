-- migration_drop_telegram.sql
-- Run once on production DB — เลิกใช้ Telegram ทั้งหมด
-- หมายเหตุ: คอลัมน์ admin_users.telegram_chat_id ไม่เคยมีจริงทั้ง prod/local
-- (bot_hook อ้างถึงแต่ไม่เคยถูกสร้าง) จึงไม่มีอะไรต้อง DROP — ลบแค่ setting keys

DELETE FROM `notification_settings`
WHERE `setting_key` IN ('telegram_bot_token', 'telegram_chat_id', 'notify_telegram_enabled');
