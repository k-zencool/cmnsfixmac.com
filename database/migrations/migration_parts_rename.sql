-- ============================================================
-- migration_parts_rename.sql
--
-- Renames every new/used part to the DEV-PART-MODEL scheme, files it
-- under a real subcategory, and fills compatible_models.
-- Machines (type=machine) and sale units are NOT touched.
--
-- Rows updated: 115
-- Safe to re-run: subcategory inserts are guarded, updates are by id.
-- ============================================================

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

-- ---------- 3. check before COMMIT ----------
-- ควรได้ 0 ทั้งสองอัน:
SELECT COUNT(*) AS still_old_sku FROM inventory WHERE type IN ('new','used')
  AND (sku LIKE 'NW-%' OR sku LIKE 'US-%' OR sku LIKE 'P-%' OR sku LIKE 'U-%' OR sku LIKE 'OTH-%');
SELECT COUNT(*) AS still_root_category FROM inventory i
  JOIN parts_categories c ON c.id = i.category_id
  WHERE i.type IN ('new','used') AND c.parent_id IS NULL;

COMMIT;
-- ถ้าตัวเลขไม่เป็น 0 → ROLLBACK; แทน COMMIT;
