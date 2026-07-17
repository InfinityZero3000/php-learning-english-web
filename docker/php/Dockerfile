FROM composer:2 AS composer

FROM php:8.5-fpm-alpine

RUN apk add --no-cache icu-dev libzip-dev \
    && docker-php-ext-install intl pdo_mysql zip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
