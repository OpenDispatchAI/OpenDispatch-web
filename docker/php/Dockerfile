FROM php:8.5-fpm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    libsqlite3-dev \
    && docker-php-ext-install \
    pdo_sqlite \
    intl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
