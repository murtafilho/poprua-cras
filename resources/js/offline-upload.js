import { imgType } from './img-format';

/**
 * SIZEM — Camada canônica de fila offline de fotos (IndexedDB + Service Worker).
 * Consumidores: vistoria-form.js, vistoria-edit.js, vistoria-show.js, app.js.
 */

const DB_NAME = 'poprua_fotos';
const DB_VERSION = 1;
const STORE_NAME = 'pendentes';

export const MAX_FILE_SIZE_BYTES = 30 * 1024 * 1024;
export const MAX_FILE_SIZE_LABEL = '30MB';

let dbPromise = null;

export function getVistoriaIdFromRecord(foto) {
    return foto.vistoria_id ?? foto.vistoriaId;
}

export function isTempRecord(foto) {
    const vid = getVistoriaIdFromRecord(foto);
    return typeof vid === 'string' && vid.startsWith('temp_');
}

export function blobFromRecord(foto) {
    if (foto.data) {
        return new Blob([foto.data], { type: foto.type || 'application/octet-stream' });
    }
    if (foto.blob instanceof Blob) {
        return foto.blob;
    }
    throw new Error('Registro de foto sem payload');
}

export function openFotosDB() {
    if (dbPromise) {
        return dbPromise;
    }

    dbPromise = new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);
        request.onupgradeneeded = (e) => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains(STORE_NAME)) {
                const store = db.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
                store.createIndex('status', 'status', { unique: false });
                store.createIndex('vistoria_id', 'vistoria_id', { unique: false });
            }
        };
        request.onsuccess = (e) => resolve(e.target.result);
        request.onerror = (e) => {
            dbPromise = null;
            reject(e.target.error);
        };
    });

    return dbPromise;
}

export async function getAllPendingPhotos() {
    const db = await openFotosDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_NAME, 'readonly');
        const req = tx.objectStore(STORE_NAME).getAll();
        req.onsuccess = () => resolve(req.result || []);
        req.onerror = () => reject(req.error);
    });
}

export async function getPendingPhotosFor(vistoriaIdOrTemp) {
    const all = await getAllPendingPhotos();
    return all.filter((f) => getVistoriaIdFromRecord(f) === vistoriaIdOrTemp);
}

export async function getSyncablePhotos() {
    const all = await getAllPendingPhotos();
    return all.filter((f) => !isTempRecord(f));
}

export async function countSyncablePhotos() {
    try {
        const fotos = await getSyncablePhotos();
        return fotos.length;
    } catch {
        return 0;
    }
}

export async function updatePendingPhotoLegenda(id, legenda) {
    const db = await openFotosDB();

    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_NAME, 'readwrite');
        const store = tx.objectStore(STORE_NAME);
        const req = store.get(id);
        req.onsuccess = () => {
            const record = req.result;
            if (record) {
                record.legenda = legenda || '';
                store.put(record);
            }
        };
        req.onerror = () => reject(req.error);
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
    });
}

export async function updatePendingPhotoPublica(id, publica) {
    const db = await openFotosDB();

    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_NAME, 'readwrite');
        const store = tx.objectStore(STORE_NAME);
        const req = store.get(id);
        req.onsuccess = () => {
            const record = req.result;
            if (record) {
                record.publica = !!publica;
                store.put(record);
            }
        };
        req.onerror = () => reject(req.error);
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
    });
}

export async function savePendingPhoto(vistoriaId, file, options = {}) {
    const data = await file.arrayBuffer();
    const db = await openFotosDB();
    const record = {
        vistoria_id: vistoriaId,
        name: options.name || file.name || `foto_${Date.now()}.jpg`,
        type: options.type || file.type || imgType(),
        data,
        created_at: new Date().toISOString(),
        status: 'pending',
        legenda: options.legenda ?? '',
        publica: options.publica ?? false,
    };

    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_NAME, 'readwrite');
        const req = tx.objectStore(STORE_NAME).add(record);
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

export async function removePendingPhotoById(id) {
    const db = await openFotosDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_NAME, 'readwrite');
        tx.objectStore(STORE_NAME).delete(id);
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
    });
}

export async function removePendingPhotoByName(vistoriaId, fileName) {
    const fotos = await getPendingPhotosFor(vistoriaId);
    const foto = fotos.find((f) => f.name === fileName);
    if (foto?.id != null) {
        await removePendingPhotoById(foto.id);
    }
}

export async function reconcileTempId(tempId, realVistoriaId) {
    if (!tempId) {
        return;
    }
    const db = await openFotosDB();
    const fotos = await getPendingPhotosFor(tempId);
    if (fotos.length === 0) {
        return;
    }

    await new Promise((resolve, reject) => {
        const tx = db.transaction(STORE_NAME, 'readwrite');
        const store = tx.objectStore(STORE_NAME);
        fotos.forEach((f) => {
            f.vistoria_id = realVistoriaId;
            delete f.vistoriaId;
            store.put(f);
        });
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
    });
}

export async function cleanupOrphanedTempRecords() {
    const ONE_HOUR = 60 * 60 * 1000;
    const db = await openFotosDB();
    const all = await getAllPendingPhotos();

    await new Promise((resolve) => {
        const tx = db.transaction(STORE_NAME, 'readwrite');
        const store = tx.objectStore(STORE_NAME);
        for (const foto of all) {
            if (!isTempRecord(foto)) {
                continue;
            }
            const createdAt = foto.created_at
                ? new Date(foto.created_at).getTime()
                : (foto.createdAt || 0);
            if (Date.now() - createdAt > ONE_HOUR) {
                store.delete(foto.id);
            }
        }
        tx.oncomplete = () => resolve();
        tx.onerror = () => resolve();
    });
}

export function initTempPhotoSession() {
    let tempId = sessionStorage.getItem('poprua_fotos_temp_id');
    if (!tempId) {
        tempId = `temp_${Date.now()}`;
        sessionStorage.setItem('poprua_fotos_temp_id', tempId);
    }
    return tempId;
}

export function getTempPhotoId() {
    return sessionStorage.getItem('poprua_fotos_temp_id');
}

export function clearTempPhotoId() {
    sessionStorage.removeItem('poprua_fotos_temp_id');
}

export async function uploadPendingPhoto(foto, options = {}) {
    const appBase = options.appBase
        ?? document.querySelector('meta[name="app-base"]')?.content
        ?? '';
    const csrfToken = options.csrfToken
        ?? document.querySelector('meta[name="csrf-token"]')?.content
        ?? '';

    const blob = blobFromRecord(foto);
    const file = new File([blob], foto.name || 'foto.jpg', { type: foto.type || blob.type });
    const formData = new FormData();
    formData.append('vistoria_id', getVistoriaIdFromRecord(foto));
    formData.append('foto', file);
    if (foto.legenda) {
        formData.append('legenda', foto.legenda);
    }
    formData.append('publica', foto.publica ? '1' : '0');

    const response = await fetch(`${appBase}/api/vistorias/fotos`, {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            Accept: 'application/json',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error(`Upload failed: ${response.status}`);
    }

    return response.json();
}

class OfflineUpload {
    constructor() {
        this._listeners = {};
        this._pollInterval = null;
        this._init();
    }

    _init() {
        if ('serviceWorker' in navigator) {
            // Caminho dinamico baseado na baseURL da aplicacao
            const base = document.querySelector('meta[name="app-base"]')?.content
                ?? document.querySelector('base')?.getAttribute('href')
                ?? '';
            const swPath = base + '/sw.js';
            navigator.serviceWorker.register(swPath)
                .then((reg) => {
                    this._swRegistration = reg;
                })
                .catch((err) => console.error('[OfflineUpload] SW registration failed:', err));

            navigator.serviceWorker.addEventListener('message', (event) => {
                this._emit(event.data.type, event.data);
            });
        }

        if (navigator.connection) {
            navigator.connection.addEventListener('change', () => this._onConnectionChange());
        }
        window.addEventListener('online', () => this._onConnectionChange());
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this._trySync();
            }
        });
    }

    async _compressImage(file, maxWidth = 1920, quality = 0.7) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const url = URL.createObjectURL(file);

            img.onload = () => {
                URL.revokeObjectURL(url);

                const ratio = Math.min(maxWidth / img.width, 1);
                const width = Math.round(img.width * ratio);
                const height = Math.round(img.height * ratio);

                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;

                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);

                canvas.toBlob(
                    (blob) => {
                        if (blob) {
                            resolve(blob);
                        } else {
                            reject(new Error('Canvas toBlob failed'));
                        }
                    },
                    imgType(),
                    quality,
                );
            };

            img.onerror = () => {
                URL.revokeObjectURL(url);
                reject(new Error('Failed to load image for compression'));
            };

            img.src = url;
        });
    }

    async addFoto(vistoriaId, file, options = {}) {
        if (file.size > MAX_FILE_SIZE_BYTES) {
            const sizeMB = (file.size / (1024 * 1024)).toFixed(1);
            const msg = `Arquivo ${sizeMB}MB excede o limite de ${MAX_FILE_SIZE_LABEL}.`;
            if (typeof window.showToast === 'function') {
                window.showToast(msg, 'warning');
            }
            throw new Error(msg);
        }

        const compressed = await this._compressImage(
            file,
            options.maxWidth || 1920,
            options.quality || 0.7,
        );

        const filename = file.name || `foto_${Date.now()}.jpg`;

        if (this._isOnWifi()) {
            try {
                const tempRecord = {
                    vistoria_id: vistoriaId,
                    name: filename,
                    type: imgType(),
                    data: await compressed.arrayBuffer(),
                };
                const result = await uploadPendingPhoto(tempRecord);
                this._emit('UPLOAD_SUCCESS', { vistoriaId, filename, result });
                return { queued: false, uploaded: true, result };
            } catch (e) {
                console.warn('[OfflineUpload] Direct upload failed, queuing:', e.message);
            }
        }

        const id = await savePendingPhoto(vistoriaId, compressed, {
            name: filename,
            legenda: options.descricao || null,
        });

        this._emit('QUEUED', {
            id,
            vistoriaId,
            filename,
            compressedSize: compressed.size,
        });

        await this._registerSync();

        return { queued: true, uploaded: false, id };
    }

    _isOnWifi() {
        if (!navigator.onLine) {
            return false;
        }
        const conn = navigator.connection || navigator.mozConnection;
        if (conn?.type) {
            return conn.type === 'wifi' || conn.type === 'ethernet';
        }
        return navigator.onLine;
    }

    async _registerSync() {
        if (this._swRegistration && 'sync' in this._swRegistration) {
            try {
                await this._swRegistration.sync.register('upload-fotos');
            } catch (e) {
                console.warn('[OfflineUpload] Background Sync registration failed:', e);
                this._startPolling();
            }
        } else {
            this._startPolling();
        }
    }

    _startPolling() {
        if (this._pollInterval) {
            return;
        }
        this._pollInterval = setInterval(() => this._trySync(), 30000);
    }

    _stopPolling() {
        if (this._pollInterval) {
            clearInterval(this._pollInterval);
            this._pollInterval = null;
        }
    }

    _onConnectionChange() {
        if (this._isOnWifi()) {
            this._trySync();
        }
    }

    async _trySync() {
        if (!this._isOnWifi()) {
            return;
        }
        if (navigator.serviceWorker?.controller) {
            navigator.serviceWorker.controller.postMessage({ type: 'TRIGGER_UPLOAD' });
        }
    }

    async getPendingCount() {
        return countSyncablePhotos();
    }

    async getPendingForVistoria(vistoriaId) {
        return getPendingPhotosFor(vistoriaId);
    }

    on(event, callback) {
        if (!this._listeners[event]) {
            this._listeners[event] = [];
        }
        this._listeners[event].push(callback);
        return () => {
            this._listeners[event] = this._listeners[event].filter((cb) => cb !== callback);
        };
    }

    _emit(event, data) {
        (this._listeners[event] || []).forEach((cb) => cb(data));
    }
}

const offlineUpload = new OfflineUpload();

export { OfflineUpload, offlineUpload };
