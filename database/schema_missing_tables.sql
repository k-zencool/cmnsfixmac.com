-- ============================================================
-- schema_missing_tables.sql
-- Tables that exist on the PRODUCTION DB but were never captured
-- in full_dump.sql. Reverse-engineered from the app's queries so a
-- fresh local Docker can reproduce every page.
--
-- Runs automatically on `docker compose up` (alphabetically AFTER
-- full_dump.sql, BEFORE the seed_*.sql files).
--
-- NOTE: this is a best-effort local mirror. Column TYPES/indexes are
-- inferred, and there is NO production data here. Replace full_dump.sql
-- with a fresh prod dump when you can and this file becomes redundant.
-- ============================================================

SET NAMES utf8mb4;

-- ---------- Inventory core ----------
CREATE TABLE IF NOT EXISTS inventory (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id         INT UNSIGNED NULL,
    sku                 VARCHAR(64)  NULL,
    name                VARCHAR(255) NOT NULL,
    image               VARCHAR(255) NULL,
    type                ENUM('new','used','machine','sale') NOT NULL DEFAULT 'new',
    original_type       ENUM('new','used','machine') NULL DEFAULT NULL,
    original_status     VARCHAR(30)  NULL DEFAULT NULL,
    asset_tag           VARCHAR(100) NULL,
    serial_number       VARCHAR(100) NULL,
    part_number         VARCHAR(100) NULL,
    compatible_models   VARCHAR(255) NULL,
    device_model        VARCHAR(120) NULL,
    location            VARCHAR(100) NULL,
    min_qty             INT NOT NULL DEFAULT 0,
    source_machine_id   INT UNSIGNED NULL,
    disassembly_status  VARCHAR(30)  NULL,
    condition_note      TEXT NULL,
    status              VARCHAR(30)  NOT NULL DEFAULT 'STOCK',
    sell_price          DECIMAL(12,2) NOT NULL DEFAULT 0,
    color               VARCHAR(50)  NULL,
    condition_grade     VARCHAR(10)  NULL,
    cpu_spec            VARCHAR(120) NULL,
    ram_spec            VARCHAR(60)  NULL,
    storage_spec        VARCHAR(60)  NULL,
    gpu_spec            VARCHAR(120) NULL,
    apple_warranty_date DATE NULL,
    store_warranty_days INT NULL,
    battery_health      INT NULL,
    battery_cycles      INT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type   (type),
    INDEX idx_status (status),
    INDEX idx_cat    (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Inventory lots (batch stock) ----------
CREATE TABLE IF NOT EXISTS inventory_lots (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inventory_id   INT UNSIGNED NOT NULL,
    lot_number     VARCHAR(50)  NULL,
    qty_received   INT NOT NULL DEFAULT 0,
    qty_remaining  INT NOT NULL DEFAULT 0,
    cost_price     DECIMAL(12,2) NOT NULL DEFAULT 0,
    warranty_start DATE NULL,
    warranty_end   DATE NULL,
    supplier_name  VARCHAR(150) NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inv (inventory_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Parts categories (inventory categories) ----------
CREATE TABLE IF NOT EXISTS parts_categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    parent_id   INT UNSIGNED NULL,
    icon        VARCHAR(80)  NULL,
    description VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Parts requisitions (stock deductions / sales log) ----------
CREATE TABLE IF NOT EXISTS parts_requisitions (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inventory_id      INT UNSIGNED NULL,
    lot_id            INT UNSIGNED NULL,
    tracking_id       INT UNSIGNED NULL,
    ticket_number     VARCHAR(50)  NULL,
    item_name         VARCHAR(255) NULL,
    item_sku          VARCHAR(64)  NULL,
    qty               INT NOT NULL DEFAULT 1,
    cost_price        DECIMAL(12,2) NOT NULL DEFAULT 0,
    sell_price        DECIMAL(12,2) NOT NULL DEFAULT 0,
    requisitioned_by  INT UNSIGNED NULL,
    admin_name        VARCHAR(100) NULL,
    remarks           VARCHAR(255) NULL,
    manager_action_id INT UNSIGNED NULL,
    status            VARCHAR(30)  NOT NULL DEFAULT 'active',
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inv (inventory_id),
    INDEX idx_trk (tracking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Service pricing (repair price tables) ----------
CREATE TABLE IF NOT EXISTS pricing_categories (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_pricing (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_type   VARCHAR(50)  NOT NULL,
    device_name   VARCHAR(150) NOT NULL,
    category_id   INT UNSIGNED NOT NULL,
    price         INT NOT NULL DEFAULT 0,
    price_note    VARCHAR(255) NULL,
    warranty_days INT NOT NULL DEFAULT 90,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    show_on_web   TINYINT(1) NOT NULL DEFAULT 1,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_dev (device_type),
    INDEX idx_cat (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Shop ----------
CREATE TABLE IF NOT EXISTS shop_categories (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS shop_listings (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inventory_id   INT UNSIGNED NULL,
    category_id    INT UNSIGNED NOT NULL,
    slug           VARCHAR(200) NULL,
    title          VARCHAR(255) NULL,
    description    TEXT NULL,
    description_en TEXT NULL,
    price          DECIMAL(12,2) NOT NULL DEFAULT 0,
    price_original DECIMAL(12,2) NULL,
    cover_image    VARCHAR(255) NULL,
    cover_w        INT NULL,
    cover_h        INT NULL,
    status         VARCHAR(20) NOT NULL DEFAULT 'published',
    views          INT NOT NULL DEFAULT 0,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_inv    (inventory_id),
    INDEX idx_cat    (category_id),
    INDEX idx_status (status),
    INDEX idx_slug   (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS shop_images (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id INT UNSIGNED NOT NULL,
    url        VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_cover   TINYINT(1) NOT NULL DEFAULT 0,
    width      INT NULL,
    height     INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_listing (listing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------- Repair tracking history ----------
CREATE TABLE IF NOT EXISTS tracking_history (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tracking_id INT UNSIGNED NOT NULL,
    changed_by  INT UNSIGNED NULL,
    admin_name  VARCHAR(100) NULL,
    status_old  VARCHAR(50)  NULL,
    status_new  VARCHAR(50)  NULL,
    cost_old    DECIMAL(12,2) NULL,
    cost_new    DECIMAL(12,2) NULL,
    appt_old    DATETIME NULL,
    appt_new    DATETIME NULL,
    pickup_old  DATETIME NULL,
    pickup_new  DATETIME NULL,
    diff_json   TEXT NULL,
    changed_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_trk (tracking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Category seeds (ids referenced hard-coded across the app)
-- ============================================================
INSERT INTO parts_categories (id, name, parent_id, icon) VALUES
    (1,'MacBook',NULL,'laptop_mac'),
    (2,'iPhone',NULL,'smartphone'),
    (3,'iPad',NULL,'tablet_mac'),
    (4,'iMac / Mac mini',NULL,'desktop_mac'),
    (5,'AirPods',NULL,'headphones'),
    (6,'Apple Watch',NULL,'watch'),
    (7,'อะไหล่ทั่วไป',NULL,'memory'),
    (8,'อุปกรณ์เสริม',NULL,'cable'),
    (9,'อื่นๆ',NULL,'category')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO shop_categories (id, name, sort_order) VALUES
    (1,'MacBook',1),
    (2,'iPhone',2),
    (3,'iPad',3),
    (4,'iMac / Mac mini',4),
    (5,'AirPods / Apple Watch',5),
    (6,'Apple Watch',6),
    (7,'อุปกรณ์เสริม',7)
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO pricing_categories (id, name, sort_order) VALUES
    (1,'เปลี่ยนหน้าจอ',1),
    (2,'เปลี่ยนแบตเตอรี่',2),
    (3,'ซ่อมเมนบอร์ด',3),
    (4,'บริการซอฟต์แวร์',4),
    (5,'อื่นๆ',5)
ON DUPLICATE KEY UPDATE name = VALUES(name);
