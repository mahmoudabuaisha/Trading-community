#!/usr/bin/env bash
# Move the site from the server's IP address to a domain name, with HTTPS.
# Run as root once the domain's DNS points at this server:
#
#   DOMAIN=sntasks.com EMAIL=you@example.com bash deploy/domain.sh
#
# DOMAIN is the bare domain (www.DOMAIN is added too). EMAIL is where Let's
# Encrypt sends certificate expiry notices. The certificate renews itself.
set -euo pipefail

: "${DOMAIN:?Set DOMAIN, e.g. DOMAIN=sntasks.com}"
: "${EMAIL:?Set EMAIL for certificate notices}"
APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
SITE=/etc/nginx/sites-available/trading-community
cd "$APP_DIR"

SERVER_IP="$(curl -fsS https://api.ipify.org || true)"
DOMAIN_IP="$(getent ahostsv4 "$DOMAIN" | awk '{print $1; exit}' || true)"
if [ -n "$SERVER_IP" ] && [ "$DOMAIN_IP" != "$SERVER_IP" ]; then
    echo "The domain $DOMAIN points to '${DOMAIN_IP:-nothing}', not to this server ($SERVER_IP)."
    echo "Add an A record for $DOMAIN (and www) with the value $SERVER_IP at your domain provider, wait a few minutes, then run this again."
    exit 1
fi

# nginx answers on the domain (and the IP keeps working until HTTPS redirects it).
sed -i -E "s|^(\s*server_name)\s+[^;]*;|\1 ${DOMAIN} www.${DOMAIN};|" "$SITE"
nginx -t && systemctl reload nginx

apt-get install -y certbot python3-certbot-nginx
certbot --nginx --non-interactive --agree-tos --redirect -m "$EMAIL" -d "$DOMAIN" -d "www.${DOMAIN}"

set_env() { # set_env KEY VALUE: replaces the line, or adds it
    local escaped
    escaped="$(printf '%s' "$2" | sed -e 's/[\\&|]/\\&/g')"
    if grep -q "^$1=" .env; then sed -i "s|^$1=.*|$1=\"${escaped}\"|" .env; else echo "$1=\"$2\"" >> .env; fi
}
set_env APP_URL "https://${DOMAIN}"
set_env SESSION_SECURE_COOKIE true
set_env SANCTUM_STATEFUL_DOMAINS "${DOMAIN},www.${DOMAIN}"
php artisan config:cache

echo "Done. Open https://${DOMAIN} — members sign in again once, since cookies now belong to the domain."
