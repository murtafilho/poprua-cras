# Tabela de Percalcos + Licoes — quality-audit POPRUA CRAS

Cada agente DEVE consultar esta tabela quando encontrar erro. Sintoma -> diagnostico -> solucao. Apos aplicar, append em `$INCIDENTS_LOG`:
```
YYYY-MM-DDTHH:MM:SS-03:00 | <dimensao> | TBL-XXX | <acao-tomada>
```

## Indice
- [TBL-001..TBL-020](#tbl-001tbl-020) — percalcos base
- [TBL-021..TBL-031](#tbl-021tbl-031) — percalcos avancados
- [Novos percalcos](#novos-percalcos)
- [Licoes consolidadas](#licoes-consolidadas)

---

## TBL-001..TBL-020

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

---

## TBL-021..TBL-031

### TBL-021 — `vendor/bin/pint: no such file or directory` mesmo com vendor OK
- **Sintoma:** `OCI runtime exec failed: stat vendor/bin/pint: no such file or directory`
- **Causa:** `WorkingDir` do container e `/var/www/html` mas o codigo Laravel esta em `/var/www/html/joomla_sufis/ginfi/poprua-cras/`. `vendor/` resolve relativo ao WORKDIR.
- **Fix:** sempre incluir `-w "$PROJECT_ROOT_HOST"` no prefixo `$EXEC`. Validado: com `-w` aplicado, `vendor/bin/pint --test` passa (181 files).
- **Lemma:** NUNCA `cd $PROJECT_ROOT && $EXEC ...` — o `cd` aplica no host, nao no container. Use `-w`.

### TBL-022 — Tabelas TIGER census poluem D5
- **Sintoma:** D5 reporta dezenas de SRID 4269 (county, state, zcta5, tabblock20, ...) como findings.
- **Causa:** imagem `postgis/postgis:17-3.5` vem com Tiger geocoder extension + dados de exemplo. Sao dados de referencia US, nao do dominio CRAS.
- **Fix:** filtrar via whitelist `$GEO_CRAS_TABLES = ('pontos','endereco_atualizados','geo_bairros','geo_regionais','geo_limite_municipio')` em TODAS as queries de D5.
- **Quando isso muda:** se o dominio adicionar novas tabelas geometricas, atualizar a whitelist na skill.

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

### TBL-031 — Path real de backups
- **Sintoma:** D7 reporta backup ausente (INF-005 critico) mesmo havendo backups.
- **Causa:** verificacao no caminho errado (`/var/backups/`).
- **Fix:** backups do banco estao em `/opt/docker/poprua-cras/backups/*.dump`, NAO em `/var/backups/`. Sempre verificar o path real antes de penalizar. A iter 2 infou D7 em -10 pts por 2 iteracoes por causa disso.

---

## Novos percalcos

Encontrou algo nao listado? Append em `$INCIDENTS_LOG` com id provisorio `TBL-NEW-<short-hash>` e descrever no relatorio. Apos confirmacao do usuario, adicionar entrada permanente nesta secao.

---

## Licoes consolidadas

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
23. **config:cache SO em prod:** rodar `php artisan config:cache` localmente cacheia o .env de dev e faz os testes falharem (DB aponta para producao em vez de poprua_cras_test). Aplicar sempre via `ssh sufis-poprua-cras "docker exec ..."`. Se rodar por engano: `php artisan config:clear` restaura imediatamente.
24. **TBL-031 — Path real de backups:** backups em `/opt/docker/poprua-cras/backups/*.dump`, NAO em `/var/backups/`. Verificar o path real antes de penalizar D7.
