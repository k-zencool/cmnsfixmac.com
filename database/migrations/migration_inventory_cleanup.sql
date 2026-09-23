-- ============================================================
-- migration_inventory_cleanup.sql
--
-- เก็บกวาดข้อมูลที่ขัดกันเองในตาราง inventory (ไม่แตะ schema เลย มีแต่ UPDATE)
--   ส่วนที่ 1  12 โปรไฟล์อะไหล่ที่ของหมดแล้ว แต่ยังบอก STOCK  -> OOS
--   ส่วนที่ 2  4 เครื่องที่ status เป็นตัวพิมพ์ใหญ่ (STOCK/READY) -> in_stock/for_sale
--   ส่วนที่ 3  4 เครื่องที่แกะอะไหล่ไปแล้วจริง แต่ disassembly_status ยังเป็น intact
--   ส่วนที่ 4  (ปิดไว้) เครื่องที่ขายไปแล้วแต่ยังนับเป็นสต็อก — ต้องยืนยันก่อนเปิด
--
-- รันซ้ำได้ ทุก UPDATE มีเงื่อนไขกำกับ รันรอบสองจะไม่มีอะไรเปลี่ยน
-- ไม่มีการลบแถวใด ๆ ทั้งสิ้น
-- ============================================================

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
-- ตรวจก่อน COMMIT — ทั้ง 4 ตัวต้องได้ 0
-- ------------------------------------------------------------
SELECT COUNT(*) AS c1_ของหมดแต่ยังบอกมีของ FROM inventory i
WHERE i.type IN ('new','used') AND i.status = 'STOCK'
  AND COALESCE((SELECT SUM(l.qty_remaining) FROM inventory_lots l WHERE l.inventory_id = i.id), 0) = 0;

SELECT COUNT(*) AS c2_status_ตัวพิมพ์ใหญ่ FROM inventory
WHERE type = 'machine' AND BINARY status IN ('STOCK','READY','OOS','GOOD','TEST','DEAD','SOLD');

SELECT COUNT(*) AS c3_แกะแล้วแต่ยังโชว์intact FROM inventory m
WHERE m.type = 'machine' AND m.disassembly_status = 'intact'
  AND EXISTS (SELECT 1 FROM (SELECT source_machine_id FROM inventory) p
              WHERE p.source_machine_id = m.id);

SELECT COUNT(*) AS c4_อะไหล่มือสองที่ยังไม่รู้รุ่น FROM inventory
WHERE sku IN ('MB-TRKP-U','MB-LOGIC-U','MB-SCRN-U','MB-BATT-U');

SELECT COUNT(*) AS c5_ขายไปแล้วแต่ยังนับสต็อก FROM inventory m
JOIN inventory s ON s.type='sale' AND s.status='SOLD' AND s.serial_number = m.serial_number
WHERE m.type='machine' AND m.status <> 'sold'
  AND m.serial_number IS NOT NULL AND TRIM(m.serial_number) NOT IN ('','-');

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
