FROM php:8.2-apache

# ติดตั้ง extension ที่จำเป็น
RUN docker-php-ext-install pdo pdo_mysql

# เปิด mod_rewrite
RUN a2enmod rewrite

# Copy โค้ดทั้งหมด
COPY . /var/www/html/

# Copy Apache config
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

# ตั้ง permission
RUN chown -R www-data:www-data /var/www/html

# รัน start.sh ของคุณ
CMD ["bash", "start.sh"]
