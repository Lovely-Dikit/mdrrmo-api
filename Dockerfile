
FROM php:8.2-apache

RUN docker-php-ext-install fileinfo

RUN a2enmod rewrite

COPY index.php /var/www/html/index.php

RUN mkdir -p /var/www/html/storage /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80
