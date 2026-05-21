#!/bin/bash
# ============================================
# Bootstrap inicial do PopRua CRAS no vlcp-sufis01
# Executar uma unica vez no host como root: sudo bash bootstrap-host.sh
#
# Pre-requisitos:
#   - Repo Git ja publicado em GitHub (https://github.com/<owner>/poprua-cras)
#   - Variaveis abaixo preenchidas
# ============================================

set -e

# ===== CONFIG =====
REPO_URL="${REPO_URL:-https://github.com/murtafilho/poprua-cras.git}"
APP_DIR="/var/www/html/joomla_sufis/ginfi/poprua-cras"
COMPOSE_DIR="/opt/docker/poprua-cras"
APP_PORT=9086
CONTAINER="php84-poprua-cras"

if [ -z "$POSTGRES_PASSWORD" ]; then
    echo "ERRO: defina POSTGRES_PASSWORD no ambiente."
    echo "      sudo POSTGRES_PASSWORD='senha-forte' bash bootstrap-host.sh"
    exit 1
fi

echo "=== PopRua CRAS - Bootstrap ==="
echo "  Repo:        $REPO_URL"
echo "  App dir:     $APP_DIR"
echo "  Compose dir: $COMPOSE_DIR"
echo "  FPM port:    $APP_PORT"
echo ""

# [1/7] Clonar repositorio
echo "[1/7] Clonando repositorio..."
if [ -d "$APP_DIR/.git" ]; then
    echo "  Ja existe, fazendo pull..."
    cd "$APP_DIR" && git pull --ff-only
else
    mkdir -p "$(dirname $APP_DIR)"
    git clone "$REPO_URL" "$APP_DIR"
fi
chown -R www-data:www-data "$APP_DIR"

# [2/7] Criar .env do compose
echo "[2/7] Criando .env do compose..."
mkdir -p "$COMPOSE_DIR"
if [ ! -f "$COMPOSE_DIR/.env" ]; then
    cat > "$COMPOSE_DIR/.env" <<EOF
POSTGRES_PASSWORD=$POSTGRES_PASSWORD
EOF
    chmod 600 "$COMPOSE_DIR/.env"
else
    echo "  Ja existe, mantendo."
fi

# [3/7] Build da stack (rebuild.sh gera o compose final)
echo "[3/7] Build da stack via rebuild.sh..."
bash "$APP_DIR/docker/rebuild.sh"

# [4/7] .env do Laravel (dentro do bind mount)
echo "[4/7] Criando .env do Laravel..."
if [ ! -f "$APP_DIR/.env" ]; then
    sudo -u www-data cp "$APP_DIR/.env.example" "$APP_DIR/.env"
    # Ajustar credenciais para producao
    sed -i "s|^APP_ENV=local|APP_ENV=production|" "$APP_DIR/.env"
    sed -i "s|^APP_DEBUG=true|APP_DEBUG=false|" "$APP_DIR/.env"
    sed -i "s|^APP_URL=http://localhost|APP_URL=https://sufis.pbh.gov.br/ginfi/poprua-cras/public|" "$APP_DIR/.env"
    sed -i "s|^DB_PASSWORD=|DB_PASSWORD=$POSTGRES_PASSWORD|" "$APP_DIR/.env"
    sed -i "s|^LOG_LEVEL=debug|LOG_LEVEL=error|" "$APP_DIR/.env"
else
    echo "  Ja existe, mantendo."
fi

# [5/7] Composer install + key:generate + migrate
echo "[5/7] Instalando dependencias e migrando..."
docker exec -u www-data "$CONTAINER" composer install --no-interaction --no-dev --optimize-autoloader
docker exec -u www-data "$CONTAINER" php artisan key:generate --no-interaction --force
docker exec -u www-data "$CONTAINER" php artisan migrate --no-interaction --force
docker exec -u www-data "$CONTAINER" php artisan config:cache
docker exec -u www-data "$CONTAINER" php artisan route:cache

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
echo "=== Bootstrap concluido ==="
echo ""
echo "Proximos passos:"
echo "  1. Criar usuario admin:"
echo "     docker exec -u www-data $CONTAINER php artisan tinker --execute=\"\\\$u=App\\\\Models\\\\User::create(['name'=>'Admin','email'=>'admin@example.com','password'=>bcrypt('CHANGEME')]);Spatie\\\\Permission\\\\Models\\\\Role::firstOrCreate(['name'=>'admin'])->users()->attach(\\\$u);\""
echo "  2. Configurar alias SSH no seu ~/.ssh/config (ssh sufis-poprua-cras na porta 2226)"
echo "  3. Verificar logs:"
echo "     ssh sufis-poprua-cras 'tail -30 $APP_DIR/storage/logs/laravel.log'"
