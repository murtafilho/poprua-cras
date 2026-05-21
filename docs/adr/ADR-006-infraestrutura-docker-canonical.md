# ADR-006: Infraestrutura Docker — fonte da verdade unica

**Data:** 2026-05-20
**Status:** Implementado (passos A-G executados; commit `214cfcd`)

## Contexto

Nas ultimas 48h de trabalho na infra do POPRUA CRAS acumulamos ~30 ajustes
reativos: `docker network connect` manual, ciclos de `chown`, paths de bind
mount inconsistentes (`/var/www/html` vs `/var/www/html/joomla_sufis/...`),
config:cache rodando indevidamente em dev e quebrando testes, workarounds
para Node/Python/pg_dump ausentes no container, e dois `docker-compose.yml`
divergentes (`./docker-compose.yml` vs `/opt/docker/poprua-cras/docker-compose.yml`).

A estrategia de tentativa-e-erro alcancou seu limite: cada ajuste novo
revela uma dependencia invisivel com algum outro. Precisamos parar de
patchear sintomas e definir o **estado-alvo da infra** numa decisao
deliberada.

## Variaveis identificadas e estado-alvo

Cada variavel tem hoje multiplos valores convivendo. O estado-alvo unifica
para um valor por variavel. Migracao depende da ordem na coluna **#**.

| #  | Variavel | Estado atual (varios) | **Estado-alvo (unico)** |
|----|----------|----------------------|-------------------------|
| 1  | Compose autoritativo | `./docker-compose.yml` + `/opt/docker/poprua-cras/docker-compose.yml` | **`./docker-compose.yml` do projeto** |
| 2  | Path do codigo no container | `/var/www/html` (compose antigo) ou `/var/www/html/joomla_sufis/...` (atual) | **`/var/www/html/joomla_sufis/ginfi/poprua-cras`** (path absoluto identico host+container) |
| 3  | `working_dir` do container | nao setado em alguns servicos | **Setado explicitamente em todo servico que roda PHP** |
| 4  | Usuario default em `docker exec` | `-u root` no CLAUDE.md, sem `-u` em alguns lugares | **`www-data` por default**; `-u root` somente para apt/install/chown deliberado |
| 5  | UID/GID dos arquivos no bind mount | mistura (root, www-data, cassio.martins) | **`www-data` (UID 33)**; init-perms sidecar normaliza no start |
| 6  | Conteudo da imagem PHP | base + git + libs gd + pg_dump + Node + Python + PCOV | **mantido** (vale o tamanho 834MB para eliminar workarounds) |
| 7  | Cache do Laravel em dev | recorrente (someone runs config:cache) | **NUNCA cacheado em dev**; init-perms limpa + TestCase guarda |
| 8  | Rede para alcancar Geo | `docker network connect` manual | **`external: poprua-geo_poprua-geo`** no compose |
| 9  | Backup destino | `/var/backups/poprua-cras/` (LOCAL) + `/opt/docker/poprua-cras/backups/` (cron antigo) | **`/var/backups/poprua-cras/`** (bind-mounted no app + queue) |
| 10 | Postgres data | named volume `poprua-cras_pgdata` (1.5GB ativo) + `/opt/.../postgres-data` (1.3GB orfao) | **named volume** (atual); `/opt/.../postgres-data` arquivada apos confirmacao |
| 11 | SSH sidecar | declarado so em `/opt/docker/poprua-cras/docker-compose.yml` (compose antigo) | **declarado no compose canonical do projeto** |
| 12 | Pre-flight (storage perms, cache stale) | manual / esquecido | **`init-perms` sidecar** corre a cada `up`/`restart`; `app` e `queue` declaram `depends_on: init-perms` |
| 13 | Backups internos (durante ETL) | dentro do container, perdia em recreacao | **`/var/backups/poprua-cras/`** bind-mounted (atual) |
| 14 | Test DB config | `phpunit.xml` + `.env.testing` + `.env` (todos influenciam) | **`phpunit.xml` e canonical**; `.env.testing` existe como redundancia; TestCase falha se cache estiver presente |
| 15 | Migrations no test DB | `--env=testing` ignora `phpunit.xml` (TBL-026) | **Criar `poprua_cras_test` e rodar migrate via `php artisan test` (PHPUnit aplica)**, nao via `--env=testing` |

## Constraints (nao negociaveis)

- Host: `vlcp-sufis01` Debian 9 (legado, nao trocamos)
- Servidor de producao = mesmo host onde dev acontece (decidido em 2026-05-20)
- Imagem base PHP: `serversideup/php:8.4-fpm-nginx` (UID `www-data` = 33)
- Dados de producao: 1.5GB no volume named `poprua-cras_pgdata` (ETL ja rodou)
- Path do projeto no host: `/var/www/html/joomla_sufis/ginfi/poprua-cras` (Apache vhost ja aponta)

## Decisoes

1. **`./docker-compose.yml` do projeto e a UNICA fonte da verdade.** `/opt/docker/poprua-cras/docker-compose.yml` e arquivado como `*.deprecated-YYYY-MM-DD`.
2. **Tudo que precisa rodar (incluindo SSH sidecar) e declarado no compose canonical.** Nenhum container "orfao" gerenciado por outro compose.
3. **`docker/rebuild.sh` vira um wrapper fino** sobre `docker compose up -d --build`. Nao gera mais nenhum yaml em `/opt/`.
4. **Init-perms sidecar e o ponto unico de normalizacao** de perms e limpeza de cache stale. App e queue declaram `depends_on: init-perms`.
5. **`docker exec` sem `-u root` por default.** CLAUDE.md e skills documentam `-u root` apenas onde estritamente necessario.
6. **Backups via `etl:run` e cron unificados** em `/var/backups/poprua-cras/`. Cron antigo (em `/opt/.../backups/`) e atualizado ou arquivado.
7. **`/opt/docker/poprua-cras/`** continua a hospedar: `backups/` (legado, arquivar), `ssh-data/` (ATIVO), `claude-data/` (ATIVO), `backup.sh` (revisar). `postgres-data/` arquivada apos snapshot de seguranca.

## Plano de migracao (ordem importa)

Cada passo e independentemente reversivel. Validar com `php artisan test` apos cada um.

| Passo | Acao | Risco | Reversao |
|-------|------|-------|----------|
| A | Mover SSH sidecar de `/opt` compose para LOCAL compose | Baixo | `git revert` + recriar container do /opt |
| B | Renomear `/opt/.../docker-compose.yml` para `*.deprecated-YYYY-MM-DD` + README explicando | Zero (nao usado para subir nada) | `mv` de volta |
| C | Simplificar `docker/rebuild.sh` (remover geracao do /opt compose) | Baixo | `git revert` |
| D | Atualizar `setup-ambiente` SKILL com nova topologia | Zero | `git revert` |
| E | Mudar CLAUDE.md: `EXEC` sem `-u root` por default | Baixo (alguns comandos podem falhar de inicio) | `git revert` |
| F | Snapshot + arquivar `/opt/.../postgres-data` | Zero (nao lido) | dir continua disponivel |
| G | Decidir backup destination unificado + atualizar cron | Medio (afeta cron) | manter cron antigo paralelamente ate validar |

## Consequencias

**Fica mais facil:**
- Um unico arquivo descreve toda a infraestrutura
- Recreacao de containers e deterministica e idempotente
- Onboarding novo (humano ou agente) le 1 compose + 1 ADR

**Fica mais dificil:**
- Mudancas precisam ser planejadas via ADR (nao mais "vou tentar")
- A revisao do CLAUDE.md exige mais cuidado para nao reintroduzir `-u root` por inercia

**O que muda:**
- Workflow de deploy: `bash docker/rebuild.sh` ja contempla tudo
- Backups: convergem em `/var/backups/poprua-cras/`
- SSH ainda funciona via `ssh sufis-poprua-cras` na mesma porta, mas o container e construido a partir do LOCAL compose

## Sinais de sucesso

Os sinais 1-5 cobrem o **interior** do sistema (containers, banco, testes, ETL).
O sinal 6 cobre o **pipe end-to-end do usuario** (Apache -> container -> resposta HTTP)
e e **obrigatorio** apos qualquer mudanca de infra.

1. `git status` apos rebuild = limpo
2. Tests passam 335/335 com `docker exec php84-poprua-cras php artisan test` (sem `-u root`, sem `-w`)
3. `etl:schema-diff` roda sem `docker network connect` manual
4. `etl:run --confirm` cria backup em `/var/backups/poprua-cras/`
5. Apos `docker compose down && docker compose up -d`: tudo volta verde sem intervencao manual
6. **`sudo bash docker/smoke-test.sh` retorna exit 0** (4 checks: PHP-FPM listening, URL publica 200+CSRF, count(pontos)>0, log sem ERROR)

### Por que o smoke-test foi adicionado

Na execucao inicial deste ADR, validei os sinais 1-5 e declarei "tudo verde",
mas o sistema estava retornando HTTP 503 na URL publica por 30+ minutos: o
compose canonical mapeava `9086:8080` (porta do nginx interno), enquanto o
Apache do host esperava FastCGI em `9086` (porta `9000` do PHP-FPM). Nenhum
dos 5 sinais internos detectou — todos eram "saude do interior". O sinal 6
forca um teste do pipe completo do usuario, fechando essa categoria de bug.
