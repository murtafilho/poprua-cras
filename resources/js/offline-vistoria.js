import { reconcileTempId, getTempPhotoId } from './offline-upload';
import { DESTINO, cabecalhos, classificarResposta, endpoint } from './offline/politica.js';

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

/**
 * Meta de exibição para Minhas Zeladorias (não vai no POST da API).
 * @returns {{ endereco_label?: string, tipo_label?: string }}
 */
function buildDisplayMeta(payload) {
    const form = document.getElementById('vistoria-form');
    const endereco =
        form?.dataset?.enderecoLabel?.trim()
        || (payload.lat != null && payload.lng != null
            ? `Lat ${Number(payload.lat).toFixed(5)} · Lng ${Number(payload.lng).toFixed(5)}`
            : 'Localização pendente');

    let tipo_label = '';
    const tipoSelect = form?.querySelector('[name="tipo_abordagem_id"]');
    if (tipoSelect instanceof HTMLSelectElement) {
        tipo_label = tipoSelect.selectedOptions[0]?.textContent?.trim() || '';
    }

    return { endereco_label: endereco, tipo_label };
}

/** Grava um payload de criação de vistoria na outbox. */
export async function enqueueVistoria(payload) {
    const db = await openDB();
    const record = {
        client_uuid: payload.client_uuid,
        temp_photo_id: getTempPhotoId(),
        payload,
        display: buildDisplayMeta(payload),
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

/**
 * Registros ainda "vivos" (não descartados definitivamente pelo servidor).
 * Um registro 'failed' foi rejeitado de forma permanente (422/409/403) e não
 * deve mais ser reenviado nem contado como pendência de sincronização.
 */
export async function getSyncableVistorias() {
    const all = await getPendingVistorias();
    return all.filter((r) => r.status !== 'failed');
}

export async function countPendingVistorias() {
    try {
        return (await getSyncableVistorias()).length;
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

/** Atualiza (merge) um registro existente da outbox, mantendo o mesmo id. */
export async function updatePendingVistoria(id, changes) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readwrite');
        const store = tx.objectStore(STORE);
        const req = store.get(id);
        req.onsuccess = () => {
            const record = req.result;
            if (record) {
                store.put({ ...record, ...changes });
            }
        };
        req.onerror = () => reject(req.error);
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
    });
}

/**
 * Envia uma vistoria pendente. Em caso de sucesso, reconcilia as fotos
 * (temp → id real) e remove da fila, retornando o id real.
 *
 * Distingue falha de REDE (fetch rejeita a Promise) — deixada subir para o
 * chamador, registro permanece 'pending' para nova tentativa — de rejeição
 * do SERVIDOR: em 4xx permanente (422/409/403) o registro é marcado
 * 'failed' (mantido no IndexedDB só para auditoria/UX, mas não deletado nem
 * relançado) e a função retorna `null` como sentinela; em 5xx/outros lança
 * (transiente → tentativa futura).
 */
export async function syncOneVistoria(record, options = {}) {
    const appBase = options.appBase
        ?? document.querySelector('meta[name="app-base"]')?.content ?? '';
    const csrf = options.csrfToken
        ?? document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    // Falha de rede: deixa o erro propagar (não captura aqui) — o registro
    // permanece 'pending' na fila para a próxima tentativa de sync.
    const resp = await fetch(endpoint(appBase, 'vistoria'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: cabecalhos({ csrf, json: true }),
        body: JSON.stringify(record.payload),
    });

    const destino = classificarResposta(resp, 'vistoria');
    if (destino !== DESTINO.SUCESSO) {
        if (destino === DESTINO.PERMANENTE) {
            await updatePendingVistoria(record.id, { status: 'failed' });
            return null;
        }
        // 5xx, 401, 419 ou redirect para login: mantém 'pending' para nova tentativa.
        throw new Error(`sync vistoria falhou: ${resp.status}`);
    }

    const data = await resp.json();
    if (record.temp_photo_id) {
        await reconcileTempId(record.temp_photo_id, data.id);
    }
    await removePendingVistoria(record.id);
    return data.id;
}

/**
 * Sincroniza todas as vistorias pendentes (nível de página).
 * Retorna { total, enviadas, falhas } para a UI poder avisar uma única vez
 * quando alguma vistoria foi definitivamente recusada pelo servidor.
 */
export async function syncPendingVistorias(options = {}) {
    const pendentes = await getSyncableVistorias();
    let enviadas = 0;
    let falhas = 0;
    for (const record of pendentes) {
        try {
            const id = await syncOneVistoria(record, options);
            if (id === null) {
                falhas++;
            } else {
                enviadas++;
            }
        } catch { /* mantém na fila p/ nova tentativa */ }
    }
    return { total: pendentes.length, enviadas, falhas };
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
