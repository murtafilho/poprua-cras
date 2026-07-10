const CACHE_VERSION = 37;
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
    if (event.tag === 'sync-vistorias') {
        event.waitUntil(syncPendingVistorias());
    }
    if (event.tag === 'sync-acoes-vistoria') {
        event.waitUntil(syncPendingAcoes());
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

function blobFromFotoRecord(foto) {
    if (foto.data) {
        return new Blob([foto.data], { type: foto.type || 'application/octet-stream' });
    }
    if (foto.blob) {
        return foto.blob;
    }
    return null;
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
            var blob = blobFromFotoRecord(foto);
            if (!blob) continue;
            var form = new FormData();
            form.append('vistoria_id', foto.vistoria_id || foto.vistoriaId);
            form.append('foto', blob, foto.name || foto.filename || 'foto.webp');
            if (foto.legenda) {
                form.append('legenda', foto.legenda);
            }
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

// Atualiza (merge) um registro existente de uma store, mantendo o mesmo id.
// Usado para marcar status='failed' sem apagar o registro (dead-letter).
function idbUpdateIn(db, store, id, changes) {
    return new Promise(function(resolve) {
        var tx = db.transaction(store, 'readwrite');
        var os = tx.objectStore(store);
        var req = os.get(id);
        req.onsuccess = function() {
            var record = req.result;
            if (record) {
                os.put(Object.assign({}, record, changes));
            }
        };
        req.onerror = function() { resolve(); };
        tx.oncomplete = function() { resolve(); };
        tx.onerror = function() { resolve(); };
    });
}

// Status HTTP que indicam rejeicao PERMANENTE pelo servidor — reenviar nao
// vai adiantar (espelha PERMANENT_REJECTION_STATUSES em offline-vistoria.js).
var PERMANENT_REJECTION_STATUSES = [422, 409, 403];

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
    var todos = await idbGetAllStore(db, 'pendentes');
    var pendentes = todos.filter(function(r) { return r.status !== 'failed'; });
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
            } else if (PERMANENT_REJECTION_STATUSES.indexOf(resp.status) !== -1) {
                // Rejeicao permanente do servidor (dado invalido/duplicidade/
                // autorizacao): marca como 'failed' em vez de deixar 'pending'
                // para nao ficar retentando para sempre (dead-letter).
                await idbUpdateIn(db, 'pendentes', rec.id, { status: 'failed' });
            }
            // 5xx/outros: mantem 'pending' na fila para nova tentativa.
        } catch (e) { /* falha de rede: mantem na fila p/ nova tentativa */ }
    }
    db.close();

    // Fotos agora reconciliadas podem subir.
    if (reconciliouAlguma) {
        await syncPendingPhotos();
    }
}

// --- Sincronizacao de acoes de estado (finalizar/cancelar/reativar) ---
// Le a outbox poprua_vistoria_acoes (Task 3), faz POST idempotente para
// /api/vistorias/{id}/{acao} autenticado via cookie XSRF. Como os endpoints
// de acao sao idempotentes (Task 2), reenviar uma acao ja aplicada retorna
// 200 — por isso o dead-letter aqui e so [403, 404, 422] (sem 409).

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
                await idbUpdateIn(db, 'pendentes', a.id, { status: 'failed' });
            }
            // 5xx/outros: mantem 'pending' na fila para nova tentativa.
        } catch (e) { /* falha de rede: mantem na fila p/ nova tentativa */ }
    }
    db.close();
}
