#!/bin/bash
# ============================================
# bootstrap-host-perms.sh — Solucao definitiva ADR-010
#
# Normaliza permissoes da arvore CRAS no HOST para permitir cross-write entre
# host (uid humano via grupo www-data gid 33) e container (uid 33 www-data).
#
# Idempotente. Rodar como root quando:
#   - Setup inicial do projeto
#   - Apos clonar/fazer um pull grande
#   - Quando perms drift de novo (apesar do init-perms self-healing)
#
# Uso: sudo bash docker/bootstrap-host-perms.sh
#
# O que faz:
#   1. chgrp -R www-data .  (exceto .git/, vendor/, node_modules/)
#   2. find -type d -exec chmod 2775  (setgid + g+rwx — novos arquivos herdam grupo)
#   3. find -type f -exec chmod 664   (g+rw)
#   4. setfacl -R -d -m g:www-data:rwx  (default ACL — novos arquivos herdam g+rwx)
#   5. setfacl -R -m  g:www-data:rwx   (ACL atual — para arquivos ja existentes)
# ============================================

set -euo pipefail

PROJECT_ROOT="${PROJECT_ROOT:-/var/www/html/joomla_sufis/ginfi/poprua-cras}"
GROUP="www-data"
GROUP_GID=33

cd "$PROJECT_ROOT"

if [ "$(id -u)" -ne 0 ]; then
  echo "ERRO: rode como root: sudo bash $0" >&2
  exit 1
fi

if ! command -v setfacl >/dev/null 2>&1; then
  echo "ERRO: setfacl nao encontrado. Instale o pacote 'acl':" >&2
  echo "  apt-get install -y acl" >&2
  exit 1
fi

if ! getent group "$GROUP" >/dev/null; then
  echo "ERRO: grupo $GROUP nao existe no host" >&2
  exit 1
fi

# Pruning: vendor/node_modules sao gerenciados por suas ferramentas; .git tem
# logica propria; pgdata/postgres-data nunca devem ser tocados.
PRUNE_ARGS=(
  -name .git -prune -o
  -name vendor -prune -o
  -name node_modules -prune -o
  -name pgdata -prune -o
  -name postgres-data -prune -o
)

echo "[1/5] chgrp -R $GROUP (excluindo .git/vendor/node_modules)..."
find . "${PRUNE_ARGS[@]}" -print0 | xargs -0 -r chgrp "$GROUP_GID"

echo "[2/5] chmod 2775 nos dirs (setgid + g+rwx)..."
find . "${PRUNE_ARGS[@]}" -type d -print0 | xargs -0 -r chmod 2775

echo "[3/5] chmod 664 nos arquivos (g+rw)..."
find . "${PRUNE_ARGS[@]}" -type f -print0 | xargs -0 -r chmod 664

echo "[4/5] setfacl -R -d -m g:$GROUP:rwx (default ACL — heranca para arquivos novos)..."
find . "${PRUNE_ARGS[@]}" -type d -print0 | xargs -0 -r setfacl -d -m "g:$GROUP:rwx"

echo "[5/5] setfacl -R -m g:$GROUP:rwx (ACL atual nos existentes)..."
find . "${PRUNE_ARGS[@]}" -print0 | xargs -0 -r setfacl -m "g:$GROUP:rwx"

# Scripts shell e binarios mantem +x
echo "[+] preservando +x em scripts (.sh, bin/, vendor/bin/)..."
find . -path ./vendor -prune -o -path ./node_modules -prune -o -name '*.sh' -type f -print0 | xargs -0 -r chmod 0775

# Restaurar perms estritas em diretorios de segredos
if [ -f .env ]; then
  chmod 640 .env || true
  chgrp "$GROUP_GID" .env || true
fi

echo ""
echo "OK — perms normalizadas. Validacao:"
echo "  touch test-host.txt && ls -la test-host.txt && rm test-host.txt"
echo "  (deve ser 'cassio.martins www-data ... -rw-rw-r--+')"
