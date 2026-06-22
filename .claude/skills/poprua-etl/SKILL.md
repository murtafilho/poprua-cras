---
name: poprua-etl
description: >
  Migracao one-shot de dados do POPRUA Geo (producao temporaria) para o POPRUA
  CRAS (este projeto, fonte de verdade canonica). O schema do CRAS sempre vence
  em divergencias. Implementacao: um arquivo etl/migrate.sql executado via
  postgres_fdw + DB::unprepared dentro de uma transacao (TRUNCATE...RESTART
  IDENTITY no inicio, INSERTs em ordem topologica de FK, reset de sequencias).
  Pre-flight via etl:schema-diff que falha se aparecer divergencia nao prevista
  em EXPECTED_DIVERGENCES (sinaliza que migrate.sql precisa ser atualizado).
  Use sempre que o usuario falar em migrar dados, trazer dados do poprua-geo,
  importar do geo, ETL, sincronizar bancos, replicar tabelas, cutover de
  producao, ou compatibilizacao de schemas entre geo e cras. Variacoes:
  'migrar dados', 'puxar dados do geo', 'importar geo', 'ETL', 'etl poprua',
  'cutover', 'rodar etl', 'apply etl', 'mexer no migrate.sql'.
user-invocable: true
allowed-tools: Read, Grep, Bash, Write, Edit
argument-hint: [diff|run|status]
version: 1.1.0
updated: 2026-05-25
---

# POPRUA ETL — Geo → CRAS (one-shot)

Migracao **unica** dos dados do `poprua-geo` (producao temporaria) para o
`poprua-cras` (este projeto). Apos rodar com sucesso e validar, o Geo e
desligado.

## Principio fundamental

**CRAS e a fonte de verdade do schema.** Em qualquer divergencia, o CRAS vence:

| Caso | Tratamento em `etl/migrate.sql` |
|---|---|
| Coluna existe so em Geo (ex: `moradores.fotografia`) | omitir do `INSERT` |
| Coluna existe so em CRAS (ex: `pontos.deleted_at`) | `SELECT ..., NULL` |
| Coluna NOT NULL com default no CRAS (ex: `vistorias.finalizada`) | `SELECT ..., FALSE` (nao NULL) |
| Tabela existe so em Geo | nao incluir no `IMPORT FOREIGN SCHEMA` |
| Tabela existe so em CRAS (ex: `membros_equipe`) | nao tocar (vem via migrations/seeders) |
| Runtime Laravel (cache/sessions/jobs/migrations) | nao migrar |

## Fluxo

```
1. etl:schema-diff   → pre-flight; falha se divergencia inesperada
2. etl:run --confirm → executa migrate.sql via DB::unprepared
3. (revisar contagens + validacao PostGIS no relatorio final)
```

### Pipeline completo (recomendado): `etl/cutover.sh`

`etl:run` cobre SO os dados. O `etl:run` **nao** migra arquivos de foto, nao
trata ambiente, nem o CASCADE colateral. Para a migracao/cutover de verdade use
o orquestrador host-level (roda NO HOST vlcp-sufis01, chama docker exec):

```bash
sudo bash etl/cutover.sh --check    # dry-run: pre-flight + schema-diff + validacao read-only
sudo bash etl/cutover.sh --apply    # backup + etl:run + rsync fotos + reseed locais + validacao
# flags: --freeze/--unfreeze (geo em maintenance), --webp, --no-rsync, --no-reseed, --skip-backup
```

Fases: (1) pre-flight/ambiente — containers, rede FDW (reconecta se preciso),
migrations; (2) schema-diff (aborta se divergir); (3) freeze geo opcional;
(4) backup pg_dump do cras; (5) etl:run; (6) **rsync de `storage/app/public/`
geo→cras** (o ETL nao copia arquivos!); (7) **reseed de `vistoria_participantes`
/`user_team`** zerados pelo CASCADE (pg_restore --data-only do backup pre-run);
(8) validacao (paridade de contagens, gap de fotos=0, PostGIS); (9) webp opcional.

Cutover real = `--apply --freeze` (sem `--unfreeze`); rehearsal = `--apply` so.

## Arquivos

| Arquivo | Funcao |
|---|---|
| `etl/cutover.sh` | **pipeline completo** (host) — ambiente + dados + fotos + reseed + webp + validacao |
| `etl/migrate.sql` | **fonte da verdade do ETL** — TRUNCATE + FDW IMPORT + INSERTs + reset seq |
| `app/Console/Commands/Etl/SchemaDiffCommand.php` | pre-flight; compara `EXPECTED_DIVERGENCES` (hardcoded) com diff real |
| `app/Console/Commands/Etl/RunCommand.php` | wrapper fino: substitui senha + DB::unprepared + relatorio |
| `config/database.php` | adiciona conexao `pgsql_geo` (ETL_SOURCE_*) |

## Pre-requisitos (sempre verificar antes)

### 1. Migrations do CRAS aplicadas

```bash
$EXEC php artisan migrate:status   # se "Migration table not found":
$EXEC php artisan migrate --force  # --force pq APP_ENV=production
```

Sem isso, o schema-diff reporta CRAS quase vazio e a migracao quebra.

### 2. Containers conectados na rede do Geo

**O FDW conecta a partir do `pg17-poprua-cras`** (Postgres backend), nao do PHP.
Precisa conectar os DOIS containers:

```bash
sudo docker network connect poprua-geo_poprua-geo pg17-poprua-cras
sudo docker network connect poprua-geo_poprua-geo php84-poprua-cras
sudo docker network connect poprua-geo_poprua-geo queue-poprua-cras
```

**ATENCAO — conexao efemera:** se qualquer container for recreado, perde a
conexao. Para persistir, declarar `external: true` no `docker-compose.yml`:

```yaml
services:
  app:     { networks: [poprua-cras, poprua-geo_external] }
  queue:   { networks: [poprua-cras, poprua-geo_external] }
  db:      { networks: [poprua-cras, poprua-geo_external] }
networks:
  poprua-cras: { driver: bridge }
  poprua-geo_external:
    external: true
    name: poprua-geo_poprua-geo
```

### 3. ETL_SOURCE_PASSWORD no `.env`

```bash
ETL_SOURCE_HOST=pg17-poprua-geo
ETL_SOURCE_PORT=5432
ETL_SOURCE_DB=poprua_geo
ETL_SOURCE_USER=poprua
ETL_SOURCE_PASSWORD=<<senha do Geo>>
```

### 4. Working dir do container

A partir do docker-compose.yml que alinha bind mount e `working_dir`, `docker
exec` funciona sem `-w`:

```bash
EXEC="sudo docker exec php84-poprua-cras"
```

## Comandos

### `etl:schema-diff`

Pre-flight. Compara schemas reais com `EXPECTED_DIVERGENCES` no codigo.
Se aparecer divergencia nao prevista, **comando falha com exit 1** e
sinaliza que `migrate.sql` precisa ser atualizado.

```bash
$EXEC php artisan etl:schema-diff
```

Saida esperada quando OK: `Migracao pronta para rodar.`

### `etl:run`

```bash
$EXEC php artisan etl:run             # dry-run (default)
$EXEC php artisan etl:run --confirm --skip-backup   # execucao real
```

`--skip-backup` e necessario hoje porque o container PHP nao tem `pg_dump`.
Se o CRAS ja tiver dados, fazer backup externo antes:

```bash
sudo docker exec pg17-poprua-cras pg_dump -Fc -U poprua_cras poprua_cras > backup.dump
```

## Quando atualizar `etl/migrate.sql`

`schema-diff` aponta automaticamente. Cenarios:

**Coluna nova nullable no CRAS** (ex: `pontos.observacao_extra`):
- adicionar em `EXPECTED_DIVERGENCES` de `SchemaDiffCommand`
- adicionar `, NULL` no `INSERT INTO public.pontos ... SELECT`

**Coluna nova NOT NULL no CRAS** (ex: `vistorias.finalizada`):
- igual ao caso acima, MAS usar o default declarado na migration:
  - `boolean default false` → `FALSE`
  - `integer default 0` → `0`
  - `string default 'x'` → `'x'`

**Coluna dropada no CRAS** (ex: `moradores.fotografia`):
- adicionar em `EXPECTED_DIVERGENCES[$tabela]['drop']`
- omitir do `INSERT INTO public.<tabela> (..., **sem ela**, ...)`

**Tabela nova no CRAS:** adicionar em `IGNORED` no `SchemaDiffCommand` (sera
seedada via migrations/seeders, nao via ETL). Exemplos atuais: `parametros`,
`user_team`, `vistoria_participantes`.

**Tipo de coluna divergente CRAS×Geo** (ex: `morador_historicos.data_entrada`
virou `timestamp` no CRAS via migration `2026_05_20_240000_promote_date_to_timestamp`,
ainda e `date` no Geo):
- registrar em `EXPECTED_TYPE_DIFFS[$tabela][$coluna] = ['cras' => 'timestamp', 'geo' => 'date']`
  no `SchemaDiffCommand` (suprime o "INESPERADO" do schema-diff)
- cast explicito no `INSERT`: `data_entrada::timestamp` em vez de `SELECT *`
  (evita ambiguidade do FDW; documenta a divergencia inline)

**Coluna nova no CRAS sem equivalente no Geo** (ex: `users.ativo` adicionado
no CRAS, nao existe no Geo):
- adicionar em `EXPECTED_DIVERGENCES[$tabela]['add']`
- no `INSERT` da tabela, **listar colunas explicitamente** (nao `SELECT *`) e
  preencher a coluna nova com o default desejado para registros herdados
  (ex: `TRUE` para `users.ativo`)
- Quebra do `SELECT *`: se a coluna so existe no destino, o FDW devolve N
  colunas e o INSERT espera N+1 — erro silencioso no Postgres em alguns
  casos, ou erro de "column count mismatch" no FDW.

## Validacao pos-execucao

`etl:run` ja imprime:
- Contagens por tabela (sanidade — compare com Geo)
- `ST_IsValid` por geometria (auto-interseccoes, etc)
- `ST_SRID = 4326` em todas as linhas

**Issues conhecidas (no proprio dado do Geo, nao do ETL):**
- `geo_bairros.id=188` (Morro dos Macacos) — self-intersection
- `geo_bairros.id=426` (Distrito Industrial do Jatoba) — self-intersection

Fix:
```sql
UPDATE public.geo_bairros SET geom = ST_MakeValid(geom)
WHERE id IN (188, 426);
```

## Troubleshooting

### `could not translate host name "pg17-poprua-geo"`

Container `pg17-poprua-cras` nao esta na rede `poprua-geo_poprua-geo`.
Conectar com `docker network connect` (ver Pre-requisito 2).

### `null value in column "X" violates not-null constraint`

Coluna nova no CRAS tem `NOT NULL` sem nullable. Em `migrate.sql`, substituir
o `NULL` correspondente pelo default da migration (`FALSE`, `0`, etc).

### Divergencias INESPERADAS no schema-diff

Migration nova foi aplicada no CRAS. Atualizar `EXPECTED_DIVERGENCES` em
`SchemaDiffCommand.php` e o `INSERT` correspondente em `migrate.sql`.

### Re-rodar de novo

`migrate.sql` e idempotente (TRUNCATE ... RESTART IDENTITY no inicio). Pode
rodar varias vezes. Util para ensaios iterativos durante o cutover.

### `password authentication failed for user "poprua"` no schema-diff

Pegadinha conhecida (2026-05-25). O `POSTGRES_PASSWORD` do compose do
`pg17-poprua-geo` foi sobrescrito depois do volume ser inicializado. A senha
REAL do user `poprua` esta em `/var/www/html/joomla_sufis/ginfi/poprua-geo/.env`
como `DB_PASSWORD` (mesma usada pela app Laravel poprua-geo). NAO copie do
`POSTGRES_PASSWORD` do `/opt/docker/poprua-geo/.env` — esse valor e historico.

Teste rapido antes de gastar tempo:
```bash
sudo docker exec php84-poprua-cras sh -c \
  'PGPASSWORD="<senha>" psql -h pg17-poprua-geo -U poprua -d poprua_geo -c "SELECT 1"'
```

O `pg_hba` do Geo usa `trust` no socket local (por isso `docker exec
pg17-poprua-geo psql -U poprua` da a falsa impressao de OK), mas `scram-sha-256`
na rede — onde o FDW e o schema-diff se conectam.

---

## Changelog

### v1.1.0 (2026-05-25)

- Documentado caso "Tipo divergente CRAS×Geo" e a constante `EXPECTED_TYPE_DIFFS`.
- Documentado caso "Coluna nova no CRAS sem equivalente" e a obrigacao de
  listar colunas no INSERT em vez de `SELECT *`.
- Listado tabelas atuais em `IGNORED` (parametros, user_team, vistoria_participantes).
- Adicionada secao "password authentication failed" em troubleshooting,
  apontando para `/var/www/html/joomla_sufis/ginfi/poprua-geo/.env DB_PASSWORD`.
- Ajustes reais no `migrate.sql` desta sessao:
  - INSERT users explicito com `ativo=TRUE`
  - vistorias inclui `houve_comunicado` e `data_comunicado` (defaults `FALSE, NULL`)
  - morador_historicos com cast `::timestamp` em `data_entrada` e `data_saida`
- Sincronizacoes no `SchemaDiffCommand.php`:
  - `IGNORED`: + parametros, user_team
  - `EXPECTED_DIVERGENCES`: + users.add=ativo, + vistorias.add=houve_comunicado/data_comunicado
  - Nova constante `EXPECTED_TYPE_DIFFS` (morador_historicos.data_entrada/saida)

## Limitacoes conscientes

- **Sem modo delta:** projeto e one-shot. Se precisar sincronizar mudancas
  do Geo apos a primeira carga, rodar novamente (TRUNCATE + INSERT — apaga
  e recarrega tudo). Para sync incremental real, precisaria de outro design.
- **3 colunas hardcoded no SQL** (pontos.deleted_at, moradores.fotografia,
  vistorias.*): se a divergencia crescer muito, vale revisitar o design.
  Por ora, 3 tabelas com edicao explicita e mais simples que abstracao.
- **Backup persistido em `/var/backups/poprua-cras`** no host (bind-mounted
  no container, sobrevive a recreacao).
