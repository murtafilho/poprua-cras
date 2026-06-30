#!/bin/bash
# ============================================
# Instala cron para deploy automatico (poll a cada 3 minutos).
#
# Uso no host vlcp-sufis01 (como root):
#   sudo bash /var/www/html/joomla_sufis/ginfi/poprua-cras/docker/install-auto-deploy-cron.sh
# ============================================
set -euo pipefail

APP_DIR="/var/www/html/joomla_sufis/ginfi/poprua-cras"
CRON_FILE="/etc/cron.d/poprua-cras-auto-deploy"

chmod +x "$APP_DIR/docker/poll-deploy.sh" "$APP_DIR/docker/deploy.sh"

cat > "$CRON_FILE" <<EOF
# PopRua CRAS — deploy automatico apos push no GitHub (poll origin/main)
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin
*/3 * * * * root $APP_DIR/docker/poll-deploy.sh
EOF

chmod 644 "$CRON_FILE"
touch /var/log/poprua-cras-poll-deploy.log
chmod 644 /var/log/poprua-cras-poll-deploy.log

echo "Cron instalado: $CRON_FILE (a cada 3 minutos)"
echo "Log: /var/log/poprua-cras-poll-deploy.log"
echo "Teste manual: sudo bash $APP_DIR/docker/poll-deploy.sh"
