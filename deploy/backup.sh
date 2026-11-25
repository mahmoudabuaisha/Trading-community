#!/usr/bin/env bash
# Daily backup of the database and uploaded files. Keeps the last 14 days.
#
#   Install once (runs every night at 03:15):
#     (crontab -l 2>/dev/null; echo "15 3 * * * bash /var/www/trading-community/deploy/backup.sh >> /var/log/sn-tasks-backup.log 2>&1") | crontab -
#
#   Restore a day:
#     gunzip < /var/backups/sn-tasks/db-2026-11-25.sql.gz | mysql "$DB_DATABASE"
#     tar -xzf /var/backups/sn-tasks/files-2026-11-25.tar.gz -C /var/www/trading-community/storage/app
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/trading-community}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/sn-tasks}"
KEEP_DAYS=14
STAMP="$(date +%F)"
STATUS="$APP_DIR/storage/app/backup-status.json"

# Read the database settings from the app's own .env; nothing is written here.
env_value() { grep -E "^$1=" "$APP_DIR/.env" | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }
DB_DATABASE="$(env_value DB_DATABASE)"
DB_USERNAME="$(env_value DB_USERNAME)"
DB_PASSWORD="$(env_value DB_PASSWORD)"

write_status() { printf '{"lastBackupAt":"%s","ok":%s}\n' "$(date -u +%FT%TZ)" "$1" > "$STATUS"; chown www-data:www-data "$STATUS" 2>/dev/null || true; }
trap 'write_status false' ERR

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
MYSQL_PWD="$DB_PASSWORD" mysqldump --single-transaction --quick -u "$DB_USERNAME" "$DB_DATABASE" | gzip > "$BACKUP_DIR/db-$STAMP.sql.gz"
tar -czf "$BACKUP_DIR/files-$STAMP.tar.gz" -C "$APP_DIR/storage/app" public
find "$BACKUP_DIR" -type f -mtime +"$KEEP_DAYS" -delete

write_status true
echo "$(date -u +%FT%TZ) backup ok: $BACKUP_DIR/db-$STAMP.sql.gz"
