# ใช้ PHP 8.1 + Apache เป็นเบส
FROM php:8.1-apache

# ติดตั้ง extensions ที่จำเป็นสำหรับการเชื่อมต่อ MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli && docker-php-ext-enable pdo_mysql

# เปิด mod_rewrite และตั้ง AllowOverride All เพื่อให้ .htaccess ทำงานได้
RUN a2enmod rewrite
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# ติดตั้ง Git, Unzip และ Composer (เครื่องมือจัดการ library ของ PHP)
RUN apt-get update && apt-get install -y git unzip
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ตั้งค่า Working Directory
WORKDIR /var/www/html

# Copy ไฟล์ composer แล้วรัน install เพื่อโหลด library ต่างๆ
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader

# Copy โค้ดโปรเจกต์ทั้งหมดที่เหลือเข้ามา
COPY . .

# เปลี่ยนเจ้าของไฟล์ให้ Apache
RUN chown -R www-data:www-data /var/www/html