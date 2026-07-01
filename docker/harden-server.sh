#!/usr/bin/env bash
# =============================================================================
# docker/harden-server.sh — Hardening do host vlcp-sufis01 (PopRua CRAS + Geo)
# =============================================================================
# Uso: sudo bash docker/harden-server.sh
# Idempotente. Rodar apos deploy de seguranca.
# =============================================================================
set -euo pipefail

CRAS_DIR=/var/www/html/joomla_sufis/ginfi/poprua-cras
GEO_DIR=/var/www/html/joomla_sufis/ginfi/poprua-geo
PHP_CRAS=php84-poprua-cras
PHP_GEO=php84-poprua-geo

ok(){ echo "  ok  $*"; }
warn(){ echo "  !!  $*"; }

echo "=== PopRua hardening — $(date '+%F %T') ==="

# ---- 1. Adminer Geo (publico) ------------------------------------------------
if [ -f /etc/apache2/conf-enabled/adminer-poprua-geo.conf ]; then
  a2disconf adminer-poprua-geo.conf >/dev/null 2>&1 || true
  ok "adminer-poprua-geo desabilitado"
else
  ok "adminer-poprua-geo ja ausente"
fi

# ---- 2. Permissoes .env ------------------------------------------------------
for f in "$CRAS_DIR/.env" "$GEO_DIR/.env"; do
  if [ -f "$f" ]; then
    chown www-data:www-data "$f" 2>/dev/null || true
    chmod 640 "$f"
    ok "chmod 640 $(basename "$(dirname "$f")")/.env"
  fi
done

# ---- 3. Geo: fechar registro publico -----------------------------------------
GEO_AUTH_SRC="$CRAS_DIR/docker/geo-routes-auth.secure.php"
GEO_AUTH_DST="$GEO_DIR/routes/auth.php"
if [ -f "$GEO_AUTH_SRC" ] && [ -d "$GEO_DIR/routes" ]; then
  cp -a "$GEO_AUTH_DST" "$GEO_AUTH_DST.bak.$(date +%Y%m%d)" 2>/dev/null || true
  cp "$GEO_AUTH_SRC" "$GEO_AUTH_DST"
  chown www-data:www-data "$GEO_AUTH_DST"
  chmod 664 "$GEO_AUTH_DST"
  ok "geo routes/auth.php — registro restrito a admin"
else
  warn "geo auth patch pulado (paths ausentes)"
fi

# ---- 4. Apache CRAS vhost (sem Indexes) -------------------------------------
CRAS_VHOST=/etc/apache2/conf-enabled/php84-poprua-cras.conf
if [ -f "$CRAS_DIR/docker/apache-vhost.conf" ]; then
  if ! grep -q 'php84-poprua-cras' "$CRAS_VHOST" 2>/dev/null; then
    cp "$CRAS_DIR/docker/apache-vhost.conf" /etc/apache2/conf-available/php84-poprua-cras.conf
    a2enconf php84-poprua-cras.conf >/dev/null 2>&1 || true
  else
    cp "$CRAS_DIR/docker/apache-vhost.conf" /etc/apache2/conf-available/php84-poprua-cras.conf
  fi
  ok "apache vhost CRAS atualizado (sem Indexes)"
fi

apache2ctl configtest >/dev/null
systemctl reload apache2
ok "apache reload"

# ---- 5. Docker: FPM/SSH apenas localhost (CRAS) ------------------------------
if [ -f "$CRAS_DIR/docker-compose.yml" ]; then
  cd "$CRAS_DIR"
  docker compose up -d --no-build app ssh 2>/dev/null || docker compose up -d app ssh
  ok "docker compose up (127.0.0.1:9086 e 127.0.0.1:2226)"
fi

# ---- 6. Caches Laravel -------------------------------------------------------
for c in "$PHP_CRAS" "$PHP_GEO"; do
  dir=$([ "$c" = "$PHP_CRAS" ] && echo "$CRAS_DIR" || echo "$GEO_DIR")
  docker exec -u www-data "$c" php "$dir/artisan" route:clear config:clear cache:clear >/dev/null 2>&1 || true
done
docker exec -u www-data "$PHP_CRAS" php "$CRAS_DIR/artisan" config:cache route:cache >/dev/null 2>&1 || true
ok "caches Laravel limpos"

# ---- 7. Fail2ban (sshd) ------------------------------------------------------
if command -v fail2ban-client >/dev/null 2>&1; then
  systemctl enable fail2ban >/dev/null 2>&1 || true
  systemctl start fail2ban >/dev/null 2>&1 || true
  ok "fail2ban ativo"
else
  if apt-get install -y fail2ban >/dev/null 2>&1; then
    cat >/etc/fail2ban/jail.d/poprua-sshd.local <<'JAIL'
[sshd]
enabled = true
port = ssh,2222,2223,2224,2226
maxretry = 5
findtime = 600
bantime = 3600
JAIL
    systemctl enable fail2ban
    systemctl restart fail2ban
    ok "fail2ban instalado e configurado"
  else
    warn "fail2ban nao instalado (apt indisponivel)"
  fi
fi

# ---- 8. Verificacao rapida ---------------------------------------------------
echo ""
echo "=== Verificacao ==="
REG_CRAS=$(curl -sI -o /dev/null -w '%{http_code}' "https://sufis.pbh.gov.br/ginfi/poprua-cras/public/register" 2>/dev/null || echo "?")
REG_GEO=$(curl -sI -o /dev/null -w '%{http_code}' "https://sufis.pbh.gov.br/ginfi/poprua-geo/public/register" 2>/dev/null || echo "?")
echo "  /register CRAS: HTTP $REG_CRAS (esperado 302)"
echo "  /register GEO:  HTTP $REG_GEO (esperado 302)"
FPM_BIND=$(ss -tlnp 2>/dev/null | grep ':9086' || true)
echo "  FPM 9086: $FPM_BIND"
echo ""
echo "=== Hardening concluido ==="
