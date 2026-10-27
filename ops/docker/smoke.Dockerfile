FROM php:8.4-apache

RUN docker-php-ext-install mysqli \
    && a2enmod rewrite \
    && sed -ri \
        's!/var/www/html!/var/www/public!g' \
        /etc/apache2/sites-available/*.conf \
        /etc/apache2/apache2.conf \
        /etc/apache2/conf-available/*.conf \
