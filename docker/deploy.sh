#!/bin/bash
# ============================================
# Deploy do PopRua CRAS a partir do GitHub (origin/main)
#
# Fonte da verdade = git@github.com:murtafilho/poprua-cras.git (origin/main).
# Substitui o deploy-from-bundle.sh (obsoleto desde a publicacao no GitHub).
#
# Fluxo: dev local -> git push origin main -> ESTE script no host.
#
# Uso (no host vlcp-sufis01, como root):
#   sudo bash /var/www/html/joomla_sufis/ginfi/poprua-cras/docker/deploy.sh
#
# Idempotente: so roda composer/npm/migrate quando os arquivos relevantes
# mudaram entre o HEAD antigo e o novo. Sempre refaz config/route cache e
# roda o smoke-test no fim.
# ============================================

set -uo pipefail

APP_DIR="/var/www/html/joomla_sufis/ginfi/poprua-cras"
CONTAINER="php84-poprua-cras"
QUEUE_CONTAINER="queue-poprua-cras"
BRANCH="${1:-main}"

GIT="git -C $APP_DIR -c safe.directory=*"
ART="docker exec -u www-data $CONTAINER php $APP_DIR/artisan"

step() { echo ""; echo "=== $1 ==="; }
die()  { echo "ERRO: $1" >&2; exit 1; }

[ -d "$APP_DIR/.git" ] || die "$APP_DIR nao e um repo git."

step "[1/8] Preflight — branch e working tree"
CUR_BRANCH=$($GIT rev-parse --abbrev-ref HEAD)
[ "$CUR_BRANCH" = "$BRANCH" ] || die "servidor esta em '$CUR_BRANCH', esperado '$BRANCH'. Faca checkout antes."

# Locks sao regenerados por composer/npm install — descartar ruido para o pull ficar limpo.
$GIT checkout -- composer.lock package-lock.json 2>/dev/null || true

# Qualquer OUTRA mudanca nao-commitada e trabalho de verdade (ex.: hotfix de CSS direto no
# servidor). NAO sobrescrever: abortar e exigir que seja capturada no git primeiro.
DIRTY=$($GIT status --porcelain --untracked-files=no)
if [ -n "$DIRTY" ]; then
    echo "$DIRTY"
    die "ha mudancas nao-commitadas no servidor (alem dos locks). Capture-as no git (commit+push) ou descarte antes de deployar."
fi

step "[2/8] Fetch origin/$BRANCH"
OLD=$($GIT rev-parse HEAD)
$GIT fetch --quiet origin "$BRANCH" || die "git fetch falhou."
NEW=$($GIT rev-parse "origin/$BRANCH")

if [ "$OLD" = "$NEW" ]; then
    echo "  Ja em $NEW — nada novo para aplicar. (Reaplicando caches + smoke-test.)"
else
    echo "  $OLD -> $NEW"
fi

step "[3/8] Pull (fast-forward only)"
$GIT merge --ff-only "origin/$BRANCH" || die "nao foi fast-forward (servidor divergiu de origin/$BRANCH). Resolva manualmente."
chown -R www-data:www-data "$APP_DIR" 2>/dev/null || true

# O que mudou entre o HEAD antigo e o novo decide o que precisa rodar.
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
# Reciclar o worker se modelo/job/config/conversao de media mudou (codigo em memoria no worker).
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
