#!/usr/bin/env bash
set -euo pipefail

APP=/var/www/clients/installment-back.ammarelgndy.cloud
ENV_BACKUP_DIR="${HOME}/.deploy-backups"
ENV_BACKUP="${ENV_BACKUP_DIR}/installment-back.env.bak"

mkdir -p "$ENV_BACKUP_DIR"

cd "$APP"
if [ -f .env ]; then
  cp .env "$ENV_BACKUP"
fi

git fetch origin
git reset --hard origin/main

if [ -f "$ENV_BACKUP" ]; then
  cp "$ENV_BACKUP" .env
fi

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
