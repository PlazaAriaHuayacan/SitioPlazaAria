/**
 * Cenacolo Staff — Service Worker
 * Scope: /CenacoloReserve/  (covers dashboard.php + floorplan.php + API calls)
 *
 * Strategies:
 *  - Staff PHP pages          → stale-while-revalidate
 *  - GET  /api/*              → network-first + IDB snapshot fallback
 *  - PUT/POST /api/reservations.php → network-or-queue (offline write support)
 *  - External CDN assets      → cache-first
 *  - Everything else          → network pass-through
 */
'use strict';

var SW_VERSION   = '1.0.0';
var STATIC_CACHE = 'cenacolo-staff-static-v1';
var API_CACHE    = 'cenacolo-staff-api-v1';

var STAFF_PAGES = [
  '/CenacoloReserve/dashboard.php',
  '/CenacoloReserve/floorplan.php'
];

var API_PREFIX  = '/CenacoloReserve/api';
var DB_NAME     = 'cenacolo-offline-staff';
var DB_VERSION  = 1;

// ── Install ────────────────────────────────────────────────────────────────

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then(function (cache) {
        // Cache staff pages (best-effort — don't block install if one fails)
        return Promise.allSettled(
          STAFF_PAGES.map(function (url) { return cache.add(url); })
        );
      })
      .then(function () { return self.skipWaiting(); })
  );
});

// ── Activate ───────────────────────────────────────────────────────────────

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys()
      .then(function (keys) {
        return Promise.all(
          keys.filter(function (k) { return k !== STATIC_CACHE && k !== API_CACHE; })
              .map(function (k) { return caches.delete(k); })
        );
      })
      .then(function () { return self.clients.claim(); })
  );
});

// ── Fetch ──────────────────────────────────────────────────────────────────

self.addEventListener('fetch', function (event) {
  var req  = event.request;
  var url  = new URL(req.url);
  var path = url.pathname;

  // ① PUT / POST to /api/reservations.php → network-or-queue (offline writes)
  if ((req.method === 'PUT' || req.method === 'POST') &&
      path.indexOf(API_PREFIX) === 0 &&
      path.indexOf('reservations') !== -1) {
    event.respondWith(networkOrQueue(req.clone()));
    return;
  }

  // ② GET /api/* → network-first with IDB fallback
  if (req.method === 'GET' && path.indexOf(API_PREFIX) === 0) {
    event.respondWith(networkFirstWithCacheFallback(req));
    return;
  }

  // ③ Staff PHP pages → stale-while-revalidate
  var isStaffPage = STAFF_PAGES.some(function (p) { return path === p; });
  if (isStaffPage) {
    event.respondWith(staleWhileRevalidate(req));
    return;
  }

  // ④ External CDN (tailwind, fonts, flatpickr, apexcharts) → cache-first
  if (url.origin !== location.origin) {
    event.respondWith(cacheFirst(req));
    return;
  }

  // ⑤ Everything else → network
  event.respondWith(fetch(req));
});

// ── Strategies ─────────────────────────────────────────────────────────────

/**
 * Network-or-queue: try network; if offline, add to outbox and return
 * a synthetic {queued: true, success: true} JSON response.
 */
function networkOrQueue(req) {
  // Clone body before reading (can only read once)
  var bodyPromise = req.text().then(function (text) {
    try { return JSON.parse(text); } catch (e) { return {}; }
  });

  return bodyPromise.then(function (payload) {
    return fetch(new Request(req.url, {
      method  : req.method,
      headers : req.headers,
      body    : JSON.stringify(payload)
    })).then(function (resp) {
      // Server returned 409 Conflict → store in conflicts
      if (resp.status === 409) {
        return resp.json().then(function (data) {
          return swOpenDB().then(function (db) {
            var entry = {
              id           : swUuid(),
              url          : req.url,
              method       : req.method,
              payload      : payload,
              action       : inferAction(req.url, payload),
              server_data  : data.server_data || {},
              conflicted_at: new Date().toISOString(),
              timestamp    : Date.now()
            };
            return swIdbPut(db, 'conflicts', entry);
          }).then(function () {
            return new Response(JSON.stringify({ conflict: true, error: 'Conflicto detectado — revisa los conflictos.' }), {
              status : 409,
              headers: { 'Content-Type': 'application/json' }
            });
          });
        });
      }
      return resp;
    }).catch(function () {
      // Network error → queue
      return swOpenDB().then(function (db) {
        var entry = {
          id        : swUuid(),
          url       : req.url,
          method    : req.method,
          payload   : payload,
          action    : inferAction(req.url, payload),
          status    : 'pending',
          timestamp : Date.now()
        };
        return swIdbPut(db, 'outbox', entry).then(function () {
          return new Response(JSON.stringify({ queued: true, success: true, message: 'Guardado offline — se sincronizará al reconectar' }), {
            status : 200,
            headers: { 'Content-Type': 'application/json' }
          });
        });
      }).catch(function () {
        return new Response(JSON.stringify({ error: 'Sin conexión y sin almacenamiento offline disponible' }), {
          status : 503,
          headers: { 'Content-Type': 'application/json' }
        });
      });
    });
  });
}

/**
 * Network-first with IDB snapshot fallback for GET /api/* requests.
 */
function networkFirstWithCacheFallback(req) {
  return fetch(req.clone()).then(function (resp) {
    if (resp.ok) {
      // Save to API cache
      caches.open(API_CACHE).then(function (cache) { cache.put(req, resp.clone()); });
    }
    return resp;
  }).catch(function () {
    // Try cache
    return caches.match(req).then(function (cached) {
      if (cached) return cached;
      return new Response(JSON.stringify({ error: 'Sin conexión', offline: true }), {
        status : 503,
        headers: { 'Content-Type': 'application/json' }
      });
    });
  });
}

/**
 * Stale-while-revalidate for PHP pages.
 */
function staleWhileRevalidate(req) {
  var cache = caches.open(STATIC_CACHE);
  return cache.then(function (c) {
    return c.match(req).then(function (cached) {
      var networkFetch = fetch(req.clone()).then(function (resp) {
        if (resp.ok) c.put(req, resp.clone());
        return resp;
      }).catch(function () { return cached; });

      return cached || networkFetch;
    });
  });
}

/**
 * Cache-first for external CDN assets (fonts, Tailwind, charts).
 */
function cacheFirst(req) {
  return caches.match(req).then(function (cached) {
    if (cached) return cached;
    return fetch(req.clone()).then(function (resp) {
      if (resp.ok) {
        caches.open(STATIC_CACHE).then(function (c) { c.put(req, resp.clone()); });
      }
      return resp;
    }).catch(function () {
      return new Response('', { status: 503 });
    });
  });
}

// ── Background Sync ────────────────────────────────────────────────────────

self.addEventListener('sync', function (event) {
  if (event.tag === 'cenacolo-staff-outbox') {
    event.waitUntil(flushOutbox());
  }
});

self.addEventListener('message', function (event) {
  if (event.data && event.data.type === 'FLUSH_OUTBOX') {
    flushOutbox().catch(function (err) {
      console.warn('[Cenacolo Staff SW] FLUSH_OUTBOX error:', err);
    });
  }
});

/**
 * FIFO outbox flush: process each pending entry in order.
 */
function flushOutbox() {
  return swOpenDB().then(function (db) {
    return swIdbGetAll(db, 'outbox').then(function (entries) {
      var pending = entries
        .filter(function (e) { return e.status === 'pending'; })
        .sort(function (a, b) { return a.timestamp - b.timestamp; });

      if (pending.length === 0) return { synced: 0, conflicts: 0 };

      var synced    = 0;
      var conflicts = 0;

      // Process FIFO using reduce chain
      return pending.reduce(function (chain, entry) {
        return chain.then(function () {
          // Mark as syncing
          entry.status = 'syncing';
          return swIdbPut(db, 'outbox', entry).then(function () {
            return fetch(entry.url, {
              method  : entry.method,
              headers : { 'Content-Type': 'application/json' },
              body    : JSON.stringify(entry.payload)
            }).then(function (resp) {
              if (resp.status === 409) {
                return resp.json().then(function (data) {
                  conflicts++;
                  var conflict = {
                    id           : swUuid(),
                    url          : entry.url,
                    method       : entry.method,
                    payload      : entry.payload,
                    action       : entry.action,
                    server_data  : data.server_data || {},
                    conflicted_at: new Date().toISOString(),
                    timestamp    : Date.now()
                  };
                  return swIdbPut(db, 'conflicts', conflict)
                    .then(function () { return swIdbDelete(db, 'outbox', entry.id); });
                });
              } else if (resp.ok) {
                synced++;
                return swIdbDelete(db, 'outbox', entry.id);
              } else {
                // Server error — keep as pending for next attempt
                entry.status = 'pending';
                return swIdbPut(db, 'outbox', entry);
              }
            }).catch(function () {
              // Network error — keep pending
              entry.status = 'pending';
              return swIdbPut(db, 'outbox', entry);
            });
          });
        });
      }, Promise.resolve()).then(function () {
        return { synced: synced, conflicts: conflicts };
      });
    });
  }).then(function (result) {
    // Notify all clients
    return self.clients.matchAll().then(function (clients) {
      clients.forEach(function (client) {
        client.postMessage({
          type     : 'SYNC_COMPLETE',
          synced   : result.synced,
          conflicts: result.conflicts
        });
      });
    });
  }).catch(function (err) {
    console.warn('[Cenacolo Staff SW] flushOutbox error:', err);
  });
}

// ── Helpers ────────────────────────────────────────────────────────────────

function inferAction(url, payload) {
  if (url.indexOf('reservations') !== -1) {
    var method = (payload && payload.id) ? 'update' : 'create';
    if (method === 'create') return 'create_reservation';
    if (payload.status) return 'update_reservation_status';
    if (payload.table_id !== undefined) return 'assign_table';
    return 'update_reservation';
  }
  if (url.indexOf('tables') !== -1) return 'update_table_status';
  return 'unknown';
}

// ── SW-side IDB helpers (cannot share window.CenacoloStaffIDB) ─────────────

var _swDb = null;

function swOpenDB() {
  if (_swDb) return Promise.resolve(_swDb);
  return new Promise(function (resolve, reject) {
    var req = indexedDB.open(DB_NAME, DB_VERSION);
    req.onupgradeneeded = function (event) {
      var db = event.target.result;
      if (!db.objectStoreNames.contains('snapshot')) {
        db.createObjectStore('snapshot', { keyPath: ['type', 'id'] });
      }
      if (!db.objectStoreNames.contains('outbox')) {
        var ob = db.createObjectStore('outbox', { keyPath: 'id' });
        ob.createIndex('by_status',    'status',    { unique: false });
        ob.createIndex('by_timestamp', 'timestamp', { unique: false });
      }
      if (!db.objectStoreNames.contains('conflicts')) {
        db.createObjectStore('conflicts', { keyPath: 'id' });
      }
    };
    req.onsuccess = function (e) { _swDb = e.target.result; resolve(_swDb); };
    req.onerror   = function (e) { reject(e.target.error); };
  });
}

function swIdbGetAll(db, storeName) {
  return new Promise(function (resolve, reject) {
    var tx  = db.transaction(storeName, 'readonly');
    var req = tx.objectStore(storeName).getAll();
    req.onsuccess = function () { resolve(req.result || []); };
    req.onerror   = function () { reject(req.error); };
  });
}

function swIdbPut(db, storeName, value) {
  return new Promise(function (resolve, reject) {
    var tx  = db.transaction(storeName, 'readwrite');
    var req = tx.objectStore(storeName).put(value);
    req.onsuccess = function () { resolve(req.result); };
    req.onerror   = function () { reject(req.error); };
  });
}

function swIdbDelete(db, storeName, key) {
  return new Promise(function (resolve, reject) {
    var tx  = db.transaction(storeName, 'readwrite');
    var req = tx.objectStore(storeName).delete(key);
    req.onsuccess = function () { resolve(); };
    req.onerror   = function () { reject(req.error); };
  });
}

function swUuid() {
  if (typeof crypto !== 'undefined' && crypto.randomUUID) {
    return crypto.randomUUID();
  }
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
    var r = Math.random() * 16 | 0;
    var v = c === 'x' ? r : (r & 0x3 | 0x8);
    return v.toString(16);
  });
}
