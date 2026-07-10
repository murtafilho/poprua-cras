# Spec — Criar vistoria offline (outbox)

- **Data:** 2026-07-10
- **Status:** aprovado (design) — pendente de revisão do spec
- **Fase do roadmap:** Fase 0 (endurecer o campo), fatia 1 de 3 do "offline de vistorias"
- **Escopo desta fatia:** criação NOVA de vistoria quando o agente perde o sinal durante o preenchimento

## 1. Contexto

Hoje a criação de vistoria é um **form POST nativo** (`resources/views/vistorias/create.blade.php`
→ `VistoriaController::store`, `RedirectResponse`) e o Service Worker **ignora tudo que não é
GET** (`public/sw.js:31`). Logo, criar vistoria sem rede não funciona.

A **fila de fotos** já resolve o mesmo problema para a sub-entidade foto, com um padrão
maduro e reaproveitável (`resources/js/offline-upload.js`, `public/sw.js`):

- id temporário `temp_<ts>` em `sessionStorage` (`initTempPhotoSession`);
- gravação otimista local no IndexedDB `poprua_fotos` (store `pendentes`);
- filtro que impede envio prematuro de registros `temp_*` (`getSyncablePhotos`);
- sincronização por **Background Sync** (SW) + eventos `online`/`visibilitychange` +
  polling 30s + botão manual;
- CSRF em background via header **`X-XSRF-TOKEN`** (cookie) no SW (`sw.js:189-195`) e
  `X-CSRF-TOKEN` (meta) na página (`offline-upload.js:238-259`);
- reconciliação `temp → id real` quando o servidor confirma o id (`reconcileTempId`).

O **servidor já resolve o ponto** (PostGIS) a partir de lat/lng no momento da criação
(`VistoriaService::criarComRelacionamentos` → `PontoService::findOrCreateFromCoordinates`).
Portanto o cliente **nunca precisa** resolver ponto offline — basta enfileirar o payload e o
servidor resolve na sincronização.

**Decisão de projeto (revisada):** a implementação **pode alterar o web**. Fazemos a solução
"do jeito certo" no app web, espelhando a infra de fotos. Como consequência positiva, o
offline de criação passa a funcionar **também no PWA no navegador**, não só no app Capacitor.

## 2. Objetivo e critério de "pronto"

O agente abre o formulário de nova vistoria (com sinal), preenche, **perde o sinal** e ao
enviar a vistoria é **salva no aparelho** e **sincronizada** quando a conexão volta — sem
perda de dados e **sem duplicar** a vistoria.

**Pronto quando:**

1. Com rede, criar vistoria funciona como hoje (redireciona para o `show`), agora via
   requisição AJAX — sem regressão para o usuário web.
2. Sem rede, ao enviar: a vistoria vai para uma fila local (IndexedDB), aparece um aviso
   "salva no aparelho — será enviada quando houver conexão" e o **badge de pendências**
   incrementa.
3. Ao voltar a conexão (evento `online`/reabertura/Background Sync/botão manual), a vistoria
   pendente é enviada, recebe o **id real**, as **fotos** daquela vistoria são reconciliadas
   (`temp → id real`) e passam a sincronizar, e a vistoria some da fila.
4. Um reenvio (retry) após resposta perdida **não cria vistoria duplicada** (idempotência).

## 3. Decisões travadas

| Decisão | Escolha | Motivo |
|---|---|---|
| Abordagem | Fetch + outbox no cliente (Abordagem A), com mudanças no web | Robusta; isola a mudança no fluxo de criação; beneficia PWA + app |
| Infra offline | **Espelhar a fila de fotos** (IndexedDB + Background Sync + reconcile + CSRF via cookie) | Padrão já comprovado no projeto; consistência e menor risco |
| Idempotência | `client_uuid` (UUID v4) gerado no cliente + índice único no servidor | Evita vistoria duplicada em retry após resposta perdida |
| Sincronização | Nível de página **e** Background Sync no SW (como fotos) | Robustez: sincroniza mesmo com o app fechado |
| Vistoria pendente | Badge global "N pendentes" + toast (v1) | Consistente com fotos; listar em "minhas-vistorias" fica para depois |
| Escopo | Só criação NOVA | Editar/finalizar offline são outras fatias |

## 4. Arquitetura

### 4.1 Backend (Laravel)

- **Endpoint JSON de criação:** `POST /api/vistorias` (grupo `['web','auth']`, mesmo modelo do
  endpoint de fotos), recebendo o payload da vistoria em JSON e retornando
  `{ "id": <int>, "redirect_url": "<url do show>", "client_uuid": "<uuid>" }`.
  Reutiliza `VistoriaService::criarComRelacionamentos` (a mesma lógica do `store` web).
- **Form Request JSON:** `StoreVistoriaApiRequest` espelhando as regras de `StoreVistoriaRequest`
  (lat/lng obrigatórios, tipos, participantes), adaptado a JSON (sem multipart de fotos — as
  fotos continuam pela fila própria).
- **Idempotência:** migração adicionando `client_uuid` (uuid, **nullable**, **índice único**) à
  tabela `vistorias`. No endpoint, se já existir vistoria com aquele `client_uuid` do mesmo
  usuário, **retorna a existente** (não recria). Coluna nullable → sem impacto no fluxo web
  atual.
- **CSRF:** o endpoint aceita `X-XSRF-TOKEN`/`X-CSRF-TOKEN` como os demais endpoints `web`.

### 4.2 Frontend (web — vale para PWA e app)

- **Interceptar o submit** em `resources/js/vistoria-form.js`: `preventDefault()`, serializar o
  form em JSON, anexar `client_uuid` (gerado uma vez por tentativa) e o `temp_id` da sessão de
  fotos, e chamar `POST /api/vistorias`.
  - **Rede OK:** recebe `id` → `reconcileTempId(temp_id, id)` → navega para `redirect_url`
    (experiência atual preservada).
  - **Falha de rede:** grava na **outbox** (novo store IndexedDB `vistorias_pendentes`), mostra
    o aviso, incrementa o badge e registra a sincronização.
- **Módulo de outbox de vistorias** (novo `resources/js/offline-vistoria.js`, reutilizando os
  helpers de IndexedDB de `offline-upload.js`): `enqueueVistoria`, `getPendingVistorias`,
  `syncPendingVistorias`. A fila vive em um **IndexedDB separado `poprua_vistorias`** (store
  `pendentes`), para **não** alterar a versão do banco de fotos `poprua_fotos` (evita
  `onupgradeneeded` e risco à fila de fotos). O sync faz `POST /api/vistorias` (com
  `client_uuid`), na volta chama `reconcileTempId` e remove da fila; então dispara o sync das
  fotos daquela vistoria.
- **Service Worker** (`public/sw.js`): novo handler de Background Sync tag `sync-vistorias`
  espelhando `syncPendingPhotos` (lê `pendentes` do IndexedDB `poprua_vistorias`, POST com
  `X-XSRF-TOKEN`, reconcilia). **Incrementar `CACHE_VERSION`.**
- **Badge:** estender `updateSyncBadge` (`resources/js/app.js`) para somar fotos + vistorias
  pendentes; incluir vistorias no botão "Sincronizar" do menu.

### 4.3 Reconciliação

- **Fotos:** `reconcileTempId(temp_id, idReal)` já existe — passa a ser chamado também no
  fluxo de sync da vistoria (além do atual, na página de show).
- **Moradores e participantes:** vão no próprio payload; são criados server-side em
  `criarComRelacionamentos`. Sem reconciliação no cliente.

## 5. Unidades de trabalho

1. **Migração `client_uuid`** em `vistorias` (nullable + índice único).
2. **Endpoint `POST /api/vistorias`** (Api\VistoriaController@store) + `StoreVistoriaApiRequest`,
   reutilizando `VistoriaService`, com dedup por `client_uuid`.
3. **Outbox de vistorias** (`resources/js/offline-vistoria.js`, IndexedDB `poprua_vistorias`
   store `pendentes`) — enqueue/get/sync.
4. **Interceptação do submit** em `vistoria-form.js` (fetch + fallback outbox + reconcile +
   navegação).
5. **Background Sync `sync-vistorias`** no `sw.js` + bump de `CACHE_VERSION`.
6. **Badge/menu** de pendências incluindo vistorias.

## 6. Fora de escopo (desta fatia)

- Editar/finalizar/cancelar vistoria offline (fatias seguintes).
- Abrir o formulário de criação já estando offline (exigiria SW cachear HTML + listas).
- Listar a vistoria pendente dentro de "minhas-vistorias" (exige mesclar lista local + servidor).
- Qualquer mudança no fluxo de fotos além de chamar `reconcileTempId` no sync da vistoria.

## 7. Assunções e riscos

| Item | Estado | Mitigação |
|---|---|---|
| Servidor resolve ponto (PostGIS) na criação a partir de lat/lng | ✅ já é assim | — |
| Payload de criação é serializável em JSON (sem binário) | ✅ fotos trafegam à parte | — |
| CSRF em background via `X-XSRF-TOKEN` (cookie) | ✅ igual às fotos | Reutilizar o mesmo mecanismo |
| Migração em produção (coluna nullable + índice único) | ⚠️ additiva | Sem backfill; nullable não afeta o fluxo atual |
| Bump de `CACHE_VERSION` invalida cache offline | ⚠️ esperado | Documentar; SW reautaliza assets |
| Deploy: mudanças no web disparam deploy de produção | ⚠️ | Implementar + revisar; **push só com confirmação explícita do usuário** |
| Duplicidade em retry | ⚠️ | Idempotência por `client_uuid` (índice único) |

## 8. Critérios de aceitação

- [ ] Com rede: criar vistoria redireciona para o `show` (sem regressão para o web).
- [ ] Sem rede: enviar salva na fila, mostra aviso e incrementa o badge.
- [ ] Voltar a rede: vistoria sincroniza, recebe id real, fotos reconciliam e enviam, fila zera.
- [ ] App fechado e reaberto com rede: Background Sync/reabertura sincroniza as pendentes.
- [ ] Reenvio após resposta perdida não cria vistoria duplicada (idempotência por `client_uuid`).
- [ ] Nenhuma regressão no fluxo web atual de criação (usuários de navegador).
- [ ] `vendor/bin/pint` e `vendor/bin/phpstan` limpos; testes do fluxo de criação passando.
