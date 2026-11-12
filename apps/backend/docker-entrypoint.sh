#!/bin/sh

set -e

# One image, three roles. The role comes from the container command, then
# DEPLOY_TYPE, and defaults to web. Anything else is run as given.
role="${1:-${DEPLOY_TYPE:-web}}"

case "$role" in
    horizon)
        cd /opt/skylogs-api
        echo "Starting Horizon..."
        exec php artisan horizon
        ;;
    crontab)
        cd /opt/skylogs-api
        # cron starts schedule:run with an empty environment, so this
        # container's environment has to be baked into the config cache.
        echo "Caching config for the scheduler..."
        php artisan config:cache
        echo "Starting cron..."
        exec cron -f
        ;;
    web)
        ;;
    *)
        if [ "$#" -eq 0 ]; then
            echo "Unknown DEPLOY_TYPE: $role (expected web, horizon or crontab)" >&2
            exit 1
        fi
        exec "$@"
        ;;
esac

cp -R /opt/skylogs-api/. /var/www/html/

if [ ! -f /var/www/html/.env ]; then
    cp /var/www/html/.env.example /var/www/html/.env
fi

chown -R www-data:www-data \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache

cd /var/www/html

if [ -z "${APP_KEY:-}" ]; then
    echo "Generating application key..."
    php artisan key:generate --force --no-interaction
fi

echo "Clearing caches..."
php artisan optimize:clear

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
