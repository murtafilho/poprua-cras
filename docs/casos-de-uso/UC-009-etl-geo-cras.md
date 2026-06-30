# UC-009 — Migração ETL Geo → CRAS (One-Shot)

**Versão:** 1.0  
**Data:** 2026-06-24  
**Status:** Implementado (operacional; cutover sob demanda)

---

## Objetivo

Descrever a **migração única** de dados do POPRUA Geo (produção temporária) para o POPRUA CRAS (fonte de verdade canônica), via `postgres_fdw` e script SQL transacional. Usado no cutover de produção quando o CRAS substitui definitivamente o Geo.

**Referências:** skill `poprua-etl` · `etl/migrate.sql` · `docs/REGRAS_NEGOCIO.md` (seção ETL, se presente).

---

## Atores

| Ator | Descrição |
|------|-----------|
| **Administrador / DBA** | Executa pre-flight, confirma cutover, valida contagens pós-migração. |
| **Sistema** | Comandos Artisan `etl:schema-diff` e `etl:run`; Postgres FDW no cluster CRAS. |

---

## Princípio fundamental

**O schema do CRAS sempre vence.** Divergências são tratadas explicitamente em `etl/migrate.sql` e na lista `EXPECTED_DIVERGENCES` do `SchemaDiffCommand`.

| Situação | Tratamento |
|----------|------------|
| Coluna só no Geo | Omitir do `INSERT` |
| Coluna só no CRAS | `SELECT ..., NULL` ou default (`FALSE`, etc.) |
| Tabela só no Geo | Fora do `IMPORT FOREIGN SCHEMA` |
| Tabela só no CRAS | Não migrada (seeders/migrations locais) |
| Cache, sessions, jobs | Ignorados |

---

## Pré-condições

1. Migrations do CRAS aplicadas (`php artisan migrate`).
2. Rede Docker: cluster `pg17-poprua-cras` alcança `pg17-poprua-geo` (FDW roda no Postgres, não no PHP).
3. Variáveis `ETL_SOURCE_*` configuradas no `.env` (host, port, database, user, password).
4. Extensão `postgres_fdw` disponível no cluster CRAS.
5. Backup do banco CRAS antes do cutover (recomendado).

---

## Fluxo Principal

| Passo | Ator | Comando / Ação | Resultado |
|-------|------|----------------|-----------|
| 1 | Admin | `php artisan etl:schema-diff` | Relatório de divergências; **falha** se houver coluna/tabela não catalogada em `EXPECTED_DIVERGENCES`. |
| 2 | Admin | Revisa diff; atualiza `migrate.sql` se necessário | Script alinhado ao schema atual. |
| 3 | Admin | `php artisan etl:run --confirm` | Executa `migrate.sql` em transação única. |
| 4 | — | Script faz `TRUNCATE ... RESTART IDENTITY` nas tabelas de domínio | CRAS limpo antes da carga. |
| 5 | — | FDW import + `INSERT ... SELECT` em ordem topológica de FK | Dados copiados Geo → CRAS. |
| 6 | — | Reset de sequências PostgreSQL | IDs novos consistentes pós-import. |
| 7 | Admin | Valida contagens, geometrias PostGIS, amostragem funcional | Cutover aprovado; Geo desligado. |

---

## Ordem de carga (topológica)

Ordem típica em `migrate.sql` (respeitar FKs):

1. Lookups (`tipo_abordagem`, `resultados_acoes`, `encaminhamentos`, …)
2. `users` / permissões (se incluídos)
3. `pontos`, `endereco_atualizados`
4. `vistorias`, pivots (`vistoria_participantes`, abrigos, …)
5. `moradores`, `morador_historicos`
6. Mídia / metadados (conforme script atual)

Consultar `etl/migrate.sql` para ordem exata — **fonte da verdade**.

---

## Comandos Artisan

| Comando | Função |
|---------|--------|
| `etl:schema-diff` | Pre-flight: compara schemas Geo vs CRAS |
| `etl:run --confirm` | Executa migração (exige flag explícita) |

Implementação: `app/Console/Commands/Etl/SchemaDiffCommand.php`, `RunCommand.php`.

Conexão fonte: `config/database.php` → `pgsql_geo` (`ETL_SOURCE_*`).

---

## Ambientes

| Ambiente | Execução |
|----------|----------|
| **Produção (Docker)** | `sudo docker exec php84-poprua-cras php artisan etl:...` |
| **Local** | Com Geo acessível na rede; raramente usado em dev |

---

## Regras de Negócio

| ID | Regra |
|----|-------|
| RN1 | ETL é **one-shot** — não é sincronização contínua. |
| RN2 | `etl:schema-diff` deve passar **antes** de `etl:run`; divergência não catalogada bloqueia. |
| RN3 | Transação única: falha no meio reverte toda a carga. |
| RN4 | Schema CRAS pós-migration é a referência; Geo não altera estrutura do CRAS. |
| RN5 | Tabelas runtime Laravel (`cache`, `sessions`, `jobs`, `migrations`) não entram na migração. |
| RN6 | Geometrias PostGIS mantêm SRID 4326; validar amostra pós-carga. |

---

## Riscos e mitigações

| Risco | Mitigação |
|-------|-----------|
| Container CRAS perde rede com Geo após rebuild | Declarar rede externa no `docker-compose.yml` (ver skill) |
| Nova coluna no Geo sem update do SQL | `schema-diff` falha até atualizar `migrate.sql` + `EXPECTED_DIVERGENCES` |
| Mídia/fotos em disco | ETL cobre metadados DB; arquivos exigem rsync/sync separado se aplicável |

---

## Critérios de aceite (operacionais)

- [x] Comandos registrados e documentados na skill `poprua-etl`
- [x] `migrate.sql` versionado em `etl/`
- [x] Pre-flight impede execução cega
- [ ] Cutover em produção executado e validado (evento único — fora do escopo de teste automatizado)

---

## Runbook de cutover (produção)

Checklist operacional para o dia do cutover Geo → CRAS. Executar **na ordem**.

### T-7 dias — preparação

| # | Ação | Responsável |
|---|------|-------------|
| 1 | Congelar schema no Geo (sem migrations novas sem espelhar no CRAS) | Dev |
| 2 | `php artisan etl:schema-diff` em staging/homolog — exit 0 | DBA |
| 3 | Dry-run: `php artisan etl:run` (sem `--confirm`) | DBA |
| 4 | Validar rede Docker: `pg17-poprua-cras` ↔ `pg17-poprua-geo` | Ops |
| 5 | Documentar URL de rollback (Geo ainda ativo) | Gestor |

### T-1 dia

| # | Ação |
|---|------|
| 1 | Backup CRAS: `pg_dump -Fc -U poprua_cras poprua_cras > backup-pre-cutover.dump` |
| 2 | Backup mídia: `tar czf storage-media-$(date +%F).tar.gz storage/app/public/` |
| 3 | Comunicar janela de manutenção aos usuários |
| 4 | Verificar `.env` `ETL_SOURCE_*` apontando para Geo produção |

### T-0 — janela de cutover

| # | Ação | Comando / critério |
|---|------|-------------------|
| 1 | Colocar Geo em **somente leitura** (ou desligar writes) | Ops |
| 2 | Pre-flight final | `php artisan etl:schema-diff` → "Migracao pronta" |
| 3 | Executar ETL | `php artisan etl:run --confirm --skip-backup` |
| 4 | Conferir contagens impressas pelo comando vs Geo | DBA |
| 5 | Validar PostGIS | `ST_SRID = 4326`, `ST_IsValid` no relatório |
| 6 | Corrigir bairros inválidos se necessário | SQL `ST_MakeValid` (ids 188, 426) |
| 7 | Smoke test CRAS | Login, mapa, listar vistorias, abrir zeladoria |
| 8 | Apontar DNS/reverse proxy para CRAS | Ops |
| 9 | Monitorar logs 24h | Ops |

### T+1 — pós-cutover

| # | Ação |
|---|------|
| 1 | Manter Geo ligado **read-only** por 7–14 dias (rollback de emergência) |
| 2 | Desligar Geo após período de estabilização |
| 3 | Arquivar dumps e registrar data do cutover em changelog interno |

### Rollback (emergência)

1. Reverter proxy para Geo.
2. Restaurar dump CRAS pré-cutover **somente** se houver writes acidentais no CRAS durante testes.
3. Dados criados no CRAS pós-cutover **não** voltam automaticamente ao Geo — exige merge manual.

---

## Glossário

| Termo | Significado |
|-------|-------------|
| **FDW** | Foreign Data Wrapper — Postgres lê tabelas remotas do Geo como foreign tables. |
| **Cutover** | Momento em que o CRAS passa a ser o sistema oficial e o Geo é desligado. |
| **EXPECTED_DIVERGENCES** | Lista allowlist de diferenas de schema conhecidas no `SchemaDiffCommand`. |
