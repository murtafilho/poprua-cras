#!/bin/bash
# ============================================
# Rebuild dos containers POPRUA CRAS
#
# Uso: sudo bash docker/rebuild.sh   (no host vlcp-sufis01)
#
# Historico:
#   <= 2026-05-19: gerava /opt/docker/poprua-cras/docker-compose.yml.
#   >= 2026-05-20: docker-compose.yml do PROJETO e a fonte da verdade
#                  (ADR-006). Este script e um wrapper fino.
# ============================================

set -e

APP_DIR="/var/www/html/joomla_sufis/ginfi/poprua-cras"
CONTAINER="php84-poprua-cras"

cd "$APP_DIR"

echo "=== POPRUA CRAS — Rebuild ==="
echo ""

echo "[1/3] Estado atual:"
docker ps --filter "name=poprua-cras" --format "  {{.Names}} -> {{.Status}}" || true
echo ""

echo "[2/3] Build + up (init-perms sobe primeiro, fixa perms de storage/ e limpa cache stale)..."
docker compose up -d --build

echo ""
echo "[3/3] composer install no app..."
docker exec "${CONTAINER}" composer install --no-interaction --quiet 2>/dev/null \
  || echo "  (composer install falhou ou nao necessario)"

echo ""
echo "=== Rebuild concluido ==="
echo ""
docker ps --filter "name=poprua-cras" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
echo ""
echo "Comandos uteis:"
echo "  ssh sufis-poprua-cras                                  # entra via SSH sidecar (porta 2226)"
echo "  docker exec ${CONTAINER} php artisan migrate --force   # apos primeira subida"
echo "  docker exec ${CONTAINER} npm run build                 # build do frontend"
echo "  docker exec ${CONTAINER} php artisan test              # rodar testes"
