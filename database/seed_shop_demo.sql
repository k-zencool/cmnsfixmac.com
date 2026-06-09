-- ============================================================
-- DEMO shop data — 20 listings (for previewing the shop UI)
-- All rows tagged with sku 'DEMO-%' / slug 'demo-%' for easy removal.
-- Apply : docker compose exec -T db mysql -ucmns_user -pcmns_password cmnsfixmac_db < database/seed_shop_demo.sql
-- Remove : docker compose exec -T db mysql -ucmns_user -pcmns_password cmnsfixmac_db < database/seed_shop_demo_cleanup.sql
-- inventory.category_id = 1 (parts_categories, unused by shop display)
-- shop_listings.category_id : 1=MacBook 2=iPhone 3=iPad 4=iMac/Mac mini 5=AirPods/Apple Watch
-- ============================================================

START TRANSACTION;

INSERT INTO inventory
  (category_id, sku, name, image, type, status, sell_price, color, condition_grade, cpu_spec, ram_spec, storage_spec, gpu_spec, battery_health, store_warranty_days)
VALUES
  (1,'DEMO-001','MacBook Air M2 13" 2022','/assets/img/shop/dev-macbook.png','used','STOCK',28900,'Midnight','A','Apple M2 8-core','8GB','256GB SSD','8-core GPU',91,180),
  (1,'DEMO-002','MacBook Air M1 13" 2020','/assets/img/shop/dev-macbook.png','used','STOCK',21900,'Space Gray','A','Apple M1 8-core','8GB','256GB SSD','7-core GPU',89,180),
  (1,'DEMO-003','MacBook Pro 14" M3 Pro 2023','/assets/img/shop/dev-macbook.png','used','STOCK',62900,'Space Black','A','Apple M3 Pro 11-core','18GB','512GB SSD','14-core GPU',96,365),
  (1,'DEMO-004','MacBook Pro 13" M1 2020','/assets/img/shop/dev-macbook.png','used','STOCK',27900,'Silver','B','Apple M1 8-core','8GB','256GB SSD','8-core GPU',85,180),
  (1,'DEMO-005','MacBook Air M2 15" 2023','/assets/img/shop/dev-macbook.png','used','STOCK',36900,'Starlight','A','Apple M2 8-core','8GB','512GB SSD','10-core GPU',94,365),
  (1,'DEMO-006','MacBook Pro 16" M1 Pro 2021','/assets/img/shop/dev-macbook.png','used','STOCK',54900,'Space Gray','A','Apple M1 Pro 10-core','16GB','512GB SSD','16-core GPU',90,180),
  (1,'DEMO-007','iPhone 13 128GB','/assets/img/shop/dev-iphone.png','used','STOCK',14900,'Midnight','A','Apple A15 Bionic',NULL,'128GB',NULL,88,90),
  (1,'DEMO-008','iPhone 14 Pro 256GB','/assets/img/shop/dev-iphone.png','used','STOCK',28900,'Deep Purple','A','Apple A16 Bionic',NULL,'256GB',NULL,91,90),
  (1,'DEMO-009','iPhone 12 128GB','/assets/img/shop/dev-iphone.png','used','STOCK',11900,'Blue','B','Apple A14 Bionic',NULL,'128GB',NULL,84,90),
  (1,'DEMO-010','iPhone 15 128GB','/assets/img/shop/dev-iphone.png','used','STOCK',26900,'Pink','A','Apple A16 Bionic',NULL,'128GB',NULL,97,180),
  (1,'DEMO-011','iPhone 13 Pro Max 256GB','/assets/img/shop/dev-iphone.png','used','STOCK',24900,'Sierra Blue','A','Apple A15 Bionic',NULL,'256GB',NULL,89,90),
  (1,'DEMO-012','iPhone 11 64GB','/assets/img/shop/dev-iphone.png','used','STOCK',7900,'White','B','Apple A13 Bionic',NULL,'64GB',NULL,81,90),
  (1,'DEMO-013','iPad Air 5 M1 64GB WiFi','/assets/img/shop/dev-ipad.png','used','STOCK',16900,'Blue','A','Apple M1 8-core',NULL,'64GB',NULL,NULL,180),
  (1,'DEMO-014','iPad Pro 11" M2 128GB','/assets/img/shop/dev-ipad.png','used','STOCK',28900,'Space Gray','A','Apple M2 8-core',NULL,'128GB',NULL,NULL,180),
  (1,'DEMO-015','iPad 9th Gen 64GB','/assets/img/shop/dev-ipad.png','used','STOCK',8900,'Silver','B','Apple A13 Bionic',NULL,'64GB',NULL,NULL,90),
  (1,'DEMO-016','iPad mini 6 64GB','/assets/img/shop/dev-ipad.png','used','STOCK',14900,'Starlight','A','Apple A15 Bionic',NULL,'64GB',NULL,NULL,180),
  (1,'DEMO-017','iMac 24" M1 2021 256GB','/assets/img/mac.png','used','STOCK',38900,'Blue','A','Apple M1 8-core','8GB','256GB SSD','8-core GPU',NULL,180),
  (1,'DEMO-018','Mac mini M2 256GB','/assets/img/mac.png','used','STOCK',19900,'Silver','A','Apple M2 8-core','8GB','256GB SSD','10-core GPU',NULL,180),
  (1,'DEMO-019','Apple Watch Series 8 45mm GPS','/assets/img/watch.png','used','STOCK',11900,'Midnight','A',NULL,NULL,'32GB',NULL,92,90),
  (1,'DEMO-020','AirPods Pro (2nd gen)','/assets/img/airpods.png','used','STOCK',6900,'White','A',NULL,NULL,NULL,NULL,NULL,90);

INSERT INTO shop_listings
  (inventory_id, category_id, slug, title, description, price, price_original, cover_image, status, views)
VALUES
  ((SELECT id FROM inventory WHERE sku='DEMO-001'),1,'demo-macbook-air-m2-2022','MacBook Air M2 13" 2022','<p>เครื่องคัดเกรด สภาพสวย ตรวจเช็คครบทุกฟังก์ชัน พร้อมใช้งาน รับประกันร้าน</p>',28900,32900,'/assets/img/shop/dev-macbook.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-002'),1,'demo-macbook-air-m1-2020','MacBook Air M1 13" 2020','<p>MacBook Air M1 ยอดนิยม น้ำหนักเบา แบตอึด สภาพดี รับประกันร้าน</p>',21900,NULL,'/assets/img/shop/dev-macbook.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-003'),1,'demo-macbook-pro-14-m3-pro-2023','MacBook Pro 14" M3 Pro 2023','<p>ตัวแรง M3 Pro จอ Liquid Retina XDR เหมาะงานตัดต่อ/กราฟิก สภาพนางฟ้า</p>',62900,68900,'/assets/img/shop/dev-macbook.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-004'),1,'demo-macbook-pro-13-m1-2020','MacBook Pro 13" M1 2020','<p>MacBook Pro M1 มีตำหนิเล็กน้อยตามการใช้งาน ฟังก์ชันครบ คุ้มราคา</p>',27900,NULL,'/assets/img/shop/dev-macbook.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-005'),1,'demo-macbook-air-m2-15-2023','MacBook Air M2 15" 2023','<p>จอใหญ่ 15 นิ้ว บางเบา SSD 512GB สภาพสวยมาก รับประกันร้าน 1 ปี</p>',36900,NULL,'/assets/img/shop/dev-macbook.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-006'),1,'demo-macbook-pro-16-m1-pro-2021','MacBook Pro 16" M1 Pro 2021','<p>จอ 16 นิ้ว M1 Pro แรงจัด เหมาะงานหนัก สภาพสวย</p>',54900,59900,'/assets/img/shop/dev-macbook.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-007'),2,'demo-iphone-13-128gb','iPhone 13 128GB','<p>iPhone 13 สภาพสวย แบต 88% ตัวเครื่องไม่มีรอยเด่น รับประกันร้าน</p>',14900,NULL,'/assets/img/shop/dev-iphone.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-008'),2,'demo-iphone-14-pro-256gb','iPhone 14 Pro 256GB','<p>iPhone 14 Pro สี Deep Purple Dynamic Island กล้องโปร สภาพสวย</p>',28900,31900,'/assets/img/shop/dev-iphone.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-009'),2,'demo-iphone-12-128gb','iPhone 12 128GB','<p>iPhone 12 มีรอยตามการใช้งานเล็กน้อย ใช้งานปกติ คุ้มมาก</p>',11900,NULL,'/assets/img/shop/dev-iphone.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-010'),2,'demo-iphone-15-128gb','iPhone 15 128GB','<p>iPhone 15 พอร์ต USB-C แบต 97% สภาพนางฟ้า รับประกันร้าน 6 เดือน</p>',26900,NULL,'/assets/img/shop/dev-iphone.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-011'),2,'demo-iphone-13-pro-max-256gb','iPhone 13 Pro Max 256GB','<p>จอใหญ่ 120Hz กล้องโปร แบตอึด สภาพสวย พร้อมใช้</p>',24900,NULL,'/assets/img/shop/dev-iphone.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-012'),2,'demo-iphone-11-64gb','iPhone 11 64GB','<p>iPhone 11 ราคาประหยัด ใช้งานทั่วไปลื่น มีรอยตามอายุการใช้งาน</p>',7900,9900,'/assets/img/shop/dev-iphone.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-013'),3,'demo-ipad-air-5-m1-64gb','iPad Air 5 M1 64GB WiFi','<p>iPad Air 5 ชิป M1 แรง รองรับ Apple Pencil 2 สภาพสวย</p>',16900,NULL,'/assets/img/shop/dev-ipad.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-014'),3,'demo-ipad-pro-11-m2-128gb','iPad Pro 11" M2 128GB','<p>iPad Pro M2 จอ 120Hz ProMotion เหมาะงานวาด/ตัดต่อ สภาพนางฟ้า</p>',28900,31900,'/assets/img/shop/dev-ipad.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-015'),3,'demo-ipad-9th-gen-64gb','iPad 9th Gen 64GB','<p>iPad รุ่นคุ้ม เหมาะเรียน/ดูหนัง สภาพใช้งาน ราคาประหยัด</p>',8900,NULL,'/assets/img/shop/dev-ipad.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-016'),3,'demo-ipad-mini-6-64gb','iPad mini 6 64GB','<p>iPad mini 6 พกพาง่าย จอ 8.3 นิ้ว รองรับ Pencil 2 สภาพสวย</p>',14900,NULL,'/assets/img/shop/dev-ipad.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-017'),4,'demo-imac-24-m1-2021','iMac 24" M1 2021 256GB','<p>iMac 24 นิ้ว สี Blue จอ 4.5K สวยมาก ชิป M1 พร้อมคีย์บอร์ด-เมาส์</p>',38900,42900,'/assets/img/mac.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-018'),4,'demo-mac-mini-m2-256gb','Mac mini M2 256GB','<p>Mac mini M2 ขนาดเล็ก แรง ประหยัดไฟ ต่อจอเองได้ สภาพสวย</p>',19900,NULL,'/assets/img/mac.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-019'),5,'demo-apple-watch-series-8-45mm','Apple Watch Series 8 45mm GPS','<p>Apple Watch S8 ตัวเรือน Midnight แบต 92% พร้อมสายใหม่ สภาพสวย</p>',11900,NULL,'/assets/img/watch.png','published',0),
  ((SELECT id FROM inventory WHERE sku='DEMO-020'),5,'demo-airpods-pro-2','AirPods Pro (2nd gen)','<p>AirPods Pro 2 ตัดเสียงรบกวน เสียงดี อุปกรณ์ครบกล่อง สภาพสวย</p>',6900,7990,'/assets/img/airpods.png','published',0);

COMMIT;
