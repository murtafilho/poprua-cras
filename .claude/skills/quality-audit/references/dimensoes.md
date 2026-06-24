# As 8 Dimensoes + Contrato JSON — quality-audit POPRUA CRAS

Cada agente de dimensao roda os blocos bash da SUA dimensao (copiados deste arquivo), calcula o score conforme a formula, e escreve `$AUDITS_DIR/harness-<dimension>.json` no contrato abaixo. Variaveis (`$EXEC`, `$DB_EXEC`, `$PROJECT_ROOT`, `$GEO_CRAS_TABLES`, `$SSH_PROD`) vem resolvidas pelo `scripts/preflight.sh` (PASSO -1).

## Contrato JSON das sub-audits

```json
{
  "skill": "harness-<dimensao>",
  "dimension": "<dimensao>",
  "score": 87,
  "max_score": 100,
  "timestamp": "2026-05-19T15:30:00-03:00",
  "degraded": false,
  "incidents": ["TBL-010"],
  "tests": { "total": 13, "passed": 12, "failed": 1, "skipped": 0 },
  "findings": [
    {
      "id": "geo-001",
      "severity": "CRITICO",
      "title": "Geometria sem indice GIST em pontos.geom",
      "description": "...",
      "file": "database/migrations/2024_xx_create_pontos.php",
      "line": 42,
      "effort_hours": 2,
      "impact": "HIGH",
      "auto_fixable": true
    }
  ],
  "kpis": { }
}
```

**Obrigatorios:** `skill`, `dimension`, `score`, `timestamp`, `findings[]`
**Opcionais:** `tests`, `kpis`, `max_score`, `degraded`, `incidents`

### Severidades -> deducoes

| Severidade | Impacto | Deducao |
|------------|---------|---------|
| CRITICO    | 10      | -15     |
| ALTO       | 7       | -8      |
| MEDIO      | 4       | -3      |
| BAIXO      | 2       | -1      |
| OK/INFO    | 0       | 0       |

---

## D1 — Code Quality

**Fonte:** pint + phpstan

```bash
$EXEC vendor/bin/pint --test --format=json 2>&1 || true
$EXEC vendor/bin/phpstan analyse --error-format=json --no-progress 2>&1 || true
wc -l "$PROJECT_ROOT/phpstan-baseline.neon" 2>/dev/null
```

**Scoring:** Base 100; pint -1/violacao (cap -30); phpstan -2/erro (cap -40); bonus +5 cada se zero.
**Percalcos:** TBL-003, TBL-004, TBL-019.
**KPIs:** `pint_violations`, `phpstan_errors`, `phpstan_level`, `phpstan_baseline_size`

## D2 — Test Coverage & Health

**Fonte:** `artisan test` + clover XML opcional

```bash
$EXEC php artisan test 2>&1 || true
$EXEC php artisan test --coverage-clover=/tmp/coverage.xml 2>&1 || true
```

**Scoring:** pass rate 60% + coverage 40%; -5/failure; zero tests = 0.
**Percalcos:** TBL-005, TBL-006.
**KPIs:** `test_count`, `pass_rate`, `fail_count`, `line_coverage_pct`

## D3 — Security

**Fonte:** `.claude/audits/security-infra-audit.json` (se < 7 dias) + fallback

```bash
$EXEC composer audit --format=json 2>&1 || true

# npm audit — node nao existe no host (TBL-030); rodar via $EXEC python3 no container
$EXEC python3 -c "
import subprocess, json, sys
try:
    r = subprocess.run(['npm','audit','--json'], capture_output=True, text=True, timeout=30)
    d = json.loads(r.stdout)
    v = d.get('metadata',{}).get('vulnerabilities',{})
    print(json.dumps(dict((k, v[k]) for k in ['high','critical','moderate'] if v.get(k,0)>0)))
except Exception as e:
    print('{}')
" 2>/dev/null || echo "npm_audit_skipped"

# .env nao commitado (logica correta — TBL-013)
git -C "$PROJECT_ROOT" ls-files --error-unmatch .env >/dev/null 2>&1 \
  && echo "CRITICO: .env tracked" || echo "OK"

# Debug em prod (TBL-012 se SSH falhar)
$SSH_PROD "grep '^APP_DEBUG' /var/www/html/joomla_sufis/ginfi/poprua-cras/.env" 2>/dev/null || echo "ssh_unreachable"

# Casts encrypted nos models sensiveis
grep -n "protected \$casts\|'encrypted'" "$PROJECT_ROOT/app/Models/Morador.php" "$PROJECT_ROOT/app/Models/User.php" 2>/dev/null

# RBAC ativo
grep -rn "middleware.*role:\|middleware.*permission:" "$PROJECT_ROOT/routes/" 2>/dev/null | wc -l

# Mass assignment
for f in "$PROJECT_ROOT"/app/Models/*.php; do
  grep -L "fillable\|guarded" "$f"
done | wc -l

# Inline CSP handlers
grep -rn 'onclick=\|onchange=\|onsubmit=' "$PROJECT_ROOT/resources/views/" --include="*.blade.php" 2>/dev/null \
  | grep -vE 'x-on:|data-|//|<!--' | wc -l

# Rate limiting
grep -rn "throttle:" "$PROJECT_ROOT/routes/" 2>/dev/null | wc -l
```

**Scoring:** se JSON < 7 dias usa-o; senao base 100, severidade; npm high -2 (cap -12); npm critical -5; mass assignment -3; inline CSP -1 (cap -10).
**Percalcos:** TBL-009, TBL-012, TBL-013.
**KPIs:** `php_vulns`, `npm_high_vulns`, `npm_critical_vulns`, `env_exposed`, `debug_in_prod`, `models_without_fillable`, `csp_inline_handlers`

## D4 — Architecture & Patterns

```bash
cd "$PROJECT_ROOT"

grep -rl "DB::\|->save()\|->create(" app/Http/Controllers/ 2>/dev/null | wc -l
find app/Models/ -name "*.php" -exec grep -l "DB::raw\|whereRaw\|selectRaw" {} \; 2>/dev/null | wc -l
ls app/Http/Requests/**/*.php app/Http/Requests/*.php 2>/dev/null | wc -l
ls app/Services/**/*.php app/Services/*.php 2>/dev/null | wc -l
ls app/Http/Controllers/**/*.php app/Http/Controllers/*.php 2>/dev/null | wc -l
grep -rn "->each\|->map" app/ --include="*.php" 2>/dev/null | grep -v "Closure\|//" | wc -l
grep -rc "->with(\|->load(" app/ --include="*.php" 2>/dev/null | grep -v ":0" | wc -l

for m in Ponto Vistoria Morador; do
  grep -l "SoftDeletes" app/Models/$m.php 2>/dev/null
done | wc -l

grep -L "public function down" database/migrations/*.php 2>/dev/null | wc -l
ls app/Http/Resources/**/*.php app/Http/Resources/*.php 2>/dev/null | wc -l
```

**Scoring:** Base 100; controllers c/ DB -3 (cap -25); raw queries em models -2 (cap -15); bonus services/controllers > 0.5 (+10); bonus requests/controllers > 0.7 (+10); soft delete faltando em Ponto/Vistoria/Morador -5 cada; migrations sem down -1 (cap -10).
**Percalcos:** TBL-017.
**KPIs:** `controllers_with_db`, `raw_queries_in_models`, `service_coverage_ratio`, `form_request_ratio`, `soft_deletes_coverage`, `irreversible_migrations`

## D5 — Geospatial Data Integrity (PostGIS)

```bash
# SRID e tipo (so tabelas do dominio CRAS — TBL-022)
$DB_EXEC -d poprua_cras -t -c "
  SELECT f_table_name, f_geometry_column, srid, type
  FROM geometry_columns
  WHERE f_table_name IN $GEO_CRAS_TABLES
  ORDER BY f_table_name;
"

# Sem GIST (so dominio CRAS)
$DB_EXEC -d poprua_cras -t -c "
  SELECT gc.f_table_name, gc.f_geometry_column
  FROM geometry_columns gc
  LEFT JOIN pg_indexes pi
    ON pi.tablename = gc.f_table_name
   AND pi.indexdef LIKE '%USING gist%'
   AND pi.indexdef LIKE '%' || gc.f_geometry_column || '%'
  WHERE pi.indexname IS NULL
    AND gc.f_table_name IN $GEO_CRAS_TABLES;
"

# SRID != 4326 (so dominio CRAS)
$DB_EXEC -d poprua_cras -t -c "
  SELECT f_table_name, srid FROM geometry_columns
  WHERE srid != 4326 AND f_table_name IN $GEO_CRAS_TABLES;
"

# Bounding box BH/MG (lat ~-21..-19, lng ~-45..-42)
$DB_EXEC -d poprua_cras -t -c "
  SELECT count(*) FROM pontos
  WHERE ST_X(geom::geometry) NOT BETWEEN -45 AND -42
     OR ST_Y(geom::geometry) NOT BETWEEN -21 AND -19;
" 2>/dev/null || true

# whereRaw espacial fora de Models/Services
grep -rn "ST_\|whereRaw.*geom\|selectRaw.*ST_" "$PROJECT_ROOT/app/" --include="*.php" 2>/dev/null \
  | grep -v "app/Models/\|app/Services/" | wc -l

# Pontos orfaos (sem EnderecoAtualizado)
$DB_EXEC -d poprua_cras -t -c "
  SELECT count(*) FROM pontos p
  LEFT JOIN endereco_atualizados e ON e.ponto_id = p.id
  WHERE e.id IS NULL;
" 2>/dev/null || true

# Geometrias invalidas (cast por TBL-011)
$DB_EXEC -d poprua_cras -t -c "
  SELECT 'geo_bairros' as t, count(*) FROM geo_bairros WHERE NOT ST_IsValid(geom::geometry)
  UNION ALL
  SELECT 'geo_regionais', count(*) FROM geo_regionais WHERE NOT ST_IsValid(geom::geometry);
" 2>/dev/null || true

# FormRequests validando coordenadas
grep -rn "'lat'\|'lng'\|'latitude'\|'longitude'" "$PROJECT_ROOT/app/Http/Requests/" --include="*.php" 2>/dev/null | wc -l
```

**Scoring:** Base 100; SRID != 4326 -10/tabela; sem GIST -8/tabela; coords fora bbox -1 (cap -15); whereRaw fora de Models/Services -2 (cap -15); pontos orfaos -1 (cap -10); geometrias invalidas -10 cada; sem validacao lat/lng -5. Bonus: todas 4326 +5; todas com GIST +5.
**Percalcos:** TBL-010, TBL-011.
**KPIs:** `srid_consistency`, `geometries_without_gist`, `invalid_geometries`, `coords_out_of_bbox`, `scattered_spatial_queries`, `orphan_pontos`

## D6 — Frontend Quality

```bash
$EXEC npm run build 2>&1 | tail -20
ls -lh "$PROJECT_ROOT/public/build/assets/"*.js "$PROJECT_ROOT/public/build/assets/"*.css 2>/dev/null

grep -rn "L.map\|new L\.\|leaflet" "$PROJECT_ROOT/resources/js/" 2>/dev/null | wc -l

# Alpine x-data sem x-cloak
grep -rl 'x-data' "$PROJECT_ROOT/resources/views/" --include="*.blade.php" 2>/dev/null \
  | xargs grep -L 'x-cloak' 2>/dev/null | wc -l

# Imagens sem alt (TBL-028: regex blade-aware; TBL-030: usar $EXEC python3, nao host)
$EXEC python3 - <<'PY'
import re, glob, os
root = '/var/www/html/joomla_sufis/ginfi/poprua-cras'
count = 0
pattern = re.compile(r'<img\b(?:[^>{]|\{\{[^}]*\}\})*?>', re.DOTALL)
for f in glob.glob(root + '/resources/views/**/*.blade.php', recursive=True):
    if '/vendor/' in f: continue
    try: text = open(f).read()
    except: continue
    for m in pattern.finditer(text):
        tag = m.group()
        if 'alt=' not in tag and ':alt=' not in tag:
            count += 1
print(count)
PY

# Inline handlers
grep -rn 'onclick=\|onchange=\|onsubmit=\|onerror=' "$PROJECT_ROOT/resources/views/" --include="*.blade.php" 2>/dev/null \
  | grep -vE 'x-on:|data-|//|<!--' | wc -l

# Tailwind classes arbitrarias
grep -roh '\[[^]]*\]' "$PROJECT_ROOT/resources/views/" --include="*.blade.php" 2>/dev/null | wc -l

# PWA
test -f "$PROJECT_ROOT/public/manifest.json" && echo "manifest OK" || echo "manifest MISSING"
test -f "$PROJECT_ROOT/public/sw.js" && echo "sw OK" || echo "sw MISSING"
```

**Scoring:** build falha = 0; senao base 100; img sem alt -2 (cap -15); FOUC -1 (cap -10); inline -3 (cap -20); PWA ausente -5 cada.
**Percalcos:** TBL-007, TBL-008.
**KPIs:** `build_ok`, `bundle_size_kb`, `images_no_alt`, `fouc_risk`, `inline_handlers`, `pwa_ready`

## D7 — Infrastructure & Deploy

```bash
$SSH_PROD "sudo docker ps --filter name=poprua-cras --format 'table {{.Names}}\t{{.Status}}'" 2>/dev/null || true
$SSH_PROD "sudo docker exec pg17-poprua-cras pg_isready -U poprua_cras" 2>/dev/null || true
$SSH_PROD "sudo docker exec redis-poprua-cras redis-cli ping" 2>/dev/null || true
$SSH_PROD "sudo docker ps --filter name=queue-poprua-cras --format '{{.Status}}'" 2>/dev/null || true
$SSH_PROD "echo | openssl s_client -connect sufis.pbh.gov.br:443 -servername sufis.pbh.gov.br 2>/dev/null | openssl x509 -noout -dates" 2>/dev/null || true
$SSH_PROD "df -h /var | tail -1" 2>/dev/null || true
$SSH_PROD "sudo du -sh /var/www/html/joomla_sufis/ginfi/poprua-cras/storage/logs/" 2>/dev/null || true
# TBL-031: backups reais em /opt/docker/poprua-cras/backups/, NAO em /var/backups/
$SSH_PROD "ls -lt /opt/docker/poprua-cras/backups/*.dump 2>/dev/null | head -3 || echo no_backups"
$EXEC php artisan about --only=environment 2>&1 | head -5 || true
$SSH_PROD "test -f /var/www/html/joomla_sufis/ginfi/poprua-cras/bootstrap/cache/config.php && echo OK || echo MISSING" 2>/dev/null || true
```

**Scoring:** se security-infra-audit.json existe usa-o; senao base 100; -20 CRITICO, -10 ALTO, -5 MEDIO; container down -20; SSL < 30d -10; disk > 90% -15; config nao cacheado -5.
**Percalcos:** TBL-012.
**KPIs:** `containers_running`, `pg_ok`, `redis_ok`, `queue_worker_ok`, `ssl_days_remaining`, `disk_usage_pct`, `config_cached`, `log_size_mb`

## D8 — Homologation Coverage

```bash
cd "$PROJECT_ROOT"
ls -d .claude/skills/test-* .claude/skills/homologar-* 2>/dev/null | wc -l
ls -d .claude/skills/vistoria .claude/skills/morador .claude/skills/ponto 2>/dev/null | wc -l
find app/Http/Controllers -name "*Controller.php" -not -path "*/Auth/*" 2>/dev/null | wc -l
find tests/Feature -name "*Test.php" 2>/dev/null | wc -l
```

**Scoring:** ratio = (skills + feature tests)/controllers * 100 (cap 100); +5 se existe skill `vistoria`; +5 se `morador` ou `ponto`; +5 se existe esta skill `quality-audit`.
**KPIs:** `homolog_skills_count`, `controllers_count`, `feature_tests_count`, `coverage_ratio`
