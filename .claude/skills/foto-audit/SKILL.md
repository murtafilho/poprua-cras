---
name: foto-audit
description: >
  Auditoria das funcionalidades de fotografia do POPRUA (vistorias e moradores)
  em 3 dimensoes: arquitetura, desempenho, usabilidade. Cada dimensao pontua
  0-100 com rubrica de deducoes explicita; produz score consolidado e matriz
  Impacto x Esforco com top quick-wins. Cobre Spatie MediaLibrary (collections
  e conversions), API endpoints, offline-upload (IndexedDB + Service Worker),
  Google Drive sync (UploadMediaToDriveJob), thumbnails, e UX no
  vistoria-form.js / morador-form.js / blade views.
  Use sempre que o usuario pedir para auditar fotos, avaliar fluxo de upload,
  analisar arquitetura ou usabilidade das fotografias, verificar saude do
  sync para Google Drive, ou pontuar como esta a funcionalidade de imagens.
  Variacoes: 'auditar fotos', 'analise de fotos', 'foto-audit', 'photo audit',
  'avaliar upload de imagens', 'usabilidade das fotos', 'avaliar mediaLibrary',
  'nota das fotos', 'avaliar fluxo de upload', 'saude do drive sync',
  'arquitetura de fotografias', 'desempenho fotos', 'fotos UX'.
user-invocable: true
allowed-tools: Read, Grep, Bash, Write, Edit, Agent
argument-hint: [arquitetura|desempenho|usabilidade|full]
version: 1.0.0
---

# Foto Audit — POPRUA

Auditoria estruturada em **3 dimensoes**, cada uma 0-100. Produz score
consolidado (media ponderada), KPIs mensuraveis, e matriz Impacto x Esforco
com top quick-wins. Reproduzivel em ambos os sistemas (Geo e CRAS).

**Escopo:** funcionalidade de fotografias — upload, armazenamento,
conversoes (thumbnails), sync para Google Drive, e UX no front-end.

**Dominios cobertos:**
- Vistoria (HasMedia, collection `fotos`)
- Morador (HasMedia, collection `fotos`)
- offline-upload (IndexedDB + Service Worker)
- Google Drive sync (cloud_status pipeline)

## Fluxo geral

```
0. Pre-flight (Spatie MediaLibrary instalado, models com HasMedia, queue rodando)
1. Coletar evidencias por dimensao em paralelo via agentes
2. Calcular score por dimensao (base 100 - deducoes capadas)
3. Agregar score geral (peso 35/35/30)
4. Matriz Impacto x Esforco
5. Relatorio markdown + JSON em .claude/audits/
```

## Pesos da dimensao

| Dimensao | Peso |
|---|---|
| Arquitetura | 35% |
| Desempenho | 35% |
| Usabilidade | 30% |

## Status (faixas)

- `OK` >= 80
- `WARN` 60-79
- `CRITICO` < 60

## Modos de invocacao

- `foto-audit` ou `foto-audit full` — auditoria completa (3 dimensoes)
- `foto-audit arquitetura` — so D1
- `foto-audit desempenho` — so D2
- `foto-audit usabilidade` — so D3

---

## D1 — Arquitetura (0-100, peso 35%)

**Foco:** como o codigo modela e gerencia fotos.

### Comandos de coleta

```bash
# Spatie MediaLibrary instalado e versao
$EXEC composer show spatie/laravel-medialibrary 2>&1 | grep -E "name|versions"

# Models com HasMedia
grep -rln "InteractsWithMedia\|implements HasMedia" app/Models/ 2>&1 | wc -l

# registerMediaCollections definidas
grep -rln "registerMediaCollections" app/Models/ 2>&1 | wc -l

# Conversoes (thumbnails) definidas
grep -rln "registerMediaConversions" app/Models/ 2>&1 | wc -l

# Collections com acceptsMimeTypes
grep -rn "acceptsMimeTypes" app/Models/ 2>&1 | wc -l

# Conversions com ->queued() (evita travar request)
grep -rn "addMediaConversion\|->queued()" app/Models/ 2>&1 | grep -c queued

# Migration cloud_sync presente?
ls database/migrations/ | grep -c "cloud_sync_to_media"

# API: singular vs plural (compat antiga ainda exposta?)
grep -E "/foto[s]?[/ ]" routes/api.php | wc -l

# Job de cloud sync existe?
ls app/Jobs/ | grep -c "UploadMedia\|MediaToDrive"

# Service de Drive existe?
ls app/Services/ | grep -c "Drive"

# tries/backoff configurados no Job?
grep -E "public int \\\$tries\|public array \\\$backoff" app/Jobs/UploadMediaToDriveJob.php | wc -l

# Cleanup de orfaos (CleanOrphanedMediaCommand)
ls app/Console/Commands/ | grep -c "Orphan"

# Soft delete em vistorias inclui purge media?
grep -A 3 "use SoftDeletes" app/Models/Vistoria.php | head -5

# Testes que cobrem o pipeline
find tests/ -name "*FotoControllerTest*" -o -name "*MediaTest*" -o -name "*UploadMedia*Test*" 2>&1 | wc -l
```

### Rubrica de pontuacao

| Item | Peso |
|---|---|
| Spatie MediaLibrary instalado e em versao suportada | -20 se ausente |
| Vistoria + Morador com `HasMedia` | -10 cada modelo faltando |
| Collection `fotos` com `acceptsMimeTypes` (whitelist explicita) | -5 por collection sem |
| `registerMediaConversions` para thumbnail com `->queued()` | -10 se thumbnails geradas sincronamente |
| API: rotas singular/plural duplicadas sem deprecacao explicita | -3 |
| `UploadMediaToDriveJob` com `tries` + `backoff` | -5 se sem retry |
| `CleanOrphanedMediaCommand` ou rotina equivalente | -8 se inexistente |
| Cloud sync columns na tabela `media` | -8 se inexistente |
| Testes de unidade para FotoControllers | -5 se zero, -2 se < 5 cobertos |

**Bonus:**
- +5 se uso de soft delete preserva media (nao apaga ao deletar vistoria)
- +5 se ha pacote de cleanup de orfaos rodando em cron

**Score final:** max(0, 100 + bonus - sum(deducoes))

### KPIs

- `medialibrary_version`
- `models_with_hasmedia` (esperado: 2 — Vistoria e Morador)
- `collections_with_mime_filter`
- `conversions_queued_count`
- `api_routes_duplicated`
- `job_has_retry`
- `orphan_cleanup_exists`
- `tests_cobrindo_fotos`

---

## D2 — Desempenho (0-100, peso 35%)

**Foco:** velocidade, escala, eficiencia operacional.

### Comandos de coleta

```bash
# Tamanho total e medio dos arquivos no DB
$DB_EXEC -d $DB -tAc "
  SELECT count(*) AS qtd, pg_size_pretty(sum(size)) AS total, pg_size_pretty(avg(size)::bigint) AS media
  FROM media
  WHERE collection_name = 'fotos';
"

# Distribuicao por tamanho — fotos muito grandes (> 5MB)
$DB_EXEC -d $DB -tAc "
  SELECT count(*) FROM media WHERE size > 5*1024*1024;
"

# Backlog de cloud sync
$DB_EXEC -d $DB -tAc "
  SELECT cloud_status, count(*)
  FROM media WHERE collection_name = 'fotos'
  GROUP BY cloud_status;
"

# Fotos sem thumbnail (generated_conversions vazio/null)
$DB_EXEC -d $DB -tAc "
  SELECT count(*) FROM media
  WHERE collection_name = 'fotos'
    AND (generated_conversions IS NULL OR generated_conversions::text = '[]' OR generated_conversions::text = '{}');
"

# Endpoint /api/vistorias/{id}/fotos faz N+1? grep query patterns
grep -B 1 -A 10 "getMedia\b" app/Http/Controllers/Api/VistoriaFotoController.php app/Http/Controllers/Api/MoradorFotoController.php 2>&1 | grep -c "with(\|eager\|->load("

# Frontend usa thumbnails ou full size na listagem?
grep -rn "getUrl('thumb')\|getUrl(.thumb.)" resources/views/ 2>&1 | wc -l
grep -rn "getUrl()" resources/views/ 2>&1 | grep -v "thumb" | wc -l

# Lazy loading nas imagens?
grep -rn "loading=.lazy.\|loading=\"lazy\"" resources/views/ 2>&1 | wc -l

# Cache de listagens de fotos
grep -rn "Cache::remember\|Cache::put" app/Http/Controllers/Api/VistoriaFotoController.php app/Http/Controllers/Api/MoradorFotoController.php 2>&1 | wc -l

# Queue worker rodando (media-conversions)
$EXEC ps aux | grep -c "queue:work.*media-conversions"

# Idade da media mais antiga PENDING (queue parada?)
$DB_EXEC -d $DB -tAc "
  SELECT max(EXTRACT(epoch FROM (now() - created_at))::int) AS idade_max_pending_seg
  FROM media WHERE cloud_status = 'pending';
"
```

### Rubrica de pontuacao

| Item | Peso |
|---|---|
| Tamanho medio das fotos | -10 se media > 3MB (cliente nao otimiza) |
| Fotos > 5MB | -1 cada (cap -15) |
| % thumbnails geradas | -20 se < 50%, -10 se 50-90% |
| Backlog cloud sync (% pending) | -20 se > 80%, -10 se 30-80%, -3 se 5-30% |
| Idade max pending | -10 se > 24h (job estagnado) |
| N+1 na listagem | -10 se eager loading ausente |
| Thumbs usados em listagens | -5 se < 70% das views usam thumb |
| `loading="lazy"` | -5 se zero |
| Cache em listagens | -3 se sem cache |
| Queue worker rodando | -15 se nao |

**Bonus:**
- +5 se imagens convertidas para WebP
- +5 se tem CDN/proxy de imagens

### KPIs

- `total_fotos`
- `total_size_mb`
- `avg_size_kb`
- `fotos_acima_5mb`
- `pct_thumbnails_geradas`
- `cloud_sync_backlog_pct`
- `idade_max_pending_horas`
- `n_plus_1_risk`
- `lazy_loading_count`

---

## D3 — Usabilidade (0-100, peso 30%)

**Foco:** experiencia do usuario final no upload e visualizacao.

### Comandos de coleta

```bash
# Drag-and-drop nos formularios
grep -rn "dragover\|dragleave\|drop\b" resources/js/ 2>&1 | wc -l

# Preview antes do upload
grep -rn "URL.createObjectURL\|FileReader\|previewImage\|preview-foto" resources/js/ 2>&1 | wc -l

# Indicacao de progresso (XHR ou Fetch + progress)
grep -rn "progress\|onprogress\|upload-progress" resources/js/ 2>&1 | wc -l

# Offline support (IndexedDB)
grep -rn "indexedDB\|openDB\b\|caches.open" resources/js/ 2>&1 | wc -l

# Service Worker
test -f public/sw.js && echo OK || echo MISSING

# Mensagens de erro/feedback claras
grep -rn "showError\|toast\|alert\|notification" resources/js/ 2>&1 | wc -l

# Limite de tamanho client-side
grep -rn "MAX_FILE_SIZE\|maxSize\|max_file_size" resources/js/ 2>&1 | wc -l

# Compressao client-side antes do upload
grep -rn "compress\|imageCompress\|browser-image-compression\|canvas.toBlob" resources/js/ 2>&1 | wc -l

# Camera capture (mobile)
grep -rn "capture=.camera\|capture=\"camera\"\|capture=\"environment\"" resources/views/ 2>&1 | wc -l

# Multi-upload (multiple)
grep -rn "multiple\b" resources/views/ 2>&1 | grep -c "input.*file\|file.*input"

# Alt em <img> com Blade
grep -rn "<img" resources/views/ --include="*.blade.php" 2>&1 | grep -c "alt="

# Acessibilidade keyboard nav (focusable)
grep -rn "tabindex\|aria-label" resources/views/moradores resources/views/vistorias 2>&1 | wc -l

# Confirmacao antes de deletar
grep -rn "confirm.*foto\|confirm-delete-foto" resources/js/ resources/views/ 2>&1 | wc -l

# Manifest PWA (suporte instalacao)
test -f public/manifest.json && echo OK || echo MISSING
```

### Rubrica de pontuacao

| Item | Peso |
|---|---|
| Drag-and-drop em pelo menos 1 formulario | -10 se zero |
| Preview antes do upload | -10 se zero |
| Indicacao de progresso visual | -8 se zero |
| Offline support (IndexedDB + SW) | -15 se zero (POPRUA precisa de campo) |
| Service Worker funcional (sw.js) | -10 se MISSING |
| Compressao client-side antes de upload | -8 se ausente |
| Camera capture em mobile (`capture="environment"`) | -10 se zero |
| Multi-upload (`<input multiple>`) | -5 se zero |
| Limite client-side com feedback | -5 se ausente |
| Confirmacao antes de deletar foto | -3 se ausente |
| `<img alt=...>` em todas as fotos | -1 por instancia ausente (cap -10) |
| Mensagens de erro/sucesso claras | -5 se < 3 ocorrencias |
| PWA manifest | -3 se MISSING |

**Bonus:**
- +5 se ha rotacao/edicao basica antes de salvar
- +5 se EXIF de coordenadas e usado para georreferenciar

### KPIs

- `drag_drop_count`
- `preview_count`
- `progress_indicator`
- `offline_indexeddb`
- `service_worker`
- `compress_client_side`
- `camera_capture_mobile`
- `multi_upload`
- `imgs_sem_alt`
- `pwa_manifest`

---

## PASSO 0 — Pre-flight

```bash
# Detector hibrido (espelha quality-audit)
RUNTIME="host"
if [ -f /.dockerenv ]; then RUNTIME="container"; fi

PROJECT_ROOT="/var/www/html/joomla_sufis/ginfi/poprua-cras"
if [ "$RUNTIME" = "container" ]; then
  EXEC=""
  DB_EXEC="psql -h db -U poprua_cras"
else
  EXEC="sudo docker exec php84-poprua-cras"
  DB_EXEC="sudo docker exec -u postgres pg17-poprua-cras psql -U poprua_cras"
fi
DB="poprua_cras"  # ou poprua_geo se rodando contra geo
AUDITS_DIR="$PROJECT_ROOT/.claude/audits"
```

### Checks criticos

- spatie/laravel-medialibrary instalado (composer)
- queue Redis ok
- Pelo menos 1 foto no DB para calcular KPIs

Se algum check falhar → marca dimensao como `degraded:true`.

---

## PASSO 2 — Agentes paralelos

Disparar 3 agentes (1 por dimensao) em paralelo. Prompt template:

> Voce e o agente da dimensao **<D1|D2|D3>** do foto-audit do POPRUA.
> Vars exportadas: RUNTIME, EXEC, DB_EXEC, DB, PROJECT_ROOT, AUDITS_DIR.
>
> 1. Rode os comandos de coleta da sua dimensao (copiados desta SKILL.md).
> 2. Aplique a rubrica e calcule o score (base 100 - deducoes capadas).
> 3. Liste top 5 findings priorizados por impacto.
> 4. Salve `$AUDITS_DIR/foto-<dimension>.json`.
> 5. Devolva ao orquestrador: score, KPIs, top findings.
>
> Contrato JSON:
> ```json
> {
>   "skill": "foto-audit",
>   "dimension": "arquitetura|desempenho|usabilidade",
>   "score": 0-100,
>   "max_score": 100,
>   "timestamp": "...",
>   "degraded": false,
>   "kpis": { },
>   "findings": [ {"id":"...","severity":"...","title":"...","effort_hours":N,"impact":"HIGH|MED|LOW","auto_fixable":bool} ]
> }
> ```
>
> Timeout: 90s.

## PASSO 3 — Agregar (bash arithmetic)

```bash
# Pesos: ARQ=35, DES=35, UX=30 (soma 100)
SC_ARQ=82; SC_DES=64; SC_UX=71
OVERALL=$(( (SC_ARQ*35 + SC_DES*35 + SC_UX*30) / 100 ))
echo "Overall: $OVERALL"
```

Grava `$AUDITS_DIR/foto-summary.json`:

```json
{
  "skill": "foto-audit",
  "iteration": N,
  "timestamp": "...",
  "overall_score": N,
  "dimensions": {
    "arquitetura": { "score": N, "weight_pct": 35, "status": "OK|WARN|CRITICO" },
    "desempenho":  { "score": N, "weight_pct": 35, "status": "..." },
    "usabilidade": { "score": N, "weight_pct": 30, "status": "..." }
  },
  "top_quick_wins": [ ... ],
  "critical_findings": [ ... ]
}
```

## PASSO 4 — Matriz Impacto x Esforco

```
              ALTO IMPACTO
                   |
   Q2 Projetos     Q1 Quick Wins
   (alto esforco)  (baixo esforco)
                   |
  -----------------+-----------------
                   |
   Q4 Ignorar      Q3 Fill-ins
                   |
              BAIXO IMPACTO
```

**Q1** = top 5 findings com `impact >= MED && effort_hours <= 2`

## PASSO 5 — Relatorio

```markdown
## Foto Audit — POPRUA (sistema=<cras|geo>)
**Data:** YYYY-MM-DD | **Score:** XX/100 | **Status:** OK/WARN/CRITICO

### Dimensoes

| # | Dimensao | Score | Peso | Status |
|---|----------|-------|------|--------|
| 1 | Arquitetura | XX | 35% | ... |
| 2 | Desempenho | XX | 35% | ... |
| 3 | Usabilidade | XX | 30% | ... |

### Quick Wins (Q1)

| # | Finding | Dim | Severidade | Esforco | Como aplicar |
|---|---------|-----|------------|---------|--------------|

### KPIs principais
- Total fotos / Tamanho / % thumb / Backlog Drive
- Drag-drop / Preview / Compress / Camera

### Findings criticos (severity HIGH)

### Recomendacoes
```

---

## Convencoes do POPRUA aplicadas

1. **Os dois sistemas tem a MESMA codebase de fotos** (Geo e CRAS — ETL copiou). Auditoria nos dois deve dar score IDENTICO em arquitetura. Diferencas serao em desempenho (volume real diferente) e UX (so se um sistema receber mudanca exclusiva).
2. **Spatie MediaLibrary** com queue `media-conversions` (definida no docker-compose). Backlog grande aqui = queue worker parado.
3. **Cloud sync para Google Drive** via `UploadMediaToDriveJob` — tres tentativas, backoff 30/60/120s. Se `cloud_status` ficar `pending` permanente, indica problema no GoogleDriveService.
4. **Offline-first** em mobile — IndexedDB + Service Worker para enfileirar uploads quando perde conexao no campo.

## Quando NAO usar

- Auditoria geral de codigo → use `quality-audit`
- Apenas testes → `php artisan test --filter=Foto`
- Debug pontual de upload — abrir devtools, nao precisa de skill

## Saidas geradas

- `$AUDITS_DIR/foto-arquitetura.json`
- `$AUDITS_DIR/foto-desempenho.json`
- `$AUDITS_DIR/foto-usabilidade.json`
- `$AUDITS_DIR/foto-summary.json`
- Relatorio markdown impresso no chat

## Versionamento

Versao 1.0.0 — 2026-05-20. Mudancas significativas na rubrica = bump
de versao para que comparacoes historicas de score sejam justas.
