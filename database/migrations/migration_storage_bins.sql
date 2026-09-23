-- migration_storage_bins.sql
-- ชั้นเก็บของ + ช่อง (กล่องใส่แฟ้ม) — ติดฉลาก QR CMNS:B-<id> หน้ากล่อง
-- สแกนแล้วเห็นว่าในช่องมีอะไร / ย้ายของเข้า-ออกจากหน้าสแกน
-- ใช้โดย admin/inventory/bins.php, print_bins.php, bin_api.php, admin/scan/bin.php
--
-- Safe to run more than once (MySQL 8 has no ADD COLUMN IF NOT EXISTS).

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
