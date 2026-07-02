#!/usr/bin/env bash
# Pre-flight do quality-audit — POPRUA CRAS
#
# USO: source este script no orquestrador para que as variaveis (RUNTIME, EXEC,
# DB_EXEC, PROJECT_ROOT, GEO_CRAS_TABLES, etc.) persistam no shell:
#
#   source .claude/skills/quality-audit/scripts/preflight.sh
#
# Ele detecta host vs container, resolve as variaveis conforme o runtime e roda
# os 9 checks criticos (echo OK/FAIL/WARN). O orquestrador interpreta a saida
# conforme a "Matriz de severidade" da SKILL.md e grava env-report.json.

# --- Detector: local (dev nativo) vs host (servidor prod) vs container (inline — TBL-023) ---
PROJECT_ROOT_LOCAL="/data/projects/poprua-cras"
RUNTIME="host"
if [ -f /.dockerenv ] || grep -qa 'docker\|containerd' /proc/1/cgroup 2>/dev/null; then
  if [ "$(hostname)" = "php84-poprua-cras" ] || { [ -d /var/www/html/ ] && php -v 2>/dev/null | grep -q "PHP 8\.4"; }; then
    RUNTIME="container"
  fi
elif [ -f "$PROJECT_ROOT_LOCAL/artisan" ] && php -v 2>/dev/null | grep -q "PHP 8\.4"; then
  # Maquina de dev local: PHP/PG/Redis nativos, PG18 na porta 5433 (ver CLAUDE.md)
  RUNTIME="local"
fi

# --- Resolver variaveis conforme runtime ---
PROJECT_ROOT_HOST="/var/www/html/joomla_sufis/ginfi/poprua-cras"

if [ "$RUNTIME" = "container" ]; then
  # Dentro do container o codigo eh bind-mounted no MESMO path do host (validado).
  PROJECT_ROOT="$PROJECT_ROOT_HOST"
  EXEC=""                                                              # comandos diretos
  DB_EXEC="psql -h pg17-poprua-cras -U poprua_cras"                    # via rede docker
  IN_CONTAINER=1
elif [ "$RUNTIME" = "local" ]; then
  PROJECT_ROOT="$PROJECT_ROOT_LOCAL"
  EXEC=""                                                              # comandos diretos
  DB_EXEC="env PGPASSWORD=poprua_cras psql -h 127.0.0.1 -p 5433 -U poprua_cras"
  IN_CONTAINER=0
else
  PROJECT_ROOT="$PROJECT_ROOT_HOST"
  # IMPORTANTE: WorkingDir padrao do container e /var/www/html (onde NAO esta o codigo).
  # Por isso precisamos -w para que `vendor/bin/pint` etc. resolvam corretamente. (TBL-021)
  EXEC="sudo docker exec -u root -w $PROJECT_ROOT_HOST php84-poprua-cras"
  DB_EXEC="sudo docker exec -u postgres pg17-poprua-cras psql -U poprua_cras"
  IN_CONTAINER=0
fi

# Whitelist de tabelas geometricas do dominio CRAS (ver TBL-022 — Tiger census ruido)
GEO_CRAS_TABLES="('pontos','endereco_atualizados','geo_bairros','geo_regionais','geo_limite_municipio')"

SSH_PROD="ssh sufis-poprua-cras"
AUDITS_DIR="$PROJECT_ROOT/.claude/audits"
INCIDENTS_LOG="$AUDITS_DIR/audit-incidents.log"

# Nota: com RUNTIME=container, "$EXEC php ..." vira " php ..." (espaco inicial, valido).
# Usar sempre como `$EXEC php ...`, nunca `${EXEC}php ...`.

# --- Checks criticos ---
echo "RUNTIME=$RUNTIME"

# 1. Codigo presente
test -f "$PROJECT_ROOT/artisan" && echo "OK artisan" || echo "FAIL artisan"

# 2. PHP responde
$EXEC php -v 2>&1 | grep -q "PHP 8" && echo "OK php" || echo "FAIL php"

# 3. Composer dependencies
$EXEC test -d "$PROJECT_ROOT/vendor" 2>/dev/null || test -d "$PROJECT_ROOT/vendor"
[ $? -eq 0 ] && echo "OK vendor" || echo "FAIL vendor"

# 4. .env presente
$EXEC test -f "$PROJECT_ROOT/.env" 2>/dev/null || test -f "$PROJECT_ROOT/.env"
[ $? -eq 0 ] && echo "OK env" || echo "FAIL env"

# 5. Conectividade DB (dev)
$DB_EXEC -d poprua_cras -tAc "SELECT 1" 2>/dev/null | grep -q "^1$" \
  && echo "OK db" || echo "FAIL db"

# 6. DB de teste
$DB_EXEC -d poprua_cras_test -tAc "SELECT 1" 2>/dev/null | grep -q "^1$" \
  && echo "OK db-test" || echo "WARN db-test"

# 7. PostGIS habilitado
$DB_EXEC -d poprua_cras -tAc "SELECT 1 FROM pg_extension WHERE extname='postgis'" 2>/dev/null \
  | grep -q "^1$" && echo "OK postgis" || echo "FAIL postgis"

# 8. node_modules (D6 — degrada, nao aborta)
test -d "$PROJECT_ROOT/node_modules" && echo "OK node" || echo "WARN node"

# 9. SSH prod (D3/D7 — degrada, nao aborta)
timeout 5 $SSH_PROD "echo ok" 2>/dev/null | grep -q ok \
  && echo "OK ssh-prod" || echo "WARN ssh-prod"
