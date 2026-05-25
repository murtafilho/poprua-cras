const CACHE_VERSION = 9;
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

function syncPendingPhotos() {
    return new Promise(function(resolve, reject) {
        var request = indexedDB.open('poprua_fotos', 1);
        request.onerror = function() { resolve(); };
        request.onsuccess = function(e) {
            var db = e.target.result;
            if (!db.objectStoreNames.contains('pendentes')) {
                db.close();
                return resolve();
            }
            var tx = db.transaction('pendentes', 'readonly');
            var store = tx.objectStore('pendentes');
            var getAll = store.getAll();
            getAll.onsuccess = function() {
                var items = getAll.result || [];
                db.close();
                if (items.length === 0) return resolve();

                var uploads = items.map(function(item) {
                    var formData = new FormData();
                    formData.append('vistoria_id', item.vistoria_id);
                    formData.append('foto', item.blob, item.filename || 'foto.jpg');
                    return fetch('/api/vistorias/fotos', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    }).then(function(response) {
                        if (response.ok) {
                            return removeFromStore(item.id);
                        }
                    }).catch(function() {});
                });
                Promise.all(uploads).then(resolve).catch(resolve);
            };
            getAll.onerror = function() { resolve(); };
        };
    });
}

function removeFromStore(id) {
    return new Promise(function(resolve) {
        var request = indexedDB.open('poprua_fotos', 1);
        request.onsuccess = function(e) {
            var db = e.target.result;
            var tx = db.transaction('pendentes', 'readwrite');
            tx.objectStore('pendentes').delete(id);
            tx.oncomplete = function() { db.close(); resolve(); };
            tx.onerror = function() { db.close(); resolve(); };
        };
        request.onerror = function() { resolve(); };
    });
}
