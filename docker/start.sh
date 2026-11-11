#!/bin/sh
set -eu
: "${APP_KEY:?Set a persistent Laravel APP_KEY in Render environment settings}"
PORT="${PORT:-10000}"
case "$PORT" in *[!0-9]*|'') echo 'Invalid PORT' >&2; exit 1;; esac
sed -i "s/^Listen .*/Listen $PORT/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:10000>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/000-default.conf
php artisan config:cache
php artisan migrate --force
php artisan db:seed --class=DeploymentAdminSeeder --force
php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache
exec apache2-foreground
