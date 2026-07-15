-- migration_line_reports_channel.sql
-- รองรับ LINE บอทตัวที่ 2 (channel 'line_reports' — รายงานเช้า/เย็น แยกโควต้าจากบอทหลัก)
-- เดิม platform เป็น ENUM('facebook','line') → ขยายเป็น VARCHAR เพื่อเพิ่ม channel ใหม่ได้
-- ค่า config ของบอทใหม่กรอกผ่านหน้า /admin/settings/notifications.php (ไม่ต้อง INSERT ที่นี่)

ALTER TABLE chat_platform_config
    MODIFY COLUMN platform VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;
