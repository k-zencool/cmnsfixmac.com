-- Migration: unique view tracking per IP per day
-- Run once: docker compose exec db mysql -u cmns_user -pcmns_password cmnsfixmac_db < db/migration_repair_views.sql

CREATE TABLE IF NOT EXISTS `repair_views` (
  `id`          INT          NOT NULL AUTO_INCREMENT,
  `repair_id`   INT          NOT NULL,
  `ip`          VARCHAR(45)  NOT NULL,
  `viewed_date` DATE         NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_repair_ip_date` (`repair_id`, `ip`, `viewed_date`),
  KEY `idx_repair_id` (`repair_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
