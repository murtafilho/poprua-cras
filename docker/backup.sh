#!/bin/bash
# Backup do banco PostgreSQL do POPRUA CRAS
# Uso: ./docker/backup.sh [--local]
# Sem args: roda via docker exec no servidor
# --local: roda localmente (dev)

set -euo pipefail

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="${BACKUP_DIR:-/opt/docker/poprua-cras/backups}"

if [ "${1:-}" = "--local" ]; then
    PGPASSWORD=poprua_cras pg_dump -U poprua_cras -h 127.0.0.1 -d poprua_cras \
        --format=custom --compress=9 \
        -f "${BACKUP_DIR}/poprua_cras_${TIMESTAMP}.dump"
else
    sudo docker exec pg17-poprua-cras \
        pg_dump -U poprua_cras -d poprua_cras \
        --format=custom --compress=9 \
        -f "/var/backups/poprua_cras_${TIMESTAMP}.dump"
fi

# Manter apenas últimos 7 backups
ls -t "${BACKUP_DIR}"/poprua_cras_*.dump 2>/dev/null | tail -n +8 | xargs rm -f 2>/dev/null || true

echo "Backup salvo: poprua_cras_${TIMESTAMP}.dump"
echo "Total backups: $(ls "${BACKUP_DIR}"/poprua_cras_*.dump 2>/dev/null | wc -l)"
