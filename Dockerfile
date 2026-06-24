# Document Tracking System — cloud deploy (Render, Railway, Fly.io, etc.)
FROM php:8.2-apache

RUN a2enmod rewrite headers \
    && docker-php-ext-install mbstring

WORKDIR /var/www/html

COPY . /var/www/html/
COPY php-uploads.ini /usr/local/etc/php/conf.d/uploads.ini

RUN mkdir -p storage/Data data \
    && chown -R www-data:www-data /var/www/html/data /var/www/html/storage \
    && chmod -R 775 /var/www/html/data /var/www/html/storage

EXPOSE 80
