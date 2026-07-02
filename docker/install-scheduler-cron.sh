#!/bin/bash
# ============================================
# Instala cron do scheduler Laravel (backups diarios as 03:00).
#
# Versao versionada do /etc/cron.d/poprua-cras-backup que ja roda no
# servidor: sem este script, um rebuild do host perderia os backups
# diarios silenciosamente (ver routes/console.php — todas as tarefas
# sao dailyAt('03:00') porque o scheduler so e invocado nesse minuto).
#
# Uso no host vlcp-sufis01 (como root):
#   sudo bash /var/www/html/joomla_sufis/ginfi/poprua-cras/docker/install-scheduler-cron.sh
# ============================================
set -euo pipefail

APP_DIR="/var/www/html/joomla_sufis/ginfi/poprua-cras"
CONTAINER="php84-poprua-cras"
CRON_FILE="/etc/cron.d/poprua-cras-backup"
LOG_FILE="/var/log/poprua-cras-scheduler.log"

cat > "$CRON_FILE" <<EOF
# PopRua CRAS — scheduler Laravel (backup:clean/run/monitor, rascunhos:limpar,
# media:clean-orphaned — todas dailyAt 03:00 em routes/console.php)
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin
0 3 * * * root docker exec -u www-data $CONTAINER php $APP_DIR/artisan schedule:run >> $LOG_FILE 2>&1
EOF

chmod 644 "$CRON_FILE"
touch "$LOG_FILE"
chmod 644 "$LOG_FILE"

echo "Cron instalado: $CRON_FILE (diario as 03:00)"
echo "Log: $LOG_FILE"
echo "Teste manual: docker exec -u www-data $CONTAINER php $APP_DIR/artisan schedule:run"
