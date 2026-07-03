-- Remove DEMO shop data seeded by seed_shop_demo.sql
-- Run: docker compose exec -T db mysql -ucmns_user -pcmns_password cmnsfixmac_db < database/seed_shop_demo_cleanup.sql
START TRANSACTION;
DELETE si FROM shop_images si JOIN shop_listings sl ON sl.id = si.listing_id WHERE sl.slug LIKE 'demo-%';
DELETE FROM shop_listings WHERE slug LIKE 'demo-%';
DELETE FROM inventory WHERE sku LIKE 'DEMO-%';
COMMIT;
