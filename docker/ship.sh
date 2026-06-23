#!/bin/bash
# ============================================
# ship.sh — deploy LOCAL do PopRua (sem GitHub).
#
# Servidor = fonte da verdade. Sem remoto, sem branch paralela.
# Faz, em UMA passada: commit do que estiver pendente -> build Vite ->
# caches (view/config/route) -> smoke-test.
#
# Uso (no host vlcp-sufis01, como root):
#   sudo bash <APP_DIR>/docker/ship.sh ["mensagem do commit"]
#
# Serve para poprua-cras E poprua-geo (deriva tudo do APP_DIR).
# ============================================
set -uo pipefail

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
APP_NAME="$(basename "$APP_DIR")"          # poprua-cras | poprua-geo
PHPC="php84-$APP_NAME"
GIT="git -C $APP_DIR -c safe.directory=*"
ART="docker exec -u www-data $PHPC php $APP_DIR/artisan"

echo "== ship $APP_NAME =="

# 1) commit do que estiver pendente (nunca deixa WIP solto em prod)
if [ -n "$($GIT status --porcelain --untracked-files=no)" ]; then
  $GIT add -A
  $GIT commit -q -m "${1:-wip: $(date +%F_%H%M)}"
  echo "  commit $($GIT rev-parse --short HEAD)  \"${1:-wip}\""
else
  echo "  nada a commitar"
fi

# 2) build Vite (container node descartavel — a imagem PHP nao traz node)
if [ -d "$APP_DIR/resources/js" ] || [ -d "$APP_DIR/resources/css" ]; then
  echo "  build Vite..."
  docker run --rm -v "$APP_DIR:/app" -w /app node:22-alpine \
    sh -c "npm ci --silent 2>/dev/null || npm install --silent; npm run build" 2>&1 | tail -4
  chown -R www-data:www-data "$APP_DIR/public/build" 2>/dev/null || true
fi

# 3) caches
$ART view:clear  >/dev/null && \
$ART config:cache >/dev/null && \
$ART route:cache  >/dev/null && echo "  caches ok"

# 4) smoke-test (roda como root via sudo bash -> docker exec funciona)
echo "  smoke-test:"
bash "$APP_DIR/docker/smoke-test.sh"
