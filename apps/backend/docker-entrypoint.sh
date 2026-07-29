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

# A node that was down has missed both configuration and replicated state, and
# catching up before it starts serving is worth a few seconds. Configuration goes
# first because reconciliation writes state onto alert rules that a brand new
# follower only receives here. Both are non-fatal: a sidecar that is still
# electing, or a single node install, must not stop the application booting.
echo "Syncing HA configuration..."
php artisan ha:config-sync || true

echo "Syncing HA history and notifies..."
php artisan ha:history-sync || true

echo "Reconciling HA state..."
php artisan ha:reconcile || true

echo "Caching config..."
php artisan config:cache
php artisan route:cache

echo "Generating Swagger..."
php artisan l5-swagger:generate

exec php-fpm
