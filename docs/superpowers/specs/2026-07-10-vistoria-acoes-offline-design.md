# Spec — Finalizar/cancelar/reativar vistoria offline

- **Data:** 2026-07-10
- **Status:** aprovado (design) — pendente de revisão do spec
- **Fase do roadmap:** Fase 0, fatia 2 de 3 do "offline de vistorias"
- **Escopo:** mudanças de estado (finalizar, cancelar, reativar) de uma vistoria existente sem rede

## 1. Contexto

A fatia 1 (criar vistoria offline via outbox) está em produção. Esta fatia leva o mesmo
padrão às **mudanças de estado** de uma vistoria já persistida (a página de show só existe
para vistoria com id real do servidor).

As três ações são **flips de flag idempotentes** (`app/Http/Controllers/VistoriaController.php`):

- `finalizar` → `finalizada=true`, `finalizada_em`, `finalizada_por` (autoriza `update`).
- `cancelar` → `cancelada=true`, `cancelada_em`, `cancelada_por` (autoriza `cancelar`).
- `reativar` → `finalizada=false` (autoriza `reativar`).

Por serem idempotentes, um reenvio é seguro — **não** precisam de `client_uuid`/dedup nem de
reconciliação de id. Isso torna esta fatia menor e mais simples que a fatia 1.

`complementar` **fica de fora**: ele *acrescenta* texto à observação (não idempotente — um
reenvio duplicaria o texto), o que exigiria dedup por `action_uuid`. É menos comum em campo.

## 2. Objetivo e "pronto"

O agente, na página de uma vistoria existente, **perde o sinal** e clica
Finalizar/Cancelar/Reativar → a ação é **salva no aparelho** e **sincronizada** ao voltar a
conexão, sem regressão para o usuário web.

**Pronto quando:**

1. Com rede: as três ações aplicam e a tela reflete o novo estado (sem regressão).
2. Sem rede: ao clicar (após o `confirm()` atual), a ação vai para a fila local, aparece
   toast "salva no aparelho", o **badge** incrementa e os botões de estado ficam
   **desabilitados/"pendente"** (evita duplo clique).
3. Ao voltar a conexão (`online`/reabertura/Background Sync/botão manual), a ação é enviada e
   o estado é aplicado no servidor.
4. Reenvio da mesma ação (retry) é seguro — o servidor só refaz o flip (idempotente).

## 3. Decisões travadas

| Decisão | Escolha | Motivo |
|---|---|---|
| Escopo | finalizar + cancelar + reativar | Idempotentes; complementar (append) fica para depois |
| Endpoints | `POST /api/vistorias/{id}/finalizar\|cancelar\|reativar` (JSON) | Consistente com a fatia 1; autorização pelas Policies |
| Lógica | Extrair para `VistoriaService` (finalizar/cancelar/reativar) | DRY: web e API chamam o mesmo método |
| Outbox | IndexedDB **separado** `poprua_vistoria_acoes` (store `pendentes`) | Não tocar o `poprua_vistorias` (produção); sem coordenar versão |
| Idempotência | Natural (flip de flag) | Reenvio seguro; sem `client_uuid`/dedup |
| Sync | Background Sync `sync-acoes-vistoria` + `online`/`visibilitychange` global + botão | Robustez, espelha a fatia 1 |
| UI offline | Toast + badge + botões desabilitados/"pendente" | Evita duplo clique; feedback claro |

## 4. Arquitetura

### 4.1 Backend

- **`VistoriaService`**: novos métodos `finalizar(Vistoria)`, `cancelar(Vistoria)`,
  `reativar(Vistoria)` com a lógica de update + invalidação de cache. O
  `VistoriaController` (web) passa a chamá-los (refatoração DRY, coberta pelos testes
  existentes).
- **`Api\VistoriaAcaoController`**: `finalizar`/`cancelar`/`reativar`, cada um autoriza pela
  Policy correspondente (`update`/`cancelar`/`reativar`), chama o service e retorna
  `{ "id": <int>, "finalizada": <bool>, "cancelada": <bool> }`.
- **Rotas** em `routes/api.php` no grupo `['web','auth']`:
  `POST /vistorias/{vistoria}/finalizar`, `/cancelar`, `/reativar`.
- CSRF via `X-XSRF-TOKEN`/`X-CSRF-TOKEN` como os demais endpoints.

### 4.2 Frontend (web — PWA e app)

- **Módulo de outbox de ações** (novo `resources/js/offline-vistoria-acao.js`, reutilizando o
  padrão de `offline-vistoria.js`): `enqueueAcao`, `getPendingAcoes`, `countPendingAcoes`,
  `syncOneAcao`, `syncPendingAcoes`, `registerAcaoSync`. IndexedDB **`poprua_vistoria_acoes`**,
  store `pendentes`, registro `{ vistoria_id, acao, status, created_at }`.
- **`resources/js/vistoria-show.js`**: interceptar os 3 forms de estado. `preventDefault` →
  manter o `confirm()` atual → tentar `POST /api/vistorias/{id}/{acao}`.
  - **Rede OK** → aplica; **recarrega a página de show** (reflete o novo estado; espelha o
    comportamento web atual, que redireciona para o show após a ação).
  - **Falha de rede** → `enqueueAcao` → toast + badge + **desabilita os botões de estado** e
    marca "pendente de envio".
- **Service Worker** (`public/sw.js`): handler Background Sync `sync-acoes-vistoria` espelhando
  o de vistorias (lê `poprua_vistoria_acoes`, POST com `X-XSRF-TOKEN`; em sucesso remove; em
  4xx permanente marca `failed`; em 5xx/rede mantém). **Incrementar `CACHE_VERSION` (36 → 37).**
- **`resources/js/app.js`**: badge global soma fotos + vistorias + **ações**; o auto-sync
  global (`online`/`visibilitychange`) e o botão manual passam a sincronizar as ações também.

### 4.3 Idempotência e reconciliação

- **Idempotência:** natural — reenviar `finalizar` mantém `finalizada=true`, etc. O endpoint
  aplica o estado desejado independentemente do estado atual (sem erro se já aplicado).
- **Reconciliação:** nenhuma (a vistoria já tem id real; sem temp ids).

## 5. Unidades de trabalho

1. **`VistoriaService`** finalizar/cancelar/reativar + refatorar `VistoriaController` (web) para usá-los.
2. **`Api\VistoriaAcaoController`** + rotas + testes de feature (aplica, autoriza, idempotente).
3. **Outbox de ações** (`offline-vistoria-acao.js`, IndexedDB `poprua_vistoria_acoes`).
4. **Interceptação em `vistoria-show.js`** (3 forms, confirm, online/offline, UI otimista).
5. **Background Sync `sync-acoes-vistoria`** no `sw.js` + bump `CACHE_VERSION` 37.
6. **Badge/auto-sync** incluindo ações (`app.js`).

## 6. Fora de escopo (desta fatia)

- `complementar` offline (append, não idempotente — exige dedup por `action_uuid`).
- Editar (update) vistoria offline (outra fatia).
- Finalizar/cancelar uma vistoria criada offline e ainda não sincronizada (não há página de show).
- `destroy` (excluir) offline.

## 7. Assunções e riscos

| Item | Estado | Mitigação |
|---|---|---|
| Endpoints da API aplicam a MESMA autorização (Policies) que a web | ⚠️ obrigatório | Autorizar `update`/`cancelar`/`reativar` no controller; testar 403 |
| Refatorar o `VistoriaController` (web) para o service | ⚠️ baixo risco | Coberto pelos testes existentes de finalizar/cancelar |
| Idempotência (reenvio) | ✅ natural (flip) | Endpoint aplica estado-alvo; teste de reenvio |
| UI otimista sem quebrar a página | ⚠️ | Desabilitar botões + marcador "pendente"; sem reescrever o show |
| Bump de `CACHE_VERSION` (37) | ⚠️ esperado | Documentar |
| Deploy = produção | ⚠️ | Implementar + revisar; **push só com confirmação explícita** |

## 8. Critérios de aceitação

- [ ] Online: finalizar/cancelar/reativar aplica e a tela reflete o estado (sem regressão web).
- [ ] Offline: clicar enfileira, toast + badge, botões desabilitados/"pendente".
- [ ] Reconexão: a ação sincroniza e o estado é aplicado no servidor.
- [ ] Reenvio da mesma ação é seguro (idempotente), sem erro.
- [ ] 403 quando o usuário não tem permissão (Policy) no endpoint da API.
- [ ] Nenhuma regressão no fluxo web de finalizar/cancelar/reativar.
- [ ] `php artisan test` (incl. novos), `vendor/bin/pint`, `vendor/bin/phpstan` limpos.
