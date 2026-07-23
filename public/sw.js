// ARQUIVO GERADO — não edite.
// Fonte: resources/js/sw/index.js (política compartilhada em resources/js/offline/politica.js).
// Regenerar: npm run build:sw

(() => {
  // resources/js/offline/politica.js
  var REJEICAO_PERMANENTE = {
    vistoria: [422, 409, 403],
    // Ações são idempotentes (reenviar uma já aplicada devolve 200), então 409
    // não é permanente aqui; 404 é, porque a vistoria não existe mais.
    acao: [403, 404, 422],
    // Fotos ainda não têm dead-letter: não há status 'failed' no banco de fotos
    // nem lugar na interface para mostrá-lo. Enquanto não houver, retentar é
    // preferível a descartar — o custo de uma foto perdida é alto.
    foto: []
  };
  var DESTINO = {
    SUCESSO: "sucesso",
    PERMANENTE: "permanente",
    TRANSIENTE: "transiente"
  };
  function classificarResposta(resp, fluxo) {
    if (resp.redirected) {
      return DESTINO.TRANSIENTE;
    }
    if (resp.ok) {
      return DESTINO.SUCESSO;
    }
    if ((REJEICAO_PERMANENTE[fluxo] ?? []).includes(resp.status)) {
      return DESTINO.PERMANENTE;
    }
    return DESTINO.TRANSIENTE;
  }
  function cabecalhos({ csrf = null, xsrf = null, json = false } = {}) {
    const headers = { Accept: "application/json" };
    if (json) {
      headers["Content-Type"] = "application/json";
    }
    if (csrf) {
      headers["X-CSRF-TOKEN"] = csrf;
    }
    if (xsrf) {
      headers["X-XSRF-TOKEN"] = xsrf;
    }
    return headers;
  }
  function endpoint(base, fluxo, params = {}) {
    const raiz = String(base ?? "").replace(/\/+$/, "");
    switch (fluxo) {
      case "vistoria":
        return `${raiz}/api/vistorias`;
      case "foto":
        return `${raiz}/api/vistorias/fotos`;
      case "acao":
        return `${raiz}/api/vistorias/${params.vistoriaId}/${params.acao}`;
      default:
        throw new Error(`fluxo desconhecido: ${fluxo}`);
    }
  }

  // resources/js/sw/index.js
  var CACHE_VERSION = 41;
  var CACHE_NAME = "poprua-v" + CACHE_VERSION;
  var TILE_CACHE = "poprua-tiles-v1";
  var API_CACHE = "poprua-api-v1";
  var MAX_TILE_CACHE = 2e3;
  var SHELL_PATHS = ["bem-vindo", "vistorias", "vistorias/create"];
  function shellUrls() {
    return SHELL_PATHS.map(function(path) {
      return new URL(path, self.registration.scope).toString();
    });
  }
  function shellFallbackUrl() {
    return shellUrls()[0];
  }
  function isShellUrl(url) {
    return shellUrls().indexOf(url.origin + url.pathname) !== -1;
  }
  function cacheShellDocument(url, response) {
    if (!response.ok || response.redirected) return Promise.resolve();
    var clone = response.clone();
    return caches.open(CACHE_NAME).then(function(cache) {
      return cache.put(url.origin + url.pathname, clone);
    }).catch(function() {
    });
  }
  function precacheShell() {
    return caches.open(CACHE_NAME).then(function(cache) {
      return Promise.all(SHELL_PATHS.map(function(path) {
        var url = new URL(path, self.registration.scope);
        return cache.match(url.origin + url.pathname).then(function(jaTem) {
          if (jaTem) {
            return null;
          }
          return fetch(url.toString(), { credentials: "same-origin" }).then(function(response) {
            return cacheShellDocument(url, response);
          }).catch(function() {
          });
        });
      }));
    }).catch(function() {
    });
  }
  self.addEventListener("install", function(event) {
    self.skipWaiting();
    event.waitUntil(precacheShell());
  });
  function shellDisponivel() {
    return caches.open(CACHE_NAME).then(function(cache) {
      return cache.match(shellFallbackUrl());
    }).then(Boolean).catch(function() {
      return false;
    });
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
  self.addEventListener("activate", function(event) {
    event.waitUntil(
      // So apaga o cache da versao anterior depois de confirmar que o shell
      // novo esta gravado. Se a rede falhar no meio da atualizacao, o agente
      // fica com o cache antigo — desatualizado, mas funcional — em vez de
      // ficar sem nada. A limpeza acontece na proxima ativacao com rede.
      // precacheShell so busca o que falta, entao rodar de novo aqui e barato
      // e recupera as paginas autenticadas que a instalacao nao conseguiu.
      precacheShell().then(shellDisponivel).then(function(ok) {
        return ok ? limparCachesAntigos() : null;
      }).then(function() {
        return clients.claim();
      })
    );
  });
  self.addEventListener("fetch", function(event) {
    var url = new URL(event.request.url);
    if (event.request.method !== "GET") return;
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
    if (url.pathname.match(/\/vistorias\/create\/?$/)) {
      event.respondWith(
        fetch(event.request).then(function(response) {
          if (response.status === 200) {
            var clone = response.clone();
            caches.open(CACHE_NAME).then(function(cache) {
              cache.put(event.request, clone);
              var shellReq = new Request(url.origin + url.pathname, {
                credentials: event.request.credentials,
                headers: event.request.headers
              });
              cache.put(shellReq, response.clone()).catch(function() {
              });
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
    if (event.request.mode === "navigate" && url.origin === self.location.origin) {
      event.respondWith(
        fetch(event.request).then(function(response) {
          if (isShellUrl(url)) {
            cacheShellDocument(url, response);
          }
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
    event.respondWith(
      fetch(event.request).catch(function() {
        return caches.match(event.request);
      })
    );
  });
  self.addEventListener("sync", function(event) {
    if (event.tag === "upload-fotos") {
      event.waitUntil(
        syncPendingPhotos()
      );
    }
    if (event.tag === "sync-vistorias") {
      event.waitUntil(syncPendingVistorias());
    }
    if (event.tag === "sync-acoes-vistoria") {
      event.waitUntil(syncPendingAcoes());
    }
  });
  function isTempRecord(foto) {
    var vid = foto.vistoria_id || foto.vistoriaId;
    return typeof vid === "string" && vid.indexOf("temp_") === 0;
  }
  function blobFromFotoRecord(foto) {
    if (foto.data) {
      return new Blob([foto.data], { type: foto.type || "application/octet-stream" });
    }
    if (foto.blob) {
      return foto.blob;
    }
    return null;
  }
  function baseDaAplicacao() {
    return self.registration.scope.replace(/\/+$/, "");
  }
  async function getXsrfToken() {
    try {
      if (self.cookieStore) {
        var c = await self.cookieStore.get("XSRF-TOKEN");
        if (c && c.value) return decodeURIComponent(c.value);
      }
    } catch (e) {
    }
    return null;
  }
  function idbOpen() {
    return new Promise(function(resolve, reject) {
      var req = indexedDB.open("poprua_fotos", 1);
      req.onsuccess = function(e) {
        resolve(e.target.result);
      };
      req.onerror = function() {
        reject(req.error);
      };
    });
  }
  function idbGetAll(db) {
    return new Promise(function(resolve) {
      if (!db.objectStoreNames.contains("pendentes")) return resolve([]);
      var tx = db.transaction("pendentes", "readonly");
      var req = tx.objectStore("pendentes").getAll();
      req.onsuccess = function() {
        resolve(req.result || []);
      };
      req.onerror = function() {
        resolve([]);
      };
    });
  }
  function idbDelete(db, id) {
    return new Promise(function(resolve) {
      var tx = db.transaction("pendentes", "readwrite");
      tx.objectStore("pendentes").delete(id);
      tx.oncomplete = function() {
        resolve();
      };
      tx.onerror = function() {
        resolve();
      };
    });
  }
  async function syncPendingPhotos() {
    var db;
    try {
      db = await idbOpen();
    } catch (e) {
      return;
    }
    var fotos = (await idbGetAll(db)).filter(function(f) {
      return !isTempRecord(f);
    });
    if (fotos.length === 0) {
      db.close();
      return;
    }
    var xsrf = await getXsrfToken();
    var url = endpoint(baseDaAplicacao(), "foto");
    for (var i = 0; i < fotos.length; i++) {
      var foto = fotos[i];
      try {
        var blob = blobFromFotoRecord(foto);
        if (!blob) continue;
        var form = new FormData();
        form.append("vistoria_id", foto.vistoria_id || foto.vistoriaId);
        form.append("foto", blob, foto.name || foto.filename || "foto.webp");
        if (foto.legenda) {
          form.append("legenda", foto.legenda);
        }
        var resp = await fetch(url, {
          method: "POST",
          body: form,
          headers: cabecalhos({ xsrf }),
          credentials: "same-origin"
        });
        if (classificarResposta(resp, "foto") === DESTINO.SUCESSO) {
          await idbDelete(db, foto.id);
        }
      } catch (e) {
      }
    }
    db.close();
  }
  function vistoriasDbOpen() {
    return new Promise(function(resolve, reject) {
      var req = indexedDB.open("poprua_vistorias", 1);
      req.onsuccess = function(e) {
        resolve(e.target.result);
      };
      req.onerror = function() {
        reject(req.error);
      };
    });
  }
  function idbGetAllStore(db, store) {
    return new Promise(function(resolve) {
      if (!db.objectStoreNames.contains(store)) return resolve([]);
      var tx = db.transaction(store, "readonly");
      var req = tx.objectStore(store).getAll();
      req.onsuccess = function() {
        resolve(req.result || []);
      };
      req.onerror = function() {
        resolve([]);
      };
    });
  }
  function idbDeleteFrom(db, store, id) {
    return new Promise(function(resolve) {
      var tx = db.transaction(store, "readwrite");
      tx.objectStore(store).delete(id);
      tx.oncomplete = function() {
        resolve();
      };
      tx.onerror = function() {
        resolve();
      };
    });
  }
  function idbUpdateIn(db, store, id, changes) {
    return new Promise(function(resolve) {
      var tx = db.transaction(store, "readwrite");
      var os = tx.objectStore(store);
      var req = os.get(id);
      req.onsuccess = function() {
        var record = req.result;
        if (record) {
          os.put(Object.assign({}, record, changes));
        }
      };
      req.onerror = function() {
        resolve();
      };
      tx.oncomplete = function() {
        resolve();
      };
      tx.onerror = function() {
        resolve();
      };
    });
  }
  async function reconcilePhotosInSw(tempId, realId) {
    if (!tempId) return;
    var db;
    try {
      db = await idbOpen();
    } catch (e) {
      return;
    }
    var fotos = (await idbGetAll(db)).filter(function(f) {
      return (f.vistoria_id || f.vistoriaId) === tempId;
    });
    await new Promise(function(resolve) {
      var tx = db.transaction("pendentes", "readwrite");
      var store = tx.objectStore("pendentes");
      fotos.forEach(function(f) {
        f.vistoria_id = realId;
        delete f.vistoriaId;
        store.put(f);
      });
      tx.oncomplete = function() {
        resolve();
      };
      tx.onerror = function() {
        resolve();
      };
    });
    db.close();
  }
  async function syncPendingVistorias() {
    var db;
    try {
      db = await vistoriasDbOpen();
    } catch (e) {
      return;
    }
    var todos = await idbGetAllStore(db, "pendentes");
    var pendentes = todos.filter(function(r) {
      return r.status !== "failed";
    });
    if (pendentes.length === 0) {
      db.close();
      return;
    }
    var xsrf = await getXsrfToken();
    var url = endpoint(baseDaAplicacao(), "vistoria");
    var reconciliouAlguma = false;
    for (var i = 0; i < pendentes.length; i++) {
      var rec = pendentes[i];
      try {
        var resp = await fetch(url, {
          method: "POST",
          body: JSON.stringify(rec.payload),
          headers: cabecalhos({ xsrf, json: true }),
          credentials: "same-origin"
        });
        var destino = classificarResposta(resp, "vistoria");
        if (destino === DESTINO.SUCESSO) {
          var data = await resp.json();
          await reconcilePhotosInSw(rec.temp_photo_id, data.id);
          await idbDeleteFrom(db, "pendentes", rec.id);
          reconciliouAlguma = true;
        } else if (destino === DESTINO.PERMANENTE) {
          await idbUpdateIn(db, "pendentes", rec.id, { status: "failed" });
        }
      } catch (e) {
      }
    }
    db.close();
    if (reconciliouAlguma) {
      await syncPendingPhotos();
    }
  }
  function acoesDbOpen() {
    return new Promise(function(resolve, reject) {
      var req = indexedDB.open("poprua_vistoria_acoes", 1);
      req.onsuccess = function(e) {
        resolve(e.target.result);
      };
      req.onerror = function() {
        reject(req.error);
      };
    });
  }
  async function syncPendingAcoes() {
    var db;
    try {
      db = await acoesDbOpen();
    } catch (e) {
      return;
    }
    var acoes = (await idbGetAllStore(db, "pendentes")).filter(function(a2) {
      return a2.status !== "failed";
    });
    if (acoes.length === 0) {
      db.close();
      return;
    }
    var xsrf = await getXsrfToken();
    for (var i = 0; i < acoes.length; i++) {
      var a = acoes[i];
      try {
        var url = endpoint(baseDaAplicacao(), "acao", { vistoriaId: a.vistoria_id, acao: a.acao });
        var resp = await fetch(url, {
          method: "POST",
          headers: cabecalhos({ xsrf }),
          credentials: "same-origin"
        });
        var destino = classificarResposta(resp, "acao");
        if (destino === DESTINO.SUCESSO) {
          await idbDeleteFrom(db, "pendentes", a.id);
        } else if (destino === DESTINO.PERMANENTE) {
          await idbUpdateIn(db, "pendentes", a.id, { status: "failed" });
        }
      } catch (e) {
      }
    }
    db.close();
  }
})();
