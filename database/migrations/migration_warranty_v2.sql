-- migration_warranty_v2.sql
-- Run once on production DB

CREATE TABLE IF NOT EXISTS `warranties` (
  `id` int NOT NULL AUTO_INCREMENT,
  `warranty_no` varchar(20) NOT NULL COMMENT 'W-YYYY-NNNN',
  `tracking_id` int DEFAULT NULL COMMENT 'FK to tracking (nullable)',
  `customer_name` varchar(120) NOT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `device_model` varchar(120) NOT NULL,
  `serial_no` varchar(40) DEFAULT NULL,
  `repair_summary` text DEFAULT NULL,
  `warranty_days` int NOT NULL DEFAULT 90,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','expired','voided') NOT NULL DEFAULT 'active',
  `void_reason` varchar(255) DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_warranty_no` (`warranty_no`),
  KEY `idx_tracking` (`tracking_id`),
  KEY `idx_serial` (`serial_no`),
  KEY `idx_status_end` (`status`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `warranty_claims` (
  `id` int NOT NULL AUTO_INCREMENT,
  `claim_no` varchar(20) NOT NULL COMMENT 'C-YYYY-NNNN',
  `warranty_id` int NOT NULL,
  `claim_date` date NOT NULL,
  `issue_desc` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','resolved') NOT NULL DEFAULT 'pending',
  `resolution` text DEFAULT NULL,
  `handled_by` int DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_claim_no` (`claim_no`),
  KEY `idx_warranty` (`warranty_id`),
  CONSTRAINT `fk_wc_warranty` FOREIGN KEY (`warranty_id`) REFERENCES `warranties` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- auto-expire on each SELECT via EVENT (optional, warranty_lib does it via PHP too)
