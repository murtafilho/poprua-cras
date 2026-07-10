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
    const id = await new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readwrite');
        const req = tx.objectStore(STORE).add(record);
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
    await registerVistoriaSync();
    return id;
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
