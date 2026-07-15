# Finalizar/Cancelar/Reativar Vistoria Offline — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Permitir finalizar/cancelar/reativar uma vistoria existente sem rede: ao clicar, tenta o POST; se falhar, enfileira numa outbox de ações e sincroniza quando a conexão volta. Ações idempotentes (reenvio seguro).

**Architecture:** Espelha a fatia 1 (criar offline), mas para mudanças de estado idempotentes de vistoria já persistida — sem `client_uuid`/dedup/reconciliação. Endpoints JSON reutilizam métodos novos do `VistoriaService` (web + API compartilham). Outbox em IndexedDB separado `poprua_vistoria_acoes`. Background Sync no SW. UI otimista.

**Tech Stack:** Laravel 12 · PostgreSQL · PHPUnit 11 · Vite · Service Worker + IndexedDB · Background Sync.

## Global Constraints

- Endpoints: `POST /api/vistorias/{vistoria}/finalizar` · `/cancelar` · `/reativar` (JSON, grupo `['web','auth']`, autorizados pelas Policies `update`/`cancelar`/`reativar`), retornam `{ "id": <int>, "finalizada": <bool>, "cancelada": <bool> }`.
- Lógica em `VistoriaService::finalizar/cancelar/reativar(Vistoria)`; o `VistoriaController` (web) passa a chamá-los (refatoração DRY).
- Outbox: IndexedDB **`poprua_vistoria_acoes`**, store `pendentes` — separado de `poprua_vistorias`/`poprua_fotos`.
- Módulo JS novo `resources/js/offline-vistoria-acao.js` (espelha `offline-vistoria.js`).
- Background Sync tag `sync-acoes-vistoria`. **Incrementar `CACHE_VERSION` 36 → 37** em `public/sw.js`.
- Idempotência natural (flip de flag) — SEM `client_uuid`.
- Escopo: finalizar + cancelar + reativar. **Fora:** complementar, editar, destroy, finalizar vistoria não sincronizada.
- Sem regressão no fluxo web. Branch `feat/vistoria-acoes-offline`; **não** dar push (dispara deploy). Todo commit termina com `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`.
- Banco de teste `poprua_cras_test` em `127.0.0.1:5433`. `vendor/bin/pint --dirty` e `vendor/bin/phpstan analyse` limpos. Tudo em pt-BR.

---

## Estrutura de arquivos

| Caminho | Ação | Responsabilidade |
|---|---|---|
| `app/Services/VistoriaService.php` | editar | `finalizar/cancelar/reativar(Vistoria)` + `invalidarCachesPosMutacao()` |
| `app/Http/Controllers/VistoriaController.php` | editar | métodos web finalizar/cancelar/reativar chamam o service |
| `app/Http/Controllers/Api/VistoriaAcaoController.php` | criar | endpoints JSON das 3 ações |
| `routes/api.php` | editar | 3 rotas de ação |
| `resources/js/offline-vistoria-acao.js` | criar | outbox IndexedDB `poprua_vistoria_acoes` |
| `resources/views/vistorias/show.blade.php` | editar | 3 forms: trocar confirm Alpine por `data-*` |
| `resources/js/vistoria-show.js` | editar | interceptar os 3 forms (confirm + online/offline + UI otimista) |
| `public/sw.js` | editar | Background Sync `sync-acoes-vistoria` + bump `CACHE_VERSION` 37 |
| `resources/js/app.js` | editar | badge/auto-sync incluindo ações |
| `tests/Feature/Api/VistoriaAcaoApiTest.php` | criar | testes dos endpoints |

---

## Task 1: `VistoriaService` — finalizar/cancelar/reativar + refatorar controller web

**Files:**
- Modify: `app/Services/VistoriaService.php`
- Modify: `app/Http/Controllers/VistoriaController.php`
- Test: usa os testes existentes de finalizar/cancelar (regressão) + novo teste de service.

**Interfaces:**
- Produces: `VistoriaService::finalizar(Vistoria): void`, `::cancelar(Vistoria): void`, `::reativar(Vistoria): void`, `::invalidarCachesPosMutacao(): void`. Consumido pela Task 2 e pelo controller web.

- [x] **Step 1: Escrever o teste (falha)**

Create `tests/Feature/VistoriaServiceEstadoTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vistoria;
use App\Services\VistoriaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class VistoriaServiceEstadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_finalizar_cancelar_reativar(): void
    {
        $user = User::factory()->create();
        Auth::login($user);
        $service = app(VistoriaService::class);
        $vistoria = Vistoria::factory()->create(['finalizada' => false, 'cancelada' => false]);

        $service->finalizar($vistoria);
        $this->assertTrue($vistoria->fresh()->finalizada);
        $this->assertSame($user->id, $vistoria->fresh()->finalizada_por);

        $service->reativar($vistoria);
        $this->assertFalse($vistoria->fresh()->finalizada);
        $this->assertNull($vistoria->fresh()->finalizada_por);

        $service->cancelar($vistoria);
        $this->assertTrue($vistoria->fresh()->cancelada);
    }
}
```

- [x] **Step 2: Rodar e ver falhar**

Run: `php artisan test --filter=VistoriaServiceEstadoTest`
Expected: FAIL (métodos não existem).

- [x] **Step 3: Adicionar os métodos ao `VistoriaService`**

Modify `app/Services/VistoriaService.php`: adicionar (junto dos métodos públicos, ex.: após `criarComRelacionamentos`). `Auth` e `Cache` já estão importados no arquivo.

```php
    public function finalizar(Vistoria $vistoria): void
    {
        $vistoria->update([
            'finalizada' => true,
            'finalizada_em' => now(),
            'finalizada_por' => Auth::id(),
        ]);
        $this->invalidarCachesPosMutacao();
    }

    public function reativar(Vistoria $vistoria): void
    {
        $vistoria->update([
            'finalizada' => false,
            'finalizada_em' => null,
            'finalizada_por' => null,
        ]);
        $this->invalidarCachesPosMutacao();
    }

    public function cancelar(Vistoria $vistoria): void
    {
        $vistoria->update([
            'cancelada' => true,
            'cancelada_em' => now(),
            'cancelada_por' => Auth::id(),
        ]);
        $this->invalidarCachesPosMutacao();
    }

    public function invalidarCachesPosMutacao(): void
    {
        Cache::forget('dashboard:totais');
        Cache::forget('dashboard:dados_mensais');
        $this->invalidarCacheListagem();
    }
```

- [x] **Step 4: Refatorar o controller web para usar o service**

Modify `app/Http/Controllers/VistoriaController.php`: nos métodos `finalizar` (linha 204), `reativar` (219), `cancelar` (234), substituir o `$vistoria->update([...]); $this->invalidarCachesPosMutacaoVistoria();` por uma chamada ao service, mantendo `authorize` e o `redirect`. Ex. para `finalizar`:

```php
    public function finalizar(Vistoria $vistoria): RedirectResponse
    {
        $this->authorize('update', $vistoria);
        $this->vistoriaService->finalizar($vistoria);

        return redirect()->route('vistorias.show', $vistoria)->with('success', 'Zeladoria finalizada com sucesso!');
    }
```

Fazer o análogo para `reativar` (`$this->vistoriaService->reativar($vistoria);`) e `cancelar` (`$this->vistoriaService->cancelar($vistoria);`). NÃO alterar `complementar`/`destroy`/`store`/`update`.

- [x] **Step 5: Rodar testes (novo + regressão)**

Run: `php artisan test --filter=VistoriaServiceEstadoTest && php artisan test --filter=Vistoria`
Expected: novo passa; regressão (finalizar/cancelar web) permanece verde.

- [x] **Step 6: Pint + PHPStan + commit**

```bash
vendor/bin/pint --dirty && vendor/bin/phpstan analyse
git add app/Services/VistoriaService.php app/Http/Controllers/VistoriaController.php tests/Feature/VistoriaServiceEstadoTest.php
git commit -m "refactor(vistoria-acoes): extrair finalizar/cancelar/reativar para VistoriaService"
```

---

## Task 2: Endpoints JSON das ações + testes

**Files:**
- Create: `app/Http/Controllers/Api/VistoriaAcaoController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/VistoriaAcaoApiTest.php`

**Interfaces:**
- Consumes: `VistoriaService` (Task 1); Policies `update`/`cancelar`/`reativar`.
- Produces: `POST /api/vistorias/{vistoria}/finalizar|cancelar|reativar` → `{ id, finalizada, cancelada }`. Consumido pelas Tasks 3-4.

- [x] **Step 1: Escrever os testes (falham)**

Create `tests/Feature/Api/VistoriaAcaoApiTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Vistoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VistoriaAcaoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_finalizar_via_api(): void
    {
        $user = User::factory()->create();
        $vistoria = Vistoria::factory()->create(['user_id' => $user->id, 'finalizada' => false]);

        $this->actingAs($user)
            ->postJson("/api/vistorias/{$vistoria->id}/finalizar")
            ->assertOk()
            ->assertJson(['id' => $vistoria->id, 'finalizada' => true]);

        $this->assertTrue($vistoria->fresh()->finalizada);
    }

    public function test_finalizar_e_idempotente(): void
    {
        $user = User::factory()->create();
        $vistoria = Vistoria::factory()->create(['user_id' => $user->id, 'finalizada' => false]);

        $this->actingAs($user)->postJson("/api/vistorias/{$vistoria->id}/finalizar")->assertOk();
        $this->actingAs($user)->postJson("/api/vistorias/{$vistoria->id}/finalizar")->assertOk();

        $this->assertTrue($vistoria->fresh()->finalizada);
    }

    public function test_cancelar_e_reativar_via_api(): void
    {
        $user = User::factory()->create();
        $vistoria = Vistoria::factory()->create(['user_id' => $user->id, 'finalizada' => true]);

        $this->actingAs($user)->postJson("/api/vistorias/{$vistoria->id}/reativar")
            ->assertOk()->assertJson(['finalizada' => false]);
        $this->assertFalse($vistoria->fresh()->finalizada);

        $this->actingAs($user)->postJson("/api/vistorias/{$vistoria->id}/cancelar")
            ->assertOk()->assertJson(['cancelada' => true]);
        $this->assertTrue($vistoria->fresh()->cancelada);
    }

    public function test_usuario_sem_permissao_recebe_403(): void
    {
        $dono = User::factory()->create();
        $outro = User::factory()->create();
        $vistoria = Vistoria::factory()->create(['user_id' => $dono->id, 'finalizada' => false]);

        $this->actingAs($outro)
            ->postJson("/api/vistorias/{$vistoria->id}/finalizar")
            ->assertStatus(403);
    }
}
```

> Nota: `test_usuario_sem_permissao_recebe_403` assume que a Policy `update` nega um usuário não-dono não-admin. Se a Policy do projeto permitir (ex.: papéis amplos), ajustar o cenário para um usuário genuinamente sem a permission — conferir `app/Policies/VistoriaPolicy.php::update` e usar um usuário/idade de acordo.

- [x] **Step 2: Rodar e ver falhar**

Run: `php artisan test --filter=VistoriaAcaoApiTest`
Expected: FAIL (rotas não existem → 404).

- [x] **Step 3: Criar o controller**

Create `app/Http/Controllers/Api/VistoriaAcaoController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vistoria;
use App\Services\VistoriaService;
use Illuminate\Http\JsonResponse;

class VistoriaAcaoController extends Controller
{
    public function __construct(private VistoriaService $vistoriaService) {}

    public function finalizar(Vistoria $vistoria): JsonResponse
    {
        $this->authorize('update', $vistoria);
        $this->vistoriaService->finalizar($vistoria);

        return $this->estado($vistoria);
    }

    public function cancelar(Vistoria $vistoria): JsonResponse
    {
        $this->authorize('cancelar', $vistoria);
        $this->vistoriaService->cancelar($vistoria);

        return $this->estado($vistoria);
    }

    public function reativar(Vistoria $vistoria): JsonResponse
    {
        $this->authorize('reativar', $vistoria);
        $this->vistoriaService->reativar($vistoria);

        return $this->estado($vistoria);
    }

    private function estado(Vistoria $vistoria): JsonResponse
    {
        return response()->json([
            'id' => $vistoria->id,
            'finalizada' => (bool) $vistoria->finalizada,
            'cancelada' => (bool) $vistoria->cancelada,
        ]);
    }
}
```

- [x] **Step 4: Registrar as rotas**

Modify `routes/api.php`: junto do grupo `['web','auth']` das vistorias, adicionar:

```php
    Route::post('/vistorias/{vistoria}/finalizar', [\App\Http\Controllers\Api\VistoriaAcaoController::class, 'finalizar']);
    Route::post('/vistorias/{vistoria}/cancelar', [\App\Http\Controllers\Api\VistoriaAcaoController::class, 'cancelar']);
    Route::post('/vistorias/{vistoria}/reativar', [\App\Http\Controllers\Api\VistoriaAcaoController::class, 'reativar']);
```

- [x] **Step 5: Rodar testes + regressão**

Run: `php artisan test --filter=VistoriaAcaoApiTest && php artisan test --filter=Vistoria`
Expected: novos passam; regressão verde.

- [x] **Step 6: Pint + PHPStan + commit**

```bash
vendor/bin/pint --dirty && vendor/bin/phpstan analyse
git add app/Http/Controllers/Api/VistoriaAcaoController.php routes/api.php tests/Feature/Api/VistoriaAcaoApiTest.php
git commit -m "feat(vistoria-acoes): endpoints JSON finalizar/cancelar/reativar"
```

---

## Task 3: Módulo outbox `offline-vistoria-acao.js`

**Files:**
- Create: `resources/js/offline-vistoria-acao.js`

**Interfaces:**
- Produces: `enqueueAcao({vistoria_id, acao})`, `getPendingAcoes()`, `getSyncableAcoes()`, `countPendingAcoes()`, `removePendingAcao(id)`, `syncOneAcao(record, options)`, `syncPendingAcoes(options)`, `registerAcaoSync()`. Consumido pelas Tasks 4 e 6.

- [x] **Step 1: Escrever o módulo**

Create `resources/js/offline-vistoria-acao.js` (espelha `resources/js/offline-vistoria.js`, com dead-letter em 4xx):

```js
/**
 * SIZEM — Outbox de ações de estado da vistoria (finalizar/cancelar/reativar),
 * offline. IndexedDB separado (poprua_vistoria_acoes). Ações idempotentes.
 */

const DB_NAME = 'poprua_vistoria_acoes';
const DB_VERSION = 1;
const STORE = 'pendentes';
const PERMANENT_REJECTION_STATUSES = [403, 404, 422];

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

export async function enqueueAcao({ vistoria_id, acao }) {
    const db = await openDB();
    const record = { vistoria_id, acao, status: 'pending', created_at: new Date().toISOString() };
    const id = await new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readwrite');
        const req = tx.objectStore(STORE).add(record);
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
    await registerAcaoSync();
    return id;
}

export async function getPendingAcoes() {
    const db = await openDB();
    return new Promise((resolve) => {
        const tx = db.transaction(STORE, 'readonly');
        const req = tx.objectStore(STORE).getAll();
        req.onsuccess = () => resolve(req.result || []);
        req.onerror = () => resolve([]);
    });
}

export async function getSyncableAcoes() {
    return (await getPendingAcoes()).filter((r) => r.status !== 'failed');
}

export async function countPendingAcoes() {
    try {
        return (await getSyncableAcoes()).length;
    } catch {
        return 0;
    }
}

async function updateAcao(record) {
    const db = await openDB();
    return new Promise((resolve) => {
        const tx = db.transaction(STORE, 'readwrite');
        tx.objectStore(STORE).put(record);
        tx.oncomplete = () => resolve();
        tx.onerror = () => resolve();
    });
}

export async function removePendingAcao(id) {
    const db = await openDB();
    return new Promise((resolve) => {
        const tx = db.transaction(STORE, 'readwrite');
        tx.objectStore(STORE).delete(id);
        tx.oncomplete = () => resolve();
        tx.onerror = () => resolve();
    });
}

export async function syncOneAcao(record, options = {}) {
    const appBase = options.appBase
        ?? document.querySelector('meta[name="app-base"]')?.content ?? '';
    const csrf = options.csrfToken
        ?? document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    const resp = await fetch(`${appBase}/api/vistorias/${record.vistoria_id}/${record.acao}`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
    });
    if (resp.ok) {
        await removePendingAcao(record.id);
        return true;
    }
    if (PERMANENT_REJECTION_STATUSES.includes(resp.status)) {
        record.status = 'failed';
        await updateAcao(record);
        return null; // rejeição permanente: não re-tenta
    }
    throw new Error(`sync acao falhou: ${resp.status}`); // 5xx/rede: mantém p/ retry
}

export async function syncPendingAcoes(options = {}) {
    const pendentes = await getSyncableAcoes();
    let enviadas = 0, falhas = 0;
    for (const record of pendentes) {
        try {
            const r = await syncOneAcao(record, options);
            if (r === true) enviadas++;
            else if (r === null) falhas++;
        } catch { /* mantém na fila p/ nova tentativa */ }
    }
    return { total: pendentes.length, enviadas, falhas };
}

export async function registerAcaoSync() {
    try {
        const reg = await navigator.serviceWorker?.ready;
        if (reg && 'sync' in reg) {
            await reg.sync.register('sync-acoes-vistoria');
        }
    } catch { /* fallback: eventos online/visibilitychange nas páginas */ }
}
```

- [x] **Step 2: Build**

Run: `npm run build`
Expected: sem erros.

- [x] **Step 3: Commit**

```bash
git add resources/js/offline-vistoria-acao.js
git commit -m "feat(vistoria-acoes): modulo outbox de acoes (IndexedDB poprua_vistoria_acoes)"
```

---

## Task 4: Interceptar os forms de estado (blade + `vistoria-show.js`)

**Files:**
- Modify: `resources/views/vistorias/show.blade.php`
- Modify: `resources/js/vistoria-show.js`

**Interfaces:**
- Consumes: `enqueueAcao`, `syncPendingAcoes` (Task 3); endpoints (Task 2); `window.updateSyncBadge` (app.js).
- Produces: os 3 forms de estado passam a: online → aplica + reload; offline → enfileira + UI otimista.

- [x] **Step 1: Trocar o confirm Alpine por `data-*` nos 3 forms**

Modify `resources/views/vistorias/show.blade.php`:
- Form **finalizar** (linha 600-601): remover o atributo `x-on:submit="..."` e adicionar
  `data-acao-offline="finalizar" data-confirm="Deseja finalizar esta zeladoria? Apos a finalizacao, nao sera possivel editar."`.
- Form **reativar** (613-614): remover `x-on:submit` e adicionar
  `data-acao-offline="reativar" data-confirm="Deseja reativar esta zeladoria para que o responsavel possa retomar a edicao?"`.
- Form **cancelar** (645-646): remover `x-on:submit` e adicionar
  `data-acao-offline="cancelar" data-confirm="Deseja cancelar esta zeladoria? Esta acao nao podera ser desfeita."`.

Deixar o form **complementar** (630) INALTERADO (fora de escopo).

- [x] **Step 2: Interceptar no `vistoria-show.js`**

Modify `resources/js/vistoria-show.js`: adicionar o import no topo e a fiação no final do arquivo.

Import:

```js
import { enqueueAcao, syncPendingAcoes } from './offline-vistoria-acao';
```

Fiação (adicionar ao final, executada no carregamento do show):

```js
(function wireAcoesEstado() {
    const APP_BASE = document.querySelector('meta[name="app-base"]')?.content ?? '';
    const forms = document.querySelectorAll('form[data-acao-offline]');

    forms.forEach((form) => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const acao = form.getAttribute('data-acao-offline');
            const msg = form.getAttribute('data-confirm');
            if (msg && !confirm(msg)) return;

            const vistoriaId = window.VISTORIA_ID;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const botoes = document.querySelectorAll('form[data-acao-offline] button');
            botoes.forEach((b) => (b.disabled = true));

            let resp;
            try {
                resp = await fetch(`${APP_BASE}/api/vistorias/${vistoriaId}/${acao}`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                });
            } catch (_networkErr) {
                await enqueueAcao({ vistoria_id: vistoriaId, acao });
                window.updateSyncBadge?.();
                window.showToast?.('Ação salva no aparelho — será enviada quando houver conexão.', 'info');
                marcarPendente(form, acao);
                return;
            }

            if (!resp.ok) {
                botoes.forEach((b) => (b.disabled = false));
                window.showToast?.('Não foi possível registrar a ação. Tente novamente.', 'error');
                return;
            }
            window.location.reload(); // reflete o novo estado (espelha o comportamento web)
        });
    });

    function marcarPendente(form, acao) {
        const btn = form.querySelector('button');
        if (btn) btn.textContent = 'Pendente de envio…';
    }

    // Auto-sync ao voltar a conexão nesta página.
    window.addEventListener('online', () => {
        syncPendingAcoes().then((r) => { if (r.enviadas > 0) window.location.reload(); });
    });
})();
```

- [x] **Step 3: Build**

Run: `npm run build`
Expected: sem erros.

- [x] **Step 4: Verificação (online, sem regressão)**

Manual (o offline é verificado no Task 7): servir a app, abrir uma vistoria não finalizada, clicar **Finalizar** → confirma → a página recarrega mostrando o estado finalizado (agora via `POST /api/vistorias/{id}/finalizar`, conferir na aba Network).

- [x] **Step 5: Commit**

```bash
git add resources/views/vistorias/show.blade.php resources/js/vistoria-show.js
git commit -m "feat(vistoria-acoes): interceptar forms de estado (online reload / offline outbox + UI otimista)"
```

---

## Task 5: Background Sync `sync-acoes-vistoria` no Service Worker

**Files:**
- Modify: `public/sw.js`

**Interfaces:**
- Consumes: outbox `poprua_vistoria_acoes` (Task 3); endpoints (Task 2).
- Produces: sincronização das ações com o app fechado (Chromium).

- [x] **Step 1: Registrar a tag no evento sync**

Modify `public/sw.js`: no listener `self.addEventListener('sync', ...)`, adicionar:

```js
    if (event.tag === 'sync-acoes-vistoria') {
        event.waitUntil(syncPendingAcoes());
    }
```

- [x] **Step 2: Implementar `syncPendingAcoes` no SW**

Modify `public/sw.js`: após a seção de `syncPendingVistorias`, adicionar (reusa `getXsrfToken`; helpers próprios para o banco de ações):

```js
// --- Sincronizacao de acoes de estado (finalizar/cancelar/reativar) ---
function acoesDbOpen() {
    return new Promise(function(resolve, reject) {
        var req = indexedDB.open('poprua_vistoria_acoes', 1);
        req.onsuccess = function(e) { resolve(e.target.result); };
        req.onerror = function() { reject(req.error); };
    });
}

async function syncPendingAcoes() {
    var db;
    try { db = await acoesDbOpen(); } catch (e) { return; }
    var acoes = (await idbGetAllStore(db, 'pendentes')).filter(function(a) { return a.status !== 'failed'; });
    if (acoes.length === 0) { db.close(); return; }

    var xsrf = await getXsrfToken();
    for (var i = 0; i < acoes.length; i++) {
        var a = acoes[i];
        try {
            var endpoint = new URL('api/vistorias/' + a.vistoria_id + '/' + a.acao, self.registration.scope).toString();
            var headers = { 'Accept': 'application/json' };
            if (xsrf) headers['X-XSRF-TOKEN'] = xsrf;
            var resp = await fetch(endpoint, { method: 'POST', headers: headers, credentials: 'same-origin' });
            if (resp.ok) {
                await idbDeleteFrom(db, 'pendentes', a.id);
            } else if ([403, 404, 422].indexOf(resp.status) !== -1) {
                a.status = 'failed';
                await idbUpdateIn(db, 'pendentes', a);
            }
        } catch (e) { /* 5xx/rede: mantem p/ retry */ }
    }
    db.close();
}
```

> `idbGetAllStore`, `idbDeleteFrom` e `idbUpdateIn` já existem no `sw.js` (criados na fatia 1). Reusar; NÃO redefinir.

- [x] **Step 3: Incrementar `CACHE_VERSION`**

Modify `public/sw.js`: linha 1, `const CACHE_VERSION = 36;` → `const CACHE_VERSION = 37;`.

- [x] **Step 4: Build + commit**

Run: `npm run build` (deve passar).

```bash
git add public/sw.js
git commit -m "feat(vistoria-acoes): background sync sync-acoes-vistoria (CACHE_VERSION 37)"
```

---

## Task 6: Badge e auto-sync incluindo ações (`app.js`)

**Files:**
- Modify: `resources/js/app.js`

**Interfaces:**
- Consumes: `countPendingAcoes`, `syncPendingAcoes` (Task 3).
- Produces: badge global soma fotos + vistorias + ações; auto-sync global e botão manual sincronizam ações.

- [x] **Step 1: Import + badge**

Modify `resources/js/app.js`:
- Adicionar ao import: `import { countPendingAcoes, syncPendingAcoes } from './offline-vistoria-acao';`
- Em `updateSyncBadge`, incluir `countPendingAcoes()` no `Promise.all` e somar ao total.

- [x] **Step 2: Auto-sync global + botão manual incluem ações**

Modify `resources/js/app.js`:
- Na função global `autoSyncVistoriasPendentes` (fatia 1), após sincronizar vistorias, chamar `await syncPendingAcoes({ appBase: APP_BASE, csrfToken });` e `updateSyncBadge()`.
- Em `window.syncAllPendingPhotos`, após `syncPendingVistorias`, chamar também `syncPendingAcoes({ appBase: APP_BASE, csrfToken })`.

- [x] **Step 3: Build + commit**

Run: `npm run build` (deve passar).

```bash
git add resources/js/app.js
git commit -m "feat(vistoria-acoes): badge e auto-sync incluindo acoes de estado"
```

---

## Task 7: Verificação de ponta a ponta

**Files:** nenhum.

- [x] **Step 1: Backend**

Run: `php artisan test --filter=Vistoria`
Expected: todos verdes (novos + regressão).

- [x] **Step 2: Suíte completa + build**

Run: `php artisan test && npm run build`
Expected: verde; build limpo.

- [x] **Step 3: Checklist funcional (navegador)**

- [x] Online: Finalizar/Cancelar/Reativar → confirma → página recarrega no novo estado (via `/api/...`).
- [x] Offline (patch de fetch ou DevTools Offline): clicar → toast "salva no aparelho", badge incrementa, botões "pendente".
- [x] Reconexão: a ação sincroniza e o estado é aplicado no servidor.
- [x] Reenvio (idempotência): repetir a mesma ação não gera erro.
- [x] Sem regressão no fluxo web (usuário de navegador).

- [x] **Step 4: Registrar o resultado** no spec e commitar.

---

## Notas de execução

- **Não dar `git push`** sem confirmação explícita (dispara o deploy de produção).
- A refatoração do controller web (Task 1) é coberta pelos testes existentes de finalizar/cancelar — rodar `--filter=Vistoria` após.
- O teste de 403 (Task 2) depende da Policy `update`/`cancelar`/`reativar` do projeto — conferir `app/Policies/VistoriaPolicy.php` e ajustar o cenário do usuário sem permissão se necessário.
- A UI otimista é mínima (desabilitar botões + rótulo "pendente"); não reescrever a página de show.
