#!/bin/bash
# ============================================
# poll-deploy.sh — deploy automatico quando origin/main avanca
#
# Roda no host vlcp-sufis01 (cron ou manual). Faz git fetch; se houver
# commit novo em origin/main, chama deploy.sh.
#
# Uso:
#   sudo bash docker/poll-deploy.sh
#   sudo bash docker/install-auto-deploy-cron.sh   # instala cron (a cada 3 min)
# ============================================
set -uo pipefail

APP_DIR="/var/www/html/joomla_sufis/ginfi/poprua-cras"
BRANCH="main"
LOCK="/var/run/poprua-cras-poll-deploy.lock"
LOG="/var/log/poprua-cras-poll-deploy.log"

DEPLOY_KEY="/root/.ssh/poprua_cras_deploy"
if [ -f "$DEPLOY_KEY" ]; then
    export GIT_SSH_COMMAND="ssh -i $DEPLOY_KEY -o IdentitiesOnly=yes -o StrictHostKeyChecking=no"
fi

GIT="git -C $APP_DIR -c safe.directory=*"
exec 9>"$LOCK"
flock -n 9 || { echo "$(date -Is) poll-deploy: outra instancia rodando" >> "$LOG"; exit 0; }

if ! $GIT fetch --quiet origin "$BRANCH" 2>>"$LOG"; then
    echo "$(date -Is) poll-deploy: fetch falhou" >> "$LOG"
    exit 1
fi

LOCAL=$($GIT rev-parse HEAD)
REMOTE=$($GIT rev-parse "origin/$BRANCH")

if [ "$LOCAL" = "$REMOTE" ]; then
    exit 0
fi

echo "$(date -Is) poll-deploy: $LOCAL -> $REMOTE — iniciando deploy" >> "$LOG"
if bash "$APP_DIR/docker/deploy.sh" >> "$LOG" 2>&1; then
    echo "$(date -Is) poll-deploy: deploy OK ($REMOTE)" >> "$LOG"
else
    echo "$(date -Is) poll-deploy: deploy FALHOU" >> "$LOG"
    exit 1
fi
