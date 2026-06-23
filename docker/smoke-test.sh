#!/bin/bash
# ============================================
# Smoke test pos-deploy / pos-rebuild — POPRUA CRAS
#
# Uso: sudo bash docker/smoke-test.sh   (no host vlcp-sufis01)
#
# Roda 4 checks essenciais que cobrem o pipe HTTP completo do usuario:
#   1. PHP-FPM (container) responde via FastCGI no host:9086
#   2. URL externa publica retorna 200 + CSRF token + titulo
#   3. Banco tem dados de dominio (count pontos > 0)
#   4. Sem ERROR no laravel.log nos ultimos 5 minutos
#
# Cada check imprime PASS/FAIL. Script sai 0 se todos PASS; senao 1.
# Adicionado por ADR-006 (Sinal #6: smoke-test obrigatorio).
# ============================================

set -u  # erro em variavel nao definida; NAO usar set -e (queremos rodar todos os checks)

APP_DIR="/var/www/html/joomla_sufis/ginfi/poprua-cras"
PROD_URL="https://sufis.pbh.gov.br/ginfi/poprua-cras/public"
LOCAL_PHP_FPM="127.0.0.1:9086"
CONTAINER="php84-poprua-cras"
LARAVEL_LOG="${APP_DIR}/storage/logs/laravel.log"

GREEN="\033[32m"
RED="\033[31m"
YELLOW="\033[33m"
RESET="\033[0m"

PASS=0
FAIL=0

pass() { echo -e "  ${GREEN}PASS${RESET}  $1"; PASS=$((PASS+1)); }
fail() { echo -e "  ${RED}FAIL${RESET}  $1"; FAIL=$((FAIL+1)); }

echo "=== POPRUA CRAS — smoke-test ($(date -Iseconds)) ==="
echo ""

# --- 1. Porta 9086 (PHP-FPM/FastCGI) esta listening no host?
# 9086 e FastCGI binario, nao HTTP — nao da pra curl. Testar com nc.
echo "[1/4] PHP-FPM listening em 127.0.0.1:9086 (FastCGI)..."
if nc -z -w 2 127.0.0.1 9086 2>/dev/null; then
  pass "127.0.0.1:9086 (FastCGI) aceita conexao"
else
  fail "127.0.0.1:9086 nao aceita conexao — Apache vai bater 503"
fi
echo ""

# --- 2. URL externa de prod retorna login + CSRF
echo "[2/4] URL publica ${PROD_URL}/login ..."
PROD_BODY=$(curl -s -L "${PROD_URL}/login" --max-time 10 2>&1)
PROD_CODE=$(curl -s -o /dev/null -w "%{http_code}" -L "${PROD_URL}/login" --max-time 10 2>&1 || echo "000")
if [ "$PROD_CODE" = "200" ] && echo "$PROD_BODY" | grep -q "csrf-token"; then
  pass "URL publica responde 200 com csrf-token presente"
else
  fail "URL publica falhou (HTTP $PROD_CODE; csrf-token presente? $(echo "$PROD_BODY" | grep -c csrf-token))"
fi
echo ""

# --- 3. DB tem dados de dominio
echo "[3/4] DB tem dados de dominio..."
PONTOS_COUNT=$(sudo docker exec pg17-poprua-cras psql -U poprua_cras -d poprua_cras -tAc "SELECT count(*) FROM pontos" 2>/dev/null || echo "ERROR")
if echo "$PONTOS_COUNT" | grep -qE '^[0-9]+$' && [ "$PONTOS_COUNT" -gt 0 ]; then
  pass "pontos = $PONTOS_COUNT (> 0)"
else
  fail "count(pontos) retornou: $PONTOS_COUNT"
fi
echo ""

# --- 4. Sem ERROR nos ultimos 5 minutos no laravel.log
echo "[4/4] laravel.log sem ERROR nos ultimos 5 minutos..."
if [ ! -f "$LARAVEL_LOG" ]; then
  echo -e "  ${YELLOW}WARN${RESET}  $LARAVEL_LOG nao existe (sem logs ainda — ok se app nunca foi acessado)"
  PASS=$((PASS+1))
else
  ERR_COUNT=$(find "$LARAVEL_LOG" -newermt "5 minutes ago" -exec grep -cE "production\.ERROR|local\.ERROR|EMERGENCY" {} + 2>/dev/null || echo "0")
  if [ "${ERR_COUNT:-0}" = "0" ]; then
    pass "sem ERROR/EMERGENCY no log nos ultimos 5min"
  else
    fail "${ERR_COUNT} ERROR/EMERGENCY no log nos ultimos 5min — checar: tail -50 ${LARAVEL_LOG}"
  fi
fi
echo ""

echo "================================="
echo "  Total: PASS=$PASS  FAIL=$FAIL"
echo "================================="
if [ "$FAIL" -gt 0 ]; then
  echo -e "${RED}Smoke-test FALHOU. Nao declarar a mudanca como concluida.${RESET}"
  exit 1
fi
echo -e "${GREEN}Smoke-test OK.${RESET}"
exit 0
