#!/bin/sh

set -e

cp -R /opt/skylogs-api/. /var/www/html/

if [ ! -f /var/www/html/.env ]; then
    cp /var/www/html/.env.example /var/www/html/.env
fi

chown -R www-data:www-data \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache

echo "Clearing caches..."
php artisan optimize:clear

echo "Waiting for database..."
sleep 5

echo "Running migrations..."
php artisan migrate --force

echo "Seeding database..."
php artisan db:seed --force

echo "Caching config..."
php artisan config:cache
php artisan route:cache

echo "Generating Swagger..."
php artisan l5-swagger:generate

exec php-fpm
