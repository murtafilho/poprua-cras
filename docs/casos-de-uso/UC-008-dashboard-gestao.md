# UC-008 — Dashboard de Gestão

**Versão:** 1.0  
**Data:** 2026-06-24  
**Status:** Implementado

---

## Objetivo

Descrever a **tela de dashboard** (`/dashboard`) — visão gerencial do fenômeno de rua em Belo Horizonte: totais agregados, evolução mensal por resultado da última zeladoria por ponto, e filtros interativos no gráfico. Complementa o mapa operacional (UC-004) com indicadores estratégicos.

**Referências:** `DashboardService` · `DashboardController` · `resources/js/dashboard.js` · testes `DashboardControllerTest`.

---

## Atores

| Ator | Descrição |
|------|-----------|
| **Usuário autenticado** | Qualquer login válido acessa o dashboard (sem role exclusiva). |
| **Gestor / analista** | Interpreta tendências de persistência, extinção e conformidade. |

---

## Pré-condições

1. Usuário autenticado.
2. Dados históricos em `vistorias` e `pontos` (migrados ou cadastrados no CRAS).

---

## Fluxo Principal

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Usuário | Acessa `/dashboard` (link no menu, bottom nav "Mais", ou pós-login Breeze). | Página carrega indicadores e gráfico. |
| 2 | — | `DashboardService` consulta cache ou DB. | Três cards: Total de Pontos, Pontos Vistoriados, Total de Vistorias. |
| 3 | — | — | Gráfico "Evolução do Fenômeno" (Chart.js) com séries mensais. |
| 4 | Usuário | Clica filtros (Todos, Ativos, Persiste, Extinto, etc.). | `dashboard.js` alterna visibilidade das séries. |

---

## Indicadores (cards)

| Card | Fonte | Cache key |
|------|-------|-----------|
| Total de Pontos | `Ponto::count()` | `dashboard:total_pontos` |
| Pontos Vistoriados | `COUNT(DISTINCT ponto_id)` em vistorias | `dashboard:totais` |
| Total de Vistorias | `COUNT(*)` vistorias | `dashboard:totais` |

TTL do cache: **30 minutos** (`CACHE_TTL = 1800`).

Invalidação manual via `Cache::forget('dashboard:*')` em `VistoriaController` após store/update/destroy de zeladorias.

---

## Gráfico — Evolução do Fenômeno

Query SQL com CTEs em `DashboardService::dadosMensais()`:

1. **meses** — série mensal desde a primeira `data_abordagem` (≥ 2017-01-01) até hoje.
2. **ponto_primeiro** — primeira vistoria de cada ponto.
3. **ultima_vistoria_mes** — última zeladoria do ponto em cada mês civil.
4. **status_no_mes** — carry-forward do último resultado conhecido até cada mês.

### Colunas por mês

| Campo | Significado |
|-------|-------------|
| `persiste` | Resultado id=1 |
| `impactado_parcial` | id=2 |
| `deixou_ocorrer` | id=3 (extinto) |
| `ausente` | id=4 |
| `nao_constatado` | id=5 |
| `conformidade` | id=6 |
| `sem_vistoria` | Sem resultado atribuído |
| `extintos` | Resultados 3 ou 5 |
| `ativos` | Resultados 1, 2, 4 ou 6 |
| `total_efetivo` | Total − extintos |

Vistorias soft-deleted são **excluídas** da agregação.

---

## Frontend

- View: `resources/views/dashboard.blade.php`
- JS: `resources/js/dashboard.js` (entry Vite separado)
- Chart.js em chunk manual (`chartjs`) via `vite.config.js`
- Dados injetados: `@json($dadosMensais)`, `$resultados`

---

## Regras de Negócio

| ID | Regra |
|----|-------|
| RN1 | Dashboard exige autenticação; guest redireciona ao login. |
| RN2 | Soft deletes de vistorias não entram nos totais nem no gráfico. |
| RN3 | Cache de 30 min — alterações recentes podem demorar até TTL para refletir (salvo invalidação no CRUD de vistorias). |
| RN4 | Mapa (`/mapa`) é home operacional; dashboard é home analítica pós-login Breeze/welcome. |
| RN5 | IDs de resultado hardcoded na SQL (1–6) — alinhados à tabela `resultados_acoes` seedada. |

---

## Critérios de aceite (verificados)

- [x] `GET /dashboard` retorna 200 autenticado
- [x] Contagens corretas com factories (`DashboardControllerTest`)
- [x] Estrutura mensal com 13 chaves numéricas + `mes`
- [x] Cache populado após primeiro acesso
- [x] Vistorias deletadas excluídas do total

---

## Glossário

| Termo | Significado |
|-------|-------------|
| **Fenômeno** | Situação de rua / população em situação de rua monitorada via pontos. |
| **Extinto** | Ponto cujo último resultado é "deixou de ocorrer" ou "não constatado". |
| **Carry-forward** | Propaga o último resultado conhecido do ponto para meses sem nova vistoria. |
