FROM php:8.1-apache-bullseye

# System packages
RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip git curl \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev libxml2-dev libicu-dev \
    libc-client-dev libkrb5-dev

# PHP extensions required by Perfex CRM
# Batch 1: Standard/Fast extensions
RUN docker-php-ext-install pdo_mysql mysqli mbstring exif pcntl bcmath zip

# Batch 2: Graphics (GD)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd

# Batch 3: Internationalization (Intl)
RUN docker-php-ext-install intl

# Batch 4: Mail (IMAP)
RUN docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install imap

# Apache modules
RUN a2enmod rewrite headers expires
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Configs
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/php.ini

# IMPORTANT — copy the real folder
WORKDIR /var/www/html
COPY perfex_crm/ /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80