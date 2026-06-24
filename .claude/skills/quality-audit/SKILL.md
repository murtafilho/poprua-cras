---
name: quality-audit
description: >
  Auditoria completa de qualidade do POPRUA CRAS: nota 0-100 por dimensao, KPIs,
  matriz Impacto x Esforco e tracking de tendencia entre execucoes. Auto-detecta
  host vs container e ajusta os comandos. Cobre 8 dimensoes — code quality,
  testes, seguranca, arquitetura Laravel, integridade geoespacial PostGIS,
  frontend (Vite/Leaflet/Alpine), infra Docker e cobertura de homologacao — via
  agentes paralelos com recuperacao automatica de percalcos. Use SEMPRE que o
  usuario pedir para analisar/avaliar qualidade, dar nota ao projeto, fazer
  auditoria ou verificar saude do codigo. Variacoes: 'quality audit', 'auditoria
  de qualidade', 'analisar qualidade', 'avaliar codigo', 'code quality',
  'nota/score do projeto', 'saude/diagnostico do codigo', 'code health/audit',
  'revisao geral', 'como esta o projeto', 'avaliar arquitetura/seguranca/postgis'.
user-invocable: true
allowed-tools: Read, Grep, Bash, Write, Edit, Agent
argument-hint: [iterar|stale|quick-wins|debug]
version: 1.5.0
---

# Quality Audit — POPRUA CRAS

Auditoria estruturada em **8 dimensoes**, cada uma com nota 0-100. Roda no host (via `docker exec`) OU dentro do container (comandos diretos) — auto-detectado no PASSO -1. Agrega sub-audits e checagens diretas. Produz matriz de prioridade, tendencia entre execucoes e log de percalcos.

**Stack:** Laravel 12 / PHP 8.4 / PostgreSQL 17 + PostGIS 3.5 / Redis / Vite. Container app: `php84-poprua-cras`. SSH prod: `ssh sufis-poprua-cras`. Dominio: **Ponto -> Vistoria -> Morador** com geometrias SRID 4326.

## Arquivos da skill (progressive disclosure)

| Arquivo | Quando ler |
|---|---|
| `scripts/preflight.sh` | PASSO -1 — `source` para detectar runtime e resolver `$EXEC`/`$DB_EXEC`/etc. |
| `references/dimensoes.md` | PASSO 2 — bash + scoring + KPIs das 8 dimensoes; o contrato JSON das sub-audits |
| `references/percalcos.md` | Sempre que um agente encontrar erro — tabela TBL-001..TBL-031 + licoes |

## Fluxo geral

```
-1. Pre-flight (source preflight.sh; aborta com diagnostico se algo critico falhar)
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

## PASSO -1 — Pre-flight

Antes de qualquer checagem, descobrir onde estamos rodando e validar dependencias. Toda a logica (detector host vs container, resolucao de variaveis e os 9 checks criticos) esta em `scripts/preflight.sh`. **Faca `source` para que as variaveis persistam no shell:**

```bash
source .claude/skills/quality-audit/scripts/preflight.sh
```

Isso popula `RUNTIME`, `EXEC`, `DB_EXEC`, `SSH_PROD`, `PROJECT_ROOT`, `AUDITS_DIR`, `INCIDENTS_LOG`, `GEO_CRAS_TABLES` e imprime uma linha de status por check (`OK`/`FAIL`/`WARN`). Interprete a saida pela matriz de severidade abaixo.

> Detalhes do porque o detector e inline (TBL-023) e do `-w` no `docker exec` (TBL-021) estao comentados no proprio script.

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

## As 8 Dimensoes

O bash, scoring e KPIs de cada dimensao estao em **`references/dimensoes.md`** (junto com o contrato JSON das sub-audits e a tabela de severidade -> deducao). Resumo:

| # | Dimensao | Peso | Fonte principal | KPIs-chave |
|---|----------|------|-----------------|------------|
| D1 | Code Quality | 15% | pint + phpstan | pint_violations, phpstan_errors, phpstan_baseline_size |
| D2 | Test Coverage | 15% | artisan test (+clover) | test_count, pass_rate, line_coverage_pct |
| D3 | Security | 20% | composer/npm audit + .env + RBAC | php_vulns, npm_*_vulns, env_exposed, csp_inline_handlers |
| D4 | Architecture | 15% | grep de patterns Laravel | controllers_with_db, service_coverage_ratio, form_request_ratio |
| D5 | Geo Integrity | 10% | geometry_columns + ST_* | srid_consistency, geometries_without_gist, orphan_pontos |
| D6 | Frontend | 10% | npm build + blades | build_ok, images_no_alt, inline_handlers, pwa_ready |
| D7 | Infrastructure | 10% | SSH prod (docker/ssl/disk) | containers_running, ssl_days_remaining, config_cached |
| D8 | Homologation | 5% | skills + feature tests / controllers | coverage_ratio, feature_tests_count |

**Deducoes por severidade:** CRITICO -15, ALTO -8, MEDIO -3, BAIXO -1 (detalhes e formula por dimensao em `references/dimensoes.md`).

---

## Percalcos

Cada agente DEVE consultar **`references/percalcos.md`** (TBL-001..TBL-031 + licoes) ao encontrar erro: sintoma -> diagnostico -> solucao. Apos aplicar, append em `$INCIDENTS_LOG`:
```
YYYY-MM-DDTHH:MM:SS-03:00 | <dimensao> | TBL-XXX | <acao-tomada>
```
Encontrou algo novo? Append com id provisorio `TBL-NEW-<short-hash>` e descreva no relatorio.

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
> Variaveis exportadas (ja resolvidas pelo orquestrador no PASSO -1):
> - `RUNTIME` = "<host|container>"
> - `EXEC` = "<prefixo ou vazio>"
> - `DB_EXEC` = "<prefixo>"
> - `SSH_PROD` = "ssh sufis-poprua-cras"
> - `PROJECT_ROOT`, `AUDITS_DIR`, `INCIDENTS_LOG`, `GEO_CRAS_TABLES`
>
> 1. Leia `$AUDITS_DIR/env-report.json` — se sua dimensao esta na lista `degraded_dimensions`, marque `degraded: true` no JSON de saida.
> 2. Leia os blocos bash da sua dimensao em `references/dimensoes.md` e rode-os.
> 3. Para cada erro encontrado, consulte `references/percalcos.md` (TBL-001..TBL-031). Aplique solucao automatica quando seguro. Logue em `$INCIDENTS_LOG`.
> 4. Calcule o score conforme a formula da dimensao.
> 5. Escreva `$AUDITS_DIR/harness-<dimension>.json` no contrato (ver `references/dimensoes.md`).
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

Preencha os campos `score` com os valores reais retornados pelos agentes antes de gravar. O template acima usa variaveis de exemplo. Adicione `quick_wins`, `critical_findings`, `delta` por dimensao e `kpis` a partir dos findings dos agentes.

**Pesos:**

| Dimensao | Peso | | Dimensao | Peso |
|----------|------|-|----------|------|
| Security | 20% | | Geo Integrity | 10% |
| Code Quality | 15% | | Infrastructure | 10% |
| Test Coverage | 15% | | Frontend | 10% |
| Architecture | 15% | | Homologation Coverage | 5% |

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
| ... (8 linhas) |

Status: OK >= 80 | WARN 60-79 | CRIT < 60

### Quick Wins (Q1)

| # | Finding | Dimensao | Severidade | Esforco | Acao |

### Percalcos desta auditoria

| TBL-id | Dimensao | Acao | Resolvido? |

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
    {"finding": "cq-007",  "skill": "simplify", "duration_s": 120, "verify": "pass", "status": "ok"}
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
echo "=== DELTA POS-FIX ==="
echo "| Dimensao        | Antes | Depois | Delta |"
# ... preencher com valores reais; recalcular overall

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

1. **Detector hibrido:** `RUNTIME=host` -> `docker exec`; `RUNTIME=container` -> direto. NUNCA hardcodar prefixo no codigo da skill (ver `scripts/preflight.sh`).
2. **DB de teste:** `poprua_cras_test` (auto-criar via TBL-005 se ausente).
3. **PostGIS:** SRID 4326 + GIST sao requisitos. D5 nao e opcional.
4. **PHPStan level 6 com baseline:** medir `phpstan_baseline_size` para detectar crescimento (TBL-019).
5. **SSH sidecar:** `sufis-poprua-cras` -> container; para host de prod, usar `ssh sufis` direto.
6. **`git ls-files --error-unmatch`:** exit 1 = sucesso (arquivo NAO tracked). Inverter logica (TBL-013).
7. **Inline handlers — pattern CONGELADO:** `onclick=|onchange=|onsubmit=|onerror=`. Agentes nao podem expandir o regex sem bump de versao (TBL-027).

## Gitignore

Adicionar:
```
.claude/audits/
```
