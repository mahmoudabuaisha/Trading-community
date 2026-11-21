#!/usr/bin/env bash
# First-time installation on a fresh Ubuntu 22.04/24.04 server (run as root).
#
#   git clone https://github.com/mahmoudabuaisha/Trading-community.git /var/www/trading-community
#   cd /var/www/trading-community
#   ADMIN_EMAIL=you@example.com ADMIN_PASSWORD='a-strong-password' ADMIN_NAME='Your Name' \
#     SITE_HOST=173.212.249.190 bash deploy/install.sh
#
# SITE_HOST is the domain or IP the site is served on. Secrets are passed as
# environment variables and written only to .env on the server — never commit them.
set -euo pipefail

: "${ADMIN_EMAIL:?Set ADMIN_EMAIL}"
: "${ADMIN_PASSWORD:?Set ADMIN_PASSWORD}"
ADMIN_NAME="${ADMIN_NAME:-Admin}"
SITE_HOST="${SITE_HOST:?Set SITE_HOST to the domain or server IP}"
APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
DB_NAME=sn_command_center
DB_USER=sn_app
PHP_VERSION=8.3

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y software-properties-common curl unzip git ca-certificates
if ! apt-cache show "php${PHP_VERSION}-fpm" >/dev/null 2>&1; then
  add-apt-repository -y ppa:ondrej/php
  apt-get update
fi
apt-get install -y nginx mysql-server \
  "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-cli" "php${PHP_VERSION}-mysql" "php${PHP_VERSION}-mbstring" \
  "php${PHP_VERSION}-xml" "php${PHP_VERSION}-curl" "php${PHP_VERSION}-zip" "php${PHP_VERSION}-intl" "php${PHP_VERSION}-bcmath"

if ! command -v composer >/dev/null; then
  curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi
if ! command -v node >/dev/null || [ "$(node -p 'process.versions.node.split(".")[0]')" -lt 20 ]; then
  curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
  apt-get install -y nodejs
fi

cd "$APP_DIR"
if [ ! -f .env ]; then
  DB_PASSWORD="$(openssl rand -hex 24)"
  mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';"
  mysql -e "ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';"
  mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;"

  cp .env.example .env
  set_env() { # set_env KEY VALUE — replaces the line, quoting the value
    local escaped
    escaped="$(printf '%s' "$2" | sed -e 's/[\\&|]/\\&/g')"
    sed -i "s|^$1=.*|$1=\"${escaped}\"|" .env
  }
  set_env APP_ENV production
  set_env APP_DEBUG false
  set_env APP_URL "http://${SITE_HOST}"
  set_env LOG_LEVEL warning
  set_env DB_PASSWORD "$DB_PASSWORD"
  set_env ADMIN_NAME "$ADMIN_NAME"
  set_env ADMIN_EMAIL "$ADMIN_EMAIL"
  set_env ADMIN_PASSWORD "$ADMIN_PASSWORD"
  chmod 640 .env
fi

export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data "$APP_DIR"
chmod -R ug+rwX storage bootstrap/cache

sed -e "s|__SITE_HOST__|${SITE_HOST}|g" -e "s|__APP_DIR__|${APP_DIR}|g" -e "s|__PHP_VERSION__|${PHP_VERSION}|g" \
  deploy/nginx.conf > /etc/nginx/sites-available/trading-community
ln -sf /etc/nginx/sites-available/trading-community /etc/nginx/sites-enabled/trading-community
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable --now "php${PHP_VERSION}-fpm" mysql
# nginx may be installed but stopped (e.g. another service held port 80 at install time).
systemctl enable nginx
systemctl reload-or-restart nginx

echo
echo "Installed. Open http://${SITE_HOST} and sign in with ${ADMIN_EMAIL}."
echo "For HTTPS with a domain: apt-get install -y certbot python3-certbot-nginx && certbot --nginx -d your-domain"
echo "then set APP_URL=https://your-domain and SESSION_SECURE_COOKIE=true in .env and run: php artisan config:cache"
