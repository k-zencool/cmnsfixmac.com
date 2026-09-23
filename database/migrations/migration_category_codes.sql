-- ============================================================
-- migration_category_codes.sql
--
-- ใส่ "รหัส" ให้หมวดหมู่ เพื่อให้ระบบประกอบ SKU เองได้
--   หมวดหลัก (root)  = รหัสอุปกรณ์   MB, IP, PD, IM, AP, MM, WA, OT
--   หมวดย่อย         = รหัสชิ้นส่วน  SCRN, BATT, FILM, FLEX, ...
--
-- SKU ที่ได้ = <รหัสอุปกรณ์>-<รหัสชิ้นส่วน>-<รุ่นที่พิมพ์เอง>   เช่น IP-FILM-15PM
-- โค้ดอ่านค่าพวกนี้ที่ includes/sku_lib.php
--
-- ต้องรันหลัง migration_parts_rename.sql (หมวดย่อยถูกสร้างที่นั่น)
-- รันซ้ำได้
-- ============================================================

-- ---------- 1. คอลัมน์ code ----------
-- MySQL 8 ไม่มี ADD COLUMN IF NOT EXISTS (guard แบบเดียวกับ migration_storage_bins.sql)
DROP PROCEDURE IF EXISTS _add_cat_code;
DELIMITER //
CREATE PROCEDURE _add_cat_code()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parts_categories' AND COLUMN_NAME = 'code'
    ) THEN
        ALTER TABLE parts_categories ADD COLUMN code VARCHAR(8) DEFAULT NULL AFTER name;
    END IF;
END //
DELIMITER ;
CALL _add_cat_code();
DROP PROCEDURE IF EXISTS _add_cat_code;


START TRANSACTION;

-- ---------- 2. รหัสอุปกรณ์ (หมวดหลัก) ----------
UPDATE parts_categories SET code = 'MB' WHERE parent_id IS NULL AND name = 'Macbook';
UPDATE parts_categories SET code = 'IP' WHERE parent_id IS NULL AND name = 'iPhone';
UPDATE parts_categories SET code = 'PD' WHERE parent_id IS NULL AND name = 'iPad';
UPDATE parts_categories SET code = 'IM' WHERE parent_id IS NULL AND name = 'iMac';
UPDATE parts_categories SET code = 'AP' WHERE parent_id IS NULL AND name = 'AirPods';
UPDATE parts_categories SET code = 'MM' WHERE parent_id IS NULL AND name = 'Macmini';
UPDATE parts_categories SET code = 'WA' WHERE parent_id IS NULL AND name = 'Watch';
UPDATE parts_categories SET code = 'OT' WHERE parent_id IS NULL AND name = 'Other';

-- ---------- 3. รหัสชิ้นส่วน (หมวดย่อย) ----------
-- ชื่อหมวดย่อยซ้ำกันได้ข้ามอุปกรณ์ (Battery มีทั้งใต้ Macbook/iPhone/iPad/...)
-- เลยไล่ตามชื่อทีเดียว ไม่ต้องแยกราย parent
UPDATE parts_categories SET code = 'SCRN'  WHERE parent_id IS NOT NULL AND name = 'Display / Screen';
UPDATE parts_categories SET code = 'BATT'  WHERE parent_id IS NOT NULL AND name = 'Battery';
UPDATE parts_categories SET code = 'KBD'   WHERE parent_id IS NOT NULL AND name = 'Keyboard';
UPDATE parts_categories SET code = 'TRKP'  WHERE parent_id IS NOT NULL AND name = 'Trackpad';
UPDATE parts_categories SET code = 'FLEX'  WHERE parent_id IS NOT NULL AND name = 'Flex Cable';
UPDATE parts_categories SET code = 'DCIN'  WHERE parent_id IS NOT NULL AND name = 'Charging Port';
UPDATE parts_categories SET code = 'GLUE'  WHERE parent_id IS NOT NULL AND name = 'Adhesive / Bezel';
UPDATE parts_categories SET code = 'ADPT'  WHERE parent_id IS NOT NULL AND name = 'Adapter / Charger';
UPDATE parts_categories SET code = 'LOGIC' WHERE parent_id IS NOT NULL AND name = 'Logic Board';
UPDATE parts_categories SET code = 'STOR'  WHERE parent_id IS NOT NULL AND name = 'Storage / RAM';
UPDATE parts_categories SET code = 'FILM'  WHERE parent_id IS NOT NULL AND name = 'Screen Protector';
UPDATE parts_categories SET code = 'TOUCH' WHERE parent_id IS NOT NULL AND name = 'Touchscreen';


-- ---------- ตรวจก่อน COMMIT — ต้องได้ 0 ----------
SELECT COUNT(*) AS หมวดที่ยังไม่มีรหัส FROM parts_categories WHERE code IS NULL OR code = '';

COMMIT;
-- ไม่เป็น 0 -> ROLLBACK; แล้วดูว่าหมวดไหนตกหล่น:
--   SELECT id, parent_id, name FROM parts_categories WHERE code IS NULL OR code = '';
-- หมวดที่เพิ่มเองทีหลังต้องใส่รหัสในหน้า "จัดการหมวดหมู่" ไม่งั้นระบบจะออก SKU ให้ไม่ได้
