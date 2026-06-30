# UC-010 — Parametrização Administrativa

**Versão:** 1.0  
**Data:** 2026-06-24  
**Status:** Implementado

---

## Objetivo

Descrever a **tela de parametrização** (`/admin/parametros`) — configuração editável em runtime de regras de workflow, mapa, listagens, limites de upload e pesos de complexidade territorial, sem necessidade de deploy. Complementa UC-005 (complexidade do ponto) e UC-002 (workflow de zeladoria).

**Referências:** `Parametro` · `ParametroController` · `resources/views/admin/parametros/index.blade.php` · permissão Spatie `gerenciar parametros`.

---

## Atores

| Ator | Descrição |
|------|-----------|
| **Administrador** | Usuário com role `admin` (`middleware role:admin`). Acessa, edita, cria e remove parâmetros. |
| **Sistema** | Lê valores via `Parametro::get()` com cache Redis/file (TTL 1 h). |

---

## Pré-condições

1. Usuário autenticado com role `admin`.
2. Tabela `parametros` migrada (seed inicial na migration `2026_05_24_220930`).

---

## Fluxo Principal — Editar parâmetros

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Admin | Acessa `/admin/parametros` (menu admin ou dashboard). | Lista agrupada por abas: Geral, Workflow, Mapa, Listagem, Limites, Complexidade. |
| 2 | Admin | Navega pelas abas e altera valores. | Campos respeitam `tipo` (string, integer, float, boolean). |
| 3 | Admin | Clica **Salvar**. | `PUT admin/parametros` → `Parametro::set()` por chave; cache `param:{chave}` invalidado. |
| 4 | — | — | Flash: "Parâmetros atualizados com sucesso." |

---

## Fluxo Alternativo A — Novo parâmetro

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Admin | Expande **Novo Parâmetro**, preenche chave, grupo, tipo, valor, descrição. | Chave: `[a-z0-9_]+`, única. |
| 2 | Admin | Submete formulário. | `POST admin/parametros` cria registro. |

---

## Fluxo Alternativo B — Remover parâmetro

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Admin | Clica ícone de lixeira na linha. | Prompt exige digitar `REMOVER`. |
| 2 | Admin | Confirma. | `DELETE admin/parametros/{chave}`; cache limpo. |

---

## Grupos e parâmetros principais

| Grupo | Chaves (exemplos) | Consumo no código |
|-------|-------------------|-------------------|
| **geral** | `app_nome`, `app_orgao` | Branding (views/relatórios) |
| **workflow** | `info_precaria_dias`, `exigir_comunicado`, `rascunho_debounce_ms`, `rascunho_dias_expiracao` | `PontoService`, `Store/UpdateVistoriaRequest`, autosave UC-006, job `rascunhos:limpar` |
| **mapa** | `mapa_centro_lat`, `mapa_centro_lng`, `mapa_zoom_padrao` | Inicialização do mapa (UC-004) |
| **listagem** | `vistorias_por_pagina`, `paginacao_max` | Paginação de listas |
| **limites** | `foto_max_tamanho_kb` | Validação de upload de fotos |
| **complexidade** | `peso_*` (16 fatores), `complexidade_critico/alto/medio` | `Ponto::complexidadeSqlParametrizada()`, badges de ponto (UC-005) |

Pesos `peso_{fator}` usam default **1** quando a chave não existe — permite cadastrar via UI sem migration.

---

## Modelo de dados

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `chave` | string PK | Identificador snake_case |
| `valor` | text | Sempre armazenado como string; cast no `get()` |
| `tipo` | enum | `string`, `integer`, `float`, `boolean` |
| `grupo` | string | Agrupa abas na UI |
| `descricao` | string | Label amigável na listagem |

### API interna

```php
Parametro::get('info_precaria_dias', 60);  // int, com default
Parametro::set('exigir_comunicado', '1');  // invalida cache
```

Cache key: `param:{chave}` · TTL: 3600 s.

---

## Regras de negócio

| # | Regra |
|---|-------|
| RN1 | Apenas role `admin` acessa rotas `/admin/parametros*`. |
| RN2 | Valores booleanos persistem como `'0'`/`'1'`; `get()` retorna bool via `filter_var`. |
| RN3 | Alteração de peso de complexidade afeta **cálculo futuro**; não recalcula histórico de vistorias. |
| RN4 | `exigir_comunicado = true` torna `data_comunicado` obrigatório antes de agendar zeladoria (`Store/UpdateVistoriaRequest`). |
| RN5 | `info_precaria_dias` define janela para status "Informação Precária" na listagem de pontos. |
| RN6 | Exclusão de parâmetro exige confirmação explícita (`REMOVER`) — evita clique acidental. |

---

## Rotas

| Método | Rota | Ação |
|--------|------|------|
| GET | `/admin/parametros` | `index` |
| PUT | `/admin/parametros` | `update` (batch) |
| POST | `/admin/parametros` | `create` |
| DELETE | `/admin/parametros/{chave}` | `destroy` |

---

## Critérios de aceite

- [x] Admin acessa tela; não-admin recebe 403.
- [x] Salvar atualiza valor e reflete em `Parametro::get()` após invalidação de cache.
- [x] `exigir_comunicado` altera validação de comunicado (`VistoriaJourneyTest`).
- [x] UI agrupa por abas sticky com contador por grupo.
- [x] Testes Feature dedicados a `ParametroController` (`ParametroControllerTest`).

---

## Dívida técnica

1. **Parâmetros opcionais** — algumas chaves documentadas na UI (`paginacao_max`, thresholds) podem ser criadas via "Novo Parâmetro" se ainda não existirem no banco de produção.

---

## Glossário

| Termo | Significado |
|-------|-------------|
| **Informação Precária** | Ponto sem vistoria há mais de N dias (`info_precaria_dias`). |
| **Peso de complexidade** | Multiplicador por flag booleana da última vistoria no cálculo territorial. |
