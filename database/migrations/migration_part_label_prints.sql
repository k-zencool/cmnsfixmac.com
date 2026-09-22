-- migration_part_label_prints.sql
-- ฉลาก QR อะไหล่ (CMNS:P-<id>) — เก็บประวัติว่าพิมพ์รายการไหน กี่ดวง ใคร เมื่อไหร่
-- ใช้โดย admin/inventory/labels.php และ print_labels.php

CREATE TABLE IF NOT EXISTS part_label_runs (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    copies      TINYINT UNSIGNED NOT NULL DEFAULT 1,  -- ดวงต่อรายการ
    item_count  SMALLINT UNSIGNED NOT NULL,           -- จำนวนรายการในรอบนี้
    start_slot  TINYINT UNSIGNED NOT NULL DEFAULT 1,  -- เริ่มพิมพ์ที่ดวงที่เท่าไหร่ของแผ่นแรก
    admin_id    INT DEFAULT NULL,
    admin_name  VARCHAR(100) DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- one row per item in a run, in the order picked (sort)
CREATE TABLE IF NOT EXISTS part_label_run_items (
    run_id        INT UNSIGNED NOT NULL,
    inventory_id  INT UNSIGNED NOT NULL,         -- inventory.id (no FK: a deleted item keeps its print history)
    sort          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (run_id, inventory_id),
    KEY idx_item (inventory_id, run_id),
    CONSTRAINT fk_plri_run FOREIGN KEY (run_id) REFERENCES part_label_runs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
