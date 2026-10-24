FROM composer:2 AS vendor
RUN apk add --no-cache icu-dev \
    && docker-php-ext-install intl
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

FROM php:8.4-fpm-bookworm
RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libpq-dev \
    && docker-php-ext-install intl pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .
RUN php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache
EXPOSE 9000
CMD ["php-fpm"]
