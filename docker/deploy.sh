#!/bin/bash
# ============================================
# Deploy do PopRua CRAS — git pull de origin/main no servidor
#
# Hub: git@github.com:murtafilho/poprua-cras.git
# Fluxo: dev local -> git push -> ESTE script (git pull + build + smoke)
#
# Idempotente: so roda composer/npm/migrate quando os arquivos relevantes
# mudaram entre o HEAD antigo e o novo. Sempre refaz config/route cache e
# roda o smoke-test no fim.
# ============================================

set -uo pipefail

APP_DIR="/var/www/html/joomla_sufis/ginfi/poprua-cras"
CONTAINER="php84-poprua-cras"
QUEUE_CONTAINER="queue-poprua-cras"
BRANCH="main"

# Deploy key opcional (ver: bash poprua setup-server)
DEPLOY_KEY="/root/.ssh/poprua_cras_deploy"
if [ -f "$DEPLOY_KEY" ]; then
    export GIT_SSH_COMMAND="ssh -i $DEPLOY_KEY -o IdentitiesOnly=yes -o StrictHostKeyChecking=no"
fi

GIT="git -C $APP_DIR -c safe.directory=*"
ART="docker exec -u www-data $CONTAINER php $APP_DIR/artisan"

step() { echo ""; echo "=== $1 ==="; }
die()  { echo "ERRO: $1" >&2; exit 1; }

[ -d "$APP_DIR/.git" ] || die "$APP_DIR nao e um repo git."

step "[1/8] Preflight — branch e working tree"
CUR_BRANCH=$($GIT rev-parse --abbrev-ref HEAD)
[ "$CUR_BRANCH" = "$BRANCH" ] || die "servidor esta em '$CUR_BRANCH', esperado '$BRANCH'. Faca checkout antes."

$GIT checkout -- composer.lock package-lock.json 2>/dev/null || true

# Drift CRLF em scripts de ops (Windows -> servidor Linux) nao deve bloquear deploy.
sanitize_crlf_drift() {
    local files f
    files=$($GIT diff --name-only 2>/dev/null) || return 0
    [ -z "$files" ] && return 0
    for f in $files; do
        if ! $GIT diff --ignore-cr-at-eol --quiet -- "$f" 2>/dev/null; then
            return 1
        fi
    done
    echo "  revertendo drift CRLF em: $(echo "$files" | tr '\n' ' ')"
    $GIT checkout -- $files
    return 0
}
sanitize_crlf_drift || true

DIRTY=$($GIT status --porcelain --untracked-files=no)
if [ -n "$DIRTY" ]; then
    echo "$DIRTY"
    die "ha mudancas nao-commitadas no servidor (alem dos locks). Capture-as no git (commit+push) ou descarte antes de deployar."
fi

OLD=$($GIT rev-parse HEAD)

step "[2/8] Fetch origin/$BRANCH"
$GIT fetch --quiet origin "$BRANCH" || die "git fetch falhou. Rode 'bash poprua setup-server' e configure deploy key."
NEW=$($GIT rev-parse "origin/$BRANCH")

if [ "$OLD" = "$NEW" ]; then
    echo "  Ja em $NEW — nada novo para aplicar. (Reaplicando caches + smoke-test.)"
else
    echo "  $OLD -> $NEW"
fi

step "[3/8] Pull (fast-forward only)"
$GIT merge --ff-only "origin/$BRANCH" || die "nao foi fast-forward (servidor divergiu de origin/$BRANCH). Resolva manualmente."
chown -R www-data:www-data "$APP_DIR" 2>/dev/null || true

# Producao: faixa de homologacao sempre desligada.
# (O script em memoria no inicio do deploy pode ser a versao antiga ate o pull;
# por isso forcamos false de forma incondicional a cada deploy.)
ENV_FILE="$APP_DIR/.env"
if [ -f "$ENV_FILE" ]; then
    if grep -qE '^[[:space:]]*APP_HOMOLOGACAO_BANNER=' "$ENV_FILE"; then
        sed -i 's/^[[:space:]]*APP_HOMOLOGACAO_BANNER=.*/APP_HOMOLOGACAO_BANNER=false/' "$ENV_FILE"
    else
        echo 'APP_HOMOLOGACAO_BANNER=false' >> "$ENV_FILE"
    fi
    echo "  .env: APP_HOMOLOGACAO_BANNER=false"
fi

CHANGED=$($GIT diff --name-only "$OLD" "$NEW" 2>/dev/null)
need() { echo "$CHANGED" | grep -qE "$1"; }

step "[4/8] Composer (so se composer.lock/json mudou)"
if [ "$OLD" = "$NEW" ] || need '^composer\.(lock|json)$'; then
    docker exec -u www-data "$CONTAINER" composer install --no-interaction --no-dev \
        --optimize-autoloader --working-dir="$APP_DIR" || die "composer install falhou."
else
    echo "  sem mudanca em composer.* — pulando."
fi

step "[5/8] Build Vite (so se assets mudaram) — container node:22-alpine"
if [ "$OLD" = "$NEW" ] || need '^(package(-lock)?\.json|vite\.config\.js|resources/(js|css)/|tailwind\.config\.js)'; then
    docker run --rm -v "$APP_DIR:/app" -w /app node:22-alpine \
        sh -c "npm ci --silent || npm install --silent; npm run build" 2>&1 | tail -8 \
        || die "npm build falhou."
    chown -R www-data:www-data "$APP_DIR/public/build" 2>/dev/null || true
else
    echo "  sem mudanca em assets — pulando."
fi

step "[6/8] Migrate (so se ha migration nova)"
if need '^database/migrations/'; then
    $ART migrate --no-interaction --force || die "migrate falhou."
else
    echo "  sem migration nova — pulando."
fi

step "[7/8] Caches + worker"
$ART config:cache
$ART route:cache
# Recarrega o FPM para o OPcache abandonar bootstrap/cache/routes*.php antigo.
# Sem isso, rotas novas (ex.: home publica) podem continuar autenticadas.
docker restart "$CONTAINER" >/dev/null 2>&1 || true
echo "  $CONTAINER reiniciado (opcache)."
if [ "$OLD" = "$NEW" ] || need '^(app/(Models|Jobs)/|config/|composer\.lock)'; then
    docker restart "$QUEUE_CONTAINER" >/dev/null 2>&1 || true
    echo "  $QUEUE_CONTAINER reiniciado."
fi

step "[8/8] Smoke-test"
bash "$APP_DIR/docker/smoke-test.sh"
RC=$?

echo ""
if [ "$RC" -eq 0 ]; then
    echo "=== Deploy OK ($NEW) ==="
else
    echo "=== Deploy aplicado mas SMOKE-TEST FALHOU — investigar antes de declarar concluido. ==="
fi
exit $RC
