-- migration_sticker_prints.sql
-- สติ๊กเกอร์เลขที่ซ่อม (QR) พิมพ์ล่วงหน้าบนแผ่น A4 — เก็บประวัติว่าใครพิมพ์ช่วงเลขไหนเมื่อไหร่
-- ใช้โดย admin/tracking/stickers.php และ stickers_print.php

CREATE TABLE IF NOT EXISTS sticker_prints (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    kind        ENUM('range','reprint') NOT NULL,
    start_no    INT UNSIGNED DEFAULT NULL,          -- range: เลขเริ่ม (ส่วนตัวเลขของ V####)
    end_no      INT UNSIGNED DEFAULT NULL,          -- range: เลขสุดท้าย (รวม)
    ticket      VARCHAR(50)  DEFAULT NULL,          -- reprint: เลขที่ซ่อมตามจริง (อาจเป็นเลขเก่าแบบ "V5508 (2)")
    per_no      TINYINT UNSIGNED NOT NULL DEFAULT 1, -- range: กี่ดวงต่อเลข (เลขละ 2 ดวง = แปะเครื่อง + ที่ชาร์จ)
    qty         SMALLINT UNSIGNED NOT NULL,         -- จำนวนดวงที่พิมพ์ทั้งหมด
    start_slot  TINYINT UNSIGNED NOT NULL DEFAULT 1, -- เริ่มพิมพ์ที่ดวงที่เท่าไหร่ของแผ่นแรก (1–จำนวนดวงต่อแผ่น)
    admin_id    INT DEFAULT NULL,
    admin_name  VARCHAR(100) DEFAULT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_range (kind, start_no, end_no),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
