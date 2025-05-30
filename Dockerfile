# ใช้ Image พื้นฐานที่เป็น PHP เวอร์ชัน 8.1 พร้อม Apache web server
# Image นี้ถูกสร้างมาให้เหมาะกับการรันแอปพลิเคชัน PHP บนเว็บเซิร์ฟเวอร์ Apache
FROM php:8.1-apache

# กำหนด Working Directory ภายใน Container เป็น /var/www/html
# คำสั่งอื่นๆ หลังจากนี้จะทำงานใน Directory นี้
WORKDIR /var/www/html

# ติดตั้งส่วนขยาย PHP ที่จำเป็น
# 'mysqli' สำหรับการเชื่อมต่อฐานข้อมูล MySQL/MariaDB
# 'pdo' และ 'pdo_mysql' เป็นส่วนเสริมของ PDO (PHP Data Objects) สำหรับการเชื่อมต่อฐานข้อมูล
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy โค้ดโปรเจกต์ทั้งหมดจาก Current Directory ของ Host (เครื่องของคุณ)
# ไปยัง Working Directory ภายใน Container (/var/www/html)
COPY . /var/www/html

# กำหนดสิทธิ์การเข้าถึงไฟล์และ Directory ให้กับ User ของ Apache (www-data)
# เพื่อให้ Apache สามารถอ่านและเขียนไฟล์ได้ถูกต้อง
RUN chown -R www-data:www-data /var/www/html

# เปิดใช้งาน Apache module 'mod_rewrite'
# mod_rewrite มักจะจำเป็นสำหรับการทำ URL Rewriting (เช่น การเปลี่ยนจาก index.php?page=x เป็น /page/x)
# ซึ่งอาจใช้ใน Frameworks หรือ CMS บางตัว
RUN a2enmod rewrite

# Expose Port 80
# เป็นการประกาศว่า Container นี้จะรับการเชื่อมต่อบน Port 80 (Port Default ของ HTTP)
# นี่ไม่ใช่การ Map Port จริงๆ แต่เป็นการบอกให้ Docker ทราบว่า Port นี้มีความสำคัญ
EXPOSE 80

# คำสั่งเริ่มต้นเมื่อ Container ถูกรัน (Apache จะถูกรันอัตโนมัติจาก Image พื้นฐาน)
# ไม่จำเป็นต้องเพิ่ม CMD ในกรณีนี้เพราะ Image พื้นฐาน php:8.1-apache มี CMD default อยู่แล้ว