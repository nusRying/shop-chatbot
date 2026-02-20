FROM php:8.1-apache-bookworm

RUN sed -i 's/Components: main/Components: main non-free non-free-firmware/g' /etc/apt/sources.list.d/debian.sources

RUN apt-get update && apt-get install -y --no-install-recommends \
    libzip-dev zip unzip git curl \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev libxml2-dev libicu-dev \
    libc-client2007e-dev libkrb5-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install pdo_mysql mysqli mbstring exif pcntl bcmath gd intl zip imap

RUN a2enmod rewrite headers expires
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# 6. Apply config and ENABLE it
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/php.ini
RUN a2ensite 000-default.conf

WORKDIR /var/www/html

# 7. Use the correct folder name from your repo
COPY perfex_crm/ . 

RUN chown -R www-data:www-data /var/www/html
EXPOSE 80
