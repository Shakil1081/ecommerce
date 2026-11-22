#!/usr/bin/env bash
# Run ON THE SERVER over SSH, from the app folder:   bash deploy/cpanel-deploy.sh
# First time:                                       bash deploy/cpanel-deploy.sh --first
#
# Expects: the code pulled from GitHub (main), .env present, and deploy/build-assets.tar.gz
# uploaded from your PC (built with deploy/build-assets.sh).
set -euo pipefail

cd "$(dirname "$0")/.."

FIRST_RUN=false
[ "${1:-}" = "--first" ] && FIRST_RUN=true

# cPanel's default `php` CLI can be an older version: use 8.2+ explicitly when available
PHP_BIN="${PHP_BIN:-php}"
for candidate in /opt/cpanel/ea-php83/root/usr/bin/php /opt/cpanel/ea-php82/root/usr/bin/php; do
    if [ "$PHP_BIN" = "php" ] && [ -x "$candidate" ]; then PHP_BIN="$candidate"; fi
done
"$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' \
    || { echo "PHP 8.2+ required (found $("$PHP_BIN" -r 'echo PHP_VERSION;')). Set PHP_BIN=/path/to/php82"; exit 1; }
echo "Using PHP $("$PHP_BIN" -r 'echo PHP_VERSION;') at $PHP_BIN"

[ -f .env ] || { echo "Missing .env: copy deploy/.env.cpanel.example to .env and fill it in."; exit 1; }

# Composer: use the global one, or download composer.phar once
if command -v composer >/dev/null 2>&1; then
    COMPOSER="composer"
else
    if [ ! -f composer.phar ]; then
        "$PHP_BIN" -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
        "$PHP_BIN" composer-setup.php --quiet
        rm -f composer-setup.php
    fi
    COMPOSER="$PHP_BIN composer.phar"
fi

"$PHP_BIN" artisan down --retry=30 || true

$COMPOSER install --no-dev --optimize-autoloader --no-interaction

# Front-end assets built on your PC
if [ -f deploy/build-assets.tar.gz ]; then
    rm -rf public/build
    tar -xzf deploy/build-assets.tar.gz -C public
    rm -f deploy/build-assets.tar.gz
fi
[ -f public/build/manifest.json ] || { echo "public/build is missing: upload deploy/build-assets.tar.gz first."; "$PHP_BIN" artisan up; exit 1; }
rm -f public/hot

if [ "$FIRST_RUN" = true ] && ! grep -q '^APP_KEY=base64:' .env; then
    "$PHP_BIN" artisan key:generate --force
fi

"$PHP_BIN" artisan migrate --force

if [ "$FIRST_RUN" = true ]; then
    # Admin user, roles, demo products, districts, delivery zones (see DEPLOYMENT.md)
    "$PHP_BIN" artisan db:seed --force
    "$PHP_BIN" artisan storage:link || echo "storage:link failed: create the public/storage link from cPanel File Manager"
else
    "$PHP_BIN" artisan db:seed --class=RolesAndPermissionsSeeder --force
fi

"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

chmod -R ug+rwX storage bootstrap/cache

"$PHP_BIN" artisan up
echo
echo "Deployed. Open https://shop.webspreed.com"
