# ใช้ PHP 7.4 + Apache — ตรงกับ prod (โฮสให้สูงสุด 7.4) เพื่อ dev/prod parity
# ถ้าโฮสอัพเป็น 8.x เมื่อไหร่ ค่อยขยับตาม พร้อมกับ php-version ใน deploy.yml
FROM php:7.4-apache

# ติดตั้ง GD สำหรับ image processing (JPEG, PNG, WebP)
RUN apt-get update && apt-get install -y \
    libgd-dev libwebp-dev libjpeg62-turbo-dev libpng-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-webp --with-jpeg --with-freetype \
    && docker-php-ext-install gd

# ติดตั้ง extensions ที่จำเป็นสำหรับการเชื่อมต่อ MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli && docker-php-ext-enable pdo_mysql

# เปิด modules ที่จำเป็น และตั้ง AllowOverride All เพื่อให้ .htaccess ทำงานได้
RUN a2enmod rewrite headers expires
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