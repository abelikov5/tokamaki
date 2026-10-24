FROM composer:2 AS web-assets
RUN apk add --no-cache icu-dev \
    && docker-php-ext-install intl
WORKDIR /app
COPY apps/web/composer.json apps/web/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts
COPY apps/web .
RUN php artisan package:discover --ansi \
    && php artisan filament:assets --ansi

FROM nginx:1.27-alpine
COPY infra/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=web-assets /app/public /var/www/html/public
