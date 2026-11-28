#!/usr/bin/env bash
# Server protection, run once as root (safe to run again):
#
#   bash deploy/harden.sh
#
# - Firewall: only SSH, HTTP and HTTPS are reachable from outside.
# - fail2ban: addresses that keep failing SSH logins, or hammer the sign-in
#   pages past nginx's limit, are blocked for an hour.
# - nginx: hides its version and limits sign-in/sign-up requests per address.
# - Security updates for the system install themselves automatically.
set -euo pipefail
APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
export DEBIAN_FRONTEND=noninteractive

apt-get update
apt-get install -y ufw fail2ban unattended-upgrades

# Firewall (SSH first, so this session is never locked out).
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

# nginx: no version in error pages; a per-address budget for the sign-in endpoints.
cat > /etc/nginx/conf.d/sn-tasks-security.conf <<'NGINX'
server_tokens off;
limit_req_zone $binary_remote_addr zone=sn_auth:10m rate=20r/m;
NGINX
for SITE in /etc/nginx/sites-available/*; do
    if grep -q "$APP_DIR" "$SITE" && ! grep -q 'zone=sn_auth' "$SITE"; then
        # Applies to /api/auth/* and /auth/google*; other requests are untouched.
        sed -i "0,/location \/ {/s//location ~ ^\/(api\/)?auth\/ {\n        limit_req zone=sn_auth burst=10 nodelay;\n        try_files \$uri \/index.php?\$query_string;\n    }\n\n    location \/ {/" "$SITE"
    fi
done
nginx -t && systemctl reload nginx

# fail2ban: SSH guessing, and addresses nginx keeps refusing for going over the sign-in limit.
cat > /etc/fail2ban/jail.d/sn-tasks.conf <<'JAIL'
[DEFAULT]
bantime = 1h
findtime = 10m
maxretry = 6

[sshd]
enabled = true

[nginx-limit-req]
enabled = true
port = http,https
logpath = /var/log/nginx/error.log
JAIL
systemctl enable --now fail2ban
systemctl restart fail2ban

# Automatic security updates.
dpkg-reconfigure -f noninteractive unattended-upgrades

echo "Done: firewall on (SSH, HTTP, HTTPS), fail2ban watching SSH and sign-in floods, nginx limits sign-in requests, security updates automatic."
