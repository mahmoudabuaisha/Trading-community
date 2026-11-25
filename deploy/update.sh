#!/usr/bin/env bash
# Pull and release the latest code on the server (run as root from the app directory).
#   bash deploy/update.sh [branch]
set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
BRANCH="${1:-$(git -C "$APP_DIR" rev-parse --abbrev-ref HEAD)}"
cd "$APP_DIR"

git config --global --add safe.directory "$APP_DIR"
php artisan down --retry=15 || true
trap 'php artisan up' EXIT

git fetch origin "$BRANCH"
git checkout "$BRANCH"
git merge --ff-only "origin/$BRANCH"

export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data "$APP_DIR"

# Upload limits: store files go up to 20MB. PHP and nginx both cap uploads, so
# raise them together (the nginx site file is only touched if it still says 2m).
PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
printf 'upload_max_filesize = 22M\npost_max_size = 25M\n' > "/etc/php/$PHP_VERSION/fpm/conf.d/99-sn-tasks.ini"
for SITE in /etc/nginx/sites-available/*; do
    if grep -q "$APP_DIR" "$SITE" && grep -q 'client_max_body_size 2m;' "$SITE"; then
        sed -i 's/client_max_body_size 2m;/client_max_body_size 25m;/' "$SITE"
        nginx -t && systemctl reload nginx
    fi
done
systemctl restart "$(systemctl list-units --type=service --no-legend 'php*-fpm.service' | awk '{print $1; exit}')"
echo "Updated to $(git rev-parse --short HEAD)."
