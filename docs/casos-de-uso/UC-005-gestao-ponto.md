# UC-005 — Gestão de Ponto e Informação Precária

**Versão:** 1.0
**Data:** 2026-06-24
**Status:** Implementado

---

## Objetivo

Descrever o ciclo de vida de um **ponto** — local físico georreferenciado onde o fenômeno da população em situação de rua é monitorado. Cobre criação automática via zeladoria, listagem com filtros, indicador de **informação precária**, cálculo de **complexidade**, edição de metadados (admin) e ajuste de coordenadas (via mapa ou formulário).

---

## Atores

| Ator | Descrição |
|------|-----------|
| **Profissional de campo** | Consulta pontos, visualiza histórico de zeladorias e aciona fluxos derivados (nova zeladoria, ajuste no mapa). |
| **Administrador** | Único perfil autorizado a editar metadados do ponto via formulário web (`PontoPolicy::update`). |
| **Sistema (PontoService)** | Cria/reutiliza pontos por proximidade espacial, calcula status derivado e serve dados ao mapa e às listagens. |

---

## Pré-condições

1. O usuário está autenticado para todas as rotas web e API de pontos.
2. Novos pontos exigem coordenadas válidas (`lat`/`lng` obrigatórios na criação de zeladoria — ADR-004).
3. A listagem web (`/pontos`) exibe apenas pontos **georreferenciados com endereço vinculado** (`endereco_atualizado_id` preenchido).

---

## Modelo de Dados Resumido

| Campo | Descrição |
|-------|-----------|
| `lat`, `lng` | Coordenadas WGS84 (`decimal(17,14)`) |
| `geom` | Coluna PostGIS `POINT SRID 4326`, sincronizada via `PontoObserver` |
| `endereco_atualizado_id` | FK opcional para tabela geocodificada da prefeitura |
| `numero`, `complemento` | Identificação descritiva do local |
| `observacao` | Texto livre |
| `caracteristica_abrigo_id` | Lookup de tipo de abrigo |
| `deleted_at` | Soft delete |

### Atributos derivados (não persistidos no ponto)

| Atributo | Fonte |
|----------|-------|
| **Resultado** | `resultado_acao_id` da última vistoria não excluída |
| **Complexidade** | Soma ponderada dos 16 flags booleanos da última vistoria (pesos configuráveis em `/admin/parametros`) |
| **Informação precária** | `true` se sem vistoria ou última vistoria há mais de N dias (parâmetro `info_precaria_dias`, default 60) |
| **Total de zeladorias** | Contagem de vistorias não excluídas do ponto |

---

## Fluxo Principal A — Criação Automática via Zeladoria

Pontos **não** são criados manualmente pelo usuário. Nascem quando uma zeladoria é registrada.

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Profissional | Registra zeladoria com `lat`/`lng` (mapa ou formulário), sem informar `ponto_id`. | `PontoService::findOrCreateFromCoordinates` é invocado. |
| 2 | Sistema | Busca ponto existente num raio de **50 metros** (`ST_Distance` em geography). | Se encontrado: reutiliza o ponto (`created = false`). |
| 3 | Sistema | — | Se não encontrado: cria ponto com `numero = 'S/N'`, atualiza `geom` (Observer) e vincula endereço mais próximo via `EnderecoService::vincularEnderecoAoPonto`. |
| 4 | Sistema | — | Zeladoria é associada ao `ponto_id` resolvido. |

**Variante:** se `ponto_id` é informado na zeladoria, o ponto existente é usado diretamente (sem busca por proximidade).

---

## Fluxo Principal B — Listar e Filtrar Pontos

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Profissional | Acessa `/pontos`. | Listagem paginada (default 5 por página, máx. 100) com endereço, resultado, complexidade e flag de informação precária. |
| 2 | Profissional | Aplica filtros opcionais. | Filtros: logradouro, número, bairro, regional, resultado (incluindo valor especial `info_precaria`). |
| 3 | Profissional | Clica em um ponto ou no botão de ações. | Acesso a detalhes, mapa, nova zeladoria ou ajuste de coordenadas. |

Ordenação: logradouro → número (ordem numérica natural).

---

## Fluxo Alternativo C — Consultar Detalhes do Ponto

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Profissional | Acessa `/pontos/{id}`. | Exibe endereço, coordenadas, resultado da última zeladoria, complexidade, moradores vinculados (se houver). |
| 2 | — | — | Lista paginada de zeladorias do ponto, ordenadas por `data_abordagem` decrescente (50 por página). |
| 3 | Profissional | Clica em "Nova zeladoria neste ponto". | Redireciona para `/pontos/{ponto}/vistorias/create` com coordenadas pré-preenchidas. |

---

## Fluxo Alternativo D — Informação Precária

Indica pontos que **precisam de visita prioritária** por desatualização.

| Condição | `info_precaria = true` |
|----------|------------------------|
| Ponto sem nenhuma zeladoria | Sim |
| Última zeladoria há mais de N dias | Sim (N = `info_precaria_dias`, default 60) |
| Última zeladoria dentro do prazo | Não |

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Profissional | Filtra listagem por `resultado=info_precaria`. | Exibe apenas pontos precários. |
| 2 | Profissional | Registra nova zeladoria no ponto. | Status precário removido automaticamente (data da nova vistoria passa a ser referência). |

O parâmetro `info_precaria_dias` é editável em `/admin/parametros`.

---

## Fluxo Alternativo E — Editar Ponto (Administrador)

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Admin | Acessa `/pontos/{id}/edit`. | Formulário com mini-mapa, autocomplete de endereço e campos editáveis. |
| 2 | Admin | Altera número, complemento, observação, coordenadas e/ou endereço vinculado. | Validação via `UpdatePontoRequest`. |
| 3 | Admin | Salva. | `PontoPolicy::update` — **somente admin** (`before()` retorna `true` para role admin). |
| 4 | Sistema | — | Se coordenadas mudaram e endereço não foi selecionado manualmente: re-vincula endereço por proximidade. `PontoObserver` atualiza `geom`. |

Usuários comuns recebem **403 Forbidden** ao tentar `PUT /pontos/{id}`.

---

## Fluxo Alternativo F — Ajustar Coordenadas pelo Mapa

Documentado em UC-004 (modo `ajustar=1`). Resumo:

| Passo | Ação | Resultado |
|-------|------|-----------|
| 1 | Na listagem de pontos, acionar "Ajustar no mapa". | Mapa abre focado no ponto com crosshair. |
| 2 | Reposicionar e salvar. | `PATCH /api/pontos/{id}/coordenadas` atualiza `lat`, `lng`, `geom` e re-vincula endereço. |

---

## Fluxo Alternativo G — API para Mapa e Autocomplete

Endpoints autenticados consumidos pelo mapa e formulários:

| Endpoint | Uso |
|----------|-----|
| `GET /api/pontos?north&south&east&west` | Marcadores no mapa (limite 5000 por bbox) |
| `GET /api/pontos/{id}` | Detalhes para popup/modal |
| `PATCH /api/pontos/{id}/coordenadas` | Ajuste/geocode de coordenadas |
| `GET /api/pontos/busca?q=` | Autocomplete de pontos por logradouro (min 3 chars) |
| `GET /api/enderecos/*` | Busca, pesquisa e reverse geocoding |

Pontos no mapa incluem: resultado, complexidade, total de vistorias e flag `info_precaria`.

---

## Complexidade do Ponto

Calculada a partir dos **16 flags booleanos** da última vistoria:

```
complexidade = Σ (flag × peso_flag)
```

Pesos default = 1, configuráveis individualmente em `/admin/parametros` (`peso_resistencia`, `peso_casal`, etc.). Expressão SQL parametrizada disponível em `Ponto::complexidadeSqlParametrizada()` para queries otimizadas.

Flags considerados: resistência, número reduzido, casal, catador, fixação antiga, excesso de objetos, tráfico, criança/adolescente, idosos, gestante, LGBTQIAPN+, cena de uso, deficiente, agrupamento químico, saúde mental, animais.

---

## Relacionamentos

```
PONTO
 ├── enderecoAtualizado (opcional)
 ├── vistorias[] (1:N)
 ├── ultimaVistoria (1:1 derivado)
 ├── moradores[] (via ponto_atual_id)
 └── historicoMoradores[] (MoradorHistorico)
```

- **Resultado exibido** = resultado da vistoria de maior `id` não excluída (proxy de "última").
- Pontos fora do limite municipal de BH foram eliminados na migração Geo→CRAS.

---

## Resumo das Regras de Negócio

| # | Regra |
|---|-------|
| RN1 | Ponto é criado automaticamente na primeira zeladoria; não há rota de criação manual. |
| RN2 | Reutilização por proximidade: ponto existente a ≤ 50 m previne duplicatas. |
| RN3 | Coordenadas são obrigatórias na criação de zeladoria; pontos sem coords não podem ser criados (ADR-004). |
| RN4 | Coluna `geom` (PostGIS SRID 4326) é sincronizada automaticamente pelo `PontoObserver` em create/update de lat/lng. |
| RN5 | Endereço oficial é vinculado por proximidade espacial (`EnderecoService`) na criação e ao atualizar coordenadas. |
| RN6 | Listagem web exige ponto georreferenciado **com** endereço vinculado; mapa exige apenas lat/lng válidos. |
| RN7 | Informação precária: sem vistoria OU última vistoria > N dias (`info_precaria_dias`, default 60). |
| RN8 | Complexidade deriva da última vistoria; pesos são parametrizáveis. |
| RN9 | Edição de ponto via web restrita a **administradores** (`PontoPolicy`). |
| RN10 | Exclusão de ponto é soft delete; activity log registra alterações (`LogsActivity`). |
| RN11 | Ajuste de coordenadas via API re-vincula endereço e retorna endereço formatado na resposta. |

---

## Rotas Principais

### Web

| Método | URI | Nome | Descrição |
|--------|-----|------|-----------|
| GET | `/pontos` | `pontos.index` | Listagem filtrada |
| GET | `/pontos/{id}` | `pontos.show` | Detalhes + zeladorias |
| GET | `/pontos/{ponto}/edit` | `pontos.edit` | Formulário (admin edita) |
| PUT | `/pontos/{ponto}` | `pontos.update` | Persistência (admin only) |
| GET | `/pontos/{ponto}/vistorias/create` | `pontos.vistorias.create` | Nova zeladoria pré-populada |

### API

| Método | URI | Descrição |
|--------|-----|-----------|
| GET | `/api/pontos` | Bounding box para mapa |
| GET | `/api/pontos/{id}` | Detalhes JSON |
| PATCH | `/api/pontos/{id}/coordenadas` | Atualizar lat/lng/geom |
| GET | `/api/pontos/busca` | Autocomplete |
| GET | `/api/pontos/{ponto}/moradores` | Moradores no ponto |

---

## Glossário

| Termo | Significado |
|-------|-------------|
| **Ponto** | Local físico georreferenciado onde o fenômeno é monitorado; agrupa zeladorias e moradores. |
| **Informação precária** | Status de desatualização — ponto sem visita recente, prioridade de campo. |
| **Complexidade** | Índice numérico de vulnerabilidade derivado dos flags da última zeladoria. |
| **Endereço atualizado** | Registro da base geocodificada da prefeitura (`endereco_atualizados`). |
| **Reutilização por proximidade** | Evita duplicar pontos a menos de 50 m de distância. |
| **Geom** | Representação espacial PostGIS do ponto, usada em queries GIST/ST_Distance. |
