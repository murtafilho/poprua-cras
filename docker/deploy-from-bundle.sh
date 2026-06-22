#!/bin/bash
# ============================================
# [OBSOLETO] Bootstrap do PopRua CRAS a partir de bundle local (sem GitHub)
#
# >>> APOSENTADO: o repo foi publicado em git@github.com:murtafilho/poprua-cras.git.
# >>> Para DEPLOY recorrente use:  docker/deploy.sh  (git pull --ff-only).
# >>> Este script so serve para BOOTSTRAP do zero sem GitHub (caso historico).
#
# Executar no host vlcp-sufis01 como root:
#   sudo POSTGRES_PASSWORD='senha-forte' bash deploy-from-bundle.sh
# ============================================

set -e

BUNDLE="/var/www/html/joomla_sufis/ginfi/poprua-geo/_bootstrap/poprua-cras.bundle"
APP_DIR="/var/www/html/joomla_sufis/ginfi/poprua-cras"
COMPOSE_DIR="/opt/docker/poprua-cras"
CONTAINER="php84-poprua-cras"

if [ -z "$POSTGRES_PASSWORD" ]; then
    echo "ERRO: defina POSTGRES_PASSWORD."
    echo "      sudo POSTGRES_PASSWORD='senha-forte' bash deploy-from-bundle.sh"
    exit 1
fi

if [ ! -f "$BUNDLE" ]; then
    echo "ERRO: bundle nao encontrado em $BUNDLE"
    exit 1
fi

echo "=== PopRua CRAS - Deploy from bundle ==="
echo ""

# [1/7] Clonar do bundle
echo "[1/7] Clonando do bundle local..."
if [ -d "$APP_DIR/.git" ]; then
    echo "  Repo ja existe, atualizando via remote bundle..."
    cd "$APP_DIR"
    git remote remove bundle 2>/dev/null || true
    git remote add bundle "$BUNDLE"
    git fetch bundle
    git reset --hard bundle/main
    git remote remove bundle
else
    mkdir -p "$(dirname $APP_DIR)"
    git clone "$BUNDLE" "$APP_DIR"
fi
chown -R www-data:www-data "$APP_DIR"

# [2/7] Compose dir e .env
echo "[2/7] Preparando $COMPOSE_DIR..."
mkdir -p "$COMPOSE_DIR"
if [ ! -f "$COMPOSE_DIR/.env" ]; then
    cat > "$COMPOSE_DIR/.env" <<EOF
POSTGRES_PASSWORD=$POSTGRES_PASSWORD
EOF
    chmod 600 "$COMPOSE_DIR/.env"
else
    echo "  Ja existe, mantendo."
fi

# [3/7] Subir stack (gera compose final + build)
echo "[3/7] Subindo stack via rebuild.sh..."
bash "$APP_DIR/docker/rebuild.sh"

# [4/7] .env do Laravel
echo "[4/7] Criando .env do Laravel..."
if [ ! -f "$APP_DIR/.env" ]; then
    sudo -u www-data cp "$APP_DIR/.env.example" "$APP_DIR/.env"
    sed -i "s|^APP_ENV=local|APP_ENV=production|" "$APP_DIR/.env"
    sed -i "s|^APP_DEBUG=true|APP_DEBUG=false|" "$APP_DIR/.env"
    sed -i "s|^APP_URL=http://localhost|APP_URL=https://sufis.pbh.gov.br/ginfi/poprua-cras/public|" "$APP_DIR/.env"
    sed -i "s|^DB_PASSWORD=|DB_PASSWORD=$POSTGRES_PASSWORD|" "$APP_DIR/.env"
    sed -i "s|^LOG_LEVEL=debug|LOG_LEVEL=error|" "$APP_DIR/.env"
else
    echo "  Ja existe, mantendo."
fi

# [5/7] Composer install + npm build + migrate
echo "[5/7] Instalando dependencias e migrando..."
sleep 5  # da tempo do FPM subir
docker exec -u www-data "$CONTAINER" composer install --no-interaction --no-dev --optimize-autoloader --working-dir=/var/www/html/joomla_sufis/ginfi/poprua-cras

# Build de assets Vite via container Node descartavel (serversideup nao tem node)
echo "  npm install + build (container Node descartavel)..."
docker run --rm -v "$APP_DIR:/app" -w /app node:22-alpine \
    sh -c "npm install --silent && npm run build" 2>&1 | tail -5

docker exec -u www-data "$CONTAINER" php /var/www/html/joomla_sufis/ginfi/poprua-cras/artisan key:generate --no-interaction --force
docker exec -u www-data "$CONTAINER" php /var/www/html/joomla_sufis/ginfi/poprua-cras/artisan migrate --no-interaction --force
docker exec -u www-data "$CONTAINER" php /var/www/html/joomla_sufis/ginfi/poprua-cras/artisan config:cache
docker exec -u www-data "$CONTAINER" php /var/www/html/joomla_sufis/ginfi/poprua-cras/artisan route:cache

# Restart do queue: ele subiu antes do composer install e ficou em loop
docker restart queue-poprua-cras > /dev/null 2>&1 || true

# [6/7] Apache vhost
echo "[6/7] Configurando Apache vhost..."
cp "$APP_DIR/docker/apache-vhost.conf" /etc/apache2/conf-available/php84-poprua-cras.conf
a2enconf php84-poprua-cras
apache2ctl configtest && systemctl reload apache2

# [7/7] Smoke test
echo "[7/7] Smoke test..."
echo ""
docker ps --filter "name=poprua-cras" --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
echo ""
echo "Resposta HTTP:"
curl -sI "https://sufis.pbh.gov.br/ginfi/poprua-cras/public/" | head -3 || true

echo ""
echo "=== Deploy concluido ==="
echo ""
echo "SSH no container: ssh sufis-poprua-cras (porta 2226)"
echo "Logs:             $APP_DIR/storage/logs/laravel.log"
echo ""
echo "Para criar usuario admin:"
echo "  docker exec -u www-data $CONTAINER php /var/www/html/joomla_sufis/ginfi/poprua-cras/artisan tinker"
