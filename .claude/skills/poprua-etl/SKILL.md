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
version: 1.0.0
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

## Arquivos

| Arquivo | Funcao |
|---|---|
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
seedada via migrations/seeders, nao via ETL).

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

## Limitacoes conscientes

- **Sem modo delta:** projeto e one-shot. Se precisar sincronizar mudancas
  do Geo apos a primeira carga, rodar novamente (TRUNCATE + INSERT — apaga
  e recarrega tudo). Para sync incremental real, precisaria de outro design.
- **3 colunas hardcoded no SQL** (pontos.deleted_at, moradores.fotografia,
  vistorias.*): se a divergencia crescer muito, vale revisitar o design.
  Por ora, 3 tabelas com edicao explicita e mais simples que abstracao.
- **Backup persistido em `/var/backups/poprua-cras`** no host (bind-mounted
  no container, sobrevive a recreacao).
