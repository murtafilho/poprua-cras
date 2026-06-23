const CACHE_VERSION = 19;
const CACHE_NAME = 'poprua-v' + CACHE_VERSION;
const TILE_CACHE = 'poprua-tiles-v1';
const API_CACHE = 'poprua-api-v1';
const MAX_TILE_CACHE = 2000;

self.addEventListener('install', function(event) {
    self.skipWaiting();
});

self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(names) {
            var keepCaches = [CACHE_NAME, TILE_CACHE, API_CACHE];
            return Promise.all(
                names.filter(function(name) {
                    return keepCaches.indexOf(name) === -1;
                }).map(function(name) {
                    return caches.delete(name);
                })
            );
        }).then(function() {
            return clients.claim();
        })
    );
});

self.addEventListener('fetch', function(event) {
    var url = new URL(event.request.url);

    if (event.request.method !== 'GET') return;

    // Vite assets com hash (imutaveis): cache-first
    if (url.pathname.match(/\/build\/assets\/.+\.[a-f0-9]{8,}\.(css|js|png|jpg|svg|woff2?)$/)) {
        event.respondWith(
            caches.match(event.request).then(function(cached) {
                return cached || fetch(event.request).then(function(response) {
                    if (response.status === 200) {
                        var clone = response.clone();
                        caches.open(CACHE_NAME).then(function(cache) {
                            cache.put(event.request, clone);
                        });
                    }
                    return response;
                });
            })
        );
        return;
    }

    // Map tiles (OSM + Esri satellite): cache-first, network fallback
    if (url.hostname.match(/tile\.openstreetmap\.org|server\.arcgisonline\.com/)) {
        event.respondWith(
            caches.open(TILE_CACHE).then(function(cache) {
                return cache.match(event.request).then(function(cached) {
                    if (cached) return cached;
                    return fetch(event.request).then(function(response) {
                        if (response.status === 200) {
                            cache.put(event.request, response.clone());
                            cache.keys().then(function(keys) {
                                if (keys.length > MAX_TILE_CACHE) {
                                    cache.delete(keys[0]);
                                }
                            });
                        }
                        return response;
                    });
                });
            })
        );
        return;
    }

    // GeoJSON API (/api/geo/*): cache-first com revalidacao em background
    if (url.pathname.match(/\/api\/geo\//)) {
        event.respondWith(
            caches.open(API_CACHE).then(function(cache) {
                return cache.match(event.request).then(function(cached) {
                    var fetchPromise = fetch(event.request).then(function(response) {
                        if (response.status === 200) {
                            cache.put(event.request, response.clone());
                        }
                        return response;
                    }).catch(function() {
                        return cached;
                    });
                    return cached || fetchPromise;
                });
            })
        );
        return;
    }

    // Tudo mais: network-first
    event.respondWith(
        fetch(event.request).catch(function() {
            return caches.match(event.request);
        })
    );
});

self.addEventListener('sync', function(event) {
    if (event.tag === 'upload-fotos') {
        event.waitUntil(
            syncPendingPhotos()
        );
    }
});

// --- Sincronizacao de fotos pendentes (Background Sync) ---
// Espelha a logica de app.js (syncAllPendingPhotos): le o registro gravado por
// salvarFotoLocal/offline-upload no shape {data:ArrayBuffer, type, name}, ignora
// registros ainda nao reconciliados (vistoria_id 'temp_*') e autentica via cookie
// XSRF-TOKEN do Laravel (header X-XSRF-TOKEN). Cobre o cenario de app fechado em
// navegadores Chromium; iOS Safari nao tem Background Sync e usa o app.js.

function isTempRecord(foto) {
    var vid = foto.vistoria_id || foto.vistoriaId;
    return typeof vid === 'string' && vid.indexOf('temp_') === 0;
}

async function getXsrfToken() {
    try {
        if (self.cookieStore) {
            var c = await self.cookieStore.get('XSRF-TOKEN');
            if (c && c.value) return decodeURIComponent(c.value);
        }
    } catch (e) {}
    return null;
}

function idbOpen() {
    return new Promise(function(resolve, reject) {
        var req = indexedDB.open('poprua_fotos', 1);
        req.onsuccess = function(e) { resolve(e.target.result); };
        req.onerror = function() { reject(req.error); };
    });
}

function idbGetAll(db) {
    return new Promise(function(resolve) {
        if (!db.objectStoreNames.contains('pendentes')) return resolve([]);
        var tx = db.transaction('pendentes', 'readonly');
        var req = tx.objectStore('pendentes').getAll();
        req.onsuccess = function() { resolve(req.result || []); };
        req.onerror = function() { resolve([]); };
    });
}

function idbDelete(db, id) {
    return new Promise(function(resolve) {
        var tx = db.transaction('pendentes', 'readwrite');
        tx.objectStore('pendentes').delete(id);
        tx.oncomplete = function() { resolve(); };
        tx.onerror = function() { resolve(); };
    });
}

async function syncPendingPhotos() {
    var db;
    try { db = await idbOpen(); } catch (e) { return; }
    var fotos = (await idbGetAll(db)).filter(function(f) { return !isTempRecord(f); });
    if (fotos.length === 0) { db.close(); return; }

    var xsrf = await getXsrfToken();
    var endpoint = new URL('api/vistorias/fotos', self.registration.scope).toString();

    for (var i = 0; i < fotos.length; i++) {
        var foto = fotos[i];
        try {
            var blob = new Blob([foto.data], { type: foto.type || 'application/octet-stream' });
            var form = new FormData();
            form.append('vistoria_id', foto.vistoria_id);
            form.append('foto', blob, foto.name || 'foto.webp');
            var headers = {};
            if (xsrf) headers['X-XSRF-TOKEN'] = xsrf;
            var resp = await fetch(endpoint, {
                method: 'POST',
                body: form,
                headers: headers,
                credentials: 'same-origin'
            });
            if (resp.ok) await idbDelete(db, foto.id);
        } catch (e) { /* mantem na fila p/ nova tentativa */ }
    }
    db.close();
}
