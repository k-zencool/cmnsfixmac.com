-- ============================================================
-- Manager Control Center — audit ledger + reversal support
-- โมเดล: ทำงานทันที (ไม่บล็อก) แต่ manager/super_admin เห็นทุก
-- transaction ที่ staff/admin ทำ แล้ว "ย้อนกลับ (reverse)" ได้
-- วิธีรัน:
--   docker exec -i cmns-db mysql -ucmns_user -pcmns_password cmnsfixmac_db < database/migrations/migration_manager_center.sql
-- ============================================================

-- 1) Audit ledger กลาง — ทุก sensitive action ลงที่นี่
-- หมายเหตุ: ใช้ COLLATE=utf8mb4_unicode_ci (portable ทั้ง MySQL 5.7 / 8 / MariaDB)
--           ห้ามใช้ utf8mb4_0900_ai_ci เพราะ prod เก่ากว่า MySQL 8 จะ error #1273
CREATE TABLE IF NOT EXISTS manager_actions (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action_type   VARCHAR(40)   NOT NULL,   -- requisition | price_set | stock_delete | stock_edit | donor_strip | to_sale | sale_status
    ref_table     VARCHAR(50)   NULL,       -- ตารางที่ถูกกระทำ (inventory / parts_requisitions / pricing ...)
    ref_id        BIGINT        NULL,       -- id ของ record ที่ถูกกระทำ
    actor_id      INT UNSIGNED  NULL,       -- คนที่ทำ
    actor_name    VARCHAR(100)  NULL,
    actor_role    VARCHAR(30)   NULL,
    summary       VARCHAR(255)  NOT NULL,   -- ข้อความอ่านง่ายสำหรับ manager
    amount        DECIMAL(12,2) NULL,       -- มูลค่าเชิงการเงิน (ไว้โชว์/รวมยอด)
    payload       JSON          NULL,       -- ข้อมูลที่ต้องใช้ตอน reverse
    status        ENUM('active','reversed') NOT NULL DEFAULT 'active',
    reversible    TINYINT(1)    NOT NULL DEFAULT 1,
    reversed_by   INT UNSIGNED  NULL,
    reversed_name VARCHAR(100)  NULL,
    reversed_at   DATETIME      NULL,
    reverse_note  VARCHAR(255)  NULL,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type   (action_type),
    INDEX idx_status (status),
    INDEX idx_ts     (created_at),
    INDEX idx_actor  (actor_id),
    INDEX idx_ref    (ref_table, ref_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) parts_requisitions: mark สถานะเมื่อถูก reverse (แทนการลบทิ้ง)
--    MySQL 8 ไม่มี ADD COLUMN IF NOT EXISTS — ถ้ารันซ้ำแล้ว error ว่า column ซ้ำ ข้ามได้เลย
ALTER TABLE parts_requisitions
    ADD COLUMN status ENUM('active','reversed') NOT NULL DEFAULT 'active',
    ADD COLUMN manager_action_id BIGINT UNSIGNED NULL,
    ADD INDEX idx_req_status (status);
