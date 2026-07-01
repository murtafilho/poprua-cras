# Skill: poprua-etl — ETL Geo → CRAS

Operações de migração one-shot do POPRUA Geo para o POPRUA CRAS na RMI (`vlcp-sufis01`).

## RBAC (Opção C — CRAS-canônico)

| Tabela | Origem no cutover |
|--------|-------------------|
| `users` | Geo (`migrate.sql`, `ativo=TRUE`) |
| `model_has_roles` | Geo com remap por `roles.name` (`etl/remap-model-has-roles.sql`, fase 5c) |
| `roles`, `permissions`, `role_has_permissions`, `model_has_permissions` | **Não migrar** — `PermissoesSeeder` (fase 5b) |

Papéis Geo com nome inexistente no CRAS ficam sem atribuição. Auditar antes do cutover:

```bash
docker exec pg17-poprua-geo psql -U poprua -d poprua_geo -c \
  "SELECT name FROM roles WHERE name NOT IN (SELECT name FROM ...)"  # ou comparar manualmente
```

Papéis canônicos do CRAS: `admin`, `supervisor`, `coordenador`, `agente`, `agentes-campo`, `agentes-slu`, `guardas-municipais`.

## Artefatos

| Arquivo | Função |
|---------|--------|
| `etl/migrate.sql` | FDW + TRUNCATE domínio/users + INSERT (sem RBAC de definição) |
| `etl/remap-model-has-roles.sql` | Remap `model_has_roles` após seeder |
| `etl/cutover.sh` | Orquestração completa (10 fases + 5b/5c RBAC) |
| `app/Console/Commands/Etl/SchemaDiffCommand.php` | `etl:schema-diff` |
| `app/Console/Commands/Etl/RunCommand.php` | `etl:run --confirm` |

## Comandos na RMI

```bash
CRAS=/var/www/html/joomla_sufis/ginfi/poprua-cras

# Pre-flight (read-only)
sudo bash $CRAS/etl/cutover.sh --check

# Rehearsal (carga real, geo continua ativo)
sudo bash $CRAS/etl/cutover.sh --apply

# Cutover real
sudo bash $CRAS/etl/cutover.sh --apply --freeze --deactivate-geo
```

Fases RBAC (só em `--apply`):

1. **5** — `etl:run --confirm` (domínio + users; `etl_geo` fica disponível)
2. **5b** — `php artisan db:seed --class=PermissoesSeeder --force`
3. **5c** — `psql < etl/remap-model-has-roles.sql`

## Pré-requisitos

- Containers: `pg17-poprua-geo`, `pg17-poprua-cras`, `php84-poprua-cras`, `queue-poprua-cras`
- Rede FDW: cluster CRAS na rede `poprua-geo_poprua-geo`
- `.env` CRAS: `ETL_SOURCE_*` com senha do Geo (não do compose histórico)
- Migrations CRAS aplicadas

## Validação pós-cutover

| Check | Critério |
|-------|----------|
| Schema | `etl:schema-diff` exit 0 |
| Domínio | `pontos`, `vistorias`, `moradores`, `media` geo = cras |
| `model_has_roles` | count geo ≈ cras (warn se divergir — papéis sem match) |
| RBAC | 23 permissions, 7 roles |
| Fotos | gap `media.id` sem pasta = 0 (fase 6 rsync obrigatório) |
| PostGIS | `ST_IsValid(geo_bairros)` = 0; `ST_SRID(pontos)` = 4326 |

## Troubleshooting

**`etl_geo` não existe na fase 5c:** rodar remap na mesma janela do `etl:run`; não reiniciar PG entre fases 5 e 5c.

**`model_has_roles` geo > cras:** papéis Geo com nome divergente; listar órfãos:

```sql
SELECT gr.name, count(*)
FROM etl_geo.model_has_roles g
JOIN etl_geo.roles gr ON gr.id = g.role_id
LEFT JOIN public.roles cr ON cr.name = gr.name AND cr.guard_name = gr.guard_name
WHERE cr.id IS NULL
GROUP BY gr.name;
```

**Senha FDW falha:** usar `DB_PASSWORD` do `.env` do **poprua-geo**, não credencial antiga do docker-compose.

**Fotos quebradas:** ETL só copia metadados; rsync fase 6 é obrigatório. Com geo ativo durante carga, usar `--freeze`.

## Referências

- `docs/casos-de-uso/UC-009-etl-geo-cras.md`
- `database/seeders/PermissoesSeeder.php`
