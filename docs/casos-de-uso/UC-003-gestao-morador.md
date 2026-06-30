# UC-003 — Gestão de Morador e Histórico de Movimentação

**Versão:** 1.0
**Data:** 2026-06-24
**Status:** Implementado

---

## Objetivo

Descrever o cadastro, a movimentação entre pontos e o arquivamento de **moradores** — pessoas identificadas vinculadas ao fenômeno da população em situação de rua. O morador é entidade de **dados pessoais (PII)**; todas as rotas exigem autenticação. O histórico de movimentação (`MoradorHistorico`) garante rastreabilidade de entrada, saída e transferência entre pontos.

---

## Atores

| Ator | Descrição |
|------|-----------|
| **Profissional de campo** | Usuário autenticado que cadastra moradores, registra presença em zeladorias e consulta histórico. |
| **Sistema (MoradorService)** | Executa transações atômicas de entrada, saída, transferência e sincronização de presença na zeladoria. |

---

## Pré-condições

1. O usuário está autenticado no sistema.
2. Para vincular morador a um ponto, o ponto deve existir na base.
3. Para registrar movimentação via zeladoria, a vistoria deve existir e pertencer ao ponto informado.

---

## Modelo de Dados Resumido

### Morador

| Campo | Obrigatório | Descrição |
|-------|:-----------:|-----------|
| `nome_social` | Sim | Nome pelo qual a pessoa é conhecida |
| `nome_registro` | Não | Nome em documentos oficiais |
| `apelido` | Não | Apelido ou nome alternativo |
| `genero` | Não | Texto livre (max 100) |
| `documento` | Não | CPF, RG etc. (max 50) |
| `contato` | Não | Telefone ou contato (max 50) |
| `observacoes` | Não | Texto livre |
| `ponto_atual_id` | Não | Ponto onde o morador está no momento; `null` se sem vínculo |

Fotos são gerenciadas pela Spatie MediaLibrary (coleção `fotos`), não por campo no banco.

### MoradorHistorico

Cada registro representa uma estadia em um ponto:

| Campo | Descrição |
|-------|-----------|
| `data_entrada` | Data de chegada (obrigatória) |
| `data_saida` | Data de saída; `null` enquanto a estadia está aberta |
| `vistoria_entrada_id` | Vistoria que registrou a chegada (opcional) |
| `vistoria_saida_id` | Vistoria que registrou a saída (opcional) |

**Invariante:** um morador não pode ter mais de um histórico aberto (`data_saida = null`) simultaneamente.

---

## Fluxo Principal A — Cadastrar Morador (Web)

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Profissional | Acessa "Moradores" no menu e clica em "Novo morador" (opcionalmente com `?ponto_id=` na URL). | Sistema exibe formulário de cadastro. Se `ponto_id` informado, o ponto aparece pré-selecionado. |
| 2 | Profissional | Preenche `nome_social` (obrigatório) e demais campos; pode anexar fotos. | Validação via `StoreMoradorRequest`. |
| 3 | Profissional | Submete o formulário. | Se `ponto_id` informado: `MoradorService::criarComEntrada` cria o morador e registra entrada no ponto em transação única. Caso contrário: morador criado sem vínculo. |
| 4 | — | — | Redirecionamento para `moradores.show` com mensagem de sucesso. |

---

## Fluxo Principal B — Consultar e Filtrar Moradores

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Profissional | Acessa listagem (`/moradores`). | Sistema exibe moradores paginados (15 por página), ordenados por `nome_social`. |
| 2 | Profissional | Aplica filtros opcionais: busca textual, gênero, situação (`com_ponto` / `sem_ponto`). | Query filtrada em `nome_social`, `apelido` e `nome_registro`. |
| 3 | Profissional | Clica em um morador. | Página de detalhes exibe dados cadastrais, ponto atual (se houver) e **histórico completo** de movimentação com endereços. |

---

## Fluxo Alternativo C — Movimentação via Zeladoria (Presença)

Executado automaticamente ao **criar** uma zeladoria (`POST /vistorias`), via `MoradorService::atualizarPresencaVistoria`.

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Profissional | No formulário de nova zeladoria, marca moradores presentes (`moradores_presentes[]`) e/ou adiciona novos inline (`novos_moradores[]`). | — |
| 2 | Sistema | Compara IDs marcados com moradores atuais do ponto. | Moradores que **sumiram** da lista recebem `registrarSaida` com data da vistoria. |
| 3 | Sistema | — | Moradores **novos** na lista (já cadastrados) recebem `registrarEntrada`. |
| 4 | Sistema | — | Entradas em `novos_moradores` são criadas via `criarComEntrada`. |
| 5 | — | — | Toda a operação roda em `DB::transaction`. |

**Nota:** a gestão de moradores **não** está no formulário de **edição** de zeladoria — apenas na criação.

---

## Fluxo Alternativo D — Entrada, Saída e Transferência (API)

Endpoints REST autenticados em `/api/moradores/{morador}/...`:

### D.1 — Registrar entrada

| Passo | Ação | Resultado |
|-------|------|-----------|
| 1 | `POST /api/moradores/{id}/entrada` com `ponto_id` (obrigatório) e `vistoria_id` (opcional). | Fecha histórico aberto anterior (se existir), atualiza `ponto_atual_id`, cria novo `MoradorHistorico`. |

### D.2 — Registrar saída

| Passo | Ação | Resultado |
|-------|------|-----------|
| 1 | `POST /api/moradores/{id}/saida` com `vistoria_id` (opcional). | Fecha histórico aberto, define `ponto_atual_id = null`. Se não houver histórico aberto, retorna sucesso sem alteração. |

### D.3 — Transferir entre pontos

| Passo | Ação | Resultado |
|-------|------|-----------|
| 1 | `POST /api/moradores/{id}/transferir` com `ponto_id` (destino) e `vistoria_id` (opcional). | Operação atômica: fecha histórico no ponto anterior e abre histórico no novo ponto. Equivalente a saída + entrada. |

---

## Fluxo Alternativo E — Busca para Detecção de Migração

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Profissional | No formulário de zeladoria, digita nome no autocomplete. | `GET /api/moradores/buscar?termo=...&excluir_ponto_id=...` retorna até 20 moradores cujo nome/apelido/nome_registro corresponda ao termo (mínimo 2 caracteres). |
| 2 | — | — | Resultado inclui endereço do ponto atual (se houver), permitindo identificar possível migração de pessoa já cadastrada. |

---

## Fluxo Alternativo F — Arquivar e Restaurar

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Profissional | Exclui morador via web (`DELETE /moradores/{id}`) ou API (`DELETE /api/moradores/{id}`). | Se vinculado a ponto: `registrarSaida` antes do soft delete. Morador arquivado (`deleted_at` preenchido); dados e histórico preservados. |
| 2 | Profissional | Consulta arquivados via `GET /api/moradores/arquivados`. | Lista paginada de moradores com soft delete. |
| 3 | Profissional | Restaura via `POST /api/moradores/{id}/restaurar`. | Remove `deleted_at`. Morador volta à listagem ativa (sem re-vincular automaticamente ao ponto). |

---

## Fluxo Alternativo G — Fotografias

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Profissional | Anexa fotos no cadastro/edição web ou via API. | Upload para coleção `fotos` (Spatie MediaLibrary). Metadado `uploaded_by_user_id` registrado. |
| 2 | Profissional | Gerencia fotos via API dedicada. | `GET/POST/DELETE /api/moradores/{id}/fotos` — listagem, upload e remoção individual. |

Formatos aceitos: JPEG, PNG, WebP. Tamanho máximo: 10 MB por arquivo.

---

## Resumo das Regras de Negócio

| # | Regra |
|---|-------|
| RN1 | Todas as rotas de morador (web e API) exigem autenticação. |
| RN2 | `nome_social` é o único campo obrigatório no cadastro. |
| RN3 | Um morador possui no máximo um histórico aberto por vez; nova entrada fecha automaticamente o histórico anterior. |
| RN4 | `ponto_atual_id` reflete a situação corrente; `null` indica morador sem vínculo ativo a ponto. |
| RN5 | Movimentações (entrada, saída, transferência, presença na zeladoria) executam-se em `DB::transaction`. |
| RN6 | A presença na zeladoria é sincronizada **somente na criação** da vistoria, não na edição. |
| RN7 | Exclusão é soft delete (arquivamento); histórico e dados cadastrais são preservados. |
| RN8 | Ao arquivar morador vinculado, o sistema registra saída automática antes do delete. |
| RN9 | Busca por nome retorna no máximo 20 resultados; termo mínimo de 2 caracteres. |
| RN10 | Fotos de morador usam exclusivamente MediaLibrary (coleção `fotos`); campo legado `fotografia` foi removido. |

---

## Rotas Principais

### Web (Blade)

| Método | URI | Nome | Descrição |
|--------|-----|------|-----------|
| GET | `/moradores` | `moradores.index` | Listagem com filtros |
| GET | `/moradores/create` | `moradores.create` | Formulário de cadastro |
| POST | `/moradores` | `moradores.store` | Persistência |
| GET | `/moradores/{morador}` | `moradores.show` | Detalhes + histórico |
| GET | `/moradores/{morador}/edit` | `moradores.edit` | Formulário de edição |
| PUT | `/moradores/{morador}` | `moradores.update` | Atualização |
| DELETE | `/moradores/{morador}` | `moradores.destroy` | Soft delete |

### API (JSON)

| Método | URI | Descrição |
|--------|-----|-----------|
| GET | `/api/moradores` | Listagem paginada (filtros: `ponto_id`, `search`, `sem_ponto`) |
| GET | `/api/moradores/buscar` | Autocomplete/migração |
| GET | `/api/moradores/arquivados` | Moradores arquivados |
| POST | `/api/moradores/{id}/restaurar` | Restaurar arquivado |
| GET | `/api/moradores/{id}/historico` | Histórico de movimentação |
| POST | `/api/moradores/{id}/entrada` | Registrar entrada |
| POST | `/api/moradores/{id}/saida` | Registrar saída |
| POST | `/api/moradores/{id}/transferir` | Transferir entre pontos |
| GET | `/api/pontos/{ponto}/moradores` | Moradores do ponto |

---

## Glossário

| Termo | Significado |
|-------|-------------|
| **Morador** | Pessoa identificada no sistema, com dados pessoais e possível vínculo a um ou mais pontos ao longo do tempo. |
| **Histórico aberto** | Registro em `MoradorHistorico` com `data_saida = null` — morador está no ponto. |
| **Transferência** | Migração atômica de um ponto para outro (saída + entrada). |
| **Arquivamento** | Soft delete do morador; dados preservados para auditoria. |
| **Presença na zeladoria** | Lista de moradores marcados como presentes durante uma abordagem; dispara sincronização automática de movimentação. |
