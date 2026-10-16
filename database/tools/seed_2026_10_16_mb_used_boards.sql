-- ------------------------------------------------------------------
-- 2026-10-16  MacBook used logic boards (donor / for-parts) — 9 boards
-- type=used, status=DEAD, 1 lot each (qty 1, cost 0)
-- Category: MacBook (MB) > Logic Board (LOGIC)
-- Safe to re-run: skips any SKU that already exists, lots only for rows without one
-- ------------------------------------------------------------------

START TRANSACTION;

SET @cat := (
    SELECT c.id
    FROM parts_categories c
    JOIN parts_categories p ON p.id = c.parent_id
    WHERE c.code = 'LOGIC' AND p.code = 'MB'
    LIMIT 1
);

-- Must not be NULL — if it is, the INSERT below inserts nothing (guarded by WHERE @cat IS NOT NULL)
SELECT @cat AS logic_board_category_id;

DROP TEMPORARY TABLE IF EXISTS tmp_boards;
CREATE TEMPORARY TABLE tmp_boards (
    sku               VARCHAR(60),
    name              VARCHAR(200),
    name_th           VARCHAR(200),
    part_number       VARCHAR(100),
    serial_number     VARCHAR(100),
    compatible_models TEXT,
    condition_note    TEXT
) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO tmp_boards VALUES
('MB-LOGIC-820-01700-A-U',   'Logic Board MacBook Pro 16" 2019 (A2141) 820-01700-A',   'บอร์ด MacBook Pro 16" 2019 (มือสอง)',   '820-01700-A', NULL, 'MacBook Pro 16" 2019 A2141',
 'บอร์ดอะไหล่ / มีสติกเกอร์ CMNS ติดอยู่'),
('MB-LOGIC-820-01700-A-U-2', 'Logic Board MacBook Pro 16" 2019 (A2141) 820-01700-A',   'บอร์ด MacBook Pro 16" 2019 (มือสอง)',   '820-01700-A', NULL, 'MacBook Pro 16" 2019 A2141',
 'บอร์ดอะไหล่ / SSD เสีย / 2/6/68 / เลขที่ซ่อม V4092 / รหัส 6280'),
('MB-LOGIC-820-01700-A-U-3', 'Logic Board MacBook Pro 16" 2019 (A2141) 820-01700-A',   'บอร์ด MacBook Pro 16" 2019 (มือสอง)',   '820-01700-A', NULL, 'MacBook Pro 16" 2019 A2141',
 'บอร์ดอะไหล่'),
('MB-LOGIC-820-01700-A-U-4', 'Logic Board MacBook Pro 16" 2019 (A2141) 820-01700-A',   'บอร์ด MacBook Pro 16" 2019 (มือสอง)',   '820-01700-A', NULL, 'MacBook Pro 16" 2019 A2141',
 'บอร์ดอะไหล่'),
('MB-LOGIC-820-00850-A-U',   'Logic Board MacBook Pro 13" 2018/2019 (A1989) 820-00850-A', 'บอร์ด MacBook Pro 13" 2018/2019 (มือสอง)', '820-00850-A', NULL, 'MacBook Pro 13" 2018/2019 A1989',
 'บอร์ดอะไหล่'),
('MB-LOGIC-820-00850-A-U-2', 'Logic Board MacBook Pro 13" 2018/2019 (A1989) 820-00850-A', 'บอร์ด MacBook Pro 13" 2018/2019 (มือสอง)', '820-00850-A', NULL, 'MacBook Pro 13" 2018/2019 A1989',
 'บอร์ดอะไหล่ / เลขที่ซ่อม V5051'),
('MB-LOGIC-820-02536-A-U',   'Logic Board MacBook Air 13" M2 2022 (A2681) 820-02536-A', 'บอร์ด MacBook Air 13" M2 2022 (มือสอง)', '820-02536-A', 'FWX2423HAL71TDCE2L', 'MacBook Air 13" M2 2022 A2681',
 'บอร์ดอะไหล่ / เลขบนบอร์ด 806-36639-A / ป้าย C02242502, 840W9CA6 01 F0'),
('MB-LOGIC-820-02016-A-U',   'Logic Board MacBook Air 13" M1 2020 (A2337) 820-02016-A', 'บอร์ด MacBook Air 13" M1 2020 (มือสอง)', '820-02016-A', NULL, 'MacBook Air 13" M1 2020 A2337',
 'บอร์ดอะไหล่'),
('MB-LOGIC-820-02899-A-U',   'Logic Board MacBook Air 13" M1 2020 (A2337) 820-02899-A', 'บอร์ด MacBook Air 13" M1 2020 (มือสอง)', '820-02899-A', NULL, 'MacBook Air 13" M1 2020 A2337',
 'บอร์ดอะไหล่ / เลขที่ซ่อม V4488 / รหัส Duang8697 Khamon2499');

-- Pre-check: any of these SKUs already in inventory? (should return 0 rows)
SELECT i.id, i.sku FROM inventory i JOIN tmp_boards t ON t.sku = i.sku;

INSERT INTO inventory
    (category_id, sku, name, name_th, type, serial_number, part_number,
     compatible_models, min_qty, disassembly_status, condition_note, status, sell_price)
SELECT @cat, t.sku, t.name, t.name_th, 'used', t.serial_number, t.part_number,
       t.compatible_models, 0, 'intact', t.condition_note, 'DEAD', 0
FROM tmp_boards t
WHERE @cat IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM inventory i WHERE i.sku = t.sku);

-- 1 lot per board (on_hand is SUM(qty_remaining) — no lot = shows 0)
INSERT INTO inventory_lots
    (inventory_id, lot_number, qty_received, qty_remaining, cost_price, warranty_start)
SELECT i.id,
       CONCAT('LOT-', UPPER(SUBSTRING(MD5(CONCAT(i.id, '-', i.sku, '-', NOW(6))), 1, 6))),
       1, 1, 0, CURDATE()
FROM inventory i
JOIN tmp_boards t ON t.sku = i.sku
WHERE NOT EXISTS (SELECT 1 FROM inventory_lots l WHERE l.inventory_id = i.id);

-- Verify: expect 9 rows, on_hand = 1 each
SELECT i.id, i.sku, i.part_number, i.serial_number, i.status,
       COALESCE(SUM(l.qty_remaining), 0) AS on_hand
FROM inventory i
JOIN tmp_boards t ON t.sku = i.sku
LEFT JOIN inventory_lots l ON l.inventory_id = i.id
GROUP BY i.id
ORDER BY i.id;

DROP TEMPORARY TABLE IF EXISTS tmp_boards;

COMMIT;
