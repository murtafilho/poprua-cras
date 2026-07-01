#!/usr/bin/env bash
# =============================================================================
# etl/cutover.sh — Pipeline de migracao/cutover  poprua-geo -> poprua-cras
# =============================================================================
# Orquestra TODOS os aspectos da migracao, que o etl:run sozinho NAO cobre:
#   ambiente (rede FDW), dados (ETL), fotos (arquivos), tabelas locais (reseed
#   pos-CASCADE), webp (regen) e validacao end-to-end.
#
# RODA NO HOST vlcp-sufis01 (precisa de docker + acesso aos bind mounts):
#   sudo bash etl/cutover.sh --check          # dry-run: pre-flight + schema-diff + validacao (read-only)
#   sudo bash etl/cutover.sh --apply          # executa a migracao completa
#
# Flags (com --apply):
#   --freeze         poe o GEO em maintenance (artisan down) ANTES de migrar (cutover real)
#   --deactivate-geo responde "sim" automaticamente a pergunta final (desativa o geo sem prompt)
#   --no-rsync       pula a sincronizacao de fotos
#   --no-reseed      nao restaura vistoria_participantes/user_team apos o CASCADE
#   --webp           dispara o media-library:regenerate (assincrono, ~horas)
#   --skip-backup    nao faz pg_dump do CRAS (use so se ja tiver backup externo)
#
# Ao FINAL (modo --apply, validacao OK) o pipeline PERGUNTA se o poprua-geo deve
# ser desativado (artisan down). Rehearsal/treino: responda 'n'. Cutover real: 'y'
# (ou use --deactivate-geo). A resposta define o estado final do geo (up/down).
#
# Idempotente: etl:run faz TRUNCATE...RESTART IDENTITY; rsync e incremental.
# =============================================================================
set -euo pipefail

# ---- Config (nomes/paths do ambiente vlcp-sufis01) --------------------------
GEO_DIR=/var/www/html/joomla_sufis/ginfi/poprua-geo
CRAS_DIR=/var/www/html/joomla_sufis/ginfi/poprua-cras
GEO_NET=poprua-geo_poprua-geo
PG_GEO=pg17-poprua-geo ;   PG_CRAS=pg17-poprua-cras
PHP_GEO=php84-poprua-geo ; PHP_CRAS=php84-poprua-cras
QUEUE_CRAS=queue-poprua-cras
BACKUP_DIR=/var/backups/poprua-cras
PUB=storage/app/public
GEO_DB=poprua_geo ;  GEO_USER=poprua
CRAS_DB=poprua_cras ; CRAS_USER=poprua_cras
# Tabelas locais do CRAS que o CASCADE do TRUNCATE zera e o ETL nao recarrega:
LOCAL_TABLES="vistoria_participantes user_team"
# Tabelas de dominio para checagem de paridade de contagem (geo x cras):
DOMAIN_TABLES="users model_has_roles caracteristica_abrigo encaminhamentos tipo_abordagem tipo_abrigo_desmontado resultados_acoes endereco_atualizados pontos moradores vistorias vistoria_fotos morador_historicos media geo_bairros geo_regionais geo_limite_municipio"

# ---- Flags ------------------------------------------------------------------
MODE="" ; FREEZE=0 ; DEACTIVATE=ask ; DO_RSYNC=1 ; DO_RESEED=1 ; DO_WEBP=0 ; SKIP_BACKUP=0
for a in "$@"; do case "$a" in
  --check) MODE=check ;;  --apply) MODE=apply ;;
  --freeze) FREEZE=1 ;;   --deactivate-geo) DEACTIVATE=yes ;;
  --no-rsync) DO_RSYNC=0 ;; --no-reseed) DO_RESEED=0 ;;
  --webp) DO_WEBP=1 ;;    --skip-backup) SKIP_BACKUP=1 ;;
  *) echo "flag desconhecida: $a" >&2 ; exit 2 ;;
esac; done
[ -n "$MODE" ] || { echo "uso: $0 --check | --apply [flags]" >&2 ; exit 2 ; }

# ---- Helpers ----------------------------------------------------------------
ts(){ date +%H:%M:%S; }
phase(){ echo ; echo "=== [$(ts)] $* ============================================" ; }
ok(){   echo "  ok  $*" ; }
warn(){ echo "  !!  $*" ; }
die(){  echo "  XX  $*" >&2 ; FAIL=1 ; exit 1 ; }
psql_geo(){  docker exec "$PG_GEO"  psql -U "$GEO_USER"  -d "$GEO_DB"  -tAF'|' -c "$1" ; }
psql_cras(){ docker exec "$PG_CRAS" psql -U "$CRAS_USER" -d "$CRAS_DB" -tAF'|' -c "$1" ; }
art_cras(){ docker exec -u www-data "$PHP_CRAS" php "$CRAS_DIR/artisan" "$@" ; }
art_geo(){  docker exec -u www-data "$PHP_GEO"  php "$GEO_DIR/artisan"  "$@" ; }
FAIL=0

echo "########################################################################"
echo "# POPRUA cutover  geo -> cras   modo=$MODE  freeze=$FREEZE deactivate=$DEACTIVATE webp=$DO_WEBP"
echo "# $(date '+%Y-%m-%d %H:%M:%S')"
echo "########################################################################"

# ---- 1. PRE-FLIGHT / AMBIENTE ----------------------------------------------
phase "1. Pre-flight / ambiente"
for c in "$PG_GEO" "$PG_CRAS" "$PHP_CRAS" "$QUEUE_CRAS" "$PHP_GEO"; do
  docker ps --format '{{.Names}}' | grep -qx "$c" || die "container ausente/parado: $c"
done
ok "containers up"
# rede FDW: garante os 3 containers do cras na rede do geo (conexao efemera)
NET_MEMBERS=$(docker network inspect "$GEO_NET" --format '{{range .Containers}}{{.Name}} {{end}}' 2>/dev/null || echo "")
for c in "$PG_CRAS" "$PHP_CRAS" "$QUEUE_CRAS"; do
  if echo "$NET_MEMBERS" | grep -qw "$c"; then ok "rede $GEO_NET: $c"
  else
    if [ "$MODE" = apply ]; then docker network connect "$GEO_NET" "$c" && ok "rede $GEO_NET: $c (conectado agora)"
    else warn "rede $GEO_NET: $c NAO conectado (apply faria connect)"; fi
  fi
done
# migrations do cras aplicadas?
art_cras migrate:status >/dev/null 2>&1 || die "cras: migrations nao aplicadas (rode: artisan migrate --force)"
ok "cras migrations aplicadas"

# ---- 2. SCHEMA-DIFF (pre-flight do ETL) ------------------------------------
phase "2. Schema-diff (Geo x CRAS)"
if art_cras etl:schema-diff; then ok "schema-diff OK"
else die "schema-diff acusou divergencia inesperada — atualizar IGNORED/migrate.sql antes"; fi

# ---- 3. FREEZE GEO (opcional, cutover real) --------------------------------
if [ "$MODE" = apply ] && [ "$FREEZE" = 1 ]; then
  phase "3. Freeze do GEO (maintenance mode)"
  art_geo down --render="errors::503" --retry=60 && ok "geo em maintenance (artisan down)"
elif [ "$MODE" = apply ]; then
  warn "GEO NAO congelado (--freeze ausente): uploads/escritas no geo durante a carga podem gerar drift (ex.: media off-by-1)"
fi

# ---- 4. BACKUP DO CRAS ------------------------------------------------------
PRE_DUMP=""
if [ "$MODE" = apply ]; then
  if [ "$SKIP_BACKUP" = 1 ]; then warn "backup pulado (--skip-backup)"
  else
    phase "4. Backup do CRAS (pg_dump -Fc)"
    mkdir -p "$BACKUP_DIR"
    PRE_DUMP="$BACKUP_DIR/cras_pre_cutover_$(date +%Y%m%d_%H%M%S).dump"
    docker exec "$PG_CRAS" pg_dump -Fc -U "$CRAS_USER" "$CRAS_DB" > "$PRE_DUMP"
    ok "backup: $PRE_DUMP ($(du -h "$PRE_DUMP" | cut -f1))"
  fi
fi
# snapshot das contagens locais antes do CASCADE (para conferir o reseed)
declare -A LOCAL_BEFORE
if [ "$MODE" = apply ]; then
  for t in $LOCAL_TABLES; do LOCAL_BEFORE[$t]=$(psql_cras "SELECT count(*) FROM $t" | tr -d ' '); done
fi

# ---- 5. ETL DE DADOS --------------------------------------------------------
if [ "$MODE" = apply ]; then
  phase "5. ETL de dados (etl:run --confirm)"
  art_cras etl:run --confirm --skip-backup
  ok "etl:run concluido"
fi

# ---- 5b. RBAC canônico do CRAS ------------------------------------------------
if [ "$MODE" = apply ]; then
  phase "5b. RBAC: PermissoesSeeder"
  art_cras db:seed --class=PermissoesSeeder --force
  ok "PermissoesSeeder concluido"
fi

# ---- 5c. Remap model_has_roles do Geo -----------------------------------------
if [ "$MODE" = apply ]; then
  phase "5c. RBAC: remap model_has_roles (role.name)"
  docker exec -i "$PG_CRAS" psql -U "$CRAS_USER" -d "$CRAS_DB" \
    < "$CRAS_DIR/etl/remap-model-has-roles.sql"
  GEO_MHR=$(psql_geo "SELECT count(*) FROM model_has_roles" | tr -d ' ')
  CRAS_MHR=$(psql_cras "SELECT count(*) FROM model_has_roles" | tr -d ' ')
  if [ "$GEO_MHR" = "$CRAS_MHR" ]; then ok "model_has_roles: geo=$GEO_MHR cras=$CRAS_MHR"
  else warn "model_has_roles: geo=$GEO_MHR cras=$CRAS_MHR (papeis Geo sem match no CRAS?)"; fi
fi

# ---- 6. FOTOS (rsync de arquivos fisicos) ----------------------------------
if [ "$MODE" = apply ] && [ "$DO_RSYNC" = 1 ]; then
  phase "6. Fotos: rsync $PHP_GEO -> $PHP_CRAS ($PUB)"
  rsync -a --stats "$GEO_DIR/$PUB/" "$CRAS_DIR/$PUB/" | grep -E 'Number of created files|Total transferred file size' || true
  chown -R www-data:www-data "$CRAS_DIR/$PUB"
  ok "fotos sincronizadas + chown www-data"
elif [ "$MODE" = apply ]; then warn "rsync de fotos pulado (--no-rsync)"; fi

# ---- 7. RESEED DAS TABELAS LOCAIS (pos-CASCADE) ----------------------------
if [ "$MODE" = apply ] && [ "$DO_RESEED" = 1 ]; then
  phase "7. Reseed de tabelas locais zeradas pelo CASCADE"
  SRC_DUMP="$PRE_DUMP"
  [ -n "$SRC_DUMP" ] || SRC_DUMP=$(ls -1t "$BACKUP_DIR"/cras_pre_*.dump 2>/dev/null | head -1 || true)
  if [ -z "$SRC_DUMP" ] || [ ! -f "$SRC_DUMP" ]; then warn "sem dump de origem p/ reseed — pulando (reseed manual necessario)"
  else
    TARGS=""; for t in $LOCAL_TABLES; do TARGS="$TARGS -t $t"; done
    # shellcheck disable=SC2086
    cat "$SRC_DUMP" | docker exec -i "$PG_CRAS" pg_restore -U "$CRAS_USER" -d "$CRAS_DB" --data-only $TARGS 2>&1 | grep -vE '^$' || true
    for t in $LOCAL_TABLES; do
      now=$(psql_cras "SELECT count(*) FROM $t" | tr -d ' ')
      was=${LOCAL_BEFORE[$t]:-?}
      [ "$now" = "$was" ] && ok "reseed $t: $was -> $now" || warn "reseed $t: antes=$was depois=$now (conferir FKs)"
    done
  fi
elif [ "$MODE" = apply ]; then warn "reseed pulado (--no-reseed): $LOCAL_TABLES ficam zeradas"; fi

# ---- 8. VALIDACAO -----------------------------------------------------------
phase "8. Validacao end-to-end"
# 8a. paridade de contagens geo x cras
build_cnt(){ local first=1 t; for t in $DOMAIN_TABLES; do [ $first = 1 ] && first=0 || printf ' UNION ALL '; printf "SELECT '%s' t,count(*) c FROM %s" "$t" "$t"; done; }
CNT_SQL="$(build_cnt) ORDER BY t"
psql_geo  "$CNT_SQL" | LC_ALL=C sort -t'|' -k1,1 > /tmp/_geo_cnt.txt
psql_cras "$CNT_SQL" | LC_ALL=C sort -t'|' -k1,1 > /tmp/_cras_cnt.txt
PARITY_FAIL=0
join -t'|' /tmp/_geo_cnt.txt /tmp/_cras_cnt.txt | while IFS='|' read -r t g c; do
  if [ "$g" = "$c" ]; then printf "  ok  %-22s geo=%-8s cras=%-8s\n" "$t" "$g" "$c"
  else printf "  !!  %-22s geo=%-8s cras=%-8s  DIFF\n" "$t" "$g" "$c"; fi
done
# recomputa fail fora do subshell do pipe
DIFFS=$(join -t'|' /tmp/_geo_cnt.txt /tmp/_cras_cnt.txt | awk -F'|' '$2!=$3{print $1}')
if [ -n "$DIFFS" ]; then
  warn "tabelas com contagem divergente: $(echo "$DIFFS" | tr '\n' ' ')"
  warn "(em --check, divergencia e esperada se o geo evoluiu; em --apply pos-carga, media pode ter off-by-1 por drift se geo nao congelado)"
fi

# 8b. gap de fotos: media sem arquivo fisico no cras (comm exige sort lexical C nos 2 lados)
docker exec "$PHP_CRAS" sh -c "cd '$CRAS_DIR/$PUB' && ls -1 | grep -E '^[0-9]+\$' | LC_ALL=C sort > /tmp/_dirs.txt"
psql_cras "SELECT id FROM media" | tr -d ' ' | LC_ALL=C sort > /tmp/_mids.txt
docker cp /tmp/_mids.txt "$PHP_CRAS":/tmp/_mids.txt >/dev/null 2>&1
GAP=$(docker exec "$PHP_CRAS" sh -c "LC_ALL=C comm -23 /tmp/_mids.txt /tmp/_dirs.txt | wc -l" | tr -d ' ')
if [ "${GAP:-1}" = "0" ]; then ok "fotos: 0 registros de media sem arquivo fisico"
else warn "fotos: $GAP registros de media SEM arquivo (rode com rsync / sem --no-rsync)"; [ "$MODE" = apply ] && [ "$DO_RSYNC" = 1 ] && PARITY_FAIL=1; fi

# 8c. PostGIS
INV=$(psql_cras "SELECT count(*) FROM geo_bairros WHERE NOT ST_IsValid(geom::geometry)" | tr -d ' ')
SRID=$(psql_cras "SELECT count(*) FROM pontos WHERE geom IS NOT NULL AND ST_SRID(geom)<>4326" | tr -d ' ')
[ "${INV:-1}" = "0" ]  && ok "geo_bairros invalidos: 0" || warn "geo_bairros invalidos: $INV"
[ "${SRID:-1}" = "0" ] && ok "pontos SRID!=4326: 0"     || warn "pontos SRID!=4326: $SRID"

# ---- 9. WEBP (opcional, assincrono) ----------------------------------------
if [ "$MODE" = apply ] && [ "$DO_WEBP" = 1 ]; then
  phase "9. WebP: media-library:regenerate (assincrono via fila)"
  art_cras media-library:regenerate --force
  ok "regenerate disparado — worker $QUEUE_CRAS processa em background (monitore os *.webp)"
fi

# ---- 10. DECISAO FINAL: desativar o poprua-geo? ----------------------------
if [ "$MODE" = apply ]; then
  phase "10. Desativar o poprua-geo?"
  if [ "${PARITY_FAIL:-0}" = 1 ]; then
    warn "validacao NAO passou — poprua-geo mantido ATIVO. Resolva as pendencias antes de desativar."
    [ "$FREEZE" = 1 ] && art_geo up && ok "geo retirado do maintenance (rollback do freeze)"
  else
    echo "  Migracao validada com sucesso."
    echo "  Desativar o poprua-geo agora? (poe o geo em maintenance/artisan down, tirando-o do ar)"
    echo "  -> rehearsal/treino: responda 'n'   |   cutover real: 'y'"
    if [ "$DEACTIVATE" = yes ]; then ans=y; echo "  (--deactivate-geo: respondendo 'y' automaticamente)"
    else printf "  Desativar poprua-geo? [y/N] "; read -r ans || ans=n; fi
    case "${ans:-n}" in
      [yY]*)
        art_geo down --render="errors::503" --retry=60 && ok "poprua-geo DESATIVADO (maintenance mode)."
        echo "     decommission completo (manual, opcional): parar os containers do stack geo —"
        echo "       sudo docker compose -f /opt/docker/poprua-geo/docker-compose.yml stop"
        ;;
      *)
        ok "poprua-geo mantido ATIVO (nao desativado)."
        [ "$FREEZE" = 1 ] && art_geo up && ok "geo retirado do maintenance (freeze revertido)"
        ;;
    esac
  fi
fi

# ---- RESUMO -----------------------------------------------------------------
phase "RESUMO"
if [ "$MODE" = check ]; then
  echo "  modo CHECK: ambiente + schema-diff + validacao read-only concluidos."
  echo "  Para treinar (rehearsal): sudo bash etl/cutover.sh --apply   (pergunta no fim se desativa o geo)"
  echo "  Para cutover real:        sudo bash etl/cutover.sh --apply --freeze --deactivate-geo"
else
  echo "  modo APPLY concluido. Backup: ${PRE_DUMP:-<skip>}"
fi
[ -n "${DIFFS:-}" ] && echo "  Atencao: contagens divergentes em: $(echo "$DIFFS" | tr '\n' ' ')"
echo "  Fim: $(date '+%Y-%m-%d %H:%M:%S')"
[ "${PARITY_FAIL:-0}" = 1 ] && { echo "  -> FALHA: validacao critica nao passou"; exit 1; }
exit 0
