# E2E — POPRUA CRAS (Playwright Test)

Suite end-to-end das jornadas criticas, dirigindo um navegador real (Chromium) via Playwright.

## Jornadas cobertas
| Spec | Jornada |
|------|---------|
| `01-vistoria-core` | Login → criar vistoria (caminho de escrita real) → editar → finalizar |
| `02-morador-equipe` | Vistoria com morador aninhado; UI de participantes/equipe (RBAC) |
| `03-mapa-pontos-fotos` | Listagem/detalhe de pontos, mapa Leaflet + markers, foto de acervo serve 200 |
| `04-admin-rbac` | Admin acessa `/admin/*`; agente de campo e **barrado** (403/redirect) |

## Pre-requisitos
- Node 18+ e `npm install` nesta pasta.
- Navegador: `npx playwright install chromium` (uma vez).
- Usuarios de teste seedados (senha `Cras@2026`): `php artisan db:seed --class=TestUsersSeeder`.

## Como rodar

```bash
cd e2e
npm install

# Alvo LOCAL (padrao) — exige o ambiente dev de pe (php artisan serve --port=8088)
npx playwright test

# Alvo PRODUCAO (com guarda de escrita: tudo marcado [HOMOLOG-E2E] e removido no afterEach)
E2E_BASE_URL=https://sufis.pbh.gov.br/ginfi/poprua-cras/public npx playwright test

# Um spec / headed / relatorio
npx playwright test tests/01-vistoria-core.spec.ts
npx playwright test --headed
npx playwright show-report
```

Credenciais e alvo sao configuraveis por env (ver `.env.example`).

## Seguranca de escrita
As specs de escrita (01, 02) criam registros marcados com `[HOMOLOG-E2E]` e os **removem no
`afterEach`** (resource destroy / soft delete). Por isso a suite e segura inclusive contra producao.
Para um expurgo definitivo dos marcados (hard delete), rodar no banco:
`DELETE FROM vistorias WHERE nomes_pessoas LIKE '[HOMOLOG-E2E]%'; DELETE FROM moradores WHERE nome_social LIKE '[HOMOLOG-E2E]%';`

## Estrutura
- `playwright.config.ts` — config; `baseURL` por `E2E_BASE_URL`; projeto `setup` faz login e salva storageState.
- `tests/auth.setup.ts` — autentica admin e agente; grava `.auth/*.json` (reusado pelos specs).
- `helpers.ts` — login, csrf, criar/finalizar/remover vistoria, primeiro ponto.
- `tests/*.spec.ts` — as 4 jornadas.

## Notas
- Roda serial (`workers: 1`) porque os testes de escrita compartilham estado de dados.
- Contra ambiente sem dados (local recem-seedado sem pontos), os specs read-only que dependem
  de pontos fazem `skip` automatico. Para cobertura plena no local, seedar dados de dominio.
