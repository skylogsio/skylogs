#!/bin/sh

set -e

cp -R /opt/skylogs-api/. /var/www/html/

if [ ! -f /var/www/html/.env ]; then
    cp /var/www/html/.env.example /var/www/html/.env
fi

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

php /var/www/html/artisan migrate --force

php /var/www/html/artisan config:clear
php /var/www/html/artisan optimize:clear
php /var/www/html/artisan config:cache
php /var/www/html/artisan route:cache
php /var/www/html/artisan l5-swagger:generate

exec php-fpm
