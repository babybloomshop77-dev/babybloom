#!/bin/sh
set -e
cd /var/www

# Тома монтируются пустыми — создаём нужные папки и права
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache

if [ "$1" = "php-fpm" ]; then
    art() { su -s /bin/sh www-data -c "php artisan $*"; }
    art config:cache
    art route:cache
    art view:cache
    # Новые миграции применяются при каждом запуске (отключить: RUN_MIGRATIONS=false)
    if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
        art migrate --force
    fi
fi

exec "$@"
