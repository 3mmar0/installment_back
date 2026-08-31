#!/usr/bin/env bash
set -euo pipefail

BACK_DIR="/var/www/clients/installment-back.ammarelgndy.cloud"
CONF_SRC="${1:-${BACK_DIR}/deploy/supervisor/installment-queue.conf}"
CONF_DEST="/etc/supervisor/conf.d/installment-queue.conf"
CRON_LINE="* * * * * cd ${BACK_DIR} && /usr/bin/php artisan schedule:run >> /dev/null 2>&1"

echo "==> Installing Supervisor program for installment queue"
cp "$CONF_SRC" "$CONF_DEST"
chmod 644 "$CONF_DEST"

echo "==> Ensuring queue log directory exists"
mkdir -p "${BACK_DIR}/storage/logs"
chown deploy:www-data "${BACK_DIR}/storage/logs"

echo "==> Reloading Supervisor"
supervisorctl reread
supervisorctl update
supervisorctl start installment-queue:* || supervisorctl restart installment-queue:*

echo "==> Installing Laravel scheduler cron for deploy user"
CURRENT_CRON="$(crontab -u deploy -l 2>/dev/null || true)"
if echo "$CURRENT_CRON" | grep -Fq "installment-back.ammarelgndy.cloud" && echo "$CURRENT_CRON" | grep -Fq "schedule:run"; then
  echo "Scheduler cron already present"
else
  {
    echo "$CURRENT_CRON" | sed '/^$/d'
    echo "$CRON_LINE"
  } | crontab -u deploy -
  echo "Scheduler cron added"
fi

echo "==> Status"
supervisorctl status installment-queue:* || true
crontab -u deploy -l | grep installment-back || true

echo "Installment queue worker and scheduler setup complete"
