#!/bin/sh

set -e

echo "Waiting for application volume at /var/www/html..."
i=0
while [ ! -f /var/www/html/artisan ] || [ ! -d /var/www/html/vendor ]; do
    i=$((i + 1))
    if [ "$i" -gt 90 ]; then
        echo "Timed out waiting for /var/www/html (back must populate app_data first)"
        exit 1
    fi
    sleep 2
done

echo "Starting cron..."
crond

cd /var/www/html
echo "Starting Horizon..."
exec php artisan horizon
