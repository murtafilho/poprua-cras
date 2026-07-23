import { DESTINO, cabecalhos, classificarResposta, endpoint } from '../offline/politica.js';

const CACHE_VERSION = 41;
const CACHE_NAME = 'poprua-v' + CACHE_VERSION;
const TILE_CACHE = 'poprua-tiles-v1';
const API_CACHE = 'poprua-api-v1';
const MAX_TILE_CACHE = 2000;

// Shell offline: paginas que precisam abrir sem rede. Sem isto, a partida a
// frio do app de campo (WebView carregando /bem-vindo) cai na pagina de erro
// do Chromium e a fila offline fica inacessivel, mesmo com os dados salvos
// no IndexedDB. Relativas ao scope (em producao o app roda em subdiretorio).
// 'vistorias/create' entra aqui (sem query) porque a troca de CACHE_VERSION
// apaga o cache da versao anterior: sem precache, quem atualiza o SW perde o
// formulario de criacao offline ate a proxima visita com rede.
var SHELL_PATHS = ['bem-vindo', 'vistorias', 'vistorias/create'];

/** URLs absolutas do shell, resolvidas contra o scope do SW. */
function shellUrls() {
    return SHELL_PATHS.map(function(path) {
        return new URL(path, self.registration.scope).toString();
    });
}

/** URL absoluta do shell principal (fallback de ultima instancia). */
function shellFallbackUrl() {
    return shellUrls()[0];
}

/** A URL navegada e uma das paginas do shell? */
function isShellUrl(url) {
    return shellUrls().indexOf(url.origin + url.pathname) !== -1;
}

/** Guarda o documento no cache do shell, sem query e sem respostas redirecionadas. */
function cacheShellDocument(url, response) {
    // Resposta redirecionada (ex.: sessao expirada -> /login) nao serve de shell
    // e ainda quebra o replay em navegacao ("redirected flag set").
    if (!response.ok || response.redirected) return Promise.resolve();
    var clone = response.clone();
    return caches.open(CACHE_NAME).then(function(cache) {
        return cache.put(url.origin + url.pathname, clone);
    }).catch(function() {});
}

/**
 * Baixa o shell que ainda nao esta em cache.
 *
 * Roda na instalacao e de novo na ativacao porque a instalacao pode acontecer
 * com o agente deslogado — ai as paginas autenticadas respondem redirect para
 * /login, sao (corretamente) recusadas pelo cache, e sem uma segunda tentativa
 * ficariam de fora ate alguem visitar cada uma delas.
 */
function precacheShell() {
    return caches.open(CACHE_NAME).then(function(cache) {
        return Promise.all(SHELL_PATHS.map(function(path) {
            var url = new URL(path, self.registration.scope);
            return cache.match(url.origin + url.pathname).then(function(jaTem) {
                if (jaTem) {
                    return null;
                }

                return fetch(url.toString(), { credentials: 'same-origin' })
                    .then(function(response) {
                        return cacheShellDocument(url, response);
                    })
                    .catch(function() { /* sem rede: fica para a proxima navegacao */ });
            });
        }));
    }).catch(function() { /* cache indisponivel: nao impede a instalacao */ });
}

self.addEventListener('install', function(event) {
    self.skipWaiting();
    event.waitUntil(precacheShell());
});

/** O shell da versao nova ja esta gravado? */
function shellDisponivel() {
    return caches.open(CACHE_NAME)
        .then(function(cache) { return cache.match(shellFallbackUrl()); })
        .then(Boolean)
        .catch(function() { return false; });
}

function limparCachesAntigos() {
    return caches.keys().then(function(names) {
        var keepCaches = [CACHE_NAME, TILE_CACHE, API_CACHE];
        return Promise.all(
            names.filter(function(name) {
                return keepCaches.indexOf(name) === -1;
            }).map(function(name) {
                return caches.delete(name);
            })
        );
    });
}

self.addEventListener('activate', function(event) {
    event.waitUntil(
        // So apaga o cache da versao anterior depois de confirmar que o shell
        // novo esta gravado. Se a rede falhar no meio da atualizacao, o agente
        // fica com o cache antigo — desatualizado, mas funcional — em vez de
        // ficar sem nada. A limpeza acontece na proxima ativacao com rede.
        // precacheShell so busca o que falta, entao rodar de novo aqui e barato
        // e recupera as paginas autenticadas que a instalacao nao conseguiu.
        precacheShell()
            .then(shellDisponivel)
            .then(function(ok) { return ok ? limparCachesAntigos() : null; })
            .then(function() { return clients.claim(); })
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

    // Create de vistoria: network-first; offline cai na URL exata ou no shell sem query
    if (url.pathname.match(/\/vistorias\/create\/?$/)) {
        event.respondWith(
            fetch(event.request).then(function(response) {
                if (response.status === 200) {
                    var clone = response.clone();
                    caches.open(CACHE_NAME).then(function(cache) {
                        cache.put(event.request, clone);
                        // Também guarda shell sem query (lat/lng aplicados no JS).
                        var shellReq = new Request(url.origin + url.pathname, {
                            credentials: event.request.credentials,
                            headers: event.request.headers,
                        });
                        cache.put(shellReq, response.clone()).catch(function() {});
                    });
                }
                return response;
            }).catch(function() {
                return caches.match(event.request).then(function(exact) {
                    if (exact) return exact;
                    return caches.match(url.origin + url.pathname);
                });
            })
        );
        return;
    }

    // Navegacao (documento HTML) na propria origem: network-first, mantendo o
    // shell atualizado com o HTML ja autenticado. Offline, tenta a URL exata,
    // depois a mesma rota sem query e, por fim, o shell — em vez de devolver
    // undefined ao respondWith, que vira a pagina de erro do Chromium.
    if (event.request.mode === 'navigate' && url.origin === self.location.origin) {
        event.respondWith(
            fetch(event.request).then(function(response) {
                if (isShellUrl(url)) {
                    cacheShellDocument(url, response);
                }
                // Completa o shell que faltou. A instalacao costuma acontecer
                // na tela de login, quando as paginas autenticadas respondem
                // redirect e nao podem ser gravadas; aqui ja ha sessao. Busca
                // so o que falta, entao na maioria das navegacoes nao faz nada.
                event.waitUntil(precacheShell());
                return response;
            }).catch(function() {
                return caches.match(event.request).then(function(exata) {
                    if (exata) return exata;
                    return caches.match(url.origin + url.pathname);
                }).then(function(cached) {
                    return cached || caches.match(shellFallbackUrl());
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

/**
 * Base da aplicacao vista do Service Worker. O scope ja aponta para o
 * subdiretorio onde o SIZEM roda em producao (/ginfi/poprua-cras/public/),
 * entao ele e a fonte correta — nao ha DOM aqui para ler a meta app-base.
 */
function baseDaAplicacao() {
    return self.registration.scope.replace(/\/+$/, '');
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
    var url = endpoint(baseDaAplicacao(), 'foto');

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
            var resp = await fetch(url, {
                method: 'POST',
                body: form,
                headers: cabecalhos({ xsrf: xsrf }),
                credentials: 'same-origin'
            });
            if (classificarResposta(resp, 'foto') === DESTINO.SUCESSO) {
                await idbDelete(db, foto.id);
            }
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
    var url = endpoint(baseDaAplicacao(), 'vistoria');
    var reconciliouAlguma = false;

    for (var i = 0; i < pendentes.length; i++) {
        var rec = pendentes[i];
        try {
            var resp = await fetch(url, {
                method: 'POST',
                body: JSON.stringify(rec.payload),
                headers: cabecalhos({ xsrf: xsrf, json: true }),
                credentials: 'same-origin'
            });
            var destino = classificarResposta(resp, 'vistoria');
            if (destino === DESTINO.SUCESSO) {
                var data = await resp.json();
                await reconcilePhotosInSw(rec.temp_photo_id, data.id);
                await idbDeleteFrom(db, 'pendentes', rec.id);
                reconciliouAlguma = true;
            } else if (destino === DESTINO.PERMANENTE) {
                // Dado invalido/duplicidade/autorizacao: marca como 'failed' em
                // vez de deixar 'pending', para nao retentar para sempre.
                await idbUpdateIn(db, 'pendentes', rec.id, { status: 'failed' });
            }
            // Transiente (5xx, 401, 419): mantem 'pending' para nova tentativa.
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
            var url = endpoint(baseDaAplicacao(), 'acao', { vistoriaId: a.vistoria_id, acao: a.acao });
            var resp = await fetch(url, {
                method: 'POST',
                headers: cabecalhos({ xsrf: xsrf }),
                credentials: 'same-origin'
            });
            var destino = classificarResposta(resp, 'acao');
            if (destino === DESTINO.SUCESSO) {
                await idbDeleteFrom(db, 'pendentes', a.id);
            } else if (destino === DESTINO.PERMANENTE) {
                await idbUpdateIn(db, 'pendentes', a.id, { status: 'failed' });
            }
            // Transiente (5xx, 401, 419): mantem 'pending' para nova tentativa.
        } catch (e) { /* falha de rede: mantem na fila p/ nova tentativa */ }
    }
    db.close();
}
