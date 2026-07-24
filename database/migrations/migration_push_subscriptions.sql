-- migration_push_subscriptions.sql
-- Web Push (แจ้งเตือนผ่านแอป PWA — ฟรี ไม่มีโควต้า): เก็บ subscription ต่ออุปกรณ์ต่อ admin
-- VAPID keypair เก็บใน chat_platform_config (platform='webpush') — สร้างอัตโนมัติครั้งแรกที่ใช้ ไม่ต้อง seed

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id      INT NOT NULL,
    endpoint      TEXT NOT NULL,
    endpoint_hash CHAR(64) NOT NULL,              -- sha256(endpoint) ไว้ dedupe/ลบ (endpoint ยาวเกิน index)
    p256dh        VARCHAR(255) NOT NULL,          -- public key ของ browser (base64url)
    auth          VARCHAR(64)  NOT NULL,          -- auth secret ของ browser (base64url)
    ua            VARCHAR(255) DEFAULT NULL,      -- user agent ไว้จำว่าเครื่องไหน
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_ok_at    DATETIME DEFAULT NULL,          -- ส่งสำเร็จล่าสุด
    PRIMARY KEY (id),
    UNIQUE KEY uq_endpoint (endpoint_hash),
    KEY idx_admin (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
