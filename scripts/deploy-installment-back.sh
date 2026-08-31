#!/usr/bin/env bash
set -euo pipefail

APP=/var/www/clients/installment-back.ammarelgndy.cloud
ENV_BACKUP_DIR="${HOME}/.deploy-backups"
ENV_BACKUP="${ENV_BACKUP_DIR}/installment-back.env.bak"
RELEASE_TAR="${1:-/tmp/installment-back-release/release.tar.gz}"
RELEASE_WORK="${RELEASE_TAR%.tar.gz}-extract"

mkdir -p "$ENV_BACKUP_DIR"

if [ ! -f "$RELEASE_TAR" ]; then
  echo "ERROR: release archive not found at $RELEASE_TAR" >&2
  exit 1
fi

# Ensure deploy user can write app files (.env, composer, artisan)
sudo chown -R deploy:www-data "$APP"

if [ -f "$APP/.env" ]; then
  cp "$APP/.env" "$ENV_BACKUP"
elif [ -f "$ENV_BACKUP" ]; then
  : # keep existing backup if .env missing this run
else
  echo "WARNING: no .env found and no backup at $ENV_BACKUP" >&2
fi

rm -rf "$RELEASE_WORK"
mkdir -p "$RELEASE_WORK"
tar -xzf "$RELEASE_TAR" -C "$RELEASE_WORK"

# Sync code from CI artifact; keep server .env, storage, and vendor until composer runs
rsync -a --delete \
  --exclude='.env' \
  --exclude='.env.*' \
  --exclude='storage/' \
  --exclude='vendor/' \
  --exclude='node_modules/' \
  "$RELEASE_WORK/" "$APP/"

if [ -f "$ENV_BACKUP" ]; then
  cp "$ENV_BACKUP" "$APP/.env"
  chmod 640 "$APP/.env"
fi

cd "$APP"

composer install --no-dev --optimize-autoloader --no-interaction

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link || true

sudo chown -R deploy:www-data "$APP"
sudo find "$APP/storage" "$APP/bootstrap/cache" -type d -exec chmod 775 {} \;
sudo setfacl -R -m u:www-data:rwx -m g:www-data:rwx -m d:u:www-data:rwx -m d:g:www-data:rwx \
  "$APP/storage" "$APP/bootstrap/cache" 2>/dev/null || true
sudo systemctl reload php8.4-fpm
sudo nginx -t
sudo systemctl reload nginx

rm -rf "$RELEASE_WORK" "$(dirname "$RELEASE_TAR")"

echo "Deploy completed successfully"
