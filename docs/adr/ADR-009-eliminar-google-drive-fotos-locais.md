# ADR-009: Eliminar Google Drive — fotos somente em Spatie MediaLibrary

**Data:** 2026-05-20
**Status:** Implementado (F0-F3 executadas; F2 consolidacao JS deixada como debito documentado)

## Contexto

O `foto-audit` de hoje revelou que **99.6% das fotos** (2634/2919) estão
com `cloud_status=pending` há ~65 dias. O pipeline de upload para o
Google Drive (`UploadMediaToDriveJob` + `GoogleDriveService`) está
operacionalmente quebrado mas o worker continua tentando.

Em paralelo, descobrimos ux-004: **duas implementações de IndexedDB**
no mesmo DB `poprua_fotos` v1 com schemas diferentes
(`offline-upload.js` e `vistoria-form.js`) — race condition latente.

A funcionalidade de fotos hoje tem 3 camadas:
1. **Spatie MediaLibrary** (disco local) — sempre escreve OK
2. **Google Drive sync** (assincrono via Job) — quebrado
3. **IndexedDB no cliente** (offline-first) — funciona mas duplicado

A complexidade da camada 2 não está pagando seu custo operacional.
Removê-la simplifica drasticamente o sistema sem perder funcionalidade
real (as fotos ja estao salvas no disco do servidor).

## Decisões

1. **Eliminar inteiramente a integração com Google Drive.**
   Remover: `GoogleDriveService`, `UploadMediaToDriveJob`, todos os
   `dispatch()` (5 sites), `google/apiclient` do composer, env vars
   `GOOGLE_DRIVE_*`, teste do Job, queue `media-conversions` (a
   conversão de thumbnail vira sincrona ou usa queue default).

2. **Dropar colunas `cloud_*` da tabela `media`.** Nova migration
   reversível (pra historico) que remove `cloud_disk`, `cloud_path`,
   `cloud_status`, `cloud_synced_at`. Os 2634 registros pending são
   simplesmente "fotos no disco local" daqui pra frente.

3. **Spatie MediaLibrary é a única fonte da verdade.** Configuração do
   disk em `config/media-library.php` permanece `public` (storage local
   bind-mounted). Backup das fotos passa a ser via `tar/rsync` do
   `storage/media-library/` (operacional, fora do scope deste ADR).

4. **UX "galeria do dispositivo até sincronizar" — manter e consolidar.**
   `offline-upload.js` já usa `URL.createObjectURL(blob)` para mostrar
   foto antes do upload concluir. Vamos:
   - Consolidar as 2 implementações de IndexedDB em uma só
     (`offline-upload.js` é a canônica; `vistoria-form.js` consome a
     API dela)
   - Garantir que o componente de visualização (`<img>`) usa o blob URL
     do IndexedDB enquanto a foto está pendente, e só faz swap para
     `getUrl('thumb')` do servidor depois que recebe `201` do POST
   - Registro de "fotos pendentes" via evento custom (já existe parte)

5. **Aplicar em ambos os sistemas (Geo e CRAS) na mesma janela.**
   Geo ainda em uso até cutover final; manter a mesma codebase entre
   os dois evita regressão de manutenção.

## Não inclui

- Substituir o Drive por outro storage remoto (R2, S3, etc) — deferred.
  Se um dia quiser cloud backup, criar ADR específico.
- Reescrever toda a UX de upload — só o pedaço que toca IndexedDB e
  display-pre-sync.
- Migrar fotos antigas (já no disco). Nada a migrar.

## Plano de execução

Fases independentemente reversíveis (cada uma um commit).

### Fase 1 — Backend cleanup (em ambos os sistemas)

| # | Ação | Impacto |
|---|------|---------|
| 1.1 | Remover `UploadMediaToDriveJob::dispatch(...)` dos 5 controllers | Zero — guarded por config |
| 1.2 | Deletar `app/Jobs/UploadMediaToDriveJob.php` | Zero |
| 1.3 | Deletar `app/Services/GoogleDriveService.php` | Zero |
| 1.4 | Deletar `tests/Feature/Jobs/UploadMediaToDriveJobTest.php` | -X testes |
| 1.5 | Remover `google/apiclient` do composer.json + `composer update` | Imagem menor |
| 1.6 | Remover env vars `GOOGLE_DRIVE_*` e `MEDIA_QUEUE_CONNECTION` do `.env.example` | Doc |
| 1.7 | Remover queue worker `media-conversions` do `docker-compose.yml` (ou re-configurar para `default`) | Worker simplificado |
| 1.8 | Migration: dropar colunas `cloud_*` da `media` | DDL reversível |

### Fase 2 — Frontend cleanup (em ambos os sistemas)

| # | Ação |
|---|------|
| 2.1 | Identificar a IndexedDB duplicada em `vistoria-form.js` |
| 2.2 | Consolidar: `vistoria-form.js` consome API de `offline-upload.js` (importar funções, não duplicar) |
| 2.3 | Garantir que `<img>` na grid usa blob URL enquanto pendente |
| 2.4 | Testar manualmente: subir foto offline → ver na galeria → reconectar → verificar swap pra server URL |
| 2.5 | Smoke test |

### Fase 3 — Validação

- Tests: 349/349 (-X tests do Drive)
- Smoke-test CRAS: 4/4 PASS
- foto-audit re-rodar: score sobe de 80 → ~94 (D2 deixa de penalizar Drive)
- Geo: aplicar mesma sequência + smoke 5/5

## Consequências

**Fica mais fácil:**
- Operação: queue só faz thumbnails (rapido); sem failed-jobs órfãos
- Manutenção: 3 arquivos a menos, 1 dep externa a menos
- Imagem Docker: menos ~20MB (google/apiclient)
- `foto-audit` D2: score sai de CRÍTICO para OK

**Fica mais difícil:**
- Backup de fotos passa a ser responsabilidade operacional explícita
  (`tar storage/media-library/`) — antes o Drive era backup implícito
  (que não funcionava)
- Se um dia o storage local pifar, sem Drive, perde tudo. Operacional
  precisa garantir backup periódico.

**O que muda:**
- API responses não trazem mais `cloud_status` ou `cloud_path` (nada
  consumia client-side mesmo)
- `composer.json` perde `google/apiclient`
- `.env.example` perde 4 variáveis
- 3 arquivos PHP deletados, 1 migration nova

## Sinais de sucesso

1. `git status` limpo após cada fase
2. `php artisan test` continua 349/349 (menos os testes do Drive removidos)
3. `docker/smoke-test.sh` continua 4/4 (CRAS) e 5/5 (Geo)
4. `foto-audit` mostra D2 ≥ 90 (sem deduções de cloud sync)
5. Após `docker compose down && up -d`: tudo verde sem intervenção
6. **UX validation:** subir foto em mobile com WiFi off, ver na galeria
   da app, reconectar, foto continua visível e swap pra server URL é
   transparente
