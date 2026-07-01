# ADR-007: Infraestrutura SIZEM — alinhar com a abordagem do CRAS

**Data:** 2026-05-20
**Status:** Implementado (passos A-I executados em 2026-05-20)

## Contexto

O POPRUA Geo esta em uso real (3060 pontos, usuarios autenticados) e
sera desligado apos o cutover para o CRAS. Enquanto isso, ele compartilha
o mesmo servidor `vlcp-sufis01` e a infraestrutura herdada do setup
inicial (composes em `/opt/docker/poprua-geo/`, idem ao que o CRAS tinha
antes do ADR-006).

Hoje (2026-05-20), as 08:48, o Geo comecou a logar
`FATAL: password authentication failed for user "poprua"` em queries
autenticadas (busca de logradouros no mapa retornava HTTP 500). Senha no
`.env`, no DB e na config do Laravel eram identicas (`poprua_secret`).

### Causa raiz (descoberta apos diagnostico)

A senha **nao mudou**. O sintoma e enganoso: o libpq usado pelo PHP-FPM
do Geo tenta SSL primeiro (`sslmode=prefer`), o PG nao suporta SSL
(`SHOW ssl` -> off), e o handshake SCRAM-SHA-256 acaba retornando
"password authentication failed" em vez de fazer fallback gracioso para
non-SSL. Comportamento ja documentado em libpq <17 em algumas
distribuicoes; o libpq embutido no PHP 8.4 base do Geo sofre disso.

Confirmado via teste isolado dentro do container Geo:

| sslmode no DSN | Resultado |
|---|---|
| `(default)` ou `prefer` | FAIL "password authentication failed" |
| `disable` | **OK** (auth funciona) |
| `require` | FAIL "server does not support SSL" |

O CRAS **NAO** sofre o mesmo bug, pois seu Dockerfile (apos ADR-006)
instala `postgresql-client-17` que traz libpq mais recente com fallback
SSL correto. Mesma config (`sslmode=prefer`), mesmo PG sem SSL, mas o
PDO conecta sem erro.

### Fix imediato aplicado (2026-05-20 10:05)

- `config/database.php` do Geo: `'sslmode' => 'prefer'` -> `env('DB_SSLMODE', 'disable')`.
- `php artisan down` durante a mudanca, `up` apos validar.
- Smoke: busca de logradouros via API retornou JSON normalmente.

### Fix definitivo (parte do plano deste ADR)

Passo D vai trazer Node 22 + Python 3 + PCOV + **postgresql-client-17**
para o Dockerfile do Geo (igual ao CRAS), o que tambem traz libpq
recente. Apos isso, `sslmode=prefer` pode voltar a ser default sem
risco.

Decisao do usuario apos o restart: aplicar no Geo a mesma abordagem do
CRAS, formalizada no ADR-006.

## Estado atual (Geo) vs estado-alvo (mesma estrutura do CRAS)

| # | Variavel | Geo agora | **Alvo (igual ao CRAS)** |
|---|----------|-----------|---------------------------|
| 1 | Compose autoritativo | `/opt/docker/poprua-geo/docker-compose.yml` | **`/var/www/html/joomla_sufis/ginfi/poprua-geo/docker-compose.yml`** (projeto) |
| 2 | Bind mount do codigo | `/var/www/html/joomla_sufis/ginfi/poprua-geo:/var/www/html/joomla_sufis/ginfi/poprua-geo` (path absoluto identico) | **mantido** (igual ao CRAS) |
| 3 | `working_dir` no compose | nao setado em todos os servicos | **setado em app + queue** |
| 4 | `docker exec` default user | `-u root` em alguns comandos | **`www-data` por default**; `-u root` so para apt/install/chown |
| 5 | UID/GID dos arquivos | mistura | **`www-data` (UID 33)**; init-perms sidecar normaliza |
| 6 | Conteudo da imagem PHP | base + git + libs (sem Node/Python/PCOV/pg_dump) | **incluir Node/Python/PCOV/pg_dump** (igual ao CRAS) — necessario para D2 coverage e backups internos |
| 7 | Cache do Laravel em dev | recorrente; recentemente causou auth fails | **NUNCA cacheado em dev** (init-perms limpa); TestCase guard se houver suite no Geo |
| 8 | Rede do CRAS para FDW reverso | nao se aplica (Geo nao puxa do CRAS) | n/a |
| 9 | Backup destino | `/opt/docker/poprua-geo/backups/` | **`/var/backups/poprua-geo/`** (bind-mounted no compose canonical) |
| 10 | Postgres data | `/opt/docker/poprua-geo/postgres-data` (bind dir) | **AVALIAR migracao para volume named** OU manter bind dir (decisao operacional) |
| 11 | Sidecar SSH | declarado em `/opt/docker/poprua-geo/docker-compose.yml` (porta 2224) | **declarado no compose canonical do projeto** |
| 12 | Pre-flight perms / cache | nao tem | **`init-perms` sidecar** corre a cada `up`/`restart` |
| 13 | Smoke-test pos-rebuild | nao tem | **`docker/smoke-test.sh`** equivalente (4 checks adaptados pro Geo) |
| 14 | OPcache em prod | habilitado (validade=2s) | **mantido** (sao envs `PHP_OPCACHE_*` no compose; suspeito de contribuir para auth fail de hoje, mas precisa investigar) |

## Constraints (nao negociaveis)

- Geo esta em PRODUCAO. Usuarios reais usam agora. Toda mudanca deve ser
  validada com smoke-test antes de declarar concluida.
- O cutover para o CRAS ja foi feito (ETL rodou, ja temos 3041 pontos no
  CRAS). O Geo continua no ar como referencia/backup ate o user-base
  ser migrado.
- A senha do `.env` (`DB_PASSWORD=poprua_secret`) e a do DB precisam
  permanecer sincronizadas. NAO mudar.
- Os dados em `/opt/docker/poprua-geo/postgres-data` SAO os dados de
  producao. Nao destruir.

## Decisoes

1. **`./docker-compose.yml` do projeto vira a fonte da verdade** (igual ao CRAS, ADR-006 decisao #1).
2. **`/opt/docker/poprua-geo/docker-compose.yml` arquivado** como `*.deprecated-YYYY-MM-DD` apos validacao do novo compose.
3. **Dockerfile do Geo atualizado** para incluir Node 22 + Python 3 + PCOV + postgresql-client-17 + entrypoint hooks (igual ao CRAS).
4. **`init-perms` sidecar adicionado** (alpine), normaliza perms de storage/ e bootstrap/cache/ no start.
5. **TestCase guard contra cache hell** se houver suite de testes (verificar se Geo tem testes).
6. **`docker exec` sem `-u root` por default.**
7. **Backups unificados** em `/var/backups/poprua-geo/` (paralelo ao do CRAS).
8. **`docker/smoke-test.sh` proprio do Geo** (4 checks: FastCGI listening, URL publica 200+CSRF, count(pontos)>0, log sem ERROR ultimos 5min).
9. **PostgreSQL data**: a principio MANTER em `/opt/docker/poprua-geo/postgres-data` (bind dir), porque migrar named volume para producao em uso e operacao de risco e fora do escopo deste ADR. Avaliar em ADR separado se for necessario.

## Plano de migracao

Cada passo independentemente reversivel. Smoke-test (sinal #6) obrigatorio
ao final de cada passo (no Geo, nao no CRAS).

| Passo | Acao | Risco | Reversao |
|-------|------|-------|----------|
| A | Snapshot completo do `/opt/docker/poprua-geo/` (cp -a -> backup-pre-adr007/) | Zero | trivial |
| B | Backup do DB do Geo via `pg_dump` (golden baseline antes de qualquer mudanca) | Zero | restore |
| C | Criar `docker-compose.yml` no projeto Geo espelhando o atual de `/opt`, mais init-perms sidecar + APP_BASE_DIR | Medio (config nova) | rebuild do /opt |
| D | Atualizar `docker/Dockerfile` do Geo com Node/Python/PCOV/pg_dump/entrypoint correto | Medio (rebuild da imagem) | revert + rebuild |
| E | `docker compose -f /var/www/html/joomla_sufis/ginfi/poprua-geo/docker-compose.yml up -d --build` (sobe a nova stack) | **ALTO** (afeta usuarios reais) | parar nova stack, reabrir /opt stack |
| F | Rodar `docker/smoke-test.sh` do Geo. Se FAIL: rollback imediato (passo G). | — | — |
| G | Plan B se algo quebrar em prod: `docker compose down` da nova; `docker compose -f /opt/docker/poprua-geo/docker-compose.yml up -d` | — | testado em "ensaios" antes do go-live |
| H | Apos 1h de smoke verde com usuarios reais: arquivar `/opt/docker/poprua-geo/docker-compose.yml` -> `.deprecated-YYYY-MM-DD` | Zero | mv de volta |
| I | Atualizar `docker/rebuild.sh` se Geo tiver um (analogo ao do CRAS) e `setup-ambiente` SKILL | Zero | git revert |

## Janela de manutencao

Passo E (subir a nova stack) implica **downtime curto** (~30s) para o Geo.
O usuario deve indicar uma janela apropriada — sugestao: **fim de tarde
ou inicio da manha**, fora do horario de pico operacional.

## Consequencias

**Fica mais facil:**
- Geo e CRAS tem a mesma topologia → onboarding e operacao unificados.
- Bug de hoje (auth fails por workers stale) e mitigado: init-perms forca
  estado limpo a cada `up`/`restart`; smoke-test pega o sintoma antes do
  usuario.
- Quando o Geo for desligado pos-cutover, e so `docker compose down` no
  compose canonical.

**Fica mais dificil:**
- Operacao do Geo durante o cutover exige cuidado dobrado (estamos
  tocando producao real).
- Cron e scripts auxiliares em `/opt/docker/poprua-geo/` precisam ser
  reapontados (igual ao que fizemos pro CRAS — passo G do ADR-006).

## Sinais de sucesso

1. `git status` apos rebuild = limpo
2. URL externa `https://sufis.pbh.gov.br/ginfi/poprua-geo/public/login` retorna 200 com CSRF
3. Usuario logado consegue ver pontos no mapa (sem auth fail no laravel.log)
4. Apos `docker compose down && up -d` no novo compose: tudo verde sem intervencao manual
5. `docker/smoke-test.sh` retorna exit 0
6. Apos 1h, zero ERROR de auth no `storage/logs/laravel-YYYY-MM-DD.log`
