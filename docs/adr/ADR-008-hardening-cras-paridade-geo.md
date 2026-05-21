# ADR-008: Aplicar no CRAS as hardenings que o Geo já tem

**Data:** 2026-05-20
**Status:** Implementado (commit `pendente`; smoke 4/4 + tests 349/349)

## Contexto

`docs/REFERENCIA_DOCKER_LEGADO.md` mapeia 8 best practices comparando
Geo vs CRAS. O Geo está com hardenings completas (security_opt, cap_drop,
image digests, DB_HOST específico) depois do ADR-007. O CRAS ainda não:
estamos em produção neste ambiente mas com menos defesa em profundidade
que o sistema legado que vamos desligar — situação que vale corrigir.

## Decisões

1. **Image digests** no `docker-compose.yml` do CRAS para `postgis/postgis:17-3.5` e `redis:7-alpine` (motivação: ataque XZ Utils de ago/2025 mostrou que digest pinning impediu propagação silenciosa).
2. **`security_opt: no-new-privileges:true`** em todos os serviços (app, db, redis, queue, ssh, init-perms).
3. **`cap_drop: [ALL]`** + `cap_add` mínimo, espelhando o Geo:
   - `app` e `queue`: `CHOWN, DAC_OVERRIDE, FOWNER, SETGID, SETUID, KILL, NET_BIND_SERVICE`
   - `db`: `CHOWN, DAC_OVERRIDE, FOWNER, SETGID, SETUID`
   - `redis`: `SETGID, SETUID`
4. **`DB_HOST=pg17-poprua-cras`** no `.env` do CRAS (em vez do alias service-level `db`). Defensivo — evita o bug de alias compartilhado se a network do CRAS um dia for incluída em outra stack via `external`.
5. **NÃO** rodar o ETL ou outros writes durante a janela; só rebuild + smoke.

## NÃO inclui

- Multi-stage build na imagem (decisão de revisitar pós-cutover, conforme doc).
- Trocar imagem base do PHP (decisão pós-cutover; a libpq depende dela).
- Migrar postgres-data do CRAS para outro storage (já está em named volume).

## Plano

Cada passo independentemente reversivel via `git revert`.

| Passo | Ação | Risco | Reversão |
|-------|------|-------|----------|
| α | Editar `docker-compose.yml` aplicando os 4 itens | Zero (arquivo) | git checkout |
| β | Editar `.env` para `DB_HOST=pg17-poprua-cras` | Zero (config) | git checkout |
| γ | `docker compose up -d` (recreate de cada serviço com novas restrições) | Médio — cap_drop errado pode quebrar nginx/fpm | rollback do compose + up |
| δ | `bash docker/smoke-test.sh` — se FAIL, rollback imediato | — | passo C |
| ε | Após smoke verde: commit + push | Zero | trivial |

## Sinais de sucesso

1. `docker compose up -d` recria sem erro
2. `php artisan test` continua 349/349
3. `docker/smoke-test.sh` continua 4/4 (PASS)
4. Após `docker compose down && up -d`: tudo verde sem intervenção

## Consequências

**Fica mais fácil:** auditoria de segurança do CRAS bate com a do Geo;
incidentes onde container é comprometido têm menos blast radius.

**Fica mais difícil:** capabilities adicionadas no futuro precisam ser
deliberadas (require `cap_add` explícito).
