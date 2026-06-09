-- Migration: unique article view tracking per IP per day
-- Run once: docker compose exec -T db mysql -u cmns_user -pcmns_password cmnsfixmac_db < database/migrations/migration_article_views.sql

CREATE TABLE IF NOT EXISTS `article_views` (
  `id`          INT          NOT NULL AUTO_INCREMENT,
  `article_id`  INT          NOT NULL,
  `ip`          VARCHAR(45)  NOT NULL,
  `viewed_date` DATE         NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_article_ip_date` (`article_id`, `ip`, `viewed_date`),
  KEY `idx_article_id` (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
