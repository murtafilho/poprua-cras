import { imgType, imgExt } from "./img-format";
/**
 * POPRUA v2 - Offline Upload Manager
 * Gerencia fila de upload de imagens com suporte offline-first.
 *
 * Uso:
 *   import { OfflineUpload } from './offline-upload';
 *   const uploader = new OfflineUpload();
 *   await uploader.addFoto(vistoriaId, file, { descricao: '...' });
 */

const DB_NAME = 'poprua_fotos';
const DB_VERSION = 1;
const STORE_NAME = 'pendentes';

// Limite client-side antes da compressao. 30MB cobre foto raw de smartphone
// moderno; arquivos maiores sao quase certamente video ou erro de selecao.
// Server-side aceita ate 10MB pos-compressao (max:10240 nas validations).
const MAX_FILE_SIZE_BYTES = 30 * 1024 * 1024;
const MAX_FILE_SIZE_LABEL = '30MB';

class OfflineUpload {
    constructor() {
        this._dbPromise = null;
        this._listeners = {};
        this._pollInterval = null;
        this._init();
    }

    _init() {
        // Register Service Worker
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js')
                .then(reg => {
                    this._swRegistration = reg;
                    console.log('[OfflineUpload] Service Worker registered');
                })
                .catch(err => console.error('[OfflineUpload] SW registration failed:', err));

            // Listen for messages from Service Worker
            navigator.serviceWorker.addEventListener('message', (event) => {
                this._emit(event.data.type, event.data);
            });
        }

        // Monitor connectivity changes
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

    _openDB() {
        if (this._dbPromise) {
            return this._dbPromise;
        }
        this._dbPromise = new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);
            request.onupgradeneeded = (e) => {
                const db = e.target.result;
                if (!db.objectStoreNames.contains(STORE_NAME)) {
                    const store = db.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
                    store.createIndex('status', 'status', { unique: false });
                    store.createIndex('vistoriaId', 'vistoriaId', { unique: false });
                }
            };
            request.onsuccess = (e) => resolve(e.target.result);
            request.onerror = (e) => {
                this._dbPromise = null;
                reject(e.target.error);
            };
        });
        return this._dbPromise;
    }

    /**
     * Compresses an image before storing.
     * Reduces typical 5MB photos to ~200-400KB.
     */
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
                    quality
                );
            };

            img.onerror = () => {
                URL.revokeObjectURL(url);
                reject(new Error('Failed to load image for compression'));
            };

            img.src = url;
        });
    }

    /**
     * Add a photo to the upload queue.
     * If online and on WiFi, uploads immediately.
     * Otherwise, stores in IndexedDB for later sync.
     */
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
            options.quality || 0.7
        );

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const appBase = document.querySelector('meta[name="app-base"]')?.content || '';

        const record = {
            vistoriaId: vistoriaId,
            blob: compressed,
            filename: file.name || `foto_${Date.now()}.jpg`,
            descricao: options.descricao || null,
            uploadUrl: `${appBase}/api/vistorias/fotos`,
            csrfToken: csrfToken,
            status: 'pending',
            createdAt: Date.now(),
            originalSize: file.size,
            compressedSize: compressed.size,
        };

        // If online and WiFi, try direct upload
        if (this._isOnWifi()) {
            try {
                const result = await this._uploadDirect(record);
                this._emit('UPLOAD_SUCCESS', {
                    vistoriaId,
                    filename: record.filename,
                    result,
                });
                return { queued: false, uploaded: true, result };
            } catch (e) {
                // Fall through to queue
                console.warn('[OfflineUpload] Direct upload failed, queuing:', e.message);
            }
        }

        // Store in IndexedDB
        const db = await this._openDB();
        const id = await new Promise((resolve, reject) => {
            const tx = db.transaction(STORE_NAME, 'readwrite');
            const store = tx.objectStore(STORE_NAME);
            const request = store.add(record);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });

        this._emit('QUEUED', {
            id,
            vistoriaId,
            filename: record.filename,
            compressedSize: compressed.size,
        });

        // Register Background Sync
        await this._registerSync();

        return { queued: true, uploaded: false, id };
    }

    async _uploadDirect(record) {
        const formData = new FormData();
        formData.append('foto', record.blob, record.filename);
        formData.append('vistoria_id', record.vistoriaId);
        if (record.descricao) {
            formData.append('descricao', record.descricao);
        }

        const response = await fetch(record.uploadUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': record.csrfToken,
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(`Upload failed: ${response.status}`);
        }

        return response.json();
    }

    _isOnWifi() {
        if (!navigator.onLine) {
            return false;
        }
        const conn = navigator.connection || navigator.mozConnection;
        if (conn && conn.type) {
            return conn.type === 'wifi' || conn.type === 'ethernet';
        }
        // Fallback: assume online is good enough if we can't detect type
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
            // Fallback for browsers without Background Sync (Safari, Firefox)
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

        // Ask Service Worker to process queue
        if (navigator.serviceWorker?.controller) {
            navigator.serviceWorker.controller.postMessage({ type: 'TRIGGER_UPLOAD' });
        }
    }

    /**
     * Get count of pending uploads.
     */
    async getPendingCount() {
        const db = await this._openDB();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(STORE_NAME, 'readonly');
            const store = tx.objectStore(STORE_NAME);
            const index = store.index('status');
            const request = index.count('pending');
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Get all pending uploads for a vistoria.
     */
    async getPendingForVistoria(vistoriaId) {
        const db = await this._openDB();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(STORE_NAME, 'readonly');
            const store = tx.objectStore(STORE_NAME);
            const index = store.index('vistoriaId');
            const request = index.getAll(vistoriaId);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    // Simple event emitter
    on(event, callback) {
        if (!this._listeners[event]) {
            this._listeners[event] = [];
        }
        this._listeners[event].push(callback);
        return () => {
            this._listeners[event] = this._listeners[event].filter(cb => cb !== callback);
        };
    }

    _emit(event, data) {
        (this._listeners[event] || []).forEach(cb => cb(data));
    }
}

// Singleton instance
const offlineUpload = new OfflineUpload();

export { OfflineUpload, offlineUpload };
