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
systemctl reload "$(systemctl list-units --type=service --no-legend 'php*-fpm.service' | awk '{print $1; exit}')"
echo "Updated to $(git rev-parse --short HEAD)."
