-- ============================================================
-- Option A: Patch critical inventory/shop flow weaknesses
-- วิธีรัน:
--   docker exec -i cmns-db mysql -ucmns_user -pcmns_password cmnsfixmac_db < db/migration_option_a.sql
-- ============================================================

SET NAMES utf8mb4;

-- 1. เก็บ type/status เดิมก่อน convert → ไม่ต้อง parse remarks อีกต่อไป
ALTER TABLE inventory
    ADD COLUMN original_type   ENUM('new','used','machine') NULL DEFAULT NULL AFTER type,
    ADD COLUMN original_status VARCHAR(30)                  NULL DEFAULT NULL AFTER status;

-- 2. Audit log สำหรับทุก status/type change ที่เกิดบน inventory
CREATE TABLE inventory_status_log (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inventory_id INT UNSIGNED  NOT NULL,
    action       VARCHAR(50)   NOT NULL,   -- to_sale | revert_sale | status_update | sold | listing_created | listing_deleted
    from_type    VARCHAR(20)   NULL,
    to_type      VARCHAR(20)   NULL,
    from_status  VARCHAR(30)   NULL,
    to_status    VARCHAR(30)   NULL,
    reference_id INT           NULL,       -- shop_listing_id หรือ tracking_id แล้วแต่ context
    created_by   INT UNSIGNED  NULL,
    admin_name   VARCHAR(100)  NULL,
    note         TEXT          NULL,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inv  (inventory_id),
    INDEX idx_ts   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
