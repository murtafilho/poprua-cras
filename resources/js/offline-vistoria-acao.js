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
