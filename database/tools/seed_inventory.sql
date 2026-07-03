SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ============================================================
-- NEW PARTS (อะไหล่ใหม่) x20
-- ============================================================
INSERT INTO inventory (category_id, sku, name, type, part_number, compatible_models, location, min_qty, status, sell_price) VALUES
(20, 'NW-DSP001', 'MacBook Pro 13" จอ Retina Assembly (A2338 M1)', 'new', '661-17548', 'A2338', 'ตู้A ชั้น1', 2, 'STOCK', 6500),
(20, 'NW-DSP002', 'MacBook Air 13" จอ Retina Assembly (A2337 M1)', 'new', '661-16809', 'A2337', 'ตู้A ชั้น1', 2, 'STOCK', 5800),
(20, 'NW-DSP003', 'MacBook Pro 14" จอ Liquid Retina XDR (A2442)', 'new', '661-19568', 'A2442', 'ตู้A ชั้น1', 1, 'STOCK', 14500),
(20, 'NW-DSP004', 'MacBook Pro 16" จอ Liquid Retina XDR (A2485)', 'new', '661-19596', 'A2485', 'ตู้A ชั้น1', 1, 'STOCK', 18500),
(21, 'NW-BAT001', 'แบตเตอรี่ MacBook Air M1 (A2337) 49.9Wh', 'new', '020-02423', 'A2337', 'ตู้B ชั้น1', 3, 'STOCK', 2200),
(21, 'NW-BAT002', 'แบตเตอรี่ MacBook Pro 13" M1 (A2338) 58.2Wh', 'new', '020-02435', 'A2338', 'ตู้B ชั้น1', 3, 'STOCK', 2600),
(21, 'NW-BAT003', 'แบตเตอรี่ MacBook Pro 14" M1 (A2442) 69.6Wh', 'new', '020-02461', 'A2442', 'ตู้B ชั้น1', 2, 'STOCK', 3800),
(21, 'NW-BAT004', 'แบตเตอรี่ MacBook Air M2 (A2681) 52.6Wh', 'new', '020-02729', 'A2681', 'ตู้B ชั้น1', 2, 'STOCK', 2500),
(22, 'NW-KBD001', 'คีย์บอร์ด MacBook Pro 13" (A2338) TH สีเทาดำ', 'new', '661-17543', 'A2338', 'ตู้C ชั้น1', 2, 'STOCK', 4200),
(22, 'NW-KBD002', 'คีย์บอร์ด MacBook Air (A2337) TH Space Gray', 'new', '661-16807', 'A2337', 'ตู้C ชั้น1', 2, 'STOCK', 3900),
(22, 'NW-KBD003', 'คีย์บอร์ด MacBook Pro 14" (A2442) TH Space Gray', 'new', '661-19562', 'A2442', 'ตู้C ชั้น1', 1, 'STOCK', 7500),
(23, 'NW-TKP001', 'แทร็คแพด MacBook Air (A2337)', 'new', '923-05047', 'A2337', 'ตู้C ชั้น2', 2, 'STOCK', 1800),
(23, 'NW-TKP002', 'แทร็คแพด MacBook Pro 13" (A2338)', 'new', '923-05148', 'A2338', 'ตู้C ชั้น2', 2, 'STOCK', 2200),
(24, 'NW-FAN001', 'พัดลม MacBook Air M1 (A2337)', 'new', '923-04928', 'A2337', 'ตู้D ชั้น1', 2, 'STOCK', 1200),
(24, 'NW-FAN002', 'พัดลม MacBook Pro 14" ซ้าย (A2442)', 'new', '923-05328', 'A2442', 'ตู้D ชั้น1', 2, 'STOCK', 1900),
(24, 'NW-FAN003', 'พัดลม MacBook Pro 14" ขวา (A2442)', 'new', '923-05329', 'A2442', 'ตู้D ชั้น1', 2, 'STOCK', 1900),
(24, 'NW-CBL001', 'สายแพร Display MacBook Pro 13" (A2338)', 'new', '923-04990', 'A2338', 'ตู้D ชั้น2', 3, 'STOCK', 850),
(25, 'NW-BDY001', 'บอดี้ล่าง MacBook Air M1 (A2337) Space Gray', 'new', '923-04836', 'A2337', 'ตู้E ชั้น1', 2, 'STOCK', 1500),
(3,  'NW-LBD001', 'Logic Board MacBook Air M1 8GB/256GB (A2337)', 'new', '661-16804', 'A2337', 'ตู้A ชั้น3', 1, 'STOCK', 12500),
(3,  'NW-LBD002', 'Logic Board MacBook Pro 13" M1 8GB/512GB (A2338)', 'new', '661-17545', 'A2338', 'ตู้A ชั้น3', 1, 'STOCK', 16500);

-- ============================================================
-- USED PARTS (อะไหล่มือสอง) x20
-- ============================================================
INSERT INTO inventory (category_id, sku, name, type, compatible_models, location, min_qty, status, sell_price) VALUES
(20, 'US-DSP001', 'จอ MacBook Pro 13" Retina (A2159) GOOD', 'used', 'A2159', 'ตู้A ชั้น2', 1, 'GOOD', 2800),
(20, 'US-DSP002', 'จอ MacBook Air 13" (A1932) มีรอยขอบนิด', 'used', 'A1932', 'ตู้A ชั้น2', 1, 'TEST', 1800),
(20, 'US-DSP003', 'จอ MacBook Pro 15" Retina (A1990) GOOD', 'used', 'A1990', 'ตู้A ชั้น2', 1, 'GOOD', 4200),
(20, 'US-DSP004', 'จอ MacBook Air 13" (A2179) สภาพดีมาก', 'used', 'A2179', 'ตู้A ชั้น2', 1, 'GOOD', 3200),
(21, 'US-BAT001', 'แบตเตอรี่ MacBook Pro 13" (A2251) ความจุ85%', 'used', 'A2251', 'ตู้B ชั้น2', 1, 'GOOD', 1200),
(21, 'US-BAT002', 'แบตเตอรี่ MacBook Air (A1932) 90 Cycle', 'used', 'A1932', 'ตู้B ชั้น2', 1, 'GOOD', 900),
(21, 'US-BAT003', 'แบตเตอรี่ MacBook Pro 16" (A2141) ความจุ80%', 'used', 'A2141', 'ตู้B ชั้น2', 1, 'TEST', 1800),
(21, 'US-BAT004', 'แบตเตอรี่ MacBook Pro 15" (A1990) ผ่านกาว', 'used', 'A1990', 'ตู้B ชั้น2', 1, 'TEST', 800),
(22, 'US-KBD001', 'คีย์บอร์ด MacBook Pro 13" (A1989) Butterfly', 'used', 'A1989', 'ตู้C ชั้น2', 1, 'GOOD', 1900),
(22, 'US-KBD002', 'คีย์บอร์ด MacBook Air (A1932) TH ครบ', 'used', 'A1932', 'ตู้C ชั้น2', 1, 'GOOD', 1500),
(22, 'US-KBD003', 'คีย์บอร์ด MacBook Pro 16" (A2141) TH มีรอย', 'used', 'A2141', 'ตู้C ชั้น2', 1, 'TEST', 2200),
(23, 'US-TKP001', 'แทร็คแพด MacBook Pro 13" (A1989) สภาพดี', 'used', 'A1989', 'ตู้C ชั้น3', 1, 'GOOD', 1200),
(23, 'US-TKP002', 'แทร็คแพด MacBook Air (A1932) สมบูรณ์', 'used', 'A1932', 'ตู้C ชั้น3', 1, 'GOOD', 900),
(24, 'US-FAN001', 'พัดลม MacBook Pro 15" ซ้าย (A1990)', 'used', 'A1990', 'ตู้D ชั้น2', 1, 'GOOD', 700),
(24, 'US-FAN002', 'พัดลม MacBook Pro 15" ขวา (A1990)', 'used', 'A1990', 'ตู้D ชั้น2', 1, 'GOOD', 700),
(24, 'US-CBL001', 'สายแพร Display MacBook Air (A1932)', 'used', 'A1932', 'ตู้D ชั้น3', 1, 'GOOD', 450),
(25, 'US-BDY001', 'บอดี้ล่าง MacBook Pro 13" (A1989) มีรอยขีด', 'used', 'A1989', 'ตู้E ชั้น2', 1, 'GOOD', 800),
(25, 'US-BDY002', 'บอดี้บน Top Case MacBook Pro 15" (A1990)', 'used', 'A1990', 'ตู้E ชั้น2', 1, 'TEST', 1200),
(3,  'US-LBD001', 'Logic Board MacBook Pro 13" A1989 i5/8GB/256', 'used', 'A1989', 'ตู้A ชั้น4', 1, 'GOOD', 5800),
(3,  'US-LBD002', 'Logic Board MacBook Pro 15" A1990 i7/16GB/512', 'used', 'A1990', 'ตู้A ชั้น4', 1, 'GOOD', 8500);

-- ============================================================
-- MACHINE / SALVAGE (เครื่องซาก) x20
-- ============================================================
INSERT INTO inventory (category_id, sku, name, type, asset_tag, serial_number, device_model, status, disassembly_status, condition_note, sell_price) VALUES
(3, 'MC-001', 'MacBook Pro 13" 2019 A1989 ซากน้ำ', 'machine', 'MC-001', 'C02XN2ADJGH5', 'A1989', 'READY', 'intact', 'น้ำเข้า บอร์ดเสีย อะไหล่ครบ', 1500),
(3, 'MC-002', 'MacBook Pro 15" 2019 A1990 ซากจอแตก', 'machine', 'MC-002', 'C02Z34LLJGH5', 'A1990', 'READY', 'intact', 'จอแตก บอร์ดใช้ได้ แบตดี', 2500),
(3, 'MC-003', 'MacBook Air 13" 2019 A1932 ตกหล่น', 'machine', 'MC-003', 'C02X12ABJGH5', 'A1932', 'READY', 'intact', 'บอดี้งอ บอร์ดเสีย อะไหล่ครบ', 800),
(3, 'MC-004', 'MacBook Pro 13" 2020 A2251 เปิดไม่ติด', 'machine', 'MC-004', 'C02D81Z4JGH5', 'A2251', 'READY', 'partially_stripped', 'เปิดไม่ติด ถอดแบตและจอแล้ว', 1200),
(3, 'MC-005', 'MacBook Air 13" 2020 A2179 บอร์ดไหม้', 'machine', 'MC-005', 'C02F12ADJGH5', 'A2179', 'READY', 'intact', 'บอร์ดช็อต จอดี แบตดี', 900),
(3, 'MC-006', 'MacBook Pro 16" 2019 A2141 ซากน้ำ', 'machine', 'MC-006', 'C02DN3ZQJGH5', 'A2141', 'READY', 'intact', 'น้ำเข้า USB เสียบางจุด', 3500),
(3, 'MC-007', 'MacBook Pro 13" 2018 A1989 เปิดไม่ติด', 'machine', 'MC-007', 'C02W45PLJGH5', 'A1989', 'READY', 'partially_stripped', 'VCC ไม่มี ถอดคีย์บอร์ดแล้ว', 700),
(3, 'MC-008', 'MacBook Pro 15" 2018 A1990 GPU เสีย', 'machine', 'MC-008', 'C02V89LNJGH5', 'A1990', 'READY', 'intact', 'GPU hang บางครั้ง จอดี', 2200),
(3, 'MC-009', 'MacBook Air 13" 2018 A1932 BackLight เสีย', 'machine', 'MC-009', 'C02X01ZMJGH5', 'A1932', 'READY', 'intact', 'BackLight เสีย มองจาง อะไหล่ดี', 600),
(3, 'MC-010', 'MacBook Pro 13" 2017 A1708 Keyboard เสีย', 'machine', 'MC-010', 'C02T55KLJGH5', 'A1708', 'READY', 'partially_stripped', 'Keyboard ถอดแล้ว บอร์ดดี', 800),
(3, 'MC-011', 'MacBook Air 13" M1 2020 A2337 แบตบวม', 'machine', 'MC-011', 'FVFDY2LHJGH5', 'A2337', 'READY', 'intact', 'แบตบวมนิด เครื่องปกติ', 4500),
(3, 'MC-012', 'MacBook Pro 13" M1 2020 A2338 จอมีเส้น', 'machine', 'MC-012', 'FVFGX3WHJGH5', 'A2338', 'READY', 'intact', 'LCD เส้นซ้าย อะไหล่ดี', 5500),
(3, 'MC-013', 'MacBook Pro 14" M1 2021 A2442 น้ำเข้า', 'machine', 'MC-013', 'FVFM45XQJGH5', 'A2442', 'READY', 'intact', 'น้ำเข้าพอร์ต บอร์ดเสียบางส่วน', 8000),
(3, 'MC-014', 'MacBook Pro 15" 2016 A1707 SMC เสีย', 'machine', 'MC-014', 'C02S12RNJGH5', 'A1707', 'READY', 'partially_stripped', 'SMC เสีย ถอดแบตแล้ว', 500),
(3, 'MC-015', 'MacBook Air 11" 2015 A1465 ซากครบ', 'machine', 'MC-015', 'C1MN88LZJGH5', 'A1465', 'READY', 'intact', 'เครื่องครบ บอร์ดตาย', 300),
(3, 'MC-016', 'MacBook Pro 13" 2016 A1706 Keyboard Butterfly', 'machine', 'MC-016', 'C02S90ABJGH5', 'A1706', 'READY', 'partially_stripped', 'Keyboard ถอดแล้ว บอร์ดดี จอดี', 600),
(3, 'MC-017', 'MacBook Pro 15" 2017 A1707 จอแตก', 'machine', 'MC-017', 'C02T32HMJGH5', 'A1707', 'READY', 'intact', 'จอแตก บอร์ดดี Radeon Pro', 1800),
(3, 'MC-018', 'MacBook Air 13" 2017 A1466 แบตตาย', 'machine', 'MC-018', 'C1MQ74NNJGH5', 'A1466', 'READY', 'intact', 'แบตตาย เครื่องสมบูรณ์', 2500),
(3, 'MC-019', 'MacBook Pro 13" 2020 A2289 จอเส้นน้ำ', 'machine', 'MC-019', 'C02F67LPJGH5', 'A2289', 'READY', 'partially_stripped', 'จอเส้นน้ำ บอร์ดดี ถอดแบตแล้ว', 1800),
(3, 'MC-020', 'MacBook Pro 16" M1 Pro 2021 A2485 บุบมุม', 'machine', 'MC-020', 'FVFP90WMJGH5', 'A2485', 'READY', 'intact', 'บอดี้บุบมุม จอดี บอร์ดดี ครบ', 12000);

-- ============================================================
-- SALE (เครื่องพร้อมขาย) x20
-- ============================================================
INSERT INTO inventory (category_id, sku, name, type, asset_tag, serial_number, device_model, status, condition_note, sell_price) VALUES
(3, 'SL-001', 'MacBook Air M1 13" 8GB/256GB Silver', 'sale', 'SL-001', 'FVFCX2ABJGH5', 'A2337', 'READY', 'Grade A มีกล่อง ชาร์จเจอร์ครบ', 22500),
(3, 'SL-002', 'MacBook Air M1 13" 8GB/512GB Space Gray', 'sale', 'SL-002', 'FVFDY3LHJGH5', 'A2337', 'READY', 'Grade A มีรอยเล็กน้อยใต้เครื่อง', 25000),
(3, 'SL-003', 'MacBook Pro M1 13" 8GB/256GB Space Gray', 'sale', 'SL-003', 'FVFGX4WHJGH5', 'A2338', 'READY', 'Grade A ยังมีฟิล์มติดอยู่', 28500),
(3, 'SL-004', 'MacBook Pro M1 13" 16GB/512GB Silver', 'sale', 'SL-004', 'FVFHX5EMJGH5', 'A2338', 'READY', 'Grade A- มีรอยขีดเล็กน้อย', 34000),
(3, 'SL-005', 'MacBook Air M2 13" 8GB/256GB Midnight', 'sale', 'SL-005', 'FVFKM6XQJGH5', 'A2681', 'READY', 'Grade A+ สภาพใหม่มาก Cycle 12', 32000),
(3, 'SL-006', 'MacBook Air M2 13" 8GB/512GB Starlight', 'sale', 'SL-006', 'FVFLN7YRJGH5', 'A2681', 'READY', 'Grade A มีกล่อง ไม่มีชาร์จเจอร์', 35000),
(3, 'SL-007', 'MacBook Pro 14" M1 Pro 16GB/512GB Space Gray', 'sale', 'SL-007', 'FVFM8ZSJGH5', 'A2442', 'READY', 'Grade A สภาพสวย Cycle 45', 52000),
(3, 'SL-008', 'MacBook Pro 14" M1 Pro 16GB/1TB Silver', 'sale', 'SL-008', 'FVFN9ATJGH5', 'A2442', 'READY', 'Grade A+ ครบกล่อง', 58000),
(3, 'SL-009', 'MacBook Pro 16" M1 Pro 16GB/512GB Space Gray', 'sale', 'SL-009', 'FVFP0BUJGH5', 'A2485', 'READY', 'Grade A มีรอยขีดบนฝา', 62000),
(3, 'SL-010', 'MacBook Pro 13" Intel i5 8GB/256GB A2289', 'sale', 'SL-010', 'C02F55LPJGH5', 'A2289', 'READY', 'Grade B มีรอยใช้งาน Cycle 180', 15000),
(3, 'SL-011', 'MacBook Air 13" Intel i3 8GB/256GB A2179 Silver', 'sale', 'SL-011', 'C02G77ADJGH5', 'A2179', 'READY', 'Grade A- สภาพดี Cycle 95', 14500),
(3, 'SL-012', 'MacBook Pro 13" Intel i5 16GB/512GB A2251', 'sale', 'SL-012', 'C02D99Z4JGH5', 'A2251', 'READY', 'Grade A Cycle 88 มีกล่อง', 19000),
(3, 'SL-013', 'MacBook Pro 15" Intel i7 16GB/256GB A1990 2019', 'sale', 'SL-013', 'C02Z12LLJGH5', 'A1990', 'READY', 'Grade B+ Radeon 555X Cycle 210', 18000),
(3, 'SL-014', 'MacBook Air 13" Intel i5 8GB/512GB A1932 2018', 'sale', 'SL-014', 'C02X55ZMJGH5', 'A1932', 'READY', 'Grade B ใช้งานปกติ Cycle 155', 10500),
(3, 'SL-015', 'MacBook Pro 13" Intel i5 8GB/128GB A1708 2017', 'sale', 'SL-015', 'C02T88KLJGH5', 'A1708', 'DISCOUNT', 'Grade C รอยขีดมาก ใช้งานได้', 7500),
(3, 'SL-016', 'MacBook Air M2 15" 8GB/256GB Space Gray', 'sale', 'SL-016', 'FVFQ1CVJGH5', 'A2941', 'READY', 'Grade A+ เปิดดูแล้ว Cycle 5', 38000),
(3, 'SL-017', 'MacBook Pro M2 13" 8GB/256GB Silver', 'sale', 'SL-017', 'FVFR2DWJGH5', 'A2686', 'READY', 'Grade A สภาพดี Cycle 32', 34500),
(3, 'SL-018', 'MacBook Pro 16" Intel i9 32GB/1TB A2141 2019', 'sale', 'SL-018', 'C02DX7ZQJGH5', 'A2141', 'READY', 'Grade A Radeon 5500M Cycle 120', 38000),
(3, 'SL-019', 'MacBook Air M1 13" 16GB/256GB Gold', 'sale', 'SL-019', 'FVFSY3EXJGH5', 'A2337', 'READY', 'Grade A CTO 16GB สภาพสวย', 28000),
(3, 'SL-020', 'MacBook Pro 14" M2 Pro 16GB/512GB Space Black', 'sale', 'SL-020', 'FVFTZ4FYJGH5', 'A2779', 'READY', 'Grade A+ Cycle 28 ครบกล่อง มีฟิล์ม', 65000);

-- ============================================================
-- LOTS สำหรับทุก item ที่เพิ่งใส่
-- ============================================================
INSERT INTO inventory_lots (inventory_id, lot_number, qty_received, qty_remaining, cost_price, warranty_start, warranty_end, supplier_name)
SELECT id,
  CONCAT('LOT-', sku),
  CASE WHEN type='new' AND sku LIKE '%BAT%' THEN 5
       WHEN type='new' AND sku LIKE '%CBL%' THEN 6
       WHEN type='new' AND sku LIKE '%LBD%' THEN 2
       WHEN type='new' THEN 3
       ELSE 1 END,
  CASE WHEN type='new' AND sku LIKE '%BAT%' THEN 5
       WHEN type='new' AND sku LIKE '%CBL%' THEN 6
       WHEN type='new' AND sku LIKE '%LBD%' THEN 2
       WHEN type='new' THEN 3
       ELSE 1 END,
  ROUND(sell_price * CASE WHEN type='new' THEN 0.65 WHEN type='used' THEN 0.50 WHEN type='machine' THEN 0.60 ELSE 0.75 END),
  '2025-01-01',
  CASE WHEN type='new' THEN '2026-01-01' ELSE NULL END,
  CASE WHEN type='new' THEN 'บริษัท ไอทีพาร์ท จำกัด'
       WHEN type='used' THEN 'ถอดจากเครื่องซาก'
       WHEN type='machine' THEN 'รับซื้อจากลูกค้า'
       ELSE 'รับซื้อ / นำเข้า' END
FROM inventory
WHERE sku LIKE 'NW-%' OR sku LIKE 'US-%' OR sku LIKE 'MC-%' OR sku LIKE 'SL-%';
