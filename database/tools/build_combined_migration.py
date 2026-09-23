#!/usr/bin/env python3
"""
Stitch the four 2026-09-23 migrations into one file to paste into phpMyAdmin.

Each source file keeps its own transaction: MySQL commits implicitly on DDL,
so the four cannot be made one atomic unit however they are arranged. What
this does give is a single paste, and one verification block at the very end
instead of four scattered through the output.

Regenerate after editing any source file.
"""
import re, os

BASE = 'database/migrations'
SRC = [
    ('migration_storage_bins.sql',
     'ชั้นเก็บของ + ช่อง (ตารางใหม่ 2 ตาราง + inventory.bin_id)'),
    ('migration_parts_rename.sql',
     'SKU + ชื่อ + คำอธิบายไทย ของอะไหล่ 115 แถว (+ inventory.name_th, หมวดย่อย 22 หมวด)'),
    ('migration_inventory_cleanup.sql',
     'เก็บกวาดข้อมูลที่ขัดกันเอง (สต็อก/สถานะเครื่อง/เครื่องที่ขายไปแล้ว)'),
    ('migration_category_codes.sql',
     'รหัสหมวด สำหรับให้ระบบออก SKU เองต่อไป (+ parts_categories.code)'),
]

def strip_checks(body: str) -> str:
    """drop each file's own check SELECTs but keep its COMMIT"""
    out, skipping = [], False
    for line in body.split('\n'):
        st = line.strip()
        if 'ตรวจก่อน COMMIT' in st or 'check before COMMIT' in st:
            skipping = True
            continue
        if skipping:
            # the COMMIT that closes the section ends the check block
            if st.upper().startswith('COMMIT'):
                skipping = False
                out.append('COMMIT;')
            continue
        out.append(line)
    return '\n'.join(out)


def banner(n, title, note):
    bar = '=' * 68
    return (f"\n\n-- {bar}\n"
            f"-- ชุดที่ {n} จาก 4 — {title}\n"
            f"-- {note}\n"
            f"-- {bar}\n")


head = """-- ############################################################
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
"""

parts = [head]
for i, (fn, note) in enumerate(SRC, 1):
    body = open(os.path.join(BASE, fn), encoding='utf-8').read()
    # drop the source file's own header block (first comment banner)
    body = re.sub(r'\A(--[^\n]*\n)+\n?', '', body)
    parts.append(banner(i, fn, note))
    parts.append(strip_checks(body).strip() + '\n')

parts.append("""

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
""")

out = os.path.join(BASE, 'migration_2026_09_23_all.sql')
open(out, 'w', encoding='utf-8').write(''.join(parts))
print('wrote', out)
