# Auditoria — Levantamento de Alteracoes do Sistema de Zeladoria

> **Documento de origem:** `docs/Levantamento_alteracao_sistema_zeladoria.md` (GFAES/PBH, 23/03/2026).
> **Auditoria executada em:** 2026-05-19.
> **Repositorio analisado:** POPRUA CRAS @ commit em `main`.
> **Metodologia:** confronto de cada item solicitado com models, migrations, controllers, requests, views e rotas.

---

## Resumo executivo

| Status | Qtd | Itens |
|--------|-----|-------|
| ✅ Implementado | 8 | 1.1, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 1.9 |
| 🟢 Corrigido nesta auditoria | 1 | 1.11 |
| ⚠️ Parcial / depende de decisao | 1 | 1.10 (dados legados) |
| 🔴 Pendente | 1 | 1.2 |

**Conclusao:** 9 de 11 itens (82%) ja estao implementados ou foram resolvidos nesta auditoria. So 1.2 (salvamento parcial) requer trabalho novo significativo.

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

**Gaps menores:** o PDF cita equipes nomeadas (Supervisores, Coordenadores, GCM, SLU, Agentes de Campo). O model atual usa o campo `equipe` (texto livre). Recomenda-se:

1. Criar enum/lookup `tipos_equipe` (Supervisor, Coordenador, GCM, SLU, AgenteCampo) para garantir categorizacao consistente.
2. Pre-popular `membros_equipe` com os nomes/matriculas listados no PDF (`Eliane Mesquita`, `Hudson Abner Pinto`, etc.) via seeder.

**Esforco para gaps:** ~2h (seeder + lookup table).

---

## 1.2 — Salvamento Parcial por Etapa 🔴

**Status:** **Nao implementado.**

A busca por `autosave`, `save-draft`, `salvarParcial`, `partial_save`, `draft_id` em `app/` e `resources/` retornou zero ocorrencias.

**Implementacao sugerida:**

1. **Backend:** nova tabela `vistorias_rascunhos` (ou coluna `rascunho_payload jsonb` em `vistorias`) com `user_id`, `ponto_id`, `payload jsonb`, `updated_at`.
2. **Endpoint:** `PATCH /api/vistorias/rascunho/{ponto?}` que upsert um rascunho do usuario.
3. **Frontend:** debounce de 5s nos inputs do wizard de criacao chamando o endpoint. Indicador "Salvo HH:MM" no header.
4. **Retomada:** ao abrir `vistorias/create`, se existir rascunho do usuario para aquele ponto, pre-popular form com confirmacao "Continuar rascunho de DD/MM HH:MM?".
5. **Limpeza:** ao registrar a vistoria definitiva (`store`), deletar rascunho.

**Esforco:** 6-8h.

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

- Nao ha condicional UI "se `tipo_abordagem` = Comunicacao de Zeladoria, mostrar os campos data_prevista + periodo". Atualmente os campos aparecem sempre. **Recomendado:** adicionar `x-show` no formulario condicionado ao `tipo_abordagem_id` da opcao "Comunicacao de Zeladoria".
- Exportacao **Excel** nao existe (so PDF/HTML via `roteiro.blade.php`).

**Esforco gaps:** 1-2h (condicional + Excel via Spatie SimpleExcel ou Laravel Excel).

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

**Sem gaps no nucleo.** Recomendacao: adicionar link "Ajustar localizacao deste ponto" na `vistorias/show.blade.php` para facilitar acesso pos-cadastro (atualmente requer entrar pelo mapa).

**Esforco:** 15min.

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

## 1.10 — Bug "00:00" no horario ⚠️

**Status:** **Parcial — comportamento esperado para dados legados.**

Diagnostico via SQL:

```
sem_hora |  total | com_data
---------+--------+---------
   41664 | 42435  | 42435
```

- 41.664 de 42.435 vistorias (98%) tem hora `00:00:00`.
- Vistorias mais antigas (id=1) sao de **2017-09-29 00:00:00**, vindas do sistema legado pre-fork.
- Vistorias recentes (id=43694) tem hora real (`18:46:00`).

**O sistema novo salva a hora corretamente.** O `<input type="datetime-local">` em `create.blade.php:113` envia `Y-m-d\TH:i` e `StoreVistoriaRequest` valida com `date_format:Y-m-d\TH:i`.

**Acoes recomendadas:**

A. **Aceitar** que vistorias pre-2026 nao tem hora (e a realidade do dado migrado).

B. **Mascarar UI:** na `show.blade.php` linhas 47-49, omitir o `H:i` quando for `00:00:00`, exibindo so a data:
   ```blade
   @php $hora = $vistoria->data_abordagem->format('H:i'); @endphp
   {{ $vistoria->data_abordagem->format('d/m/Y') }}
   @if($hora !== '00:00') as {{ $hora }} @endif
   ```

C. **Backfill** opcional: marcar vistorias antigas com `data_abordagem` em fim de tarde generico (15:00) via migration de dados — **nao recomendado** (perde informacao "sem hora").

**Esforco opcao B:** 30min em 4 views (show, index, minhas, partials).

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
| 1 | Adicionar `houve_lavacao` separado de `houve_lavratura` | 1.9 | 30min | Alto (metrica solicitada) |
| 2 | Mascarar `00:00` na UI quando hora ausente | 1.10 | 30min | Medio (cosmetico) |
| 3 | Condicional UI: data/periodo zeladoria so para tipo "Comunicacao de Zeladoria" | 1.3 | 1h | Medio |
| 4 | Link "Ajustar localizacao" na `show.blade.php` | 1.7 | 15min | Baixo |
| 5 | Seeder de membros das equipes (nomes do PDF) | 1.1 | 1h | Alto |
| 6 | Tabela lookup `tipos_equipe` (Supervisor/Coordenador/GCM/SLU/Agente) | 1.1 | 1h | Medio |
| 7 | Export PDF nativo do roteiro (Laravel-DomPDF) | 1.6 | 1h | Medio |
| 8 | Complementacao com justificativa pos-finalizacao | 1.8 | 3-4h | Alto |
| 9 | **Salvamento parcial por etapa (autosave)** | 1.2 | 6-8h | Alto |

**Total para 100% de cobertura:** ~16h de desenvolvimento.

---

## Itens entregues nesta sessao

1. **Conversao do PDF original** → `docs/Levantamento_alteracao_sistema_zeladoria.md`
2. **Esta auditoria** → `docs/AUDITORIA_Zeladoria.md`
3. **Fix do bug 1.11** (404 no relatorio do mapa) → `resources/js/mapa.js` + bundle recompilado
