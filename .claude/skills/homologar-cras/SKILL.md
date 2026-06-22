---
name: homologar-cras
description: >
  Homologacao (aceite pre-go-live) do POPRUA CRAS contra PRODUCAO, apos a migracao
  Geo->CRAS. Orquestra 5 dimensoes: (D1) integridade dos dados migrados, (D2) funcional
  ponta-a-ponta dos fluxos Ponto->Vistoria->Morador->finalizacao via Playwright, (D3)
  qualidade tecnica [delega quality-audit], (D4) UX [delega ux-friction], (D5) fotos
  [delega foto-audit]. Produz matriz de aceite (pass/fail por criterio), lista de
  blockers/pendencias e recomendacao GO / NO-GO, com relatorio versionado.
  Use sempre que o usuario pedir para homologar o sistema, validar aceite, testar
  se o CRAS esta apto a assumir producao, rodar homologacao, checklist de aceite,
  go/no-go, ou variacoes: 'homologar', 'homologacao', 'aceite', 'validar go-live',
  'sistema esta pronto', 'pode virar producao', 'checklist de homologacao', 'UAT'.
user-invocable: true
allowed-tools: Read, Grep, Bash, Write, Edit, Agent, Skill
argument-hint: [full|dados|funcional|--write|rapido]
version: 1.0.0
---

# Homologar CRAS — aceite pre-go-live (pos-migracao Geo->CRAS)

Homologacao = decidir se o **poprua-cras** (com os dados/fotos migrados do Geo)
esta **apto a assumir producao**. Esta skill consolida 5 dimensoes numa **matriz de
aceite** e emite **GO / NO-GO**, com relatorio versionado em `.claude/homologacao/`.

**Alvo padrao: PRODUCAO** (`https://sufis.pbh.gov.br/ginfi/poprua-cras/public`,
banco `pg17-poprua-cras` em vlcp-sufis01). Stack: Laravel 12 / PostGIS / Redis / Vite.
Dominio: **Ponto -> Vistoria -> Morador**, geometrias SRID 4326.

> Contexto: o ETL de dados (`etl/cutover.sh`, `etl:run`) traz so as linhas; fotos vem
> por rsync; tabelas locais (`vistoria_participantes`/`user_team`) sao re-seedadas pos
> CASCADE. A homologacao valida o **resultado** desse processo, nao o executa.

## ⚠️ Seguranca em PRODUCAO

Homologar mexe em producao. Regras:
- **Default = nao-destrutivo:** D1 e leitura (psql via `ssh sufis`); D2 navega/preenche
  mas **NAO submete** create/finalizar (para antes do submit); D3/D4/D5 sao read-only.
- **`--write`:** exercita os submits de escrita com registros **marcados** `[HOMOLOG]`
  e **limpeza ao final** (soft-delete/remocao + log). Nunca rodar `--write` sem combinar
  a limpeza e sem o usuario de teste correto.
- Usuario de teste: `claude.test@interno.local` (role `agentes-campo`, migrado do geo).
  Credenciais via env `UX_USER`/`UX_PASS` (ver skill `ux-friction` p/ reset em prod).
- Backup antes de `--write`: `ssh sufis "sudo docker exec pg17-poprua-cras pg_dump -Fc -U poprua_cras poprua_cras > /var/backups/poprua-cras/pre_homolog_$(date +%Y%m%d_%H%M).dump"`

## Pre-flight

```bash
APP_URL="https://sufis.pbh.gov.br/ginfi/poprua-cras/public"
PSQL='ssh sufis "sudo docker exec pg17-poprua-cras psql -U poprua_cras -d poprua_cras -tAF\"|\" -c"'
HDIR=".claude/homologacao"; mkdir -p "$HDIR"

# 1. App (login) responde
curl -s -o /dev/null -w "%{http_code}\n" --max-time 20 "$APP_URL/login"   # esperado 200
# 2. Containers up
ssh sufis "sudo docker ps --filter name=poprua-cras --format '{{.Names}} {{.Status}}'"
# 3. Playwright local (runner roda na maquina do dev contra prod)
npx playwright --version || echo "npx playwright install chromium"
# 4. Rota local->prod (necessaria p/ Playwright)
curl -s -o /dev/null -w "rota local->prod: %{http_code}\n" --max-time 20 "$APP_URL/login"
```

---

## D1 — Integridade dos dados migrados (peso 30%)

Leitura via `ssh sufis "... psql ..."`. Cada item e PASS/FAIL.

```bash
EXEC="ssh sufis \"sudo docker exec pg17-poprua-cras psql -U poprua_cras -d poprua_cras -tAF'|' -c\""

# D1.1 Volumes plausiveis (compare com o Geo se ainda existir; senao sanidade absoluta)
ssh sufis "sudo docker exec pg17-poprua-cras psql -U poprua_cras -d poprua_cras -tAF'|' -c \"
  SELECT 'pontos',count(*) FROM pontos UNION ALL SELECT 'vistorias',count(*) FROM vistorias
  UNION ALL SELECT 'moradores',count(*) FROM moradores UNION ALL SELECT 'media',count(*) FROM media
  UNION ALL SELECT 'users',count(*) FROM users UNION ALL SELECT 'endereco_atualizados',count(*) FROM endereco_atualizados\""

# D1.2 Integridade referencial (orfaos = FAIL se > 0)
ssh sufis "sudo docker exec pg17-poprua-cras psql -U poprua_cras -d poprua_cras -tAF'|' -c \"
  SELECT 'vist_sem_ponto',     count(*) FROM vistorias v LEFT JOIN pontos p ON p.id=v.ponto_id WHERE p.id IS NULL
  UNION ALL SELECT 'vist_tipo_abord_invalido', count(*) FROM vistorias v LEFT JOIN tipo_abordagem t ON t.id=v.tipo_abordagem_id WHERE v.tipo_abordagem_id IS NOT NULL AND t.id IS NULL
  UNION ALL SELECT 'morador_sem_ponto', count(*) FROM moradores m LEFT JOIN pontos p ON p.id=m.ponto_atual_id WHERE m.ponto_atual_id IS NOT NULL AND p.id IS NULL
  UNION ALL SELECT 'hist_sem_morador', count(*) FROM morador_historicos h LEFT JOIN moradores m ON m.id=h.morador_id WHERE m.id IS NULL
  UNION ALL SELECT 'ponto_orfao_end',  count(*) FROM pontos p LEFT JOIN endereco_atualizados e ON e.id=p.endereco_atualizado_id WHERE e.id IS NULL\""

# D1.3 PostGIS valido (FAIL se invalidas > 0 ou SRID != 4326)
ssh sufis "sudo docker exec pg17-poprua-cras psql -U poprua_cras -d poprua_cras -tAF'|' -c \"
  SELECT 'geo_bairros_invalidos', count(*) FROM geo_bairros WHERE NOT ST_IsValid(geom::geometry)
  UNION ALL SELECT 'pontos_srid_errado', count(*) FROM pontos WHERE geom IS NOT NULL AND ST_SRID(geom)<>4326
  UNION ALL SELECT 'pontos_fora_bh', count(*) FROM pontos WHERE geom IS NOT NULL AND (ST_X(geom::geometry) NOT BETWEEN -45 AND -42 OR ST_Y(geom::geometry) NOT BETWEEN -21 AND -19)\""

# D1.4 Fotos: media sem arquivo fisico (FAIL se > 0) — reaproveita a logica do etl/cutover.sh
ssh sufis "sudo docker exec php84-poprua-cras sh -c 'cd /var/www/html/joomla_sufis/ginfi/poprua-cras/storage/app/public && ls -1 | grep -E \"^[0-9]+\$\" | LC_ALL=C sort > /tmp/_h_dirs.txt'
sudo docker exec pg17-poprua-cras psql -U poprua_cras -d poprua_cras -tAc 'SELECT id FROM media' | tr -d ' ' | LC_ALL=C sort > /tmp/_h_mids.txt
sudo docker cp /tmp/_h_mids.txt php84-poprua-cras:/tmp/_h_mids.txt
echo media_sem_arquivo=\$(sudo docker exec php84-poprua-cras sh -c 'LC_ALL=C comm -23 /tmp/_h_mids.txt /tmp/_h_dirs.txt | wc -l')"

# D1.5 Tabelas locais re-seedadas pos-CASCADE (esperado > 0 se havia dados)
ssh sufis "sudo docker exec pg17-poprua-cras psql -U poprua_cras -d poprua_cras -tAF'|' -c \"
  SELECT 'vistoria_participantes',count(*) FROM vistoria_participantes UNION ALL SELECT 'user_team',count(*) FROM user_team\""
```

**Criterios D1 (todos devem passar):** orfaos = 0; geometrias invalidas = 0; SRID
sempre 4326; `media_sem_arquivo` = 0; volumes coerentes com o Geo de origem.
**Score:** 100 - 25*(criterios falhos). Qualquer FAIL aqui e **BLOCKER**.

---

## D2 — Funcional ponta-a-ponta (peso 35%) — CORE

Playwright local contra prod, sessao unica (login persiste). Para cada fluxo: navegar,
validar render/criterio de aceite, screenshot em `$HDIR/screens/`. Submits so com `--write`.

| ID | Fluxo | Rota | Criterio de aceite (PASS) |
|----|-------|------|---------------------------|
| AC-01 | Login (agentes-campo) | `/login` | login OK, redireciona p/ `/mapa` ou `/`; sem erro 419/500 |
| AC-02 | Listar pontos + dados migrados | `/pontos` | lista carrega, mostra pontos reais (count ~ D1.1), paginacao/filtro funcionam |
| AC-03 | Abrir um ponto migrado | `/pontos/{id}` | detalhe abre, endereco vinculado visivel, vistorias do ponto listadas |
| AC-04 | Render criar vistoria | `/pontos/{id}/vistorias/create` | form abre, steps/campos renderizam, sem JS error; **--write:** cria `[HOMOLOG]`, salva, aparece na lista |
| AC-05 | Editar + finalizar vistoria | `/vistorias/{id}/edit` | form pre-populado; **--write:** finalizar pede confirmacao, grava `finalizada=true`, feedback ok |
| AC-06 | Morador na vistoria | modal em create | modal abre, campos ok; **--write:** cria morador `[HOMOLOG]`, aparece na lista |
| AC-07 | Mapa + markers | `/mapa` | tiles carregam, markers dos pontos migrados aparecem, popup com link p/ ponto |
| AC-08 | Fotos servem (migradas) | `/vistorias/{id}` c/ fotos | `<img>` de foto carrega 200 (nao 404); thumb/preview/webp servem |
| AC-09 | Permissoes/RBAC | rotas admin | agentes-campo NAO acessa `/admin/*` (403/redirect); admin acessa |
| AC-10 | Parametros | `/admin/parametros` (admin) | pagina carrega, parametros migrados/seedados visiveis |

AC-08 e critico pos-migracao (fotos = rsync separado). Validar via HTTP a URL real:
`curl -s -o /dev/null -w "%{http_code}" "$APP_URL/storage/{media_id}/{file}"` deve dar 200.

**--write — registros marcados + limpeza:**
- vistoria: `nomes_pessoas='[HOMOLOG] '||now()`; morador: `nome_registro='[HOMOLOG] ...'`.
- limpeza ao final: `DELETE`/soft-delete dos `[HOMOLOG]` criados nesta execucao (logar ids).
```bash
# limpeza (ao final do --write):
ssh sufis "sudo docker exec pg17-poprua-cras psql -U poprua_cras -d poprua_cras -c \"
  DELETE FROM moradores WHERE nome_registro LIKE '[HOMOLOG]%';
  DELETE FROM vistorias WHERE nomes_pessoas LIKE '[HOMOLOG]%';\""
```

**Criterios D2:** AC-01..AC-03, AC-07, AC-08, AC-09 sempre avaliados (read-only).
AC-04..AC-06, AC-10 conforme `--write`/role. **Score:** % de AC em PASS.
AC-01/AC-08/AC-09 falhos = **BLOCKER**.

---

## D3 — Qualidade tecnica (peso 15%) — delega `quality-audit`

```
Skill(quality-audit)   # roda as 8 dimensoes; importar overall_score e criticos
```
PASS se `overall_score >= 80` e zero finding CRITICO em seguranca/geo.
Pre-condicao corrigida 2026-06-22: D5 orfaos usa `pontos.endereco_atualizado_id`.

## D4 — UX (peso 10%) — delega `ux-friction`

```
Skill(ux-friction)     # ALVO=prod, modo nao-destrutivo (ou --write coordenado)
```
PASS se score geral >= 70 (WARN aceitavel p/ go-live; CRIT em F3/F4 = blocker).

## D5 — Fotos (peso 10%) — delega `foto-audit` (v1.1.0)

```
Skill(foto-audit)      # arquitetura/desempenho/usabilidade; cloud-sync ja removido
```
PASS se score >= 70 e cobertura WebP coerente com o regen mais recente.

---

## Execucao

```
1. Pre-flight (app, containers, playwright, rota local->prod)
2. D1 dados (psql via ssh) — read-only, sempre
3. D2 funcional (Playwright local->prod, sessao unica) — submits so com --write
4. D3/D4/D5 em paralelo via Agent/Skill (quality-audit, ux-friction, foto-audit)
5. Consolidar matriz de aceite + score ponderado + GO/NO-GO
6. Gravar relatorio + snapshot em .claude/homologacao/
```

**Pesos:** D1 30% · D2 35% · D3 15% · D4 10% · D5 10%.
**Decisao:** **GO** se score >= 85 **e** zero BLOCKER; **GO condicional** 70-84 sem
blocker (com pendencias listadas); **NO-GO** se houver qualquer BLOCKER ou < 70.

### Modos
- `homologar-cras` ou `full` — 5 dimensoes (D2 nao-destrutivo)
- `homologar-cras dados` — so D1
- `homologar-cras funcional` — so D2
- `homologar-cras --write` — D2 com submits marcados + limpeza
- `homologar-cras rapido` — D1 + D2 read-only (pula D3/D4/D5)

---

## Relatorio (`$HDIR/homologacao-<data>.md` + `summary.json`)

```
## Homologacao POPRUA CRAS — <data>
**Alvo:** producao | **Score:** XX/100 | **Decisao:** GO / GO-condicional / NO-GO

### Matriz de aceite
| Dim | Criterio | Resultado | Evidencia |
|-----|----------|-----------|-----------|
| D1  | orfaos=0 | PASS/FAIL | ... |
| D2  | AC-08 fotos servem | PASS/FAIL | screenshot/HTTP |
| ... |

### Blockers (impedem go-live)
### Pendencias (nao bloqueiam, corrigir pos-go-live)
### Scores por dimensao  | ### Recomendacao
```

`summary.json`: `{ "data", "alvo":"prod", "score", "decisao", "blockers":[], "dimensoes":{...} }`

---

## Percalcos

- **HMG-001 — rota local->prod falha:** Playwright local nao alcanca `sufis.pbh.gov.br`.
  Confirmar rede RMI/PBH; alternativa: rodar o runner Playwright de dentro de um
  container `node:22` no host (que alcanca o app pela rede docker/proxy).
- **HMG-002 — login 419 (CSRF/sessao):** ver memoria poprua-geo-session-419 (SESSION_SECURE_COOKIE
  em HTTPS via proxy). Garantir cookies Secure; reusar a sessao apos o primeiro login.
- **HMG-003 — senha do claude.test desconhecida:** resetar via tinker (conta de teste) — ver `ux-friction`.
- **HMG-004 — `--write` deixou lixo:** rodar a limpeza `[HOMOLOG]` (D2) e conferir contagens.
- **HMG-005 — AC-08 fotos 404:** o rsync de `storage/app/public` nao rodou apos o ETL.
  Rodar `etl/cutover.sh` (fase 6) ou o rsync geo->cras manual antes de re-homologar.

## Integracao com o pipeline de cutover
Ordem recomendada no go-live real: `etl/cutover.sh --apply --freeze` -> **`homologar-cras`**
-> se **GO**, responder 'y' na pergunta de desativacao do geo (ou `--deactivate-geo`).
A homologacao e o gate entre a carga e a desativacao do Geo.
