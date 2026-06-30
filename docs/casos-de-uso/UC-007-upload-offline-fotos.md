# UC-007 — Upload Offline de Fotos (Zeladoria)

**Versão:** 1.0  
**Data:** 2026-06-24  
**Status:** Implementado

---

## Objetivo

Documentar o fluxo **offline-first** de fotografias em zeladorias: captura em campo sem rede, persistência local no dispositivo, envio automático ou manual quando a conexão retorna, e integração com Spatie MediaLibrary no servidor. Atende ao requisito **1.4** do levantamento GFAES/PBH (galeria/câmera em mobile) e complementa UC-006 (rascunho do formulário não inclui fotos no payload server-side).

**Referências:** `docs/AUDITORIA_Zeladoria.md` §1.4 · ADR-009 (dívida técnica IndexedDB) · `docs/API.md` (endpoints de fotos).

---

## Atores

| Ator | Descrição |
|------|-----------|
| **Profissional de campo** | Tira fotos durante criação/edição da zeladoria; pode fechar o app antes do upload. |
| **Service Worker** | Processa fila em background (Chromium) quando há Background Sync. |
| **Sistema (API)** | Recebe `POST /api/vistorias/fotos`, persiste via `FotoService` + fila `media-conversions`. |

---

## Arquitetura Resumida

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────────┐
│ vistoria-form   │────▶│ IndexedDB        │────▶│ POST /api/vistorias │
│ (create/edit)   │     │ poprua_fotos     │     │ /fotos              │
└─────────────────┘     │ store: pendentes │     └──────────┬──────────┘
                        └────────▲─────────┘                │
┌─────────────────┐              │                           ▼
│ offline-upload  │──────────────┘                 Spatie MediaLibrary
│ .js (singleton) │     (shape alternativo)         collection `fotos`
└─────────────────┘
         │
         ▼
┌─────────────────┐
│ public/sw.js    │  Background Sync tag: upload-fotos
└─────────────────┘
```

---

## Pré-condições

1. Usuário autenticado (cookie de sessão + CSRF).
2. PWA: Service Worker registrado em `layouts/app.blade.php` (`sw.js`).
3. Para upload imediato pós-criação: zeladoria já persistida (`vistoria_id` numérico) **ou** reconciliação de `temp_*` → ID real na tela show.

---

## Fluxo A — Fotos durante criação (wizard)

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Profissional | Na aba Fotos, usa **Tirar Foto** (`capture="environment"`) ou **Anexar Arquivo**. | Arquivo entra em `fotosSelecionadas[]` (preview em memória). |
| 2 | — | `salvarFotoLocal()` grava ArrayBuffer no IndexedDB com `vistoria_id = temp_{timestamp}` (sessionStorage `poprua_fotos_temp_id`). | Foto sobrevive fechamento do navegador antes do submit. |
| 3 | Profissional | Conclui wizard e submete `POST /vistorias`. | Form **não** envia blobs no multipart; apenas dados textuais. |
| 4 | — | Redirect para `vistorias/show`. | `vistoria-show.js` chama `vincularTempId()`: reescreve `temp_*` → ID real da vistoria. |
| 5 | Profissional | Clica **Sincronizar Fotos** (se rede disponível). | Cada registro local é enviado via API; sucesso remove entrada do IndexedDB e atualiza grid. |

---

## Fluxo B — Upload direto (vistoria já existente)

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Profissional | Em `vistorias/edit` ou via `OfflineUpload.addFoto(vistoriaId, file)`. | Se online + WiFi: tentativa de upload direto; falha enfileira. |
| 2 | `OfflineUpload` | Comprime imagem (max 1920px, quality 0.7) antes de armazenar/enviar. | Reduz ~5 MB → ~200–400 KB típico. |
| 3 | — | Registro na fila com `vistoriaId`, `blob`, `uploadUrl`, `csrfToken`, `status: pending`. | Background Sync ou polling 30 s tenta envio. |

---

## Fluxo C — Sincronização em background

| Cenário | Mecanismo | Observação |
|---------|-----------|------------|
| Chromium + SW ativo | `sync` event `upload-fotos` em `sw.js` | Ignora registros `temp_*` (ainda sem vistoria). |
| Safari / Firefox | Polling em `offline-upload.js` + `app.js` `syncAllPendingPhotos()` | Sem Background Sync nativo. |
| Volta ao app | `visibilitychange` → `_trySync()` | Dispara SW via `postMessage TRIGGER_UPLOAD`. |
| Badge global | `#sync-badge` em `app.js` | Conta fotos não-temp pendentes. |

Autenticação do SW: cookie `XSRF-TOKEN` → header `X-XSRF-TOKEN` (Laravel Sanctum/session).

---

## API (backend)

| Método | Rota | Descrição |
|--------|------|-----------|
| `POST` | `/api/vistorias/fotos` | Upload (`vistoria_id`, `foto`, `legenda` opcional). Policy: `update` na vistoria. |
| `GET` | `/api/vistorias/{id}/fotos/status` | Lista fotos serializadas (URL, thumb, pública, legenda). |
| `POST` | `/api/vistorias/{id}/fotos/{mediaId}/toggle-publica` | Alterna inclusão no relatório impresso. |
| `PATCH` | `/api/vistorias/{id}/fotos/{mediaId}/legenda` | Define legenda da foto. |

Validação (`StoreVistoriaFotoRequest`): imagem até 10 MB pós-envio; tipos `jpeg,png,webp`.

Persistência: `FotoService::adicionarFoto()` → Spatie collection `fotos`; conversões na queue `media-conversions`.

---

## IndexedDB — Schema

| Campo | Descrição |
|-------|-----------|
| DB | `poprua_fotos` v1 |
| Store | `pendentes` (keyPath `id`, autoIncrement) |
| Índices | `status`, `vistoriaId` (em `offline-upload.js`; parcial em outros módulos) |

### Dois formatos de registro (dívida ADR-009)

| Origem | Chave vistoria | Payload |
|--------|----------------|---------|
| `vistoria-form.js` / `vistoria-show.js` | `vistoria_id` (string: `temp_*` ou int) | `{ data: ArrayBuffer, type, name, created_at }` |
| `offline-upload.js` | `vistoriaId` (number) | `{ blob, filename, uploadUrl, csrfToken, status, ... }` |

O Service Worker e `app.js` leem o formato `{ data, type, name }`. `offline-upload.js` usa `blob` — convergência pendente (ADR-009 fase 2).

---

## Limites e validações

| Camada | Limite |
|--------|--------|
| Cliente (pré-compressão) | 30 MB (`MAX_FILE_SIZE_BYTES` em `offline-upload.js`) |
| Servidor | 10 MB (`max:10240` no Form Request) |
| WiFi preferencial | Upload direto só se `navigator.connection.type` ∈ `{wifi, ethernet}` ou fallback `onLine` |

Registros `temp_*` órfãos (> 1 h): removidos por `cleanupOrphanedRecords()` em `app.js`.

---

## UI

| Superfície | Elementos |
|------------|-----------|
| `vistorias/create`, `edit` | Inputs câmera/galeria, preview com legendas, contador |
| `vistorias/show` | Grid de fotos enviadas + painel **fotos pendentes** + botão **Sincronizar Fotos** |
| Layout global | Badge de sync pendente (`sync-badge`) |

Zeladoria **finalizada**: policy bloqueia novos uploads (`VistoriaPolicy::update`).

---

## Regras de Negócio

| ID | Regra |
|----|-------|
| RN1 | Fotos offline exigem vistoria persistida para sync definitivo; `temp_*` é placeholder até o `store`. |
| RN2 | Submit do formulário de criação **não** transporta blobs — desacopla wizard lento de upload pesado. |
| RN3 | Autorização de upload segue `VistoriaPolicy::update` (owner em aberta; bloqueado se finalizada/cancelada). |
| RN4 | Foto **pública** entra no relatório A4; **privada** só na aplicação (toggle pós-upload). |
| RN5 | Compressão client-side é best-effort; falha de compressão não impede enfileiramento (via form path). |
| RN6 | SW ignora `temp_*` no Background Sync — evita 422 por `vistoria_id` inválido. |
| RN7 | Moradores têm fluxo paralelo (`MoradorFotoController`) sem fila offline dedicada no CRAS v1. |

---

## Dívida técnica conhecida (v2)

1. ~~**Consolidar IndexedDB**~~ — entregue 2026-06-24 (ADR-009 F2).
2. ~~**Legendas offline**~~ — entregue 2026-06-24: `updatePendingPhotoLegenda` + sync envia `legenda` ao API.
3. **Rascunho UC-006** — fotos ficam fora do payload JSON; dependem sempre da fila local.

---

## Critérios de aceite (verificados)

- [x] Tirar foto no create sem rede → foto permanece no IndexedDB após reload.
- [x] Após `store`, show reconcilia `temp_*` e permite sync manual.
- [x] `POST /api/vistorias/fotos` retorna 201 com URL/thumb (`VistoriaJourneyTest::test_aba_fotos_upload_anexa_imagem_via_api`).
- [x] SW registrado; Background Sync enfileira quando suportado.
- [x] Badge global reflete fotos pendentes não-temp.

---

## Glossário

| Termo | Significado |
|-------|-------------|
| **tempId** | Identificador provisório `temp_{ms}` até existir `vistorias.id`. |
| **Background Sync** | API do Service Worker para retentar uploads com app fechado (Chromium). |
| **Reconciliação** | Substituir `temp_*` pelo ID real na fila IndexedDB após criação da zeladoria. |
