-- ============================================================
-- DEMO shop data BATCH 2 — +20 listings (DEMO-021..040)
-- Brings total demo listings to 40 → exercises pagination (pp=24).
-- All rows tagged sku 'DEMO-%' / slug 'demo-%' — removed by seed_shop_demo_cleanup.sql
-- Apply : docker compose exec -T db mysql -ucmns_user -pcmns_password cmnsfixmac_db < database/seed_shop_demo_batch2.sql
-- shop_listings.category_id : 1=MacBook 2=iPhone 3=iPad 4=iMac/Mac mini 5=AirPods/Apple Watch
-- ============================================================

START TRANSACTION;

INSERT INTO inventory
  (category_id, sku, name, image, type, status, sell_price, color, condition_grade, cpu_spec, ram_spec, storage_spec, gpu_spec, battery_health, store_warranty_days)
VALUES
  (1,'DEMO-021','MacBook Air M3 13" 2024','/assets/img/shop/dev-macbook.png','used','STOCK',39900,'Midnight','A','Apple M3 8-core','16GB','512GB SSD','10-core GPU',98,365),
  (1,'DEMO-022','MacBook Pro 14" M2 Pro 2023','/assets/img/shop/dev-macbook.png','used','STOCK',52900,'Silver','A','Apple M2 Pro 10-core','16GB','512GB SSD','16-core GPU',95,180),
  (1,'DEMO-023','MacBook Pro 16" M3 Max 2023','/assets/img/shop/dev-macbook.png','used','STOCK',89900,'Space Black','A','Apple M3 Max 14-core','36GB','1TB SSD','30-core GPU',97,365),
  (1,'DEMO-024','MacBook Air M1 13" 2020 (256GB)','/assets/img/shop/dev-macbook.png','used','STOCK',19900,'Gold','B','Apple M1 8-core','8GB','256GB SSD','7-core GPU',83,90),
  (2,'DEMO-025','iPhone 15 Pro 256GB','/assets/img/shop/dev-iphone.png','used','STOCK',36900,'Natural Titanium','A','Apple A17 Pro',NULL,'256GB',NULL,99,180),
  (2,'DEMO-026','iPhone 15 Pro Max 256GB','/assets/img/shop/dev-iphone.png','used','STOCK',42900,'Blue Titanium','A','Apple A17 Pro',NULL,'256GB',NULL,98,180),
  (2,'DEMO-027','iPhone 14 128GB','/assets/img/shop/dev-iphone.png','used','STOCK',19900,'Starlight','A','Apple A15 Bionic',NULL,'128GB',NULL,90,90),
  (2,'DEMO-028','iPhone 13 mini 128GB','/assets/img/shop/dev-iphone.png','used','STOCK',13900,'Green','A','Apple A15 Bionic',NULL,'128GB',NULL,86,90),
  (2,'DEMO-029','iPhone 12 Pro 256GB','/assets/img/shop/dev-iphone.png','used','STOCK',16900,'Pacific Blue','B','Apple A14 Bionic',NULL,'256GB',NULL,82,90),
  (2,'DEMO-030','iPhone SE 3rd Gen 128GB','/assets/img/shop/dev-iphone.png','used','STOCK',8900,'Midnight','A','Apple A15 Bionic',NULL,'128GB',NULL,93,90),
  (3,'DEMO-031','iPad Pro 12.9" M2 256GB','/assets/img/shop/dev-ipad.png','used','STOCK',39900,'Space Gray','A','Apple M2 8-core',NULL,'256GB',NULL,NULL,180),
  (3,'DEMO-032','iPad Air 4 64GB','/assets/img/shop/dev-ipad.png','used','STOCK',12900,'Sky Blue','B','Apple A14 Bionic',NULL,'64GB',NULL,NULL,90),
  (3,'DEMO-033','iPad 10th Gen 64GB','/assets/img/shop/dev-ipad.png','used','STOCK',11900,'Silver','A','Apple A14 Bionic',NULL,'64GB',NULL,NULL,180),
  (3,'DEMO-034','iPad mini 6 256GB Cellular','/assets/img/shop/dev-ipad.png','used','STOCK',18900,'Space Gray','A','Apple A15 Bionic',NULL,'256GB',NULL,NULL,180),
  (4,'DEMO-035','iMac 24" M3 2023 512GB','/assets/img/mac.png','used','STOCK',49900,'Green','A','Apple M3 8-core','8GB','512GB SSD','10-core GPU',NULL,365),
  (4,'DEMO-036','Mac mini M2 Pro 512GB','/assets/img/mac.png','used','STOCK',32900,'Silver','A','Apple M2 Pro 10-core','16GB','512GB SSD','16-core GPU',NULL,180),
  (4,'DEMO-037','Mac Studio M1 Max 512GB','/assets/img/mac.png','used','STOCK',54900,'Silver','A','Apple M1 Max 10-core','32GB','512GB SSD','24-core GPU',NULL,180),
  (5,'DEMO-038','Apple Watch Ultra 49mm','/assets/img/watch.png','used','STOCK',24900,'Titanium','A',NULL,NULL,'32GB',NULL,95,180),
  (5,'DEMO-039','Apple Watch Series 9 41mm GPS','/assets/img/watch.png','used','STOCK',13900,'Starlight','A',NULL,NULL,'64GB',NULL,96,90),
  (5,'DEMO-040','AirPods Max','/assets/img/airpods.png','used','STOCK',13900,'Space Gray','A',NULL,NULL,NULL,NULL,NULL,90);

INSERT INTO shop_listings
  (inventory_id, category_id, slug, title, description, price, price_original, cover_image, status, views)
VALUES
  ((SELECT id FROM inventory WHERE sku='DEMO-021'),1,'demo-macbook-air-m3-2024','MacBook Air M3 13" 2024','<p>MacBook Air M3 ล่าสุด RAM 16GB SSD 512GB แบต 98% สภาพนางฟ้า รับประกันร้าน 1 ปี</p>',39900,43900,'/assets/img/shop/dev-macbook.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-022'),1,'demo-macbook-pro-14-m2-pro-2023','MacBook Pro 14" M2 Pro 2023','<p>M2 Pro 10-core จอ Liquid Retina XDR เหมาะงานหนัก สภาพสวย รับประกันร้าน</p>',52900,57900,'/assets/img/shop/dev-macbook.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-023'),1,'demo-macbook-pro-16-m3-max-2023','MacBook Pro 16" M3 Max 2023','<p>ตัวท็อป M3 Max RAM 36GB SSD 1TB แรงสุดสำหรับงาน Pro สภาพนางฟ้า</p>',89900,99900,'/assets/img/shop/dev-macbook.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-024'),1,'demo-macbook-air-m1-256gb','MacBook Air M1 13" 2020 (256GB)','<p>MacBook Air M1 ราคาประหยัด มีรอยตามการใช้งาน ฟังก์ชันครบ คุ้มมาก</p>',19900,NULL,'/assets/img/shop/dev-macbook.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-025'),2,'demo-iphone-15-pro-256gb','iPhone 15 Pro 256GB','<p>iPhone 15 Pro ไทเทเนียม ปุ่ม Action กล้องโปร แบต 99% สภาพนางฟ้า</p>',36900,39900,'/assets/img/shop/dev-iphone.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-026'),2,'demo-iphone-15-pro-max-256gb','iPhone 15 Pro Max 256GB','<p>จอใหญ่ ซูม 5x ไทเทเนียม Blue แบต 98% สภาพสวยมาก รับประกันร้าน 6 เดือน</p>',42900,46900,'/assets/img/shop/dev-iphone.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-027'),2,'demo-iphone-14-128gb','iPhone 14 128GB','<p>iPhone 14 สี Starlight สภาพสวย แบต 90% ตัวเครื่องไม่มีรอยเด่น</p>',19900,NULL,'/assets/img/shop/dev-iphone.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-028'),2,'demo-iphone-13-mini-128gb','iPhone 13 mini 128GB','<p>เครื่องเล็กกะทัดรัด สี Green สภาพสวย เหมาะคนชอบจอเล็ก</p>',13900,NULL,'/assets/img/shop/dev-iphone.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-029'),2,'demo-iphone-12-pro-256gb','iPhone 12 Pro 256GB','<p>iPhone 12 Pro Pacific Blue ความจุเยอะ มีรอยตามอายุ ใช้งานปกติ</p>',16900,18900,'/assets/img/shop/dev-iphone.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-030'),2,'demo-iphone-se-3-128gb','iPhone SE 3rd Gen 128GB','<p>iPhone SE 3 ชิป A15 แรง ราคาคุ้ม แบต 93% สภาพสวย เหมาะใช้งานทั่วไป</p>',8900,NULL,'/assets/img/shop/dev-iphone.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-031'),3,'demo-ipad-pro-129-m2-256gb','iPad Pro 12.9" M2 256GB','<p>iPad Pro จอใหญ่ 12.9 นิ้ว mini-LED ProMotion ชิป M2 เหมาะงานวาด/ตัดต่อ</p>',39900,44900,'/assets/img/shop/dev-ipad.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-032'),3,'demo-ipad-air-4-64gb','iPad Air 4 64GB','<p>iPad Air 4 สี Sky Blue รองรับ Pencil 2 มีรอยตามการใช้งาน คุ้มราคา</p>',12900,NULL,'/assets/img/shop/dev-ipad.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-033'),3,'demo-ipad-10th-gen-64gb','iPad 10th Gen 64GB','<p>iPad รุ่นใหม่ USB-C ดีไซน์ทันสมัย สภาพสวย เหมาะเรียน/ดูหนัง</p>',11900,NULL,'/assets/img/shop/dev-ipad.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-034'),3,'demo-ipad-mini-6-256gb-cellular','iPad mini 6 256GB Cellular','<p>iPad mini 6 ใส่ซิมได้ ความจุเยอะ พกพาง่าย รองรับ Pencil 2 สภาพสวย</p>',18900,21900,'/assets/img/shop/dev-ipad.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-035'),4,'demo-imac-24-m3-2023','iMac 24" M3 2023 512GB','<p>iMac 24 นิ้ว สี Green ชิป M3 จอ 4.5K สวยมาก พร้อมคีย์บอร์ด-เมาส์</p>',49900,54900,'/assets/img/mac.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-036'),4,'demo-mac-mini-m2-pro-512gb','Mac mini M2 Pro 512GB','<p>Mac mini M2 Pro แรงจัด RAM 16GB เหมาะงาน Pro ขนาดเล็ก ประหยัดไฟ</p>',32900,NULL,'/assets/img/mac.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-037'),4,'demo-mac-studio-m1-max-512gb','Mac Studio M1 Max 512GB','<p>Mac Studio M1 Max RAM 32GB พอร์ตครบ เหมาะงาน Pro หนักๆ สภาพสวย</p>',54900,59900,'/assets/img/mac.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-038'),5,'demo-apple-watch-ultra-49mm','Apple Watch Ultra 49mm','<p>Apple Watch Ultra ตัวเรือนไทเทเนียม แบตอึด เหมาะสายลุย แบต 95% สภาพสวย</p>',24900,27900,'/assets/img/watch.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-039'),5,'demo-apple-watch-series-9-41mm','Apple Watch Series 9 41mm GPS','<p>Apple Watch S9 ชิปใหม่ จอสว่าง แบต 96% พร้อมสายใหม่ สภาพสวย</p>',13900,NULL,'/assets/img/watch.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-040'),5,'demo-airpods-max','AirPods Max','<p>AirPods Max หูฟังครอบหัว เสียงระดับ Hi-Fi ตัดเสียงรบกวน สภาพสวย ครบกล่อง</p>',13900,16900,'/assets/img/airpods.png','published',0);

COMMIT;
