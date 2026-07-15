-- migration_line_per_duty_recipients.sql
-- ผู้รับแจ้งเตือน LINE แยกต่อบอท (duty): jobs = การ์ดงานซ่อม (บอทหลัก) · reports = รายงานเช้า-เย็น (บอทรายงาน)
-- ต้องรันหลัง migration_line_notify_per_recipient.sql (ใช้ค่า line_notify_enabled เดิมเป็นค่าตั้งต้น)

-- กลุ่ม: เลือกได้ว่ากลุ่มไหนรับจากบอทไหน (is_active ยังเป็น master switch — บอทโดนเตะ = 0)
ALTER TABLE line_groups
    ADD COLUMN recv_jobs    TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active,
    ADD COLUMN recv_reports TINYINT(1) NOT NULL DEFAULT 1 AFTER recv_jobs;

-- admin รายคน: แยกรับต่อ duty · ตั้งต้นตามค่าสวิตช์รวมเดิม แล้วถอดคอลัมน์เก่าทิ้ง
ALTER TABLE admin_users
    ADD COLUMN line_notify_jobs    TINYINT(1) NOT NULL DEFAULT 1 AFTER line_notify_enabled,
    ADD COLUMN line_notify_reports TINYINT(1) NOT NULL DEFAULT 1 AFTER line_notify_jobs;

UPDATE admin_users
   SET line_notify_jobs    = line_notify_enabled,
       line_notify_reports = line_notify_enabled;

ALTER TABLE admin_users DROP COLUMN line_notify_enabled;
