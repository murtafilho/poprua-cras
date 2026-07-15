# Criar Vistoria Offline (Outbox) — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Permitir criar uma vistoria sem rede (perder sinal durante o preenchimento): ao enviar, tenta o POST; se falhar, enfileira numa outbox IndexedDB e sincroniza quando a conexão volta, sem duplicar.

**Architecture:** Espelha a fila de fotos já existente. Backend ganha um endpoint JSON `POST /api/vistorias` (reusa `VistoriaService`) com idempotência por `client_uuid`. O front intercepta o submit do formulário: online → POST + redirect (como hoje); offline → grava na outbox (`poprua_vistorias`) e agenda Background Sync. A reconciliação `temp → id real` das fotos reusa `reconcileTempId`. Muda o web (vale para PWA e app Capacitor).

**Tech Stack:** Laravel 12 · PostgreSQL/PostGIS · PHPUnit 11 · Vite · Service Worker + IndexedDB · Background Sync.

## Global Constraints

- Endpoint novo: `POST /api/vistorias` (JSON), grupo `['web','auth']` (sessão + CSRF via `X-XSRF-TOKEN`/`X-CSRF-TOKEN`), retorna `{ "id": <int>, "redirect_url": "<url>", "client_uuid": "<uuid>" }`.
- Idempotência: coluna `client_uuid` em `vistorias` (**uuid, nullable, índice único**); reenvio com o mesmo `client_uuid` retorna a vistoria existente (não recria).
- Outbox de vistorias: IndexedDB **`poprua_vistorias`**, store `pendentes` — **não** alterar a versão do banco de fotos `poprua_fotos`.
- Novo módulo JS: `resources/js/offline-vistoria.js` (reusa helpers de `offline-upload.js`); **não** modificar o fluxo de fotos além de chamar `reconcileTempId` no sync da vistoria.
- Background Sync tag: `sync-vistorias`. **Incrementar `CACHE_VERSION`** em `public/sw.js`.
- Escopo: **só criação nova**. Fora: editar/finalizar offline, abrir form offline, listar pendente em "minhas-vistorias".
- Sem regressão no fluxo web atual de criação (usuário de navegador continua sendo redirecionado ao `show`).
- Banco de teste: `poprua_cras_test` em `127.0.0.1:5433` (já em `phpunit.xml`). Rodar `php artisan test`.
- `vendor/bin/pint --dirty` e `vendor/bin/phpstan analyse` limpos antes de finalizar cada tarefa de backend.
- Branch `feat/vistoria-offline-outbox`; **não** dar push (push dispara deploy de produção). Todo commit termina com o trailer `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`.
- Tudo em pt-BR com acentuação.

---

## Estrutura de arquivos

| Caminho | Ação | Responsabilidade |
|---|---|---|
| `database/migrations/XXXX_add_client_uuid_to_vistorias.php` | criar | Coluna `client_uuid` (uuid, nullable, unique) |
| `app/Models/Vistoria.php` | editar | `client_uuid` em `$fillable` |
| `app/Services/VistoriaService.php` | editar | Injetar `client_uuid` no create quando presente |
| `app/Http/Requests/Api/StoreVistoriaApiRequest.php` | criar | Regras (estende `StoreVistoriaRequest`) + `client_uuid` obrigatório |
| `app/Http/Controllers/Api/VistoriaController.php` | criar | `store()` JSON + idempotência |
| `routes/api.php` | editar | `POST /vistorias` no grupo `['web','auth']` |
| `resources/js/offline-vistoria.js` | criar | Outbox IndexedDB `poprua_vistorias` + sync no cliente |
| `resources/js/vistoria-form.js` | editar | Interceptar submit (fetch + fallback outbox + reconcile + navegação) |
| `public/sw.js` | editar | Background Sync `sync-vistorias` + bump `CACHE_VERSION` |
| `resources/js/app.js` | editar | Badge/menu incluindo vistorias pendentes |
| `tests/Feature/Api/VistoriaStoreApiTest.php` | criar | Testes do endpoint (criação, idempotência, validação) |

---

## Task 1: Migração `client_uuid` + model

**Files:**
- Create: `database/migrations/2026_07_10_120000_add_client_uuid_to_vistorias.php`
- Modify: `app/Models/Vistoria.php` (`$fillable`)
- Test: `tests/Feature/Api/VistoriaClientUuidTest.php`

**Interfaces:**
- Produces: coluna `vistorias.client_uuid` (nullable, unique) e `Vistoria` aceitando `client_uuid` em mass-assignment. Consumido pelas Tasks 2.

- [x] **Step 1: Escrever o teste (falha)**

Create `tests/Feature/Api/VistoriaClientUuidTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Vistoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VistoriaClientUuidTest extends TestCase
{
    use RefreshDatabase;

    public function test_vistoria_persiste_client_uuid(): void
    {
        $uuid = '11111111-1111-4111-8111-111111111111';
        $vistoria = Vistoria::factory()->create(['client_uuid' => $uuid]);

        $this->assertDatabaseHas('vistorias', [
            'id' => $vistoria->id,
            'client_uuid' => $uuid,
        ]);
    }

    public function test_client_uuid_tem_indice_unico(): void
    {
        $uuid = '22222222-2222-4222-8222-222222222222';
        Vistoria::factory()->create(['client_uuid' => $uuid]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Vistoria::factory()->create(['client_uuid' => $uuid]);
    }
}
```

- [x] **Step 2: Rodar e ver falhar**

Run: `php artisan test --filter=VistoriaClientUuidTest`
Expected: FAIL (coluna `client_uuid` não existe).

- [x] **Step 3: Criar a migração**

Create `database/migrations/2026_07_10_120000_add_client_uuid_to_vistorias.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vistorias', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('vistorias', function (Blueprint $table) {
            $table->dropUnique(['client_uuid']);
            $table->dropColumn('client_uuid');
        });
    }
};
```

- [x] **Step 4: Adicionar ao `$fillable`**

Modify `app/Models/Vistoria.php`: no início do array `$fillable` (após `protected $fillable = [`, linha 38), inserir a linha:

```php
        'client_uuid',
```

- [x] **Step 5: Migrar e rodar o teste (passa)**

Run: `php artisan migrate && php artisan test --filter=VistoriaClientUuidTest`
Expected: `2 passed`.

- [x] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty
git add database/migrations app/Models/Vistoria.php tests/Feature/Api/VistoriaClientUuidTest.php
git commit -m "feat(vistoria-offline): coluna client_uuid em vistorias (idempotencia)"
```

---

## Task 2: Endpoint JSON `POST /api/vistorias` + idempotência

**Files:**
- Create: `app/Http/Requests/Api/StoreVistoriaApiRequest.php`
- Create: `app/Http/Controllers/Api/VistoriaController.php`
- Modify: `app/Services/VistoriaService.php` (injetar `client_uuid`)
- Modify: `routes/api.php` (rota)
- Test: `tests/Feature/Api/VistoriaStoreApiTest.php`

**Interfaces:**
- Consumes: `VistoriaService::criarComRelacionamentos`, `StoreVistoriaRequest` (regras), coluna `client_uuid` (Task 1).
- Produces: `POST /api/vistorias` retornando `{ id, redirect_url, client_uuid }`. Consumido pelas Tasks 3-4.

- [x] **Step 1: Escrever os testes (falham)**

Create `tests/Feature/Api/VistoriaStoreApiTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\ResultadoAcao;
use App\Models\TipoAbordagem;
use App\Models\User;
use App\Models\Vistoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VistoriaStoreApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payloadValido(string $uuid): array
    {
        return [
            'client_uuid' => $uuid,
            'lat' => -19.9227,
            'lng' => -43.9451,
            'data_abordagem' => now()->format('Y-m-d\TH:i'),
            'tipo_abordagem_id' => TipoAbordagem::query()->value('id') ?? TipoAbordagem::factory()->create()->id,
            'resultado_acao_id' => ResultadoAcao::query()->value('id') ?? ResultadoAcao::factory()->create()->id,
        ];
    }

    public function test_cria_vistoria_via_json_e_retorna_id_e_redirect(): void
    {
        $user = User::factory()->create();
        $uuid = '33333333-3333-4333-8333-333333333333';

        $resp = $this->actingAs($user)
            ->postJson('/api/vistorias', $this->payloadValido($uuid));

        $resp->assertOk()
            ->assertJsonStructure(['id', 'redirect_url', 'client_uuid']);
        $this->assertDatabaseHas('vistorias', [
            'id' => $resp->json('id'),
            'client_uuid' => $uuid,
            'user_id' => $user->id,
        ]);
    }

    public function test_reenvio_com_mesmo_client_uuid_nao_duplica(): void
    {
        $user = User::factory()->create();
        $uuid = '44444444-4444-4444-8444-444444444444';
        $payload = $this->payloadValido($uuid);

        $r1 = $this->actingAs($user)->postJson('/api/vistorias', $payload);
        $r2 = $this->actingAs($user)->postJson('/api/vistorias', $payload);

        $r1->assertOk();
        $r2->assertOk();
        $this->assertSame($r1->json('id'), $r2->json('id'));
        $this->assertSame(1, Vistoria::where('client_uuid', $uuid)->count());
    }

    public function test_valida_campos_obrigatorios(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/vistorias', ['client_uuid' => '55555555-5555-4555-8555-555555555555'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lat', 'lng', 'data_abordagem', 'tipo_abordagem_id', 'resultado_acao_id']);
    }

    public function test_exige_autenticacao(): void
    {
        $this->postJson('/api/vistorias', [])->assertStatus(401);
    }
}
```

- [x] **Step 2: Rodar e ver falhar**

Run: `php artisan test --filter=VistoriaStoreApiTest`
Expected: FAIL (rota `/api/vistorias` não existe → 404).

- [x] **Step 3: Form Request da API**

Create `app/Http/Requests/Api/StoreVistoriaApiRequest.php`:

```php
<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\StoreVistoriaRequest;

/**
 * Criação de vistoria via JSON (fila offline). Reaproveita as regras e as
 * validações compostas de StoreVistoriaRequest e exige o client_uuid usado
 * para idempotência da sincronização.
 */
class StoreVistoriaApiRequest extends StoreVistoriaRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'client_uuid' => 'required|uuid',
        ]);
    }
}
```

- [x] **Step 4: Injetar `client_uuid` no create (VistoriaService)**

Modify `app/Services/VistoriaService.php`: dentro de `criarComRelacionamentos`, logo após a linha `$fields['user_id'] = $request->user()->id;` (linha 57), inserir:

```php
            if (! empty($validated['client_uuid'])) {
                $fields['client_uuid'] = $validated['client_uuid'];
            }
```

- [x] **Step 5: Controller da API**

Create `app/Http/Controllers/Api/VistoriaController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreVistoriaApiRequest;
use App\Models\Vistoria;
use App\Services\VistoriaService;
use Illuminate\Http\JsonResponse;

class VistoriaController extends Controller
{
    public function __construct(private VistoriaService $vistoriaService) {}

    /**
     * Criação de vistoria via JSON (usada pela fila offline).
     * Idempotente por client_uuid: reenvio do mesmo uuid retorna a existente.
     */
    public function store(StoreVistoriaApiRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $userId = $request->user()->id;

        $existente = Vistoria::query()
            ->where('user_id', $userId)
            ->where('client_uuid', $validated['client_uuid'])
            ->first();

        if ($existente) {
            return $this->respostaVistoria($existente->id, $validated['client_uuid']);
        }

        $result = $this->vistoriaService->criarComRelacionamentos($request, $validated);
        $this->vistoriaService->invalidarCacheListagem();

        return $this->respostaVistoria($result['vistoria']->id, $validated['client_uuid']);
    }

    private function respostaVistoria(int $id, string $clientUuid): JsonResponse
    {
        return response()->json([
            'id' => $id,
            'redirect_url' => route('vistorias.show', $id),
            'client_uuid' => $clientUuid,
        ]);
    }
}
```

- [x] **Step 6: Registrar a rota**

Modify `routes/api.php`: dentro do grupo de vistorias autenticado (junto de `Route::post('/vistorias/fotos', ...)`, próximo à linha 50), adicionar:

```php
    Route::post('/vistorias', [\App\Http\Controllers\Api\VistoriaController::class, 'store']);
```

- [x] **Step 7: Rodar os testes (passam)**

Run: `php artisan test --filter=VistoriaStoreApiTest`
Expected: `4 passed`. Se algum factory (`TipoAbordagem`/`ResultadoAcao`) não existir, criar via seeder mínimo no teste ou usar os IDs já semeados — ajustar o helper `payloadValido` para os dados disponíveis no banco de teste.

- [x] **Step 8: Pint + PHPStan + commit**

```bash
vendor/bin/pint --dirty && vendor/bin/phpstan analyse
git add app/Http/Requests/Api/StoreVistoriaApiRequest.php app/Http/Controllers/Api/VistoriaController.php app/Services/VistoriaService.php routes/api.php tests/Feature/Api/VistoriaStoreApiTest.php
git commit -m "feat(vistoria-offline): endpoint JSON POST /api/vistorias com idempotencia"
```

---

## Task 3: Módulo outbox `offline-vistoria.js`

**Files:**
- Create: `resources/js/offline-vistoria.js`

**Interfaces:**
- Consumes: `reconcileTempId` de `./offline-upload`; endpoint `POST /api/vistorias` (Task 2).
- Produces: `enqueueVistoria(payload)`, `getPendingVistorias()`, `countPendingVistorias()`, `syncPendingVistorias()`, `removePendingVistoria(id)`. Consumido pelas Tasks 4 e 6.

- [x] **Step 1: Escrever o módulo**

Create `resources/js/offline-vistoria.js`:

```js
import { reconcileTempId, getTempPhotoId } from './offline-upload';

/**
 * SIZEM — Outbox de criação de vistoria offline (IndexedDB separado).
 * Espelha o padrão da fila de fotos. Banco próprio para não colidir com
 * a versão de `poprua_fotos`.
 */

const DB_NAME = 'poprua_vistorias';
const DB_VERSION = 1;
const STORE = 'pendentes';

let dbPromise = null;

function openDB() {
    if (dbPromise) return dbPromise;
    dbPromise = new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION);
        req.onupgradeneeded = (e) => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains(STORE)) {
                db.createObjectStore(STORE, { keyPath: 'id', autoIncrement: true });
            }
        };
        req.onsuccess = (e) => resolve(e.target.result);
        req.onerror = (e) => { dbPromise = null; reject(e.target.error); };
    });
    return dbPromise;
}

/** Grava um payload de criação de vistoria na outbox. */
export async function enqueueVistoria(payload) {
    const db = await openDB();
    const record = {
        client_uuid: payload.client_uuid,
        temp_photo_id: getTempPhotoId(),
        payload,
        created_at: new Date().toISOString(),
        status: 'pending',
    };
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readwrite');
        const req = tx.objectStore(STORE).add(record);
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

export async function getPendingVistorias() {
    const db = await openDB();
    return new Promise((resolve) => {
        const tx = db.transaction(STORE, 'readonly');
        const req = tx.objectStore(STORE).getAll();
        req.onsuccess = () => resolve(req.result || []);
        req.onerror = () => resolve([]);
    });
}

export async function countPendingVistorias() {
    try {
        return (await getPendingVistorias()).length;
    } catch {
        return 0;
    }
}

export async function removePendingVistoria(id) {
    const db = await openDB();
    return new Promise((resolve) => {
        const tx = db.transaction(STORE, 'readwrite');
        tx.objectStore(STORE).delete(id);
        tx.oncomplete = () => resolve();
        tx.onerror = () => resolve();
    });
}

/**
 * Envia uma vistoria pendente. Em caso de sucesso, reconcilia as fotos
 * (temp → id real) e remove da fila. Retorna o id real ou lança em falha.
 */
export async function syncOneVistoria(record, options = {}) {
    const appBase = options.appBase
        ?? document.querySelector('meta[name="app-base"]')?.content ?? '';
    const csrf = options.csrfToken
        ?? document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    const resp = await fetch(`${appBase}/api/vistorias`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf,
        },
        body: JSON.stringify(record.payload),
    });
    if (!resp.ok) {
        throw new Error(`sync vistoria falhou: ${resp.status}`);
    }
    const data = await resp.json();
    if (record.temp_photo_id) {
        await reconcileTempId(record.temp_photo_id, data.id);
    }
    await removePendingVistoria(record.id);
    return data.id;
}

/** Sincroniza todas as vistorias pendentes (nível de página). */
export async function syncPendingVistorias(options = {}) {
    const pendentes = await getPendingVistorias();
    let enviadas = 0;
    for (const record of pendentes) {
        try {
            await syncOneVistoria(record, options);
            enviadas++;
        } catch { /* mantém na fila p/ nova tentativa */ }
    }
    return { total: pendentes.length, enviadas };
}
```

- [x] **Step 2: Verificar sintaxe/build**

Run: `npm run build`
Expected: build sem erros (o módulo é importado nas Tasks seguintes; aqui só garante que compila).

- [x] **Step 3: Commit**

```bash
git add resources/js/offline-vistoria.js
git commit -m "feat(vistoria-offline): modulo outbox de vistorias (IndexedDB poprua_vistorias)"
```

---

## Task 4: Interceptar o submit em `vistoria-form.js`

**Files:**
- Modify: `resources/js/vistoria-form.js`

**Interfaces:**
- Consumes: `enqueueVistoria`, `syncPendingVistorias` (Task 3); `reconcileTempId`, `getTempPhotoId` (offline-upload); `serializeFormToPayload`, `APP_BASE`, `showSubmitSavingIndicator`, `limparRascunho` (mesmo arquivo).
- Produces: submit que faz POST AJAX (online) ou enfileira (offline). Só na página de CRIAÇÃO (`vistorias.store`), não na edição.

- [x] **Step 1: Importar o módulo de outbox**

Modify `resources/js/vistoria-form.js`: no topo do arquivo, junto dos outros imports de `./offline-upload`, adicionar:

```js
import { enqueueVistoria, syncPendingVistorias } from './offline-vistoria';
import { reconcileTempId, getTempPhotoId } from './offline-upload';
```

(Se `reconcileTempId`/`getTempPhotoId` já estiverem importados de `./offline-upload`, não duplicar — apenas garantir que estão disponíveis.)

- [x] **Step 2: Substituir o handler de submit**

Modify `resources/js/vistoria-form.js`: substituir o bloco atual do listener de submit (linhas 1026-1032):

```js
const formEl = document.getElementById('vistoria-form');
if (formEl) {
    formEl.addEventListener('submit', function() {
        formSubmitting = true;
        showSubmitSavingIndicator();
        limparRascunho();
    });
```

por:

```js
const formEl = document.getElementById('vistoria-form');
if (formEl) {
    formEl.addEventListener('submit', async function(e) {
        // Só interceptamos a CRIAÇÃO (POST em vistorias.store). Edição segue nativa.
        const isCreate = formEl.getAttribute('method')?.toUpperCase() === 'POST'
            && !formEl.querySelector('input[name="_method"]');
        if (!isCreate) {
            formSubmitting = true;
            showSubmitSavingIndicator();
            limparRascunho();
            return;
        }

        e.preventDefault();
        formSubmitting = true;
        showSubmitSavingIndicator();

        const payload = serializeFormToPayload(formEl);
        payload.client_uuid = crypto.randomUUID();
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

        try {
            const resp = await fetch(`${APP_BASE}/api/vistorias`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify(payload),
            });
            if (!resp.ok) {
                if (resp.status === 422) {
                    const err = await resp.json().catch(() => ({}));
                    const msg = err.message || 'Verifique os campos obrigatórios.';
                    window.showToast?.(msg, 'warning');
                    formSubmitting = false;
                    return;
                }
                throw new Error(`status ${resp.status}`);
            }
            const data = await resp.json();
            await reconcileTempId(getTempPhotoId(), data.id);
            limparRascunho();
            window.location.assign(data.redirect_url);
        } catch (_) {
            // Rede indisponível → enfileira na outbox e sincroniza depois.
            await enqueueVistoria(payload);
            limparRascunho();
            window.updateSyncBadge?.();
            window.showToast?.('Vistoria salva no aparelho — será enviada quando houver conexão.', 'info');
            window.location.assign(`${APP_BASE}/minhas-vistorias`);
        }
    });
```

- [x] **Step 3: Disparar sync ao voltar online (nesta página)**

Modify `resources/js/vistoria-form.js`: logo após o bloco do listener de submit, adicionar:

```js
window.addEventListener('online', () => {
    syncPendingVistorias().then((r) => {
        if (r.enviadas > 0) window.updateSyncBadge?.();
    });
});
```

- [x] **Step 4: Build**

Run: `npm run build`
Expected: build sem erros.

- [x] **Step 5: Verificar (online, sem regressão)**

Verificação manual (o app é validado em aparelho/navegador — não há harness JS de offline):
- Servir a app (`php artisan serve --port=8088`), logar, abrir `/vistorias/create`, preencher o mínimo e enviar **online**.
- Esperado: cria a vistoria e **redireciona para o `show`** (mesma experiência de hoje), agora via `POST /api/vistorias` (conferir na aba Network).

- [x] **Step 6: Commit**

```bash
git add resources/js/vistoria-form.js
git commit -m "feat(vistoria-offline): interceptar submit de criacao (fetch + fallback outbox)"
```

---

## Task 5: Background Sync `sync-vistorias` no Service Worker

**Files:**
- Modify: `public/sw.js`

**Interfaces:**
- Consumes: outbox `poprua_vistorias` (Task 3), banco `poprua_fotos` (para reconciliar), endpoint `POST /api/vistorias`.
- Produces: sincronização das vistorias pendentes com o app fechado (Chromium), disparando depois o sync das fotos.

- [x] **Step 1: Registrar a tag no evento sync**

Modify `public/sw.js`: no listener `self.addEventListener('sync', ...)` (linha 102), adicionar um segundo `if`:

```js
    if (event.tag === 'sync-vistorias') {
        event.waitUntil(syncPendingVistorias());
    }
```

- [x] **Step 2: Implementar `syncPendingVistorias` no SW**

Modify `public/sw.js`: ao final da seção de sincronização (após `syncPendingPhotos`, linha 201), adicionar:

```js
// --- Sincronizacao de vistorias pendentes (Background Sync) ---
// Le a outbox poprua_vistorias, faz POST JSON /api/vistorias autenticado via
// cookie XSRF, reconcilia as fotos temp -> id real no banco poprua_fotos e,
// ao final, dispara o sync das fotos ja reconciliadas.

function vistoriasDbOpen() {
    return new Promise(function(resolve, reject) {
        var req = indexedDB.open('poprua_vistorias', 1);
        req.onsuccess = function(e) { resolve(e.target.result); };
        req.onerror = function() { reject(req.error); };
    });
}

function idbGetAllStore(db, store) {
    return new Promise(function(resolve) {
        if (!db.objectStoreNames.contains(store)) return resolve([]);
        var tx = db.transaction(store, 'readonly');
        var req = tx.objectStore(store).getAll();
        req.onsuccess = function() { resolve(req.result || []); };
        req.onerror = function() { resolve([]); };
    });
}

function idbDeleteFrom(db, store, id) {
    return new Promise(function(resolve) {
        var tx = db.transaction(store, 'readwrite');
        tx.objectStore(store).delete(id);
        tx.oncomplete = function() { resolve(); };
        tx.onerror = function() { resolve(); };
    });
}

// Reescreve vistoria_id temp_* -> id real no banco de fotos poprua_fotos.
async function reconcilePhotosInSw(tempId, realId) {
    if (!tempId) return;
    var db;
    try { db = await idbOpen(); } catch (e) { return; }
    var fotos = (await idbGetAll(db)).filter(function(f) {
        return (f.vistoria_id || f.vistoriaId) === tempId;
    });
    await new Promise(function(resolve) {
        var tx = db.transaction('pendentes', 'readwrite');
        var store = tx.objectStore('pendentes');
        fotos.forEach(function(f) { f.vistoria_id = realId; delete f.vistoriaId; store.put(f); });
        tx.oncomplete = function() { resolve(); };
        tx.onerror = function() { resolve(); };
    });
    db.close();
}

async function syncPendingVistorias() {
    var db;
    try { db = await vistoriasDbOpen(); } catch (e) { return; }
    var pendentes = await idbGetAllStore(db, 'pendentes');
    if (pendentes.length === 0) { db.close(); return; }

    var xsrf = await getXsrfToken();
    var endpoint = new URL('api/vistorias', self.registration.scope).toString();
    var reconciliouAlguma = false;

    for (var i = 0; i < pendentes.length; i++) {
        var rec = pendentes[i];
        try {
            var headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
            if (xsrf) headers['X-XSRF-TOKEN'] = xsrf;
            var resp = await fetch(endpoint, {
                method: 'POST',
                body: JSON.stringify(rec.payload),
                headers: headers,
                credentials: 'same-origin'
            });
            if (resp.ok) {
                var data = await resp.json();
                await reconcilePhotosInSw(rec.temp_photo_id, data.id);
                await idbDeleteFrom(db, 'pendentes', rec.id);
                reconciliouAlguma = true;
            }
        } catch (e) { /* mantem na fila p/ nova tentativa */ }
    }
    db.close();

    // Fotos agora reconciliadas podem subir.
    if (reconciliouAlguma) {
        await syncPendingPhotos();
    }
}
```

- [x] **Step 3: Incrementar `CACHE_VERSION`**

Modify `public/sw.js`: linha 1, `const CACHE_VERSION = 34;` → `const CACHE_VERSION = 35;`.

- [x] **Step 4: Registrar o Background Sync no cliente**

Modify `resources/js/offline-vistoria.js` (Task 3): acrescentar uma função que registra a tag e um fallback de polling, e chamá-la no `enqueueVistoria`. Adicionar ao final do arquivo:

```js
/** Agenda a sincronização (Background Sync, com fallback para o evento online). */
export async function registerVistoriaSync() {
    try {
        const reg = await navigator.serviceWorker?.ready;
        if (reg && 'sync' in reg) {
            await reg.sync.register('sync-vistorias');
            return;
        }
    } catch { /* cai no fallback */ }
    // Fallback: dispara ao voltar a conexão (tratado nas páginas via evento online).
}
```

E em `enqueueVistoria`, antes do `return`, chamar `await registerVistoriaSync();`.

- [x] **Step 5: Build**

Run: `npm run build`
Expected: sem erros.

- [x] **Step 6: Verificar (offline → reconexão, em aparelho/navegador)**

Verificação manual (checklist de campo):
- Online: abrir `/vistorias/create`, preencher, **ativar modo avião** (ou DevTools → Offline), enviar.
  - Esperado: toast "salva no aparelho", redireciona para `/minhas-vistorias`, badge incrementa; nada no servidor ainda.
- **Desativar o modo avião** (voltar a rede) e aguardar (ou reabrir o app).
  - Esperado: a vistoria é enviada, some da fila, o badge zera; a vistoria aparece no servidor (`/vistorias`); fotos tiradas antes reconciliam e sobem.

- [x] **Step 7: Commit**

```bash
git add public/sw.js resources/js/offline-vistoria.js
git commit -m "feat(vistoria-offline): background sync sync-vistorias + reconcile no SW (CACHE_VERSION 35)"
```

---

## Task 6: Badge e menu incluindo vistorias pendentes

**Files:**
- Modify: `resources/js/app.js`

**Interfaces:**
- Consumes: `countPendingVistorias`, `getPendingVistorias`, `syncPendingVistorias` (Task 3); `countSyncablePhotos` (offline-upload).
- Produces: badge global somando fotos + vistorias; botão "Sincronizar" cobrindo ambos; expõe `window.updateSyncBadge`.

- [x] **Step 1: Importar o módulo de vistorias**

Modify `resources/js/app.js`: junto dos imports de `./offline-upload`, adicionar:

```js
import { countPendingVistorias, syncPendingVistorias } from './offline-vistoria';
```

- [x] **Step 2: Somar vistorias no badge e expor global**

Modify `resources/js/app.js`: substituir a função `updateSyncBadge` (linhas 144-151) por:

```js
    async function updateSyncBadge() {
        const [fotos, vistorias] = await Promise.all([
            countSyncablePhotos(),
            countPendingVistorias(),
        ]);
        const count = fotos + vistorias;
        const badge = document.getElementById('sync-badge');
        if (badge) {
            badge.textContent = count;
            badge.classList.toggle('hidden', count === 0);
        }
    }
    window.updateSyncBadge = updateSyncBadge;
```

- [x] **Step 3: Sincronizar vistorias no botão manual**

Modify `resources/js/app.js`: dentro de `window.syncAllPendingPhotos` (linha 153), antes do bloco que trata as fotos, sincronizar as vistorias primeiro (elas reconciliam as fotos). Logo após `window.syncAllPendingPhotos = async function() {`, inserir:

```js
        const rv = await syncPendingVistorias({ appBase: APP_BASE, csrfToken });
        if (rv.enviadas > 0) {
            showToast(`${rv.enviadas} vistoria(s) enviada(s).`, 'success');
        }
        await updateSyncBadge();
```

- [x] **Step 4: Build + verificação**

Run: `npm run build`
Expected: sem erros. Verificação manual: com uma vistoria e uma foto pendentes, o badge mostra a soma; o botão "Sincronizar Fotos" do menu envia a vistoria (e depois as fotos) e zera o badge.

- [x] **Step 5: Commit**

```bash
git add resources/js/app.js
git commit -m "feat(vistoria-offline): badge e sync manual incluindo vistorias pendentes"
```

---

## Task 7: Verificação de ponta a ponta

**Files:** nenhum (verificação).

- [x] **Step 1: Suíte de backend**

Run: `php artisan test --filter=Vistoria`
Expected: todos os testes de vistoria passam (novos + existentes, sem regressão).

- [x] **Step 2: Build de produção**

Run: `npm run build`
Expected: sem erros; os novos entries compilados.

- [x] **Step 3: Checklist funcional (navegador/aparelho)**

- [x] Online: criar vistoria → redireciona para o `show` (sem regressão).
- [x] Offline: enviar → toast "salva no aparelho", badge incrementa, nada no servidor.
- [x] Reconexão: vistoria sincroniza, badge zera, aparece no servidor; fotos reconciliam e sobem.
- [x] App fechado + reaberto com rede (Chromium): Background Sync sincroniza a pendente.
- [x] Reenvio (simular resposta perdida): não cria vistoria duplicada (idempotência por `client_uuid`).
- [x] Fluxo web de criação para usuário de navegador continua funcionando.

- [x] **Step 4: Registrar o resultado**

Anexar o resultado do checklist ao final do spec (`docs/superpowers/specs/2026-07-10-vistoria-offline-outbox-design.md`, seção "Critérios de aceitação") e commitar.

---

## Notas de execução

- **Não dar `git push`** sem confirmação explícita do usuário — push dispara o deploy de produção (inclui a migração e o bump de `CACHE_VERSION`).
- A migração é aditiva (coluna nullable + índice único); segura em produção, sem backfill.
- Se `serializeFormToPayload` não incluir algum campo que o endpoint exige (ex.: `lat`/`lng` de contexto, `participantes`, `novos_moradores`), ajustar o payload no submit para incluí-los — conferir o que `salvarRascunho` já serializa (é a mesma função) e completar apenas o que faltar para o endpoint validar. Este é o ponto mais provável de exigir um ajuste durante a execução.
- Booleans: o endpoint usa `$request->boolean(...)`; garantir que o payload envie os campos de complexidade em formato aceito por `boolean()` (`true`/`1`/`"1"`).
