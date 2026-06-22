---
name: quality-audit
description: >
  Auditoria completa de qualidade do POPRUA CRAS com notas 0-100 por dimensao, KPIs
  mensuraveis, matriz Impacto x Esforco, e tracking de tendencia entre auditorias.
  Detecta automaticamente se esta rodando no host ou dentro do container e ajusta
  os comandos. Cobre 8 dimensoes: code quality, testes, seguranca, arquitetura
  Laravel, integridade geoespacial PostGIS, frontend (Vite/Leaflet/Alpine), infra
  Docker, e cobertura de homologacao. Usa agentes paralelos, com diagnostico
  automatico de percalcos e estrategias de recuperacao.
  Use sempre que o usuario pedir para analisar qualidade, avaliar codigo, fazer
  auditoria, dar nota ao projeto, verificar saude do codigo. Variacoes:
  'harness audit', 'auditoria de qualidade', 'quality audit', 'analisar qualidade',
  'avaliar codigo', 'code quality', 'nota do projeto', 'score do projeto',
  'saude do projeto', 'diagnostico do codigo', 'code health', 'code audit',
  'analise tecnica', 'revisao geral', 'quanto ta o codigo', 'como esta o projeto',
  'review geral', 'avaliar arquitetura', 'avaliar seguranca', 'avaliar geo',
  'avaliar postgis'.
user-invocable: true
allowed-tools: Read, Grep, Bash, Write, Edit, Agent
argument-hint: [iterar|stale|quick-wins|debug]
version: 1.4.1
---

# Quality Audit — POPRUA CRAS

Auditoria estruturada em **8 dimensoes**, cada uma com nota 0-100. Roda no host (via `docker exec`) OU dentro do container (comandos diretos) — auto-detectado no PASSO -1. Agrega sub-audits e checagens diretas. Produz matriz de prioridade, tendencia entre execucoes e log de percalcos.

**Stack:** Laravel 12 / PHP 8.4 / PostgreSQL 17 + PostGIS 3.5 / Redis / Vite. Container app: `php84-poprua-cras`. SSH prod: `ssh sufis-poprua-cras`. Dominio: **Ponto -> Vistoria -> Morador** com geometrias SRID 4326.

## Fluxo geral

```
-1. Pre-flight (detector hibrido + checagens criticas; aborta com diagnostico se algo critico falhar)
 0. Ler ultimo summary.json (baseline para trending)
 1. Coletar JSONs de sub-audits existentes
 2. Rodar 8 dimensoes em paralelo via agentes (2 batches de 4)
 3. Agregar scores -> summary.json  [bash arithmetic — TBL-030]
 4. Calcular matriz Impacto x Esforco (Q1/Q2/Q3/Q4)
 4.5. [modo iterar] Aplicar Quick Wins via sub-skills (rotear por tipo)
 5. Gerar relatorio com trending + percalcos + sub-skill log
 6. [modo iterar] Re-audit das dimensoes afetadas; calcular delta
 7. Snapshot historico
```

### Sub-skills integradas

| Finding type | Dimensao | Sub-skill | Quando acionar |
|---|---|---|---|
| Controllers com DB direto, N+1, arquitetura | D1, D4 | `/simplify` | Q1 com esforco <= 2h |
| Vulnerabilidades, CSP, headers | D3 | `/security-review` | finding CRITICO ou ALTO |
| Verificar que fix nao quebrou testes | D2 | `/verify` | apos qualquer fix de codigo |
| Pontos orfaos, schema geo | D5 | `/poprua-etl` | orphan_pontos > 50 |
| Config:cache, healthcheck, OPcache | D7 | bash direto | quick win < 5 min |
| Inline handlers, imgs sem alt | D6 | `/simplify` | esforco <= 2h |

---

## PASSO -1 — Detector hibrido + Pre-flight

Antes de qualquer checagem, descobrir onde estamos rodando e validar dependencias.

### Detector: host vs container

```bash
# Detector inline (NAO usar funcao com return + $(...) — TBL-023)
RUNTIME="host"
if [ -f /.dockerenv ] || grep -qa 'docker\|containerd' /proc/1/cgroup 2>/dev/null; then
  if [ "$(hostname)" = "php84-poprua-cras" ] || { [ -d /var/www/html/ ] && php -v 2>/dev/null | grep -q "PHP 8\.4"; }; then
    RUNTIME="container"
  fi
fi
```

### Resolver variaveis conforme runtime

```bash
PROJECT_ROOT_HOST="/var/www/html/joomla_sufis/ginfi/poprua-cras"

if [ "$RUNTIME" = "container" ]; then
  # Dentro do container o codigo eh bind-mounted no MESMO path do host (validado).
  PROJECT_ROOT="$PROJECT_ROOT_HOST"
  EXEC=""                                                              # comandos diretos
  DB_EXEC="psql -h pg17-poprua-cras -U poprua_cras"                    # via rede docker
  IN_CONTAINER=1
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
```

**Nota sobre $EXEC vazio no container:** quando `RUNTIME=container`, `$EXEC php artisan ...` vira ` php artisan ...` (espaco no inicio, valido). Usar sempre como `$EXEC php ...`, nao `${EXEC} php ...`.

### Checks criticos

```bash
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
```

### Matriz de severidade

| Check | FAIL = | WARN = | Acao automatica |
|-------|--------|--------|-----------------|
| artisan | abortar | — | repositorio incompleto: avisar usuario |
| php | abortar | — | container parado: `sudo docker start php84-poprua-cras && sleep 3` e reentrar |
| vendor | abortar | — | `$EXEC composer install --no-interaction` |
| env | abortar | — | copiar `.env.example` -> `.env`; `$EXEC php artisan key:generate` |
| db | abortar | — | container parado: `sudo docker start pg17-poprua-cras && sleep 3` e reentrar |
| db-test | — | degrada D2 (so pass-rate) | criar DB: `CREATE DATABASE poprua_cras_test`; migrar |
| postgis | abortar D5 (so) | — | `CREATE EXTENSION postgis` no DB |
| node | — | degrada D6 | `$EXEC npm install --no-audit --no-fund` |
| ssh-prod | — | degrada D3/D7 | sem auto-fix; checagens locais apenas |

### Saida do pre-flight

Gravar `$AUDITS_DIR/env-report.json`:
```json
{
  "timestamp": "2026-05-19T10:00:00-03:00",
  "runtime": "host",
  "checks": {
    "artisan": "OK", "php": "OK", "vendor": "OK", "env": "OK",
    "db": "OK", "db_test": "WARN", "postgis": "OK",
    "node": "OK", "ssh_prod": "WARN"
  },
  "degraded_dimensions": ["test-coverage", "security", "infrastructure"],
  "aborted": false,
  "abort_reason": null
}
```

Se aborted=true: parar e mostrar diagnostico + comando de correcao sugerido.
Se so WARN: continuar, marcar dimensoes afetadas com `degraded: true` (badge no relatorio, score cap em 80).

---

## Tabela de Percalcos (TBL-001..TBL-020)

Cada agente DEVE consultar essa tabela quando encontrar erro. Sintoma -> diagnostico -> solucao. Apos aplicar, append em `$INCIDENTS_LOG`:
```
YYYY-MM-DDTHH:MM:SS-03:00 | <dimensao> | TBL-XXX | <acao-tomada>
```

### TBL-001 — `Container is not running`
- **Sintoma:** `Error response from daemon: container ... is not running`
- **Causa:** container app/db parado
- **Fix:** `sudo docker start php84-poprua-cras pg17-poprua-cras && sleep 3`; se persistir, abortar e pedir `sudo docker logs <container> --tail 50`

### TBL-002 — `Permission denied` em `.claude/audits/*.json`
- **Sintoma:** EACCES no Write tool
- **Causa:** diretorio criado por root/www-data
- **Fix:** `sudo chown -R $(whoami):$(whoami) $AUDITS_DIR`. Se persistir, gravar em `/tmp/audit-<x>.json` e logar caminho

### TBL-003 — `pint --test` retorna JSON sem `files`
- **Causa:** schema novo ou lista vazia
- **Fix:** parse defensivo `jq '.files // [] | length'`; zero = caminho feliz

### TBL-004 — PHPStan `Memory limit exceeded`
- **Fix:** `$EXEC php -d memory_limit=1G vendor/bin/phpstan analyse --error-format=json`; se persistir, `$EXEC rm -rf storage/phpstan` e reanalisar

### TBL-005 — `database "poprua_cras_test" does not exist`
- **Fix automatico:**
  ```bash
  $DB_EXEC -d poprua_cras -c "CREATE DATABASE poprua_cras_test OWNER poprua_cras;"
  $DB_EXEC -d poprua_cras_test -c "CREATE EXTENSION IF NOT EXISTS postgis;"
  $EXEC php artisan migrate --env=testing --no-interaction
  ```
- Se persistir: D2 degradada com cap 50

### TBL-006 — `No code coverage driver available`
- **Causa:** PCOV/Xdebug nao instalado
- **Fix:** rodar sem coverage; D2 com pass rate peso 100% (ignorar coverage)

### TBL-007 — `npm run build` `EACCES` em `node_modules`
- **Fix:** `$EXEC chown -R www-data:www-data node_modules` ou `sudo chown -R $(whoami):$(whoami) node_modules`

### TBL-008 — `npm run build` `Cannot find module`
- **Fix:** `$EXEC npm install --no-audit --no-fund`

### TBL-009 — `npm audit` retorna JSON malformado
- **Causa:** proxy ou registry indisponivel
- **Fix:** tentar com `--registry=https://registry.npmjs.org/`; se persistir, marcar `npm_audit_unavailable=true` em vez de zerar

### TBL-010 — `geometry_columns` vazia mas tabelas existem
- **Fix:** `$DB_EXEC -d poprua_cras -c "SELECT Populate_Geometry_Columns();"`

### TBL-011 — `ST_IsValid: function does not exist`
- **Causa:** coluna e `geography`, nao `geometry`
- **Fix:** sempre castar: `ST_IsValid(geom::geometry)`

### TBL-012 — SSH prod `Connection timed out`
- **Fix:** degradar D3/D7; KPI `ssh_unreachable=true`; logar e NAO reintentar

### TBL-013 — `git ls-files --error-unmatch .env` exit 1
- **Causa:** comportamento esperado quando .env NAO esta tracked
- **Fix:** logica correta: `git ls-files --error-unmatch .env >/dev/null 2>&1 && echo CRITICO || echo OK`

### TBL-014 — Agente concluiu mas JSON ausente
- **Fix:** orquestrador checa existencia; se ausente, usa o texto de retorno do agente como fallback (parse menos confiavel) e loga

### TBL-015 — Pre-commit hook reverte auto-fixes do modo `iterar`
- **Fix:** NUNCA usar `--no-verify`. Rodar `$EXEC vendor/bin/pint --dirty` ANTES do `git add` para alinhar

### TBL-016 — Score regride sem mudanca de codigo
- **Causa:** dep atualizou (composer/npm audit) ou cache stale do PHPStan
- **Fix:** comparar `composer.lock` em snapshots; log do que mudou

### TBL-017 — Counts inconsistentes em `grep -r`
- **Fix:** sempre `... | wc -l` direto; nao capturar saida completa em variavel

### TBL-018 — Auditoria > 5 minutos
- **Fix:** timeout 120s por agente; agentes que excedem reportam parcial e marcam degradada

### TBL-019 — `phpstan-baseline.neon` mascara regressoes
- **Fix:** KPI `phpstan_baseline_size`; finding MEDIO se cresceu vs auditoria anterior

### TBL-020 — Findings duplicados na Q1
- **Fix:** dedup por `(file, line, ~title)` mantendo o de maior severidade

### TBL-021 — `vendor/bin/pint: no such file or directory` mesmo com vendor OK
- **Sintoma:** `OCI runtime exec failed: stat vendor/bin/pint: no such file or directory`
- **Causa:** `WorkingDir` do container e `/var/www/html` mas o codigo Laravel esta em `/var/www/html/joomla_sufis/ginfi/poprua-cras/`. `vendor/` resolve relativo ao WORKDIR.
- **Fix:** sempre incluir `-w "$PROJECT_ROOT_HOST"` no prefixo `$EXEC`. Validado: com `-w` aplicado, `vendor/bin/pint --test` passa (181 files).
- **Lemma:** NUNCA `cd $PROJECT_ROOT && $EXEC ...` — o `cd` aplica no host, nao no container. Use `-w`.

### TBL-023 — `detect_runtime()` retorna string vazia
- **Sintoma:** `RUNTIME=` (vazio) apos `RUNTIME=$(detect_runtime)`, mesmo com a logica aparentando estar correta.
- **Causa:** funcoes shell com `echo + return` combinadas com command substitution `$(...)` podem perder a saida quando o `if`/`fi` interno falha silenciosamente (set +e + agrupamentos `{...}`).
- **Fix:** sempre usar **detector inline** (RUNTIME="host"; if ...; then RUNTIME="container"; fi), nao funcao. Validado: 2 execucoes em sequencia, RUNTIME corretamente populado.

### TBL-024 — `npm/node` ausentes no container — **RESOLVIDO em 2026-05-19**
- **Resolucao:** Node 22 instalado via NodeSource no `docker/Dockerfile` (commit). `$EXEC npm install` e `$EXEC npm run build` funcionam diretamente. D6 buildar de verdade, sem `degraded:true`.
- **Historico:** o workaround anterior era validar bundles existentes em `public/build/assets/*` sem rebuildar.
- **D2 / Coverage:** `pecl install pcov` agora vem na imagem; `$EXEC php artisan test --coverage` funciona; TBL-006 nao se aplica mais.

### TBL-025 — Python no host antigo — **RESOLVIDO em 2026-05-19**
- **Resolucao:** o orquestrador roda dentro do container do app (que tem Python 3.13). Workaround de bash arithmetic ainda funciona, mas nao e mais obrigatorio. TBL-030 segue valida para scripts que precisam rodar especificamente no host.

### TBL-026 — `php artisan migrate --env=testing` nao respeita `DB_DATABASE` do `phpunit.xml`
- **Sintoma:** apos `artisan migrate --env=testing`, a migration aparece como "Ran" em `migrate:status --env=testing`, mas a coluna nao existe no DB de teste real.
- **Causa:** `--env=testing` carrega `.env.testing` (se existir) ou cai no `.env` padrao. NAO le `phpunit.xml`, que so e aplicado durante execucao do PHPUnit. Resultado: migration roda contra o DB dev e ja vai mascarada na proxima.
- **Fix opcao A (preferida):** criar `.env.testing` com `DB_DATABASE=poprua_cras_test`.
- **Fix opcao B (aplicado ate criar .env.testing):** aplicar a alteracao direto via SQL no DB test:
  ```bash
  $DB_EXEC -d poprua_cras_test -c "ALTER TABLE pontos ADD COLUMN IF NOT EXISTS deleted_at timestamp(0);"
  ```
- **Detector futuro:** comparar `migrate:status --env=testing` com `information_schema.columns` do DB test apos a migration; divergencia = TBL-026 ativo.

### TBL-027 — Pattern de inline handlers divergente entre iteracoes
- **Sintoma:** D3 ou D6 reporta delta de inline handlers (ex: 70 -> 84) sem mudanca real de codigo.
- **Causa:** o agente expandiu o regex (incluiu `oninput|onload|onkeyup|onkeydown` etc) em vez de manter o pattern original (`onclick|onchange|onsubmit|onerror`).
- **Fix:** **CONGELAR** o pattern no SKILL.md: somente `onclick=|onchange=|onsubmit=|onerror=`. Qualquer extensao do escopo exige bump de versao da skill e e reportada explicitamente no relatorio. Agentes devem usar o regex EXATO da SKILL.md, sem "melhorias".

### TBL-028 — Falso positivo de "img sem alt" em tags com Blade `{{ $obj->method() }}`
- **Sintoma:** D6 reporta 8 imagens sem alt, mas inspecao manual mostra que so 1 nao tem.
- **Causa:** o regex `<img\b(?:(?!>).){0,300}>` para no PRIMEIRO `>` apos `<img`. Em `src="{{ $foto->getUrl() }}"`, o `>` do operador `->` corta o match cedo demais e o `alt=` que vem depois nao e considerado.
- **Fix:** usar regex que aceita blocos `{{...}}` no meio: `<img\b(?:[^>{]|\{\{[^}]*\}\})*?>`. Validado: contagem caiu de 8 (falso) para 0 (real).
- **Aplicar tambem em outras buscas em blades** que precisam reconhecer atributos com Blade interpolation.

### TBL-029 — Falso positivo de "FOUC" em componentes Alpine com `display:none` inline
- **Sintoma:** D6 reporta `x-data` sem `x-cloak` em dropdown/modal, sugerindo FOUC.
- **Causa:** a checagem nao detecta `style="display: none; ..."` inline que ja resolve o flash do conteudo controlado por `x-show`. Adicionar `x-cloak` na div root esconderia ate o trigger.
- **Fix:** considerar a checagem FOUC como heuristica BAIXA. Se o componente tem `x-show=` + `style="display: none"` inline, NAO penalizar. Implementacao futura: na checagem `xargs grep -L 'x-cloak'`, adicionar `grep -L 'display:\s*none'` em pipeline OR para zerar falso positivo.
- **Workaround atual:** marcar findings de FOUC como severidade BAIXO em vez de MEDIO, ja que a maioria sao falso positivos.

### TBL-030 — Node.js e Python 3 ausentes no host — **PARCIALMENTE RESOLVIDO em 2026-05-19**
- **Resolucao no container:** Node 22 e Python 3.13 vem no Dockerfile. `$EXEC python3` e `$EXEC node` funcionam.
- **No host (Debian 9) o problema persiste:** se o orquestrador rodar `python3 -c` ou `node` diretamente no host, ainda quebra. Mantenha bash arithmetic no PASSO 3 quando o quality-audit roda de fora dos containers. Scripts de analise (D3/D6) podem usar `$EXEC python3` sem ressalvas.

### TBL-022 — Tabelas TIGER census poluem D5
- **Sintoma:** D5 reporta dezenas de SRID 4269 (county, state, zcta5, tabblock20, ...) como findings.
- **Causa:** imagem `postgis/postgis:17-3.5` vem com Tiger geocoder extension + dados de exemplo. Sao dados de referencia US, nao do dominio CRAS.
- **Fix:** filtrar via whitelist `$GEO_CRAS_TABLES = ('pontos','endereco_atualizados','geo_bairros','geo_regionais','geo_limite_municipio')` em TODAS as queries de D5.
- **Quando isso muda:** se o dominio adicionar novas tabelas geometricas, atualizar a whitelist na skill.

### Novos percalcos

Encontrou algo nao listado? Append em `$INCIDENTS_LOG` com id provisorio `TBL-NEW-<short-hash>` e descrever no relatorio. Apos confirmacao do usuario, adicionar entrada permanente nesta secao.

---

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

## As 8 Dimensoes

### D1 — Code Quality

**Fonte:** pint + phpstan

```bash
$EXEC vendor/bin/pint --test --format=json 2>&1 || true
$EXEC vendor/bin/phpstan analyse --error-format=json --no-progress 2>&1 || true
wc -l "$PROJECT_ROOT/phpstan-baseline.neon" 2>/dev/null
```

**Scoring:** Base 100; pint -1/violacao (cap -30); phpstan -2/erro (cap -40); bonus +5 cada se zero.
**Percalcos:** TBL-003, TBL-004, TBL-019.
**KPIs:** `pint_violations`, `phpstan_errors`, `phpstan_level`, `phpstan_baseline_size`

### D2 — Test Coverage & Health

**Fonte:** `artisan test` + clover XML opcional

```bash
$EXEC php artisan test 2>&1 || true
$EXEC php artisan test --coverage-clover=/tmp/coverage.xml 2>&1 || true
```

**Scoring:** pass rate 60% + coverage 40%; -5/failure; zero tests = 0.
**Percalcos:** TBL-005, TBL-006.
**KPIs:** `test_count`, `pass_rate`, `fail_count`, `line_coverage_pct`

### D3 — Security

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

### D4 — Architecture & Patterns

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

### D5 — Geospatial Data Integrity (PostGIS)

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

# Pontos orfaos (sem EnderecoAtualizado vinculado)
# FK real: pontos.endereco_atualizado_id -> endereco_atualizados.id
# (NAO existe endereco_atualizados.ponto_id — erro corrigido em 2026-06-22)
$DB_EXEC -d poprua_cras -t -c "
  SELECT count(*) FROM pontos p
  LEFT JOIN endereco_atualizados e ON e.id = p.endereco_atualizado_id
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

### D6 — Frontend Quality

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

### D7 — Infrastructure & Deploy

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

### D8 — Homologation Coverage

```bash
cd "$PROJECT_ROOT"
ls -d .claude/skills/test-* .claude/skills/homologar-* 2>/dev/null | wc -l
ls -d .claude/skills/vistoria .claude/skills/morador .claude/skills/ponto 2>/dev/null | wc -l
find app/Http/Controllers -name "*Controller.php" -not -path "*/Auth/*" 2>/dev/null | wc -l
find tests/Feature -name "*Test.php" 2>/dev/null | wc -l
```

**Scoring:** ratio = (skills + feature tests)/controllers * 100 (cap 100); +5 se existe skill `vistoria`; +5 se `morador` ou `ponto`; +5 se existe esta skill `quality-audit`.
**KPIs:** `homolog_skills_count`, `controllers_count`, `feature_tests_count`, `coverage_ratio`

---

## PASSO 0..1 — Baseline e sub-audits

```bash
cat "$AUDITS_DIR/summary.json" 2>/dev/null || echo '{"iteration": 0, "dimensions": {}}'
ls -la "$AUDITS_DIR"/*.json 2>/dev/null
```

JSONs com timestamp > 7 dias = **stale**.

## PASSO 2 — Disparar agentes

**Batch 1 (paralelo):** D1, D2, D4, D6
**Batch 2 (paralelo):** D3, D5, D7, D8

### Prompt template para agentes

> Voce e o agente da dimensao **<NOME>** do quality-audit do POPRUA CRAS.
>
> Variaveis exportadas (use-as como referencia, ja resolvidas pelo orquestrador):
> - `RUNTIME` = "<host|container>"
> - `EXEC` = "<prefixo ou vazio>"
> - `DB_EXEC` = "<prefixo>"
> - `SSH_PROD` = "ssh sufis-poprua-cras"
> - `PROJECT_ROOT`, `AUDITS_DIR`, `INCIDENTS_LOG`
>
> 1. Leia `$AUDITS_DIR/env-report.json` — se sua dimensao esta na lista `degraded_dimensions`, marque `degraded: true` no JSON de saida.
> 2. Rode os blocos bash da sua dimensao (copiados da SKILL.md).
> 3. Para cada erro encontrado, consulte TBL-001..TBL-020. Aplique solucao automatica quando seguro. Logue em `$INCIDENTS_LOG`.
> 4. Calcule o score conforme a formula da dimensao.
> 5. Escreva `$AUDITS_DIR/harness-<dimension>.json` no contrato.
> 6. Retorne ao orquestrador: top 5 findings + score + KPIs + incidents.
>
> Timeout: 120s. Se exceder, escreva JSON parcial com `degraded: true`.
> NAO toque em outras dimensoes. NAO escreva fora de `$AUDITS_DIR`.

## PASSO 3 — Agregar

**CRITICO — TBL-030 + TBL-025:** Python 3.5 no host nao suporta f-strings. Node.js nao existe no host. Use **exclusivamente bash arithmetic** para agregar scores. Nao tente `python3 -c` nem `node` no host para este passo.

```bash
# Calculo ponderado via bash integer arithmetic (x100 para evitar decimais)
# security=20% code-quality=15% test-coverage=15% architecture=15%
# geo-integrity=10% infrastructure=10% frontend=10% homologation=5%
SC_SEC=81; SC_CQ=81; SC_TC=100; SC_AR=86
SC_GEO=85; SC_INF=67; SC_FE=78; SC_HOM=84
OVERALL=$(( (SC_SEC*20 + SC_CQ*15 + SC_TC*15 + SC_AR*15 + SC_GEO*10 + SC_INF*10 + SC_FE*10 + SC_HOM*5) / 100 ))

# Ler iteracao anterior do summary.json
PREV_ITER=$(grep '"iteration"' "$AUDITS_DIR/summary.json" 2>/dev/null | grep -o '[0-9]*' | head -1 || echo 0)
ITER=$(( PREV_ITER + 1 ))

# Gravar summary.json usando heredoc (bash-safe, sem dependencias)
cat > "$AUDITS_DIR/summary.json" << JSONEOF
{
  "iteration": $ITER,
  "timestamp": "$(date +%Y-%m-%dT%H:%M:%S-03:00)",
  "project": "poprua-cras",
  "runtime": "$RUNTIME",
  "overall_score": $OVERALL,
  "dimensions": {
    "security":              {"score": $SC_SEC},
    "code-quality":          {"score": $SC_CQ},
    "test-coverage":         {"score": $SC_TC},
    "architecture":          {"score": $SC_AR},
    "geo-integrity":         {"score": $SC_GEO},
    "infrastructure":        {"score": $SC_INF},
    "frontend":              {"score": $SC_FE},
    "homologation-coverage": {"score": $SC_HOM}
  },
  "quick_wins": [],
  "critical_findings": []
}
JSONEOF
```

Preencha os campos `score` com os valores reais retornados pelos agentes antes de gravar. O template acima usa variaveis de exemplo — substitua pelos scores reais. Adicione `quick_wins` e `critical_findings` a partir dos findings dos agentes.

Apos os 8 agentes, escrever `$AUDITS_DIR/summary.json`:

```json
{
  "iteration": 1,
  "timestamp": "2026-05-19T16:00:00-03:00",
  "project": "poprua-cras",
  "runtime": "host",
  "overall_score": 82,
  "env_report": { },
  "dimensions": {
    "code-quality":         { "score": 91, "delta": "+3",  "degraded": false, "kpis": {} },
    "test-coverage":        { "score": 74, "delta": "-2",  "degraded": false, "kpis": {} },
    "security":             { "score": 88, "delta": "+5",  "degraded": false, "kpis": {} },
    "architecture":         { "score": 79, "delta": "0",   "degraded": false, "kpis": {} },
    "geo-integrity":        { "score": 87, "delta": "+12", "degraded": false, "kpis": {} },
    "frontend":             { "score": 85, "delta": "+1",  "degraded": false, "kpis": {} },
    "infrastructure":       { "score": 90, "delta": "0",   "degraded": true,  "kpis": {} },
    "homologation-coverage":{ "score": 62, "delta": "+4",  "degraded": false, "kpis": {} }
  },
  "all_findings": [],
  "quick_wins": [],
  "stale_dimensions": [],
  "incidents_summary": { }
}
```

**Pesos:**

| Dimensao              | Peso |
|-----------------------|------|
| Security              | 20%  |
| Code Quality          | 15%  |
| Test Coverage         | 15%  |
| Architecture          | 15%  |
| Geo Integrity         | 10%  |
| Infrastructure        | 10%  |
| Frontend              | 10%  |
| Homologation Coverage | 5%   |

## PASSO 4 — Matriz Impacto x Esforco

```
              ALTO IMPACTO
                   |
   Q2 — Projetos   Q1 — Quick Wins
   (alto esforco)  (baixo esforco)
                   |
  -----------------+----------------
                   |
   Q4 — Ignorar    Q3 — Fill-ins
   (alto esforco)  (baixo esforco)
                   |
              BAIXO IMPACTO
```

**Impacto:** CRITICO=10, ALTO=7, MEDIO=4, BAIXO=2
**Esforco:** Baixo <= 2h, Medio 2-8h, Alto > 8h
**Q1:** top 5 com impacto >= 4 e esforco <= 2h, dedup por `(file, line, ~title)` (TBL-020).

## PASSO 5 — Relatorio + snapshot

```bash
cp "$AUDITS_DIR/summary.json" "$AUDITS_DIR/history/summary-$(date +%Y-%m-%dT%H%M).json"
```

### Formato do relatorio

```
## Quality Audit — POPRUA CRAS
**Iteracao:** #N | **Data:** YYYY-MM-DD HH:MM | **Runtime:** host/container | **Score geral:** XX/100

### Pre-flight
- Containers: app OK, db OK
- SSH prod: WARN (timeout)
- Dimensoes degradadas: infrastructure, security (parcial)

### Scores por Dimensao

| # | Dimensao              | Score | Delta | Status     |
|---|----------------------|-------|-------|------------|
| 1 | Security             | XX    | +/-N  | OK/WARN/CRIT |
| 2 | Code Quality         | XX    | +/-N  | ...         |
| 3 | Test Coverage        | XX    | +/-N  | ...         |
| 4 | Architecture         | XX    | +/-N  | ...         |
| 5 | Geo Integrity        | XX    | +/-N  | ...         |
| 6 | Infrastructure       | XX    | +/-N  | ... [degradada] |
| 7 | Frontend             | XX    | +/-N  | ...         |
| 8 | Homologation Coverage| XX    | +/-N  | ...         |

Status: OK >= 80 | WARN 60-79 | CRIT < 60

### Quick Wins (Q1)

| # | Finding | Dimensao | Severidade | Esforco | Acao |
|---|---------|----------|------------|---------|------|

### Percalcos desta auditoria

| TBL-id | Dimensao | Acao | Resolvido? |
|--------|----------|------|------------|

### Dimensoes Stale

### KPIs Consolidados
```

---

## PASSO 4.5 — Aplicar Quick Wins via Sub-skills  *(modo iterar apenas)*

Apos calcular a matriz Q1, rotear cada finding para a sub-skill correta e aplicar. Limite: **5 quick wins por iteracao** (para evitar cascata de mudancas nao verificadas).

### Algoritmo de roteamento

```
Para cada finding em Q1 (ordenado por impacto DESC, esforco ASC):
  1. Classificar o tipo do finding (tabela abaixo)
  2. Aplicar via sub-skill ou bash direto
  3. Rodar /verify imediatamente apos
  4. Se /verify falhar: reverter (git checkout), logar, pular para proximo
  5. Commitar o fix com mensagem "fix(quality-audit): <finding-id> <titulo>"
```

### Roteamento por tipo de finding

**Infra — bash direto** (esforco < 5 min, sem risco de regressao de codigo):
```bash
# INF-001: config:cache — APENAS em prod via SSH (local quebra testes — Licao #23)
ssh sufis-poprua-cras "sudo docker exec -u root -w /var/www/html/joomla_sufis/ginfi/poprua-cras php84-poprua-cras php artisan config:cache --no-interaction"
# /verify NAO necessario para config:cache (nao altera codigo)

# INF-003: healthcheck queue misconfigured
# Editar docker-compose.yml: substituir `curl localhost:8080` por `pgrep -f queue:work`
# Restartar: ssh sufis-poprua-cras "sudo docker compose up -d queue-poprua-cras"

# INF-006: OPcache no queue
# Adicionar PHP_OPCACHE_ENABLE=1 no environment do servico queue no docker-compose.yml
```

**Code Quality / Architecture — `/simplify`**:
- Acionar quando: `controllers_with_db > 2`, `raw_queries_in_models > 0`, `phpstan_errors > 0`
- Passar o arquivo especifico: `/simplify app/Http/Controllers/VistoriaController.php`
- Sempre rodar `/verify` apos o simplify antes de commitar

**Security — `/security-review`**:
- Acionar quando: finding CRITICO ou ALTO em D3 (CSP, headers, vulns)
- Nao auto-aplicar — `/security-review` produz relatorio; implementacao e manual ou via `/simplify`

**Geo — `/poprua-etl`**:
- Acionar quando: `orphan_pontos > 50`
- Modo dry-run primeiro; apply somente apos confirmacao

**Testes — `/verify`**:
- Acionar SEMPRE apos qualquer fix de codigo (exceto bash puro de infra)
- Se falhar: reverter e registrar em `$INCIDENTS_LOG` como `TBL-NEW-VERIFY-FAIL`

### Log de sub-skills

Registrar em `$AUDITS_DIR/subskills-log.json` a cada iteracao:
```json
{
  "iteration": 2,
  "applied": [
    {"finding": "INF-001", "skill": "bash", "duration_s": 3,  "verify": "skip", "status": "ok"},
    {"finding": "cq-007",  "skill": "simplify", "duration_s": 120, "verify": "pass", "status": "ok"},
    {"finding": "fe-001",  "skill": "simplify", "duration_s": 180, "verify": "pass", "status": "ok"}
  ],
  "skipped": [
    {"finding": "INF-005", "reason": "requer decisao humana (backup strategy)"}
  ]
}
```

---

## PASSO 6 — Re-audit delta  *(modo iterar apenas)*

Apos aplicar os fixes, re-rodar **apenas as dimensoes afetadas** (nao todas as 8) para medir o ganho real.

### Identificar dimensoes afetadas

```bash
# Dimensoes mapeadas pelos findings aplicados
# INF-001/003/006 -> infrastructure
# cq-007 -> code-quality + architecture
# fe-001 -> frontend + security (CSP)
DIMS_AFETADAS="infrastructure code-quality architecture frontend"
```

### Re-rodar agentes seletivos

Disparar um agente por dimensao afetada (em paralelo), com o mesmo prompt template do PASSO 2, mas adicionando no inicio:

> "Esta e uma RE-AUDITORIA parcial. O baseline e o score da iteracao anterior: <score>. Foque em verificar se os fixes aplicados resolveram os findings reportados. Liste explicitamente o que mudou."

### Calcular e exibir delta

```bash
# Atualizar apenas as dimensoes re-auditadas no summary.json
# Recalcular overall com os novos scores
# Exibir tabela de delta:
echo "=== DELTA POS-FIX ==="
echo "| Dimensao        | Antes | Depois | Delta |"
echo "|-----------------|-------|--------|-------|"
# ... preencher com valores reais

# Criterio de parada do loop iterar:
# - delta_overall < 2 pts -> parar (convergiu)
# - iteracao >= 5 -> parar (limite de seguranca)
# - nenhum Q1 restante -> parar (esgotou quick wins)
```

### Snapshot pos-fix

```bash
cp "$AUDITS_DIR/summary.json" "$AUDITS_DIR/history/summary-$(date +%Y-%m-%dT%H%M)-pos-fix.json"
```

---

## Modos de invocacao

- `quality-audit` — auditoria completa (PASSOS -1..5 + 7); **sem** aplicar fixes
- `quality-audit iterar` — fluxo completo com fixes: audit → Q1 → sub-skills (PASSO 4.5) → re-audit (PASSO 6) → loop ate delta < 2 ou 5 iteracoes
- `quality-audit stale` — so lista stale + comandos para atualizar
- `quality-audit quick-wins` — so matriz Q1 (sem rodar dimensoes — usa summary.json existente)
- `quality-audit debug` — so PASSO -1 (pre-flight) + dump do env-report.json
- `quality-audit <dimensao>` — re-auditar uma dimensao especifica (ex: `quality-audit infrastructure`)

---

## Convencoes especificas do POPRUA CRAS

1. **Detector hibrido:** `RUNTIME=host` -> `docker exec`; `RUNTIME=container` -> direto. NUNCA hardcodar prefixo no codigo da skill.
2. **DB de teste:** `poprua_cras_test` (auto-criar via TBL-005 se ausente).
3. **PostGIS:** SRID 4326 + GIST sao requisitos. D5 nao e opcional.
4. **PHPStan level 6 com baseline:** medir `phpstan_baseline_size` para detectar crescimento (TBL-019).
5. **SSH sidecar:** `sufis-poprua-cras` -> container; para host de prod, usar `ssh sufis` direto.
6. **`git ls-files --error-unmatch`:** exit 1 = sucesso (arquivo NAO tracked). Inverter logica (TBL-013).

## Gitignore

Adicionar:
```
.claude/audits/
```

---

## Licoes (manter atualizadas)

1. npm audit tao importante quanto composer audit — supply chain.
2. CSP inline handlers = finding frontend mais impactante (bloqueia CSP enforce).
3. MEDIO + baixo esforco = quick wins mais valiosos por hora; threshold de impacto >= 4.
4. Contar `test-*` E `homologar-*` em D8.
5. Imagens sem alt: regex DOTALL multi-line, nao grep linha simples.
6. **Tudo roda em container** — esquecer prefixo `docker exec` usa PHP do host (versao errada), resultados invalidos. Por isso o detector hibrido.
7. **PostGIS e dominio central** — queries espaciais escondem N+1 e indices ausentes degradam mapas em segundos.
8. **TBL-013:** exit code 1 do `git ls-files` e SUCESSO, nao falha.
9. **TBL-011:** sempre castar `geom::geometry` antes de `ST_IsValid`/`ST_X`/`ST_Y`.
10. **TBL-005:** auto-criar `poprua_cras_test` na primeira auditoria; senao D2 fica zerada.
11. **TBL-021:** flag `-w` no `docker exec` e obrigatoria. Codigo Laravel nao esta no WORKDIR padrao do container.
12. **TBL-022:** Tiger census data e ruido — sempre filtrar D5 pela whitelist `$GEO_CRAS_TABLES`.
13. **TBL-023:** funcao `detect_runtime()` com `echo+return` em `$()` falha silenciosamente — usar detector **inline**.
14. **TBL-024:** container PHP nao tem Node — D6 valida bundles existentes em vez de re-buildar.
15. **TBL-025:** Python 3.5 no host — escrever scripts sem f-strings e sem `astimezone()` em naive datetime, ou usar `$EXEC python3` no container.
16. **TBL-026:** `artisan migrate --env=testing` nao le `phpunit.xml`. Criar `.env.testing` ou aplicar SQL direto no DB test.
17. **TBL-027:** congelar o pattern de inline handlers (`onclick=|onchange=|onsubmit=|onerror=`) — agentes nao podem expandir o regex.
18. **TBL-028:** regex de img alt deve aceitar `{{...}}` blade internamente. O regex antigo cortava no `>` de `->method()`.
19. **TBL-029:** FOUC = heuristica BAIXA. Componentes com `style="display:none"` inline em x-show ja resolvem — nao penalizar.
20. **TBL-030 — PASSO 3 usa bash puro:** nunca usar `python3` nem `node` no orquestrador para agregar scores. O host tem Python 3.5 (sem f-strings) e sem Node. Usar `$(( ... ))` e heredoc. Scripts das dimensoes (D3/D6) usam `$EXEC python3` (container tem Python >= 3.9).
21. **Sub-skills no modo iterar:** a ordem e fixa — infra bash primeiro (sem risco), depois `/simplify` (codigo), depois `/verify` (confirmar), depois re-audit seletivo. Nunca aplicar mais de 5 Q1 por iteracao para evitar cascata nao verificada.
22. **Re-audit seletivo e mais rapido:** no PASSO 6 rodar apenas as dimensoes afetadas pelos fixes, nao todas as 8. Economiza ~70% do tempo de re-auditoria e mantem o delta preciso.
23. **config:cache SÓ em prod:** rodar `php artisan config:cache` localmente cacheia o .env de dev e faz os testes falharem (DB aponta para producao em vez de poprua_cras_test). Aplicar sempre via `ssh sufis-poprua-cras "docker exec ..."`. Se rodar por engano: `php artisan config:clear` restaura imediatamente.
24. **TBL-031 — Path real de backups:** backups do banco estao em `/opt/docker/poprua-cras/backups/*.dump`, NAO em `/var/backups/`. A iter 2 reportou INF-005 como finding critico porque verificou o caminho errado — falso negativo que infou D7 em -10 pts por 2 iteracoes. Sempre verificar o path real antes de penalizar.
