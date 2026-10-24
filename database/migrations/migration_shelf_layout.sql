-- =========================================================
-- migration_shelf_layout.sql — per-shelf label size + slots per row
--
-- storage_shelves.label_size  'box'   = 72 × 88 mm QR card on a box front (default)
--                             'strip' = 126 × 20 mm strip slid into a drawer label
--                                       holder; only the middle 90 mm shows
-- storage_shelves.cols        slots per row on the real shelf (e.g. 7), so the
--                             grid in bins.php matches it; NULL = auto (up to 10)
--
-- Safe to run more than once. Code still works before it runs (falls back
-- to box labels / auto grid), so it may run before or after the deploy.
-- =========================================================

DROP PROCEDURE IF EXISTS _add_shelf_layout;
DELIMITER //
CREATE PROCEDURE _add_shelf_layout()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'storage_shelves' AND COLUMN_NAME = 'label_size'
    ) THEN
        ALTER TABLE storage_shelves ADD COLUMN label_size ENUM('box','strip') NOT NULL DEFAULT 'box' AFTER name;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'storage_shelves' AND COLUMN_NAME = 'cols'
    ) THEN
        ALTER TABLE storage_shelves ADD COLUMN cols TINYINT UNSIGNED DEFAULT NULL AFTER label_size;
    END IF;
END //
DELIMITER ;
CALL _add_shelf_layout();
DROP PROCEDURE IF EXISTS _add_shelf_layout;
