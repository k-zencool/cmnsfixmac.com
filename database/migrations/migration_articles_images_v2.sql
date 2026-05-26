-- ============================================================
-- Migration: Articles Image System v2
-- - WebP path support
-- - alt text (TH + EN) for SEO
-- - width/height stored for og:image dimensions
-- - sort_order + is_cover matching repairs pattern
-- - FK with CASCADE DELETE
-- - og_image_width / og_image_height on articles table
-- - updated_at for JSON-LD dateModified
-- ============================================================

-- 1. article_images: add new columns
ALTER TABLE article_images
  ADD COLUMN alt        VARCHAR(300) DEFAULT NULL                                    COMMENT 'Alt text TH for SEO'     AFTER image_path,
  ADD COLUMN alt_en     VARCHAR(300) DEFAULT NULL                                    COMMENT 'Alt text EN for SEO'     AFTER alt,
  ADD COLUMN width      SMALLINT UNSIGNED DEFAULT NULL                               COMMENT 'Image width px'          AFTER caption_en,
  ADD COLUMN height     SMALLINT UNSIGNED DEFAULT NULL                               COMMENT 'Image height px'         AFTER width,
  ADD COLUMN file_size  INT UNSIGNED DEFAULT NULL                                    COMMENT 'File size bytes'         AFTER height,
  ADD COLUMN sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0                         COMMENT 'Display order'           AFTER file_size,
  ADD COLUMN is_cover   TINYINT(1) NOT NULL DEFAULT 0                               COMMENT '1 = cover image'         AFTER sort_order,
  ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP                                                   AFTER is_cover,
  MODIFY COLUMN caption    VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  MODIFY COLUMN caption_en VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL;

-- 2. article_images: add indexes
ALTER TABLE article_images
  ADD KEY idx_art_img_sort  (article_id, sort_order),
  ADD KEY idx_art_img_cover (article_id, is_cover);

-- 3. article_images: FK with CASCADE DELETE
ALTER TABLE article_images
  ADD CONSTRAINT fk_art_img_article
    FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE ON UPDATE CASCADE;

-- 4. Backfill sort_order for existing rows (sequential per article)
UPDATE article_images ai
  JOIN (
    SELECT id,
           ROW_NUMBER() OVER (PARTITION BY article_id ORDER BY id ASC) AS rn
    FROM article_images
  ) ranked ON ai.id = ranked.id
SET ai.sort_order = ranked.rn
WHERE ai.sort_order = 0;

-- 5. articles: add OG image dimensions + updated_at
ALTER TABLE articles
  ADD COLUMN og_image_width  SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Cover image width for og:image'  AFTER image,
  ADD COLUMN og_image_height SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Cover image height for og:image' AFTER og_image_width,
  ADD COLUMN updated_at      TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'For JSON-LD dateModified' AFTER created_at;

-- 6. articles: index on slug (slug lookup is frequent)
ALTER TABLE articles
  ADD KEY idx_articles_slug    (slug),
  ADD KEY idx_articles_slug_en (slug_en),
  ADD KEY idx_articles_status  (status, created_at DESC);
