# syntax=docker/dockerfile:1

# ---- 1. PHP-зависимости (без dev) ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts --ignore-platform-reqs

# ---- 2. Сборка фронтенда (Vue) ----
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY --from=vendor /app/vendor ./vendor
COPY resources ./resources
COPY public ./public
COPY vite.config.js postcss.config.js tailwind.config.js jsconfig.json ./
RUN npm run build

# ---- 3. PHP-FPM с приложением ----
FROM php:8.3-fpm-alpine AS app
RUN apk add --no-cache libzip-dev icu-dev oniguruma-dev libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip intl bcmath exif opcache
COPY docker/prod/php.ini /usr/local/etc/php/conf.d/zz-app.ini
WORKDIR /var/www
COPY --from=vendor /app /var/www
COPY --from=assets /app/public/build /var/www/public/build
RUN rm -f public/hot \
    && ln -sfn /var/www/storage/app/public /var/www/public/storage \
    && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
COPY docker/prod/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# ---- 4. Caddy: HTTPS + статические файлы ----
FROM caddy:2-alpine AS web
COPY docker/prod/Caddyfile /etc/caddy/Caddyfile
COPY --from=app /var/www/public /var/www/public
