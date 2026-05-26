-- ============================================================
-- Migration: parts_donors → inventory (type = 'machine')
-- ============================================================
-- วิธีรัน:
--   docker exec -i cmns-db mysql -ucmns_user -pcmns_password cmnsfixmac_db < db/migrate_donors_to_inventory.sql
-- หรือรันใน phpMyAdmin (import ไฟล์นี้)
--
-- หลังรัน SQL แล้วให้ copy รูปด้วยคำสั่ง:
--   cp uploads/parts/* uploads/inventory/
-- ============================================================

SET NAMES utf8mb4;
START TRANSACTION;

-- ============================================================
-- STEP 1: สร้าง categories ใน parts_categories สำหรับ Machine
--         ถ้ายังไม่มี (ใช้ IF NOT EXISTS แบบ safe)
-- ============================================================

INSERT INTO parts_categories (name, parent_id, icon, description)
SELECT 'MacBook (เครื่องซาก)', NULL, 'laptop_mac', 'เครื่องซาก MacBook ทุกรุ่น'
WHERE NOT EXISTS (SELECT 1 FROM parts_categories WHERE name = 'MacBook (เครื่องซาก)');

INSERT INTO parts_categories (name, parent_id, icon, description)
SELECT 'iMac (เครื่องซาก)', NULL, 'desktop_mac', 'เครื่องซาก iMac ทุกรุ่น'
WHERE NOT EXISTS (SELECT 1 FROM parts_categories WHERE name = 'iMac (เครื่องซาก)');

INSERT INTO parts_categories (name, parent_id, icon, description)
SELECT 'Mac mini (เครื่องซาก)', NULL, 'computer', 'เครื่องซาก Mac mini ทุกรุ่น'
WHERE NOT EXISTS (SELECT 1 FROM parts_categories WHERE name = 'Mac mini (เครื่องซาก)');

INSERT INTO parts_categories (name, parent_id, icon, description)
SELECT 'iPhone (เครื่องซาก)', NULL, 'smartphone', 'เครื่องซาก iPhone ทุกรุ่น'
WHERE NOT EXISTS (SELECT 1 FROM parts_categories WHERE name = 'iPhone (เครื่องซาก)');

INSERT INTO parts_categories (name, parent_id, icon, description)
SELECT 'iPad (เครื่องซาก)', NULL, 'tablet_mac', 'เครื่องซาก iPad ทุกรุ่น'
WHERE NOT EXISTS (SELECT 1 FROM parts_categories WHERE name = 'iPad (เครื่องซาก)');

-- ============================================================
-- STEP 2: Migrate parts_donors → inventory
-- ============================================================
-- หมายเหตุ: ถ้า column `internal_id` ไม่มีในตาราง parts_donors ของคุณ
--   ให้แทน pd.internal_id ด้วย NULL ทั้งสองที่ที่ใช้
-- ============================================================

INSERT INTO inventory (
    category_id,
    sku,
    name,
    type,
    asset_tag,
    serial_number,
    compatible_models,
    location,
    min_qty,
    disassembly_status,
    status,
    condition_note,
    image,
    sell_price,
    created_at
)
SELECT

    -- category_id: map จาก parts_donors.category (string) → parts_categories.id
    CASE pd.category
        WHEN 'MacBook' THEN (SELECT id FROM parts_categories WHERE name = 'MacBook (เครื่องซาก)' LIMIT 1)
        WHEN 'iMac'    THEN (SELECT id FROM parts_categories WHERE name = 'iMac (เครื่องซาก)'    LIMIT 1)
        WHEN 'Mac'     THEN (SELECT id FROM parts_categories WHERE name = 'Mac mini (เครื่องซาก)' LIMIT 1)
        WHEN 'iPhone'  THEN (SELECT id FROM parts_categories WHERE name = 'iPhone (เครื่องซาก)'  LIMIT 1)
        WHEN 'iPad'    THEN (SELECT id FROM parts_categories WHERE name = 'iPad (เครื่องซาก)'    LIMIT 1)
        ELSE                (SELECT id FROM parts_categories WHERE name = 'MacBook (เครื่องซาก)' LIMIT 1)
    END                                                                     AS category_id,

    -- sku: ใช้ internal_id ถ้ามีค่า ไม่งั้นสร้างจาก id เดิม
    COALESCE(NULLIF(TRIM(pd.internal_id), ''), CONCAT('MC-OLD-', LPAD(pd.id, 4, '0')))   AS sku,

    pd.device_name                                                          AS name,

    'machine'                                                               AS type,

    -- asset_tag: เหมือน sku
    COALESCE(NULLIF(TRIM(pd.internal_id), ''), CONCAT('MC-OLD-', LPAD(pd.id, 4, '0')))   AS asset_tag,

    NULLIF(TRIM(pd.serial_no), '')                                          AS serial_number,

    NULLIF(TRIM(pd.device_models), '')                                      AS compatible_models,

    NULLIF(TRIM(pd.location_index), '')                                     AS location,

    1                                                                       AS min_qty,

    -- disassembly_status: map จาก parts_donors.status
    CASE pd.status
        WHEN 'stripped'  THEN 'stripped'
        WHEN 'reserved'  THEN 'intact'
        WHEN 'in_stock'  THEN 'intact'
        WHEN 'sold'      THEN 'stripped'
        WHEN 'disposed'  THEN 'stripped'
        ELSE 'intact'
    END                                                                     AS disassembly_status,

    -- inventory.status สำหรับ machine คือ READY/PARTIAL/stripped
    'READY'                                                                 AS status,

    -- condition_note: รวม remarks + ค่าใช้จ่าย + ref + flag ขายออกไปแล้ว
    TRIM(CONCAT_WS(' | ',
        NULLIF(TRIM(pd.remarks), ''),
        IF(pd.purchase_cost > 0,
            CONCAT('ทุน ฿', FORMAT(pd.purchase_cost, 0)),
            NULL),
        IF(pd.reserved_ref IS NOT NULL AND TRIM(pd.reserved_ref) != '',
            CONCAT('Ref: ', TRIM(pd.reserved_ref)),
            NULL),
        IF(pd.status IN ('sold','disposed'),
            CONCAT('[สถานะเก่า: ', pd.status, ']'),
            NULL)
    ))                                                                      AS condition_note,

    -- image: filename เดิม (ต้อง cp uploads/parts/* uploads/inventory/ ด้วยมือ)
    NULLIF(TRIM(pd.image_url), '')                                          AS image,

    0                                                                       AS sell_price,

    pd.created_at                                                           AS created_at

FROM parts_donors pd

-- ป้องกัน duplicate: ถ้ามี serial_number เดียวกันอยู่ใน inventory type=machine แล้ว ข้ามไป
-- ถ้า serial_no ว่างเปล่า ใช้ internal_id เป็น fallback check แทน
WHERE NOT EXISTS (
    SELECT 1 FROM inventory i
    WHERE i.type = 'machine'
      AND (
          (pd.serial_no IS NOT NULL AND pd.serial_no != ''
            AND i.serial_number = pd.serial_no)
          OR
          (COALESCE(NULLIF(TRIM(pd.internal_id), ''), '') != ''
            AND i.asset_tag = COALESCE(NULLIF(TRIM(pd.internal_id), ''), ''))
      )
);

COMMIT;

-- ============================================================
-- STEP 3: ตรวจสอบผลลัพธ์
-- ============================================================
SELECT
    COUNT(*) AS migrated_count,
    SUM(CASE WHEN disassembly_status = 'intact'   THEN 1 ELSE 0 END) AS intact,
    SUM(CASE WHEN disassembly_status = 'stripped' THEN 1 ELSE 0 END) AS stripped,
    SUM(CASE WHEN image IS NOT NULL               THEN 1 ELSE 0 END) AS has_image
FROM inventory
WHERE type = 'machine'
  AND sku LIKE 'MC-OLD-%'   -- เฉพาะที่ migrate มาใหม่ (ไม่มี internal_id)
   OR asset_tag IN (SELECT COALESCE(NULLIF(TRIM(internal_id),''), '') FROM parts_donors WHERE internal_id IS NOT NULL AND internal_id != '');

-- ============================================================
-- STEP 4 (optional): DROP ตาราง parts เก่าหลังตรวจสอบแล้วว่าข้อมูลครบ
-- ============================================================
-- รันเฉพาะเมื่อมั่นใจว่า migrate สำเร็จ 100%:
--
-- DROP TABLE IF EXISTS parts_used_log;
-- DROP TABLE IF EXISTS parts_doc_lines;
-- DROP TABLE IF EXISTS parts_docs;
-- DROP TABLE IF EXISTS parts_used;
-- DROP TABLE IF EXISTS parts_new;
-- DROP TABLE IF EXISTS parts_donors;
