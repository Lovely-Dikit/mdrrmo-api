
FROM php:8.2-apache

RUN docker-php-ext-install fileinfo

RUN a2enmod rewrite

RUN sed -i 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf \
    && sed -i 's/:80>/:10000>/' /etc/apache2/sites-available/000-default.conf

RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

COPY index.php /var/www/html/index.php
COPY .htaccess /var/www/html/.htaccess

RUN mkdir -p /var/www/html/storage /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html

EXPOSE 10000
