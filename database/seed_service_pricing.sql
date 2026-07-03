-- Demo repair pricing so /services/* pages render a price table locally.
-- category_id: 1=หน้าจอ 2=แบตเตอรี่ 3=เมนบอร์ด 4=ซอฟต์แวร์ 5=อื่นๆ
SET NAMES utf8mb4;

INSERT INTO service_pricing (device_type, device_name, category_id, price, price_note, warranty_days, is_active, show_on_web) VALUES
-- MacBook
('MacBook','MacBook Air M2 13"',1,8900,'ราคารวมอะไหล่แท้ถอด',90,1,1),
('MacBook','MacBook Air M2 13"',2,4500,NULL,180,1,1),
('MacBook','MacBook Pro 14" M3',1,15900,'จอ Liquid Retina XDR',90,1,1),
('MacBook','MacBook Pro 14" M3',3,6900,'เริ่มต้น ขึ้นกับอาการ',90,1,1),
('MacBook','MacBook Air M1',2,3900,NULL,180,1,1),
-- iPhone
('iPhone','iPhone 15 Pro Max',1,12900,'จอแท้ Service Pack',90,1,1),
('iPhone','iPhone 15 Pro Max',2,2500,NULL,180,1,1),
('iPhone','iPhone 14',1,7900,NULL,90,1,1),
('iPhone','iPhone 13',2,1900,NULL,180,1,1),
('iPhone','iPhone 13',3,3500,'เริ่มต้น',90,1,1),
-- iPad
('iPad','iPad Pro 11" M2',1,8900,NULL,90,1,1),
('iPad','iPad Air 5',2,2900,NULL,180,1,1),
-- iMac
('iMac','iMac 24" M1',1,12900,NULL,90,1,1),
('iMac','iMac 24" M1',3,5900,'เริ่มต้น',90,1,1),
-- AirPods
('AirPods','AirPods Pro 2',2,1500,'เปลี่ยนแบตตลับ',90,1,1),
('AirPods','AirPods Pro 2',5,900,'ทำความสะอาด/ปรับเสียง',30,1,1),
-- Apple Watch
('Apple Watch','Apple Watch Series 9',1,5900,NULL,90,1,1),
('Apple Watch','Apple Watch Series 9',2,1900,NULL,180,1,1),
-- Software
('Software','ลง macOS ใหม่',4,590,'พร้อมย้ายข้อมูล',0,1,1),
('Software','กู้ข้อมูล',4,1500,'เริ่มต้น ขึ้นกับความเสียหาย',0,1,1);
