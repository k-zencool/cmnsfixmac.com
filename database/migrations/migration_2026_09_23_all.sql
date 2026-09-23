-- ############################################################
--  migration_2026_09_23_all.sql
--
--  รวม migration ของวันที่ 23 ก.ย. 2026 ไว้ไฟล์เดียว paste ทีเดียวจบ
--  ประกอบจาก 4 ไฟล์นี้ (ยังอยู่ครบ ถ้าอยากรันแยกทีละตัว):
--      1. migration_storage_bins.sql
--      2. migration_parts_rename.sql
--      3. migration_inventory_cleanup.sql
--      4. migration_category_codes.sql
--
--  ก่อนรัน: Export ฐานข้อมูลเก็บไว้ก่อน (แตะข้อมูลจริง 115 แถว)
--  หลังรัน: เลื่อนไปดูผลตรวจท้ายไฟล์ ต้องเป็น 0 ทุกตัว
--
--  หมายเหตุ: MySQL จะ commit เองทุกครั้งที่เจอคำสั่งสร้าง/แก้ตาราง
--  สี่ส่วนนี้จึงรวมเป็นทรานแซกชันเดียวไม่ได้ ไม่ว่าจะจัดยังไง
--  ถ้าส่วนไหนพัง ส่วนก่อนหน้าจะลงไปแล้ว — ทุกไฟล์รันซ้ำได้ ไม่ซ้ำซ้อน
-- ############################################################


-- ====================================================================
-- ชุดที่ 1 จาก 4 — migration_storage_bins.sql
-- ชั้นเก็บของ + ช่อง (ตารางใหม่ 2 ตาราง + inventory.bin_id)
-- ====================================================================
-- a shelf: "A", "B", … — a slot's code is shelf code + 2-digit slot ("A-03")
CREATE TABLE IF NOT EXISTS storage_shelves (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code        VARCHAR(4) NOT NULL,                  -- A, B, … AA
    name        VARCHAR(100) DEFAULT NULL,            -- optional, e.g. "ชั้นหลังร้าน"
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- a slot on a shelf, holding one box. What may go in is fixed per slot
-- (item_type + root category); NULL = not set yet, nothing goes in.
CREATE TABLE IF NOT EXISTS storage_bins (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    shelf_id     INT UNSIGNED NOT NULL,
    slot         SMALLINT UNSIGNED NOT NULL,          -- 1..99
    item_type    ENUM('new','used','machine','sale') DEFAULT NULL,
    category_id  INT UNSIGNED DEFAULT NULL,           -- root parts_categories.id
    note         VARCHAR(200) DEFAULT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_shelf_slot (shelf_id, slot),
    CONSTRAINT fk_bin_shelf FOREIGN KEY (shelf_id) REFERENCES storage_shelves (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- inventory.bin_id — which slot the item sits in (free-text `location` stays as it was)
DROP PROCEDURE IF EXISTS _add_bin_col;
DELIMITER //
CREATE PROCEDURE _add_bin_col()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory' AND COLUMN_NAME = 'bin_id'
    ) THEN
        ALTER TABLE inventory
            ADD COLUMN bin_id INT UNSIGNED DEFAULT NULL AFTER location,
            ADD KEY idx_bin (bin_id),
            ADD CONSTRAINT fk_inv_bin FOREIGN KEY (bin_id) REFERENCES storage_bins (id) ON DELETE SET NULL;
    END IF;
END //
DELIMITER ;
CALL _add_bin_col();
DROP PROCEDURE IF EXISTS _add_bin_col;


-- ====================================================================
-- ชุดที่ 2 จาก 4 — migration_parts_rename.sql
-- SKU + ชื่อ + คำอธิบายไทย ของอะไหล่ 115 แถว (+ inventory.name_th, หมวดย่อย 22 หมวด)
-- ====================================================================
-- ---------- 0. inventory.name_th ----------
-- Thai description shown under the English name.
-- (MySQL 8 has no ADD COLUMN IF NOT EXISTS — same guard as migration_storage_bins.sql)
DROP PROCEDURE IF EXISTS _add_name_th;
DELIMITER //
CREATE PROCEDURE _add_name_th()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory' AND COLUMN_NAME = 'name_th'
    ) THEN
        ALTER TABLE inventory ADD COLUMN name_th VARCHAR(200) DEFAULT NULL AFTER name;
    END IF;
END //
DELIMITER ;
CALL _add_name_th();
DROP PROCEDURE IF EXISTS _add_name_th;

START TRANSACTION;

-- ---------- 1. subcategories ----------
INSERT INTO parts_categories (parent_id, name, icon, description)
SELECT 1, 'Display / Screen', 'monitor', ''
FROM DUAL WHERE NOT EXISTS (
  SELECT 1 FROM parts_categories WHERE parent_id = 1 AND name = 'Display / Screen');
INSERT INTO parts_categories (parent_id, name, icon, description)
SELECT 1, 'Battery', 'battery_full', ''
FROM DUAL WHERE NOT EXISTS (
  SELECT 1 FROM parts_categories WHERE parent_id = 1 AND name = 'Battery');
INSERT INTO parts_categories (parent_id, name, icon, description)
SELECT 1, 'Keyboard', 'keyboard', ''
FROM DUAL WHERE NOT EXISTS (
  SELECT 1 FROM parts_categories WHERE parent_id = 1 AND name = 'Keyboard');
INSERT INTO parts_categories (parent_id, name, icon, description)
SELECT 1, 'Trackpad', 'trackpad_input', ''
FROM DUAL WHERE NOT EXISTS (
  SELECT 1 FROM parts_categories WHERE parent_id = 1 AND name = 'Trackpad');
INSERT INTO parts_categories (parent_id, name, icon, description)
SELECT 1, 'Flex Cable', 'cable', ''
FROM DUAL WHERE NOT EXISTS (
  SELECT 1 FROM parts_categories WHERE parent_id = 1 AND name = 'Flex Cable');
INSERT INTO parts_categories (parent_id, name, icon, description)
SELECT 1, 'Charging Port', 'power', ''
FROM DUAL WHERE NOT EXISTS (
  SELECT 1 FROM parts_categories WHERE parent_id = 1 AND name = 'Charging Port');
INSERT INTO parts_categories (parent_id, name, icon, description)
SELECT 1, 'Adhesive / Bezel', 'colors', ''
FROM DUAL WHERE NOT EXISTS (
  SELECT 1 FROM parts_categories WHERE parent_id = 1 AND name = 'Adhesive / Bezel');
INSERT INTO parts_categories (parent_id, name, icon, description)
SELECT 1, 'Adapter / Charger', 'power_input', ''
FROM DUAL WHERE NOT EXISTS (
  SELECT 1 FROM parts_categories WHERE parent_id = 1 AND name = 'Adapter / Charger');
INSERT INTO parts_categories (parent_id, name, icon, description)
SELECT 1, 'Logic Board', 'developer_board', ''
FROM DUAL WHERE NOT EXISTS (
  SELECT 1 FROM parts_categories WHERE parent_id = 1 AND name = 'Logic Board');
INSERT INTO parts_categories (parent_id, name, icon, description)
SELECT 1, 'Storage / RAM', 'memory', ''
FROM DUAL WHERE NOT EXISTS (
  SELECT 1 FROM parts_categories WHERE parent_id = 1 AND name = 'Storage / RAM');
INSERT INTO parts_categories (parent_id, name, icon, description)
SELECT 2, 'Screen Protector', 'screen_lock_portrait', ''
FROM DUAL WHERE NOT EXISTS (
  SELECT 1 FROM parts_categories WHERE parent_id = 2 AND name = 'Screen Protector');
INSERT INTO parts_categories (parent_id, name, icon, description)
SELECT 2, 'Battery', 'battery_full', ''
FROM DUAL WHERE NOT EXISTS (
  SELECT 1 FROM parts_categories WHERE parent_id = 2 AND name = 'Battery');
INSERT INTO parts_categories (parent_id, name, icon, description)
SELECT 2, 'Flex Cable', 'cable', ''
FROM DUAL WHERE NOT EXISTS (
  SELECT 1 FROM parts_categories WHERE parent_id = 2 AND name = 'Flex Cable');
INSERT INTO parts_categories (parent_id, name, icon, description)
SELECT 3, 'Display / Screen', 'monitor', ''
FROM DUAL WHERE NOT EXISTS (
  SELECT 1 FROM parts_categories WHERE parent_id = 3 AND name = 'Display / Screen');
INSERT INTO parts_categories (parent_id, name, icon, description)
SELECT 3, 'Touchscreen', 'touch_app', ''
FROM DUAL WHERE NOT EXISTS (
  SELECT 1 FROM parts_categories WHERE parent_id = 3 AND name = 'Touchscreen');
INSERT INTO parts_categories (parent_id, name, icon, description)
SELECT 3, 'Battery', 'battery_full', ''
FROM DUAL WHERE NOT EXISTS (
  SELECT 1 FROM parts_categories WHERE parent_id = 3 AND name = 'Battery');
INSERT INTO parts_categories (parent_id, name, icon, description)
SELECT 4, 'Adhesive / Bezel', 'colors', ''
FROM DUAL WHERE NOT EXISTS (
  SELECT 1 FROM parts_categories WHERE parent_id = 4 AND name = 'Adhesive / Bezel');
INSERT INTO parts_categories (parent_id, name, icon, description)
SELECT 4, 'Flex Cable', 'cable', ''
FROM DUAL WHERE NOT EXISTS (
  SELECT 1 FROM parts_categories WHERE parent_id = 4 AND name = 'Flex Cable');
INSERT INTO parts_categories (parent_id, name, icon, description)
SELECT 4, 'Adapter / Charger', 'power_input', ''
FROM DUAL WHERE NOT EXISTS (
  SELECT 1 FROM parts_categories WHERE parent_id = 4 AND name = 'Adapter / Charger');
INSERT INTO parts_categories (parent_id, name, icon, description)
SELECT 5, 'Battery', 'battery_full', ''
FROM DUAL WHERE NOT EXISTS (
  SELECT 1 FROM parts_categories WHERE parent_id = 5 AND name = 'Battery');
INSERT INTO parts_categories (parent_id, name, icon, description)
SELECT 7, 'Battery', 'battery_full', ''
FROM DUAL WHERE NOT EXISTS (
  SELECT 1 FROM parts_categories WHERE parent_id = 7 AND name = 'Battery');
INSERT INTO parts_categories (parent_id, name, icon, description)
SELECT 8, 'Adapter / Charger', 'power_input', ''
FROM DUAL WHERE NOT EXISTS (
  SELECT 1 FROM parts_categories WHERE parent_id = 8 AND name = 'Adapter / Charger');

-- ---------- 2. parts ----------

-- 2 / Screen Protector
UPDATE inventory SET sku = 'IP-FILM-X', name = 'Screen Protector iPhone X/XS/11 Pro', name_th = 'ฟิล์มกันรอย iPhone X / XS / 11 Pro', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Screen Protector' LIMIT 1) AS c), compatible_models = 'iPhone X, iPhone XS, iPhone 11 Pro' WHERE id = 30119;
UPDATE inventory SET sku = 'IP-FILM-XSMAX', name = 'Screen Protector iPhone XS Max/11 Pro Max', name_th = 'ฟิล์มกันรอย iPhone XS Max / 11 Pro Max', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Screen Protector' LIMIT 1) AS c), compatible_models = 'iPhone XS Max, iPhone 11 Pro Max' WHERE id = 30120;
UPDATE inventory SET sku = 'IP-FILM-XR', name = 'Screen Protector iPhone XR/11', name_th = 'ฟิล์มกันรอย iPhone XR / 11', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Screen Protector' LIMIT 1) AS c), compatible_models = 'iPhone XR, iPhone 11' WHERE id = 30121;
UPDATE inventory SET sku = 'IP-FILM-11', name = 'Screen Protector iPhone 11', name_th = 'ฟิล์มกันรอย iPhone 11', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Screen Protector' LIMIT 1) AS c), compatible_models = 'iPhone 11' WHERE id = 30118;
UPDATE inventory SET sku = 'IP-FILM-12', name = 'Screen Protector iPhone 12', name_th = 'ฟิล์มกันรอย iPhone 12', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Screen Protector' LIMIT 1) AS c), compatible_models = 'iPhone 12' WHERE id = 30122;
UPDATE inventory SET sku = 'IP-FILM-12MINI', name = 'Screen Protector iPhone 12 mini', name_th = 'ฟิล์มกันรอย iPhone 12 mini', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Screen Protector' LIMIT 1) AS c), compatible_models = 'iPhone 12 mini' WHERE id = 30123;
UPDATE inventory SET sku = 'IP-FILM-12PRO', name = 'Screen Protector iPhone 12 Pro', name_th = 'ฟิล์มกันรอย iPhone 12 Pro', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Screen Protector' LIMIT 1) AS c), compatible_models = 'iPhone 12 Pro' WHERE id = 30124;
UPDATE inventory SET sku = 'IP-FILM-12PM', name = 'Screen Protector iPhone 12 Pro Max', name_th = 'ฟิล์มกันรอย iPhone 12 Pro Max', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Screen Protector' LIMIT 1) AS c), compatible_models = 'iPhone 12 Pro Max' WHERE id = 30125;
UPDATE inventory SET sku = 'IP-FILM-13', name = 'Screen Protector iPhone 13', name_th = 'ฟิล์มกันรอย iPhone 13', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Screen Protector' LIMIT 1) AS c), compatible_models = 'iPhone 13' WHERE id = 30126;
UPDATE inventory SET sku = 'IP-FILM-13MINI', name = 'Screen Protector iPhone 13 mini', name_th = 'ฟิล์มกันรอย iPhone 13 mini', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Screen Protector' LIMIT 1) AS c), compatible_models = 'iPhone 13 mini' WHERE id = 30127;
UPDATE inventory SET sku = 'IP-FILM-13PRO', name = 'Screen Protector iPhone 13 Pro', name_th = 'ฟิล์มกันรอย iPhone 13 Pro', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Screen Protector' LIMIT 1) AS c), compatible_models = 'iPhone 13 Pro' WHERE id = 30128;
UPDATE inventory SET sku = 'IP-FILM-13PM', name = 'Screen Protector iPhone 13 Pro Max', name_th = 'ฟิล์มกันรอย iPhone 13 Pro Max', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Screen Protector' LIMIT 1) AS c), compatible_models = 'iPhone 13 Pro Max' WHERE id = 30129;
UPDATE inventory SET sku = 'IP-FILM-14PRO', name = 'Screen Protector iPhone 14 Pro', name_th = 'ฟิล์มกันรอย iPhone 14 Pro', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Screen Protector' LIMIT 1) AS c), compatible_models = 'iPhone 14 Pro' WHERE id = 30130;
UPDATE inventory SET sku = 'IP-FILM-14PM', name = 'Screen Protector iPhone 14 Pro Max', name_th = 'ฟิล์มกันรอย iPhone 14 Pro Max', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Screen Protector' LIMIT 1) AS c), compatible_models = 'iPhone 14 Pro Max' WHERE id = 30131;
UPDATE inventory SET sku = 'IP-FILM-15', name = 'Screen Protector iPhone 15', name_th = 'ฟิล์มกันรอย iPhone 15', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Screen Protector' LIMIT 1) AS c), compatible_models = 'iPhone 15' WHERE id = 30132;
UPDATE inventory SET sku = 'IP-FILM-15PLUS', name = 'Screen Protector iPhone 15 Plus', name_th = 'ฟิล์มกันรอย iPhone 15 Plus', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Screen Protector' LIMIT 1) AS c), compatible_models = 'iPhone 15 Plus' WHERE id = 30133;
UPDATE inventory SET sku = 'IP-FILM-15PRO', name = 'Screen Protector iPhone 15 Pro', name_th = 'ฟิล์มกันรอย iPhone 15 Pro', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Screen Protector' LIMIT 1) AS c), compatible_models = 'iPhone 15 Pro' WHERE id = 30134;
UPDATE inventory SET sku = 'IP-FILM-15PM', name = 'Screen Protector iPhone 15 Pro Max', name_th = 'ฟิล์มกันรอย iPhone 15 Pro Max', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Screen Protector' LIMIT 1) AS c), compatible_models = 'iPhone 15 Pro Max' WHERE id = 30135;
UPDATE inventory SET sku = 'IP-FILM-16', name = 'Screen Protector iPhone 16', name_th = 'ฟิล์มกันรอย iPhone 16', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Screen Protector' LIMIT 1) AS c), compatible_models = 'iPhone 16' WHERE id = 30136;
UPDATE inventory SET sku = 'IP-FILM-16PRO', name = 'Screen Protector iPhone 16 Pro', name_th = 'ฟิล์มกันรอย iPhone 16 Pro', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Screen Protector' LIMIT 1) AS c), compatible_models = 'iPhone 16 Pro' WHERE id = 30137;
UPDATE inventory SET sku = 'IP-FILM-16PLUS', name = 'Screen Protector iPhone 16 Plus', name_th = 'ฟิล์มกันรอย iPhone 16 Plus', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Screen Protector' LIMIT 1) AS c), compatible_models = 'iPhone 16 Plus' WHERE id = 30138;
UPDATE inventory SET sku = 'IP-FILM-16PM', name = 'Screen Protector iPhone 16 Pro Max', name_th = 'ฟิล์มกันรอย iPhone 16 Pro Max', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Screen Protector' LIMIT 1) AS c), compatible_models = 'iPhone 16 Pro Max' WHERE id = 30139;
UPDATE inventory SET sku = 'IP-FILM-17', name = 'Screen Protector iPhone 17', name_th = 'ฟิล์มกันรอย iPhone 17', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Screen Protector' LIMIT 1) AS c), compatible_models = 'iPhone 17' WHERE id = 30140;
UPDATE inventory SET sku = 'IP-FILM-17AIR', name = 'Screen Protector iPhone 17 Air', name_th = 'ฟิล์มกันรอย iPhone 17 Air', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Screen Protector' LIMIT 1) AS c), compatible_models = 'iPhone 17 Air' WHERE id = 30141;
UPDATE inventory SET sku = 'IP-FILM-17PRO', name = 'Screen Protector iPhone 17 Pro', name_th = 'ฟิล์มกันรอย iPhone 17 Pro', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Screen Protector' LIMIT 1) AS c), compatible_models = 'iPhone 17 Pro' WHERE id = 30142;
UPDATE inventory SET sku = 'IP-FILM-17PM', name = 'Screen Protector iPhone 17 Pro Max', name_th = 'ฟิล์มกันรอย iPhone 17 Pro Max', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Screen Protector' LIMIT 1) AS c), compatible_models = 'iPhone 17 Pro Max' WHERE id = 30143;

-- 2 / Battery
UPDATE inventory SET sku = 'IP-BATT-12PM', name = 'Battery iPhone 12 Pro Max (Dissing HC)', name_th = 'แบตเตอรี่ iPhone 12 Pro Max (Dissing HC)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Battery' LIMIT 1) AS c), compatible_models = 'iPhone 12 Pro Max' WHERE id = 30152;

-- 2 / Flex Cable
UPDATE inventory SET sku = 'IP-FLEX-DOCK-16PM', name = 'Charging Port Flex iPhone 16 Pro Max', name_th = 'แพรก้นชาร์จ iPhone 16 Pro Max', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 2 AND name = 'Flex Cable' LIMIT 1) AS c), compatible_models = 'iPhone 16 Pro Max' WHERE id = 30157;

-- 1 / Display / Screen
UPDATE inventory SET sku = 'MB-SCRN-A2338', name = 'Screen MacBook Pro A2338', name_th = 'จอ MacBook Pro A2338', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Display / Screen' LIMIT 1) AS c), compatible_models = 'A2338' WHERE id = 30037;
UPDATE inventory SET sku = 'MB-SCRN-A3240', name = 'Screen MacBook A3240', name_th = 'จอ MacBook A3240', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Display / Screen' LIMIT 1) AS c), compatible_models = 'A3240' WHERE id = 30158;
UPDATE inventory SET sku = 'MB-SCRN-A1466', name = 'Screen MacBook Air A1466', name_th = 'จอ MacBook Air A1466', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Display / Screen' LIMIT 1) AS c), compatible_models = 'A1466' WHERE id = 20137;
UPDATE inventory SET sku = 'MB-SCRN-A2179', name = 'Screen MacBook Air A2179', name_th = 'จอ MacBook Air A2179', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Display / Screen' LIMIT 1) AS c), compatible_models = 'A2179' WHERE id = 20136;
UPDATE inventory SET sku = 'MB-SCRN-A2337', name = 'Screen MacBook Air A2337', name_th = 'จอ MacBook Air A2337', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Display / Screen' LIMIT 1) AS c), compatible_models = 'A2337' WHERE id = 20139;
UPDATE inventory SET sku = 'MB-SCRN-A2681', name = 'Screen MacBook Air A2681', name_th = 'จอ MacBook Air A2681', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Display / Screen' LIMIT 1) AS c), compatible_models = 'A2681' WHERE id = 20122;
UPDATE inventory SET sku = 'MB-SCRN-A2941', name = 'Screen MacBook Air 15" A2941', name_th = 'จอ MacBook Air 15 นิ้ว A2941', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Display / Screen' LIMIT 1) AS c), compatible_models = 'A2941' WHERE id = 20140;
UPDATE inventory SET sku = 'MB-SCRN-A2941-2', name = 'Screen MacBook Air 15" A2941/A3114/A3241 (M2/M3/M4)', name_th = 'จอ MacBook Air 15 นิ้ว A2941 / A3114 / A3241 (M2 M3 M4)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Display / Screen' LIMIT 1) AS c), compatible_models = 'A2941, A3114, A3241' WHERE id = 20143;
UPDATE inventory SET sku = 'MB-SCRN-A2442', name = 'Screen MacBook Pro 14" A2442', name_th = 'จอ MacBook Pro 14 นิ้ว A2442', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Display / Screen' LIMIT 1) AS c), compatible_models = 'A2442' WHERE id = 20138;
UPDATE inventory SET sku = 'MB-SCRN-A1708-U', name = 'Screen MacBook Pro A1706/A1708 (Used)', name_th = 'จอ MacBook Pro A1706 / A1708 (มือสอง)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Display / Screen' LIMIT 1) AS c), compatible_models = 'A1706, A1708' WHERE id = 30156;
UPDATE inventory SET sku = 'MB-SCRN-A1708-U2', name = 'Screen MacBook Pro A1706/A1708 Full Lid Space Gray (Used)', name_th = 'จอ MacBook Pro A1706 / A1708 ยกหัว สี Space Gray (มือสอง)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Display / Screen' LIMIT 1) AS c), compatible_models = 'A1706, A1708' WHERE id = 30151;
UPDATE inventory SET sku = 'MB-SCRN-U', name = 'Screen MacBook (Used)', name_th = 'จอ MacBook (มือสอง ยังไม่ระบุรุ่น)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Display / Screen' LIMIT 1) AS c), compatible_models = NULL WHERE id = 30026;

-- 1 / Battery
UPDATE inventory SET sku = 'MB-BATT-A1965', name = 'Battery MacBook A1965', name_th = 'แบตเตอรี่ MacBook A1965', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Battery' LIMIT 1) AS c), compatible_models = 'A1965' WHERE id = 20294;
UPDATE inventory SET sku = 'MB-BATT-A1417', name = 'Battery MacBook A1417', name_th = 'แบตเตอรี่ MacBook A1417', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Battery' LIMIT 1) AS c), compatible_models = 'A1417' WHERE id = 20091;
UPDATE inventory SET sku = 'MB-BATT-A1496', name = 'Battery MacBook A1496', name_th = 'แบตเตอรี่ MacBook A1496', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Battery' LIMIT 1) AS c), compatible_models = 'A1496' WHERE id = 20090;
UPDATE inventory SET sku = 'MB-BATT-A1582', name = 'Battery MacBook A1582/A1493', name_th = 'แบตเตอรี่ MacBook A1582 / A1493', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Battery' LIMIT 1) AS c), compatible_models = 'A1582, A1493' WHERE id = 20089;
UPDATE inventory SET sku = 'MB-BATT-A1819', name = 'Battery MacBook A1819', name_th = 'แบตเตอรี่ MacBook A1819', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Battery' LIMIT 1) AS c), compatible_models = 'A1819' WHERE id = 20296;
UPDATE inventory SET sku = 'MB-BATT-A1819-2', name = 'Battery MacBook A1819', name_th = 'แบตเตอรี่ MacBook A1819', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Battery' LIMIT 1) AS c), compatible_models = 'A1819' WHERE id = 20297;
UPDATE inventory SET sku = 'MB-BATT-A1820', name = 'Battery MacBook A1820', name_th = 'แบตเตอรี่ MacBook A1820', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Battery' LIMIT 1) AS c), compatible_models = 'A1820' WHERE id = 20312;
UPDATE inventory SET sku = 'MB-BATT-A1953', name = 'Battery MacBook A1953', name_th = 'แบตเตอรี่ MacBook A1953', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Battery' LIMIT 1) AS c), compatible_models = 'A1953' WHERE id = 20315;
UPDATE inventory SET sku = 'MB-BATT-A1964', name = 'Battery MacBook A1964', name_th = 'แบตเตอรี่ MacBook A1964', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Battery' LIMIT 1) AS c), compatible_models = 'A1964' WHERE id = 20313;
UPDATE inventory SET sku = 'MB-BATT-A2113', name = 'Battery MacBook A2113', name_th = 'แบตเตอรี่ MacBook A2113', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Battery' LIMIT 1) AS c), compatible_models = 'A2113' WHERE id = 20311;
UPDATE inventory SET sku = 'MB-BATT-A2171', name = 'Battery MacBook A2171', name_th = 'แบตเตอรี่ MacBook A2171', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Battery' LIMIT 1) AS c), compatible_models = 'A2171' WHERE id = 20088;
UPDATE inventory SET sku = 'MB-BATT-A2519', name = 'Battery MacBook A2519', name_th = 'แบตเตอรี่ MacBook A2519', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Battery' LIMIT 1) AS c), compatible_models = 'A2519' WHERE id = 20336;
UPDATE inventory SET sku = 'MB-BATT-A2113-U', name = 'Battery MacBook A2113 (Used)', name_th = 'แบตเตอรี่ MacBook A2113 (มือสอง)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Battery' LIMIT 1) AS c), compatible_models = 'A2113' WHERE id = 30004;
UPDATE inventory SET sku = 'MB-BATT-U', name = 'Battery MacBook (Used)', name_th = 'แบตเตอรี่ MacBook (มือสอง ยังไม่ระบุรุ่น)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Battery' LIMIT 1) AS c), compatible_models = NULL WHERE id = 30027;

-- 1 / Keyboard
UPDATE inventory SET sku = 'MB-KBD-TH', name = 'Keyboard MacBook (Thai)', name_th = 'คีย์บอร์ด MacBook แป้นไทย', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Keyboard' LIMIT 1) AS c), compatible_models = NULL WHERE id = 20163;
UPDATE inventory SET sku = 'MB-KBD-UK', name = 'Keyboard MacBook (UK)', name_th = 'คีย์บอร์ด MacBook แป้น UK', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Keyboard' LIMIT 1) AS c), compatible_models = NULL WHERE id = 20164;
UPDATE inventory SET sku = 'MB-KBD-UK-2', name = 'Keyboard MacBook (UK)', name_th = 'คีย์บอร์ด MacBook แป้น UK', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Keyboard' LIMIT 1) AS c), compatible_models = NULL WHERE id = 20165;
UPDATE inventory SET sku = 'MB-KBD-UK-3', name = 'Keyboard MacBook (UK)', name_th = 'คีย์บอร์ด MacBook แป้น UK', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Keyboard' LIMIT 1) AS c), compatible_models = NULL WHERE id = 20166;

-- 1 / Trackpad
UPDATE inventory SET sku = 'MB-TRKP', name = 'Trackpad MacBook', name_th = 'ทัชแพด MacBook', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Trackpad' LIMIT 1) AS c), compatible_models = NULL WHERE id = 20329;
UPDATE inventory SET sku = 'MB-TRKP-U', name = 'Trackpad MacBook (Used)', name_th = 'ทัชแพด MacBook (มือสอง)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Trackpad' LIMIT 1) AS c), compatible_models = NULL WHERE id = 30022;

-- 1 / Flex Cable
UPDATE inventory SET sku = 'MB-FLEX-TRKP', name = 'Trackpad Flex Cable MacBook', name_th = 'สายแพรทัชแพด MacBook', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Flex Cable' LIMIT 1) AS c), compatible_models = NULL WHERE id = 20330;
UPDATE inventory SET sku = 'MB-FLEX-TRKP-A1708', name = 'Trackpad Flex Cable MacBook Pro A1708', name_th = 'สายแพรทัชแพด MacBook Pro A1708', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Flex Cable' LIMIT 1) AS c), compatible_models = 'A1708' WHERE id = 20157;
UPDATE inventory SET sku = 'MB-FLEX-TRKP-A2179', name = 'Trackpad Flex Cable MacBook Air A2179', name_th = 'สายแพรทัชแพด MacBook Air A2179', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Flex Cable' LIMIT 1) AS c), compatible_models = 'A2179' WHERE id = 20159;
UPDATE inventory SET sku = 'MB-FLEX-BL-14', name = 'Backlight Flex Cable MacBook 14"', name_th = 'สายแพร Backlight 14 นิ้ว', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Flex Cable' LIMIT 1) AS c), compatible_models = NULL WHERE id = 20154;
UPDATE inventory SET sku = 'MB-FLEX-BL-16', name = 'Backlight Flex Cable MacBook 16"', name_th = 'สายแพร Backlight 16 นิ้ว', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Flex Cable' LIMIT 1) AS c), compatible_models = NULL WHERE id = 20155;
UPDATE inventory SET sku = 'MB-FLEX-LCD', name = 'LCD Display Flex Cable MacBook', name_th = 'สายแพรจอ LCD MacBook', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Flex Cable' LIMIT 1) AS c), compatible_models = NULL WHERE id = 20158;

-- 1 / Charging Port
UPDATE inventory SET sku = 'MB-DCIN-USBC', name = 'DC-In Jack MacBook (USB-C)', name_th = 'ตูดชาร์จ USB-C (DC-in Jack)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Charging Port' LIMIT 1) AS c), compatible_models = NULL WHERE id = 20153;
UPDATE inventory SET sku = 'MB-DCIN-USBC-2', name = 'DC-In Jack MacBook (USB-C)', name_th = 'อะไหล่ตูดชาร์จ USB-C (พอร์ต DC-IN)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Charging Port' LIMIT 1) AS c), compatible_models = NULL WHERE id = 20156;
UPDATE inventory SET sku = 'MB-BOARD-USBC', name = 'Charging Port Board MacBook (USB-C)', name_th = 'บอร์ดพอร์ตชาร์จ USB-C', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Charging Port' LIMIT 1) AS c), compatible_models = NULL WHERE id = 20152;
UPDATE inventory SET sku = 'MB-DCIN-A1466-U', name = 'DC-In Jack MacBook Air A1466 (Used)', name_th = 'ตูดชาร์จ MacBook Air A1466 (มือสอง)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Charging Port' LIMIT 1) AS c), compatible_models = 'A1466' WHERE id = 30028;

-- 1 / Adhesive / Bezel
UPDATE inventory SET sku = 'MB-GLUE', name = 'Screen Adhesive MacBook', name_th = 'กาวจอ MacBook', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Adhesive / Bezel' LIMIT 1) AS c), compatible_models = NULL WHERE id = 20121;
UPDATE inventory SET sku = 'MB-GLUE-13', name = 'Screen Adhesive MacBook 13"', name_th = 'กาวจอ MacBook 13 นิ้ว', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Adhesive / Bezel' LIMIT 1) AS c), compatible_models = NULL WHERE id = 20145;
UPDATE inventory SET sku = 'MB-GLUE-16', name = 'Screen Adhesive MacBook 16" (M2+)', name_th = 'กาวจอ MacBook 16 นิ้ว (M2+)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Adhesive / Bezel' LIMIT 1) AS c), compatible_models = NULL WHERE id = 20144;
UPDATE inventory SET sku = 'MB-BEZEL-133', name = 'Display Bezel Rubber MacBook 13.3"', name_th = 'ขอบยางจอ 13.3 นิ้ว', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Adhesive / Bezel' LIMIT 1) AS c), compatible_models = NULL WHERE id = 20107;
UPDATE inventory SET sku = 'MB-BEZEL-136', name = 'Display Bezel Rubber MacBook 13.6"', name_th = 'ขอบยางจอ 13.6 นิ้ว', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Adhesive / Bezel' LIMIT 1) AS c), compatible_models = NULL WHERE id = 20110;
UPDATE inventory SET sku = 'MB-BEZEL-16', name = 'Display Bezel Rubber MacBook 16"', name_th = 'ขอบยางจอ 16 นิ้ว', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Adhesive / Bezel' LIMIT 1) AS c), compatible_models = NULL WHERE id = 20111;
UPDATE inventory SET sku = 'MB-BEZEL-162', name = 'Display Bezel Rubber MacBook 16.2"', name_th = 'ขอบยางจอ 16.2 นิ้ว', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Adhesive / Bezel' LIMIT 1) AS c), compatible_models = NULL WHERE id = 20112;
UPDATE inventory SET sku = 'MB-LOGO-A2337', name = 'Display Logo MacBook Air A2337', name_th = 'โลโก้จอ MacBook Air A2337', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Adhesive / Bezel' LIMIT 1) AS c), compatible_models = 'A2337' WHERE id = 20108;
UPDATE inventory SET sku = 'MB-LOGO-A2681', name = 'Display Logo MacBook Air A2681', name_th = 'โลโก้จอ MacBook Air A2681', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Adhesive / Bezel' LIMIT 1) AS c), compatible_models = 'A2681' WHERE id = 20109;
UPDATE inventory SET sku = 'MB-LOGO-U', name = 'Display Logo MacBook Pro (Used)', name_th = 'โลโก้จอ MacBook Pro (มือสอง)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Adhesive / Bezel' LIMIT 1) AS c), compatible_models = NULL WHERE id = 30008;

-- 1 / Adapter / Charger
UPDATE inventory SET sku = 'MB-ADPT-MAGSAFE-T1', name = 'MagSafe Adapter MacBook (Type 1)', name_th = 'อะแดปเตอร์ MagSafe MacBook (หัว T1)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Adapter / Charger' LIMIT 1) AS c), compatible_models = NULL WHERE id = 20146;
UPDATE inventory SET sku = 'MB-ADPT-MAGSAFE-T2', name = 'MagSafe Adapter MacBook (Type 2)', name_th = 'อะแดปเตอร์ MagSafe MacBook (หัว T2)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Adapter / Charger' LIMIT 1) AS c), compatible_models = NULL WHERE id = 20147;

-- 1 / Logic Board
UPDATE inventory SET sku = 'MB-GPU-U', name = 'Graphics Card MacBook (Used)', name_th = 'การ์ดจอ MacBook (มือสอง)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Logic Board' LIMIT 1) AS c), compatible_models = NULL WHERE id = 30016;
UPDATE inventory SET sku = 'MB-LOGIC-U', name = 'Logic Board MacBook (Used)', name_th = 'บอร์ด MacBook (มือสอง)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Logic Board' LIMIT 1) AS c), compatible_models = NULL WHERE id = 30025;

-- 1 / Storage / RAM
UPDATE inventory SET sku = 'MB-HDD-1TB-U', name = 'Hard Disk MacBook 1TB (Used)', name_th = 'ฮาร์ดดิสก์ MacBook 1TB (มือสอง)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Storage / RAM' LIMIT 1) AS c), compatible_models = NULL WHERE id = 30013;
UPDATE inventory SET sku = 'MB-RAM-U', name = 'RAM MacBook (Used)', name_th = 'แรม MacBook (มือสอง ยังไม่ระบุขนาด)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 1 AND name = 'Storage / RAM' LIMIT 1) AS c), compatible_models = NULL WHERE id = 30014;

-- 3 / Battery
UPDATE inventory SET sku = 'PD-BATT-A1577', name = 'Battery iPad mini 4 (A1577)', name_th = 'แบตเตอรี่ iPad mini 4 (A1577)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 3 AND name = 'Battery' LIMIT 1) AS c), compatible_models = 'A1577' WHERE id = 20266;
UPDATE inventory SET sku = 'PD-BATT-IPAD789', name = 'Battery iPad 7/8/9', name_th = 'แบตเตอรี่ iPad 7 / 8 / 9', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 3 AND name = 'Battery' LIMIT 1) AS c), compatible_models = 'iPad 7, iPad 8, iPad 9' WHERE id = 20334;
UPDATE inventory SET sku = 'PD-BATT-AIR2', name = 'Battery iPad Air 2', name_th = 'แบตเตอรี่ iPad Air 2', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 3 AND name = 'Battery' LIMIT 1) AS c), compatible_models = 'iPad Air 2' WHERE id = 20306;
UPDATE inventory SET sku = 'PD-BATT-AIR45', name = 'Battery iPad Air 4/5', name_th = 'แบตเตอรี่ iPad Air 4 / 5', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 3 AND name = 'Battery' LIMIT 1) AS c), compatible_models = 'iPad Air 4, iPad Air 5' WHERE id = 20314;
UPDATE inventory SET sku = 'PD-BATT-PRO129-G1', name = 'Battery iPad Pro 12.9" Gen 1', name_th = 'แบตเตอรี่ iPad Pro 12.9 นิ้ว Gen 1', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 3 AND name = 'Battery' LIMIT 1) AS c), compatible_models = 'iPad Pro 12.9 Gen 1' WHERE id = 20295;

-- 3 / Display / Screen
UPDATE inventory SET sku = 'PD-SCRN-IPAD10', name = 'Screen iPad 10', name_th = 'จอใน iPad Gen 10', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 3 AND name = 'Display / Screen' LIMIT 1) AS c), compatible_models = 'iPad 10' WHERE id = 20303;
UPDATE inventory SET sku = 'PD-SCRN-AIR45', name = 'Screen iPad Air 4/5', name_th = 'จอ iPad Air 4 / 5', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 3 AND name = 'Display / Screen' LIMIT 1) AS c), compatible_models = 'iPad Air 4, iPad Air 5' WHERE id = 30154;
UPDATE inventory SET sku = 'PD-SCRN-PRO11-2018', name = 'Screen iPad Pro 11" (2018)', name_th = 'จอ iPad Pro 11 นิ้ว (2018)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 3 AND name = 'Display / Screen' LIMIT 1) AS c), compatible_models = 'iPad Pro 11 2018' WHERE id = 30155;
UPDATE inventory SET sku = 'PD-SCRN-MINI13-U', name = 'Screen iPad mini 1/2/3 (Used)', name_th = 'จอ iPad mini 1 / 2 / 3 (มือสอง)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 3 AND name = 'Display / Screen' LIMIT 1) AS c), compatible_models = 'iPad mini 1, iPad mini 2, iPad mini 3' WHERE id = 30010;

-- 3 / Touchscreen
UPDATE inventory SET sku = 'PD-TOUCH-IPAD10', name = 'Touchscreen iPad 10', name_th = 'ทัชสกรีน iPad Gen 10', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 3 AND name = 'Touchscreen' LIMIT 1) AS c), compatible_models = 'iPad 10' WHERE id = 20304;
UPDATE inventory SET sku = 'PD-TOUCH-IPAD2-WH', name = 'Touchscreen iPad 2 (White)', name_th = 'ทัชสกรีน iPad 2 (ขาว)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 3 AND name = 'Touchscreen' LIMIT 1) AS c), compatible_models = 'iPad 2' WHERE id = 20123;
UPDATE inventory SET sku = 'PD-TOUCH-IPAD2-BK', name = 'Touchscreen iPad 2 (Black)', name_th = 'ทัชสกรีน iPad 2 (ดำ)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 3 AND name = 'Touchscreen' LIMIT 1) AS c), compatible_models = 'iPad 2' WHERE id = 20124;
UPDATE inventory SET sku = 'PD-TOUCH-IPAD5-WH', name = 'Touchscreen iPad 5 (White)', name_th = 'ทัชสกรีน iPad 5 (ขาว)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 3 AND name = 'Touchscreen' LIMIT 1) AS c), compatible_models = 'iPad 5' WHERE id = 20126;
UPDATE inventory SET sku = 'PD-TOUCH-IPAD5-BK', name = 'Touchscreen iPad 5 (Black)', name_th = 'ทัชสกรีน iPad 5 (ดำ)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 3 AND name = 'Touchscreen' LIMIT 1) AS c), compatible_models = 'iPad 5' WHERE id = 20125;
UPDATE inventory SET sku = 'PD-TOUCH-IPAD9-BK', name = 'Touchscreen iPad 9 (Black)', name_th = 'ทัชสกรีน iPad 9 (ดำ)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 3 AND name = 'Touchscreen' LIMIT 1) AS c), compatible_models = 'iPad 9' WHERE id = 20127;
UPDATE inventory SET sku = 'PD-TOUCH-AIR2-BK', name = 'Touchscreen iPad Air 2 (Black)', name_th = 'ทัชสกรีน iPad Air 2 (ดำ)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 3 AND name = 'Touchscreen' LIMIT 1) AS c), compatible_models = 'iPad Air 2' WHERE id = 20128;

-- 4 / Adhesive / Bezel
UPDATE inventory SET sku = 'IM-GLUE-21', name = 'Screen Adhesive iMac 21"', name_th = 'กาวจอ iMac 21 นิ้ว', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 4 AND name = 'Adhesive / Bezel' LIMIT 1) AS c), compatible_models = NULL WHERE id = 20118;
UPDATE inventory SET sku = 'IM-GLUE-24', name = 'Screen Adhesive iMac 24" (2020+)', name_th = 'กาวจอ iMac 24 นิ้ว (2020+)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 4 AND name = 'Adhesive / Bezel' LIMIT 1) AS c), compatible_models = NULL WHERE id = 20119;
UPDATE inventory SET sku = 'IM-GLUE-27', name = 'Screen Adhesive iMac 27"', name_th = 'กาวจอ iMac 27 นิ้ว', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 4 AND name = 'Adhesive / Bezel' LIMIT 1) AS c), compatible_models = NULL WHERE id = 20120;

-- 4 / Flex Cable
UPDATE inventory SET sku = 'IM-FLEX-M1-24-U', name = 'Display Flex Cable iMac 24" M1 (Used)', name_th = 'สายแพรจอ iMac 24 นิ้ว M1 (มือสอง)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 4 AND name = 'Flex Cable' LIMIT 1) AS c), compatible_models = NULL WHERE id = 30007;

-- 4 / Adapter / Charger
UPDATE inventory SET sku = 'IM-ADPT-24-U', name = 'Power Adapter iMac 24" M1 (Used)', name_th = 'อะแดปเตอร์ iMac 24 นิ้ว M1 (มือสอง)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 4 AND name = 'Adapter / Charger' LIMIT 1) AS c), compatible_models = NULL WHERE id = 30036;
UPDATE inventory SET sku = 'IM-PSU-ADP310AF-U', name = 'Power Supply iMac 27" (ADP-310AF B) (Used)', name_th = 'เพาเวอร์ซัพพลาย iMac 27 นิ้ว ADP-310AF B (มือสอง)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 4 AND name = 'Adapter / Charger' LIMIT 1) AS c), compatible_models = NULL WHERE id = 30015;

-- 5 / Battery
UPDATE inventory SET sku = 'AP-BATT-PRO2-L', name = 'Battery AirPods Pro 2 (Left)', name_th = 'แบตเตอรี่ AirPods Pro 2 (ข้างซ้าย)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 5 AND name = 'Battery' LIMIT 1) AS c), compatible_models = 'AirPods Pro 2' WHERE id = 20308;
UPDATE inventory SET sku = 'AP-BATT-PRO2-R', name = 'Battery AirPods Pro 2 (Right)', name_th = 'แบตเตอรี่ AirPods Pro 2 (ข้างขวา)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 5 AND name = 'Battery' LIMIT 1) AS c), compatible_models = 'AirPods Pro 2' WHERE id = 20309;

-- 7 / Battery
UPDATE inventory SET sku = 'WA-BATT-S4-40', name = 'Battery Apple Watch S4 40mm', name_th = 'แบตเตอรี่ Apple Watch S4 40mm', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 7 AND name = 'Battery' LIMIT 1) AS c), compatible_models = 'Apple Watch S4 40mm' WHERE id = 20151;
UPDATE inventory SET sku = 'WA-BATT-S6-44', name = 'Battery Apple Watch S6 44mm', name_th = 'แบตเตอรี่ Apple Watch S6 44mm', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 7 AND name = 'Battery' LIMIT 1) AS c), compatible_models = 'Apple Watch S6 44mm' WHERE id = 20335;

-- 8 / Adapter / Charger
UPDATE inventory SET sku = 'OT-ADPT-M2', name = 'M.2 Adapter for Mac', name_th = 'หัวแปลง M.2 ใส่ Mac', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 8 AND name = 'Adapter / Charger' LIMIT 1) AS c), compatible_models = NULL WHERE id = 30153;
UPDATE inventory SET sku = 'OT-PSU', name = 'Power Supply', name_th = 'เพาเวอร์ซัพพลาย (ยังไม่ระบุว่าของเครื่องไหน)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 8 AND name = 'Adapter / Charger' LIMIT 1) AS c), compatible_models = NULL WHERE id = 20162;
UPDATE inventory SET sku = 'OT-PSU-ADP185BF-U', name = 'Power Supply ADP-185BF T (Used)', name_th = 'เพาเวอร์ซัพพลาย ADP-185BF T (มือสอง)', category_id = (SELECT id FROM (SELECT id FROM parts_categories WHERE parent_id = 8 AND name = 'Adapter / Charger' LIMIT 1) AS c), compatible_models = NULL WHERE id = 30012;

COMMIT;
-- ถ้าตัวเลขไม่เป็น 0 → ROLLBACK; แทน COMMIT;


-- ====================================================================
-- ชุดที่ 3 จาก 4 — migration_inventory_cleanup.sql
-- เก็บกวาดข้อมูลที่ขัดกันเอง (สต็อก/สถานะเครื่อง/เครื่องที่ขายไปแล้ว)
-- ====================================================================
START TRANSACTION;


-- ------------------------------------------------------------
-- ส่วนที่ 1 — ของหมดแล้วแต่ยังบอกว่ามีของ
--
-- โปรไฟล์อะไหล่ที่ lot เหลือรวมกัน 0 ต้องเป็น OOS แต่ 12 แถวยังค้าง STOCK
-- (ของเก่าก่อนโค้ดจะ flip OOS อัตโนมัติ แล้วไม่เคยย้อนไปแก้)
-- คำนวณจาก lot จริง ไม่ได้ไล่ใส่ราย id
-- ------------------------------------------------------------
UPDATE inventory i
SET i.status = 'OOS'
WHERE i.type IN ('new', 'used')
  AND i.status = 'STOCK'
  AND COALESCE((SELECT SUM(l.qty_remaining) FROM inventory_lots l WHERE l.inventory_id = i.id), 0) = 0;


-- ------------------------------------------------------------
-- ส่วนที่ 2 — status ของเครื่องใช้คนละภาษา
--
-- 129 แถวใช้ตัวพิมพ์เล็ก (in_stock/for_sale/stripped/sold) ซึ่งมาจากระบบ donor เดิม
-- อีก 4 แถวหลุดเป็นตัวพิมพ์ใหญ่ เพราะเพิ่มผ่านฟอร์มใหม่ที่ใช้ vocabulary ของอะไหล่
-- ย้าย 4 แถวมาเข้าพวกกับ 129 แถว:  STOCK -> in_stock,  READY -> for_sale
--
--   10083 MB-202510-A0079  MacBook Pro A2338   STOCK -> in_stock
--   10097 MB-202511-A0086  MacBook Pro A2338   STOCK -> in_stock
--   30144 MC-V5584         MacbookPro A1990    READY -> for_sale
--   30150 (ไม่มี asset tag) MacBook Pro A1708   READY -> for_sale
-- ------------------------------------------------------------
UPDATE inventory SET status = 'in_stock'
WHERE type = 'machine' AND BINARY status = 'STOCK';

UPDATE inventory SET status = 'for_sale'
WHERE type = 'machine' AND BINARY status = 'READY';


-- ------------------------------------------------------------
-- ส่วนที่ 3 — แกะอะไหล่ไปแล้ว แต่หน้าจอยังบอกว่าเครื่องยังไม่ถูกแตะ
--
-- ทั้งแอป (badge ในคลัง, การ์ดตอนสแกน, bin_api) อ่าน disassembly_status
-- ไม่ได้อ่าน status  ดังนั้น 4 เครื่องนี้ถูกแกะไปแล้วจริงแต่ยังโชว์ว่า intact
--
-- ตั้งเป็น partially_stripped ไม่ใช่ stripped โดยตั้งใจ:
--   - แต่ละเครื่องมีอะไหล่ที่บันทึกไว้แค่ชิ้นเดียว ยังไม่พอจะบอกว่าแกะหมด
--   - ถ้าตั้ง stripped ปุ่ม "แยกอะไหล่" ในหน้าสแกนจะหายไป (part.php เช็คค่านี้)
--     ตั้ง partially_stripped แล้วยังแกะต่อได้
--
-- เงื่อนไขคือ "มีอะไหล่ที่ชี้กลับมาหาเครื่องนี้จริง" ไม่ได้ไล่ใส่ราย id
-- ไม่ได้ดู status ด้วย เพราะเครื่องที่ขายไปแล้วก็ถูกแกะได้เหมือนกัน (10072)
--   10016 MB-202510-A0013  MacBook A2681       status=stripped
--   10025 MB-202510-A0022  MacBook Air A1466   status=stripped
--   10075 MB-202510-A0072  MacBook Air A2337   status=stripped
--   10095 MB-202511-A0084  MacBook Pro A1989   status=stripped
--   10072 MB-202510-A0069  MacBook Air A2337   status=sold  <- ตัวนี้ status ไม่ใช่ stripped
-- ------------------------------------------------------------
UPDATE inventory m
SET m.disassembly_status = 'partially_stripped'
WHERE m.type = 'machine'
  AND m.disassembly_status = 'intact'
  AND EXISTS (SELECT 1 FROM (SELECT source_machine_id FROM inventory) p
              WHERE p.source_machine_id = m.id);


-- ------------------------------------------------------------
-- ส่วนที่ 4 — เครื่องที่ขายออกไปแล้วแต่ยังนับเป็นสต็อก
--
-- กฎที่เจ้าของร้านยืนยัน 2026-09-23:
--   "แถวขายที่ SOLD แล้ว = เครื่องนั้นไม่อยู่ในร้านแล้ว"
-- เพราะแถวขายส่วนใหญ่ถูกคีย์มือใหม่แทนที่จะกดแปลงจากแถวเครื่อง
-- แถวเครื่องเดิมเลยค้างเป็น in_stock ทั้งที่ของออกจากร้านไปแล้ว
--
-- เขียนเป็นกฎ ไม่ได้ไล่ใส่ราย id จะได้ครอบคลุมตัวที่เติม serial ย้อนหลังทีหลังด้วย
-- จับคู่ด้วย serial เท่านั้น (ต้องตรงเป๊ะ) ไม่เดาจากชื่อหรือรุ่น
--
-- ตอนนี้เข้าเงื่อนไข 1 เครื่อง:
--   10141  Mac mini 2014 A1347  serial C07NH16XG1HW  in_stock
--          -> คู่กับแถวขาย 30116 ที่ SOLD ไปแล้ว 1 ก.ค. 2026 ฿6,200
-- ------------------------------------------------------------
UPDATE inventory m
JOIN inventory s
     ON  s.type = 'sale'
     AND s.status = 'SOLD'
     AND s.serial_number = m.serial_number
SET m.status = 'sold'
WHERE m.type = 'machine'
  AND m.status <> 'sold'
  AND m.serial_number IS NOT NULL
  AND TRIM(m.serial_number) NOT IN ('', '-');


-- ------------------------------------------------------------
-- ส่วนที่ 5 — เติมรุ่นให้อะไหล่มือสอง 4 ตัวที่ migration_parts_rename ทิ้ง TODO ไว้
--
-- ตอนตั้งชื่อรอบก่อนไม่รู้ว่าอะไหล่พวกนี้มาจากเครื่องรุ่นไหน เลยตั้ง SKU ห้วน ๆ ว่า
-- MB-TRKP-U / MB-LOGIC-U / MB-SCRN-U / MB-BATT-U
-- แต่ source_machine_id ชี้กลับไปหาเครื่องต้นทางอยู่แล้ว เอารุ่นมาเติมได้เลย
--
--   30022  MB-TRKP-U   <- เครื่อง 10016 MacBook A2681
--   30025  MB-LOGIC-U  <- เครื่อง 10095 MacBook Pro A1989
--   30026  MB-SCRN-U   <- เครื่อง 10072 MacBook Air A2337
--   30027  MB-BATT-U   <- เครื่อง 10075 MacBook Air A2337
--
-- หมายเหตุ: A#### ในนี้คือรหัส "เครื่อง" (รุ่นที่อะไหล่ตัวนี้ถอดออกมา)
-- จอ/ทัชแพด/บอร์ด ใช้รหัสเครื่องอยู่แล้วเหมือน MB-SCRN-A2338 เลยเข้าพวกกันพอดี
-- ยกเว้น MB-BATT-A2337-U ตัวเดียว: SKU แบตของใหม่ (MB-BATT-A2171) ใช้รหัส "แบต"
-- ไม่ใช่รหัสเครื่อง  ถ้ามึงรู้รหัสแบตจริงของ A2337 ค่อยมาแก้ตัวนี้ทีหลัง
-- ------------------------------------------------------------
UPDATE inventory SET sku = 'MB-TRKP-A2681-U',
    name = 'Trackpad MacBook A2681 (Used)', name_th = 'ทัชแพด MacBook A2681 (มือสอง)',
    compatible_models = 'A2681'
WHERE id = 30022 AND sku = 'MB-TRKP-U';

UPDATE inventory SET sku = 'MB-LOGIC-A1989-U',
    name = 'Logic Board MacBook Pro A1989 (Used)', name_th = 'บอร์ด MacBook Pro A1989 (มือสอง)',
    compatible_models = 'A1989'
WHERE id = 30025 AND sku = 'MB-LOGIC-U';

UPDATE inventory SET sku = 'MB-SCRN-A2337-U',
    name = 'Screen MacBook Air A2337 (Used)', name_th = 'จอ MacBook Air A2337 (มือสอง)',
    compatible_models = 'A2337'
WHERE id = 30026 AND sku = 'MB-SCRN-U';

UPDATE inventory SET sku = 'MB-BATT-A2337-U',
    name = 'Battery MacBook Air A2337 (Used)', name_th = 'แบตเตอรี่ MacBook Air A2337 (มือสอง)',
    compatible_models = 'A2337'
WHERE id = 30027 AND sku = 'MB-BATT-U';


-- ------------------------------------------------------------
COMMIT;
-- ถ้าตัวเลขไหนไม่เป็น 0 ให้พิมพ์  ROLLBACK;  แทน  COMMIT;


-- ============================================================
-- ยังไม่ได้แก้ให้ ต้องเดินไปดูของจริง — ไม่มีข้อมูลพอให้ตัดสินแทน
--
-- ก. 9 เครื่องที่ status='stripped' แต่ไม่มีอะไหล่บันทึกไว้เลยสักชิ้น
--    (แกะแล้วไม่ได้คีย์ หรือ status ผิด — ไม่รู้)
--    10022 10046 10067 10069 10076 10082 10093 10096 10137
--    ดูรายการ:
--      SELECT id, asset_tag, name, status, disassembly_status FROM inventory
--      WHERE id IN (10022,10046,10067,10069,10076,10082,10093,10096,10137);
--
-- ข. เครื่อง 10082 (A1708, serial C02TV2KFHV27) ซ้ำกับแถวขาย 30145 (READY ฿12,000)
--    เครื่องยังอยู่ในร้านมั้ย ถ้าอยู่ ต้องลบแถวขายทิ้งหรือแถวเครื่องทิ้ง
--
-- ค. เครื่อง 10090 (iPad A2757, for_sale) อาจซ้ำกับแถวขาย 157 (iPad Gen10 A2757 ฿9,500)
--    แถว 157 ไม่มี serial เลยเทียบไม่ได้ ต้องหยิบ iPad มาดู serial
--
-- ง. แถวขาย 23 จาก 30 แถวไม่มี serial เลย -> ตรวจซ้ำกับเครื่องไม่ได้ตลอดไป
--    ควรไล่เติม serial ย้อนหลัง อย่างน้อยเฉพาะตัวที่ยังไม่ขาย
-- ============================================================


-- ====================================================================
-- ชุดที่ 4 จาก 4 — migration_category_codes.sql
-- รหัสหมวด สำหรับให้ระบบออก SKU เองต่อไป (+ parts_categories.code)
-- ====================================================================
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


COMMIT;
-- ไม่เป็น 0 -> ROLLBACK; แล้วดูว่าหมวดไหนตกหล่น:
--   SELECT id, parent_id, name FROM parts_categories WHERE code IS NULL OR code = '';
-- หมวดที่เพิ่มเองทีหลังต้องใส่รหัสในหน้า "จัดการหมวดหมู่" ไม่งั้นระบบจะออก SKU ให้ไม่ได้


-- ############################################################
--  ตรวจผล — ต้องได้ 0 ทั้ง 7 ตัว
--  ตัวไหนไม่เป็น 0 อย่าเพิ่ง push โค้ด มาบอกก่อน
-- ############################################################

SELECT 'อะไหล่ที่ยังใช้ SKU แบบเก่า' AS ตรวจ, COUNT(*) AS ต้องเป็นศูนย์
FROM inventory WHERE type IN ('new','used')
  AND (sku LIKE 'NW-%' OR sku LIKE 'US-%' OR sku LIKE 'P-%' OR sku LIKE 'U-%' OR sku LIKE 'OTH-%')
UNION ALL
SELECT 'อะไหล่ที่ยังค้างอยู่หมวดหลัก', COUNT(*)
FROM inventory i JOIN parts_categories c ON c.id = i.category_id
WHERE i.type IN ('new','used') AND c.parent_id IS NULL
UNION ALL
SELECT 'อะไหล่ที่ยังไม่มีคำอธิบายไทย', COUNT(*)
FROM inventory WHERE type IN ('new','used') AND (name_th IS NULL OR name_th = '')
UNION ALL
SELECT 'ของหมดแล้วแต่ยังบอกว่ามีของ', COUNT(*)
FROM inventory i WHERE i.type IN ('new','used') AND i.status = 'STOCK'
  AND COALESCE((SELECT SUM(l.qty_remaining) FROM inventory_lots l WHERE l.inventory_id = i.id), 0) = 0
UNION ALL
SELECT 'สถานะเครื่องที่ยังเป็นตัวพิมพ์ใหญ่', COUNT(*)
FROM inventory WHERE type = 'machine'
  AND BINARY status IN ('STOCK','READY','OOS','GOOD','TEST','DEAD','SOLD')
UNION ALL
SELECT 'เครื่องที่แกะแล้วแต่ยังโชว์ว่ายังไม่แกะ', COUNT(*)
FROM inventory m WHERE m.type = 'machine' AND m.disassembly_status = 'intact'
  AND EXISTS (SELECT 1 FROM (SELECT source_machine_id FROM inventory) p
              WHERE p.source_machine_id = m.id)
UNION ALL
SELECT 'หมวดหมู่ที่ยังไม่มีรหัส', COUNT(*)
FROM parts_categories WHERE code IS NULL OR code = '';


-- ############################################################
--  ของที่ต้องไปดูของจริงเอง ไม่ได้แก้ให้ (ดูรายละเอียดท้าย
--  migration_inventory_cleanup.sql)
--   - 9 เครื่องที่ status='stripped' แต่ไม่มีอะไหล่บันทึกไว้เลย
--   - เครื่อง 10082 ซ้ำกับแถวขาย 30145
--   - เครื่อง 10090 (iPad A2757) อาจซ้ำกับแถวขาย 157
--   - แถวขาย 23 แถวไม่มี serial เลย
--
--  และถ้าเพิ่มอะไหล่บน prod หลังเวลา 12:45 ของวันที่ 23 ก.ย.
--  ตัวนั้นจะไม่โดนเปลี่ยนชื่อ (migration อ้าง id ตายตัว) เช็คด้วย:
--    SELECT id, sku, name FROM inventory
--    WHERE type IN ('new','used') AND sku LIKE 'NW-%';
-- ############################################################
