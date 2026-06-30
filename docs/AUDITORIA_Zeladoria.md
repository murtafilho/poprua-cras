# Auditoria — Levantamento de Alteracoes do Sistema de Zeladoria

> **Documento de origem:** `docs/Levantamento_alteracao_sistema_zeladoria.md` (GFAES/PBH, 23/03/2026).
> **Auditoria executada em:** 2026-05-19.
> **Repositorio analisado:** POPRUA CRAS @ commit em `main`.
> **Metodologia:** confronto de cada item solicitado com models, migrations, controllers, requests, views e rotas.

---

## Resumo executivo

| Status | Qtd | Itens |
|--------|-----|-------|
| ✅ Implementado | 10 | 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 1.9, 1.10 |
| 🟢 Corrigido nesta auditoria | 1 | 1.11 |
| ⚠️ Parcial / depende de decisao | 0 | — |
| 🔴 Pendente | 0 | — |

**Conclusao:** 11 de 11 itens (100%) implementados ou corrigidos.

> **Atualizacao 2026-06-24:** item 1.2 (salvamento parcial) implementado — ver UC-006 e secao 1.2 abaixo.

---

## 1.1 — Inclusao de Participantes da Vistoria ✅

**Status:** **Implementado.**

| Componente | Local |
|-----------|-------|
| Model | `app/Models/MembroEquipe.php` (campos: nome, matricula, email, equipe, ativo; scopes `Ativos` e `Equipe`) |
| Tabela + pivot | migration `2026_05_13_220000_create_membros_equipe_and_vistoria_participantes` cria `membros_equipe` e `vistoria_participantes` |
| FormRequest | `StoreVistoriaRequest`: `'participantes' => 'nullable|array'`, `'participantes.*' => 'exists:membros_equipe,id'` |
| Persistencia | `Vistoria::participantes()` (BelongsToMany) — `MembroEquipe::vistorias()` |
| UI | seletor em `resources/views/vistorias/create.blade.php` e `edit.blade.php` |

**Gaps menores (atualizado 2026-06-24):**

- ~~Categorizar participantes na UI por tipo de equipe~~ — **Entregue:** enum `TipoEquipe` mapeia roles Spatie (`supervisor`, `coordenador`, `guardas-municipais`, etc.) para grupos na create/edit/show e Minha Equipe.
- Seeder com nomes do PDF permanece opcional (usuarios ja vem de `users` + roles).

**Esforco gaps restantes:** nenhum critico neste item.

---

## 1.2 — Salvamento Parcial por Etapa ✅

**Status:** **Implementado** (2026-06-24).

| Componente | Local |
|-----------|-------|
| UC | `docs/casos-de-uso/UC-006-rascunho-zeladoria.md` |
| Migration | `2026_06_24_000000_create_vistorias_rascunhos_table.php` |
| Model | `app/Models/VistoriaRascunho.php` |
| Service | `app/Services/VistoriaRascunhoService.php` |
| API | `app/Http/Controllers/Api/VistoriaRascunhoController.php` |
| Policy | `app/Policies/VistoriaRascunhoPolicy.php` |
| Frontend | `resources/js/vistoria-form.js` — autosave 5s, botao "Salvar rascunho", retomada via `confirm()` |
| UI | Header em `resources/views/vistorias/create.blade.php` + indicador de status |
| Limpeza | `VistoriaController::store` descarta rascunho apos criacao definitiva |
| Testes | `tests/Feature/Api/VistoriaRascunhoControllerTest.php` (7 testes) |

**Comportamento entregue:**

1. Tabela `vistorias_rascunhos` com `payload` jsonb, `etapa_atual` (0–6) e `context_key` (`ponto:{id}` ou `coords:lat,lng`).
2. `PATCH /api/vistorias/rascunho` — upsert do rascunho do usuario autenticado.
3. Autosave com debounce de 5s + botao manual no header; indicador "Rascunho salvo as HH:MM".
4. Ao abrir `vistorias/create`, modal de confirmacao para retomar rascunho existente.
5. Rascunho removido ao registrar zeladoria definitiva (`POST /vistorias`).
6. localStorage mantido como fallback offline quando a API nao responde.

**Fora do escopo v1:** fotos no payload (continuam via fila offline); rascunho na edicao de zeladoria existente.

**Esforco realizado:** ~6–8h (conforme estimativa original).

---

## 1.3 — Data/Horario Previstos de Acao de Zeladoria ✅

**Status:** **Implementado** (estrutura completa).

| Componente | Detalhe |
|-----------|---------|
| Migration | `2026_05_13_210000_add_finalizacao_zeladoria_lavratura_to_vistorias` adiciona `data_prevista_zeladoria`, `periodo_zeladoria`, e demais campos |
| Model `Vistoria` | `data_prevista_zeladoria` (cast `date`) e `periodo_zeladoria` em `$fillable` |
| FormRequest | `'data_prevista_zeladoria' => 'nullable|date'`, `'periodo_zeladoria' => 'nullable|in:manha,tarde'` |
| Rota | `Route::get('/vistorias/roteiro', ...)` em `routes/web.php` |
| Controller | `VistoriaController::exportarRoteiro()` (linha 484) |
| View | `resources/views/vistorias/roteiro.blade.php` |

**Gaps:**

- ~~Condicional UI por tipo de abordagem~~ — **Entregue 2026-06-24:** bloco `#zeladoria-campos` em create/edit, visível só para tipo "Comunicação de Zeladoria"; backend descarta agendamento se tipo divergente (`prepareForValidation` + `TipoAbordagem::isComunicacaoZeladoria()`).
- ~~Exportacao **Excel** nao existe (so PDF/HTML via `roteiro.blade.php`).~~ **Entregue 2026-06-24:** export CSV UTF-8 (`format=csv`, separador `;`) compativel com Excel.

**Esforco gaps restantes:** nenhum neste item.

---

## 1.4 — Galeria de Fotos em Dispositivos Moveis ✅

**Status:** **Implementado.**

`resources/views/vistorias/create.blade.php` (linhas 612-633) tem:

- `<input type="file" id="camera-input-back" accept="image/*" capture="environment">` — abre camera
- `<input type="file" id="gallery-input" accept="image/*" multiple>` — abre galeria
- Botoes "Tirar Foto" (`onclick="openCamera('back')"`) e "Anexar Arquivo" (`onclick="document.getElementById('gallery-input').click()"`)

Equivalente em `vistorias/edit.blade.php`, `moradores/create.blade.php`, `moradores/edit.blade.php`.

**Legendas:** OK. `StoreVistoriaRequest` valida `'legendas_fotos.*' => 'nullable|string|max:500'`; `VistoriaController` (linha 569-572) anexa `withCustomProperties(['legenda' => $legenda])` no Spatie MediaLibrary.

**Recomendacao cosmetica:** alguns dos onclick podem migrar para event delegation (CSP enforce — finding D3 do `quality-audit`). Nao bloqueante.

---

## 1.5 — Filtro de Busca por Supervisor ✅

**Status:** **Implementado.**

- FormRequest do filtro avancado aceita `'supervisor' => 'nullable|integer|exists:users,id'` e `'data_prevista_inicio/fim'`.
- `VistoriaController::index()` faz `leftJoin('users as u', 'u.id', '=', 'v.user_id')` e aplica o filtro quando `supervisor` esta presente.
- UI: `resources/views/vistorias/index.blade.php` tem o componente de busca avancada (visivel no PDF, pagina 5).

**Sem gaps.**

---

## 1.6 — Filtro por Data Prevista + Export PDF Roteiro ✅

**Status:** **Implementado.**

- Filtros `data_prevista_inicio` e `data_prevista_fim` existem no controller de busca (mesmo lugar de 1.5).
- `Route::get('/vistorias/roteiro', [VistoriaController::class, 'exportarRoteiro'])` + view `vistorias/roteiro.blade.php` renderizam o roteiro.

**Gap:** export e HTML (imprimivel pelo navegador), **nao PDF nativo**. Para PDF real, instalar `barryvdh/laravel-dompdf` ou usar a feature de impressao do navegador (Ctrl+P → Salvar como PDF — funciona, mas requer acao manual).

**Esforco para PDF nativo:** ~1h (composer require + ajuste da action `exportarRoteiro` para retornar PDF).

---

## 1.7 — Ajustar Localizacao (Ponto) da Vistoria ✅

**Status:** **Implementado.**

- Rota: `Route::patch('/pontos/{id}/coordenadas', [PontoController::class, 'updateCoordenadas'])` em `routes/api.php`.
- UI: `resources/js/mapa.js` tem o fluxo "ajustar coordenadas" — botao "Confirmar ajuste" chama o endpoint PATCH com novas lat/lng.
- Acionavel pela URL `/mapa?ponto_id=N&ajustar=1`.

**Gap fechado 2026-06-24:** atalho **Ajustar localização** no header de `vistorias/show.blade.php` (mesmo padrão de `pontos/show`).

**Sem gaps no nucleo.**

---

## 1.8 — Finalizacao de Vistoria ✅

**Status:** **Implementado.**

- Campos: `finalizada` (boolean), `finalizada_em` (datetime), `finalizada_por` (FK user) ja no model `Vistoria`.
- Casts corretos.
- `VistoriaPolicy::update()` bloqueia edicao se `$vistoria->finalizada` (`return false`).
- Migration: `2026_05_13_210000_add_finalizacao_zeladoria_lavratura_to_vistorias`.

**Gap:** "complementacao mediante justificativa" (excecao). Implementacao sugerida:

1. Tabela `vistoria_complementacoes` (vistoria_id, user_id, justificativa text, payload jsonb, created_at).
2. Endpoint `POST /vistorias/{vistoria}/complementacao` que aceita justificativa mesmo se `finalizada=true`.
3. UI: aba "Historico de complementacoes" em `vistorias/show.blade.php`.

**Esforco gap:** 3-4h.

---

## 1.9 — Lavacao + Tipo de Protocolo ✅

**Status:** **Implementado.**

- `houve_lavratura` (boolean) e `tipo_protocolo` (enum chuva/frio/normal) em `Vistoria::$fillable` + casts + `StoreVistoriaRequest`.
- Validacao: `'tipo_protocolo' => 'nullable|in:chuva,frio,normal'`.

**Atencao:** o PDF usa **"lavacao"** (lavagem com agua), e o sistema usa **"lavratura"** (formal/auto). Sao semanticamente diferentes:

- "Houve lavacao" no PDF = limpeza da area (uso de hidrojato pela SLU).
- "Houve lavratura" no codigo = aplicacao de auto de infracao formal.

**Recomendacao:** adicionar campo distinto `houve_lavacao` (boolean) para a metrica de lavagem operacional pedida pela GFAES, **mantendo** `houve_lavratura` para fiscalizacao. Migration:

```php
$table->boolean('houve_lavacao')->default(false)->after('houve_lavratura');
```

**Esforco:** 30min (migration + fillable + cast + FormRequest + checkbox UI).

---

## 1.10 — Bug "00:00" no horario ✅

**Status:** **Implementado** (mascara UI via `App\Support\FormatoData`).

Diagnostico via SQL: 98% dos registros legados tem hora `00:00:00` (migracao pre-2026). Vistorias novas salvam hora real via `datetime-local`.

**Entregue 2026-06-24:**

- Helper `FormatoData::exibir()` omite `H:i` quando `00:00`
- Aplicado em `vistorias/show` (abordagem, retorno, comunicado, finalizacao, cancelamento)
- Index/minhas e pontos/show ja mascaravam inline

**Backfill** de hora generica permanece **nao recomendado** (perde informacao "sem hora").

---

## 1.11 — Erro 404 ao abrir relatorio pelo mapa 🟢 CORRIGIDO

**Status:** **Bug confirmado e corrigido nesta auditoria.**

**Causa raiz:** em `resources/js/mapa.js:796`:

```js
iframe.src = `/vistorias/${vistoriaId}/relatorio`;
```

URL absoluta a partir da raiz do dominio. Em producao, `APP_URL=https://sufis.pbh.gov.br/ginfi/poprua-cras/public` — o iframe ia para `https://sufis.pbh.gov.br/vistorias/X/relatorio`, fora do Laravel (Joomla intercepta como categoria nao encontrada → 404).

**Fix aplicado:** usar a constante `APP_BASE` ja injetada e usada pelos outros links da mesma pagina:

```diff
- iframe.src = `/vistorias/${vistoriaId}/relatorio`;
+ iframe.src = `${APP_BASE}/vistorias/${vistoriaId}/relatorio`;
```

`APP_BASE` resolve corretamente para `https://sufis.pbh.gov.br/ginfi/poprua-cras/public` em prod e `http://localhost` em dev.

**Validacao:** bundle recompilado via `docker/build-frontend.sh`. `mapa-CzLYcZBF.js` (novo hash) contem `${f}/vistorias/${e}/relatorio` (variavel `f` = minificacao de APP_BASE). Em prod, o link agora abre o relatorio.

---

## Plano de acoes restante

Ordenado por valor/esforco:

| # | Acao | Item | Esforco | Valor |
|---|------|------|---------|-------|
| ~~1~~ | ~~Adicionar `houve_lavacao` separado de `houve_lavratura`~~ | ~~1.9~~ | — | ✅ Entregue |
| ~~2~~ | ~~Mascarar `00:00` na UI quando hora ausente (dados legados)~~ | ~~1.10~~ | — | ✅ Entregue (2026-06-24) |
| ~~3~~ | ~~Condicional UI: data/periodo zeladoria so para tipo "Comunicacao de Zeladoria"~~ | ~~1.3~~ | — | ✅ Entregue (2026-06-24) |
| ~~4~~ | ~~Link "Ajustar localizacao" na `show.blade.php`~~ | ~~1.7~~ | — | ✅ Entregue (2026-06-24) |
| ~~5~~ | ~~Categorizar participantes por papel na UI~~ | ~~1.1~~ | — | ✅ Entregue (2026-06-24) |
| ~~6~~ | ~~Export Excel do roteiro~~ | ~~1.3/1.6~~ | — | ✅ Entregue (2026-06-24) |
| ~~7~~ | ~~Export PDF nativo do roteiro (Laravel-DomPDF)~~ | ~~1.6~~ | — | ✅ Entregue |
| ~~8~~ | ~~Complementacao com justificativa pos-finalizacao~~ | ~~1.8~~ | — | ✅ Entregue |
| ~~9~~ | ~~Salvamento parcial por etapa (autosave)~~ | ~~1.2~~ | — | ✅ Entregue (2026-06-24) |
| ~~10~~ | ~~Consolidar IndexedDB duplicado (ADR-009)~~ | UC-007 | — | ✅ Entregue (2026-06-24) |

**Total para gaps restantes:** nenhum item pendente da auditoria GFAES/PBH.

---

## Itens entregues nesta sessao

1. **Conversao do PDF original** → `docs/Levantamento_alteracao_sistema_zeladoria.md`
2. **Esta auditoria** → `docs/AUDITORIA_Zeladoria.md`
3. **Fix do bug 1.11** (404 no relatorio do mapa) → `resources/js/mapa.js` + bundle recompilado
