#!/bin/sh

set -e

cp -R /opt/skylogs/. /var/www/

if [ ! -f /var/www/.env ]; then
    cp /var/www/.env.example /var/www/.env
fi

chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

php /var/www/artisan migrate --force

php /var/www/artisan config:clear
php /var/www/artisan optimize:clear
php /var/www/artisan config:cache
php /var/www/artisan route:cache
php /var/www/artisan l5-swagger:generate

exec php-fpm
