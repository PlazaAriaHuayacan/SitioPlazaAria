/**
 * CenacoloReserve Service Worker
 * Bump SW_VERSION on every deploy to trigger reinstall.
 */
const SW_VERSION    = '1.0.0';
const STATIC_CACHE  = 'cenacolo-static-' + SW_VERSION;
const API_CACHE     = 'cenacolo-api-'    + SW_VERSION;
const PORTAL_PREFIX = '/CenacoloReserve/portal';
const API_PREFIX    = '/CenacoloReserve/api';
const DB_NAME       = 'cenacolo-offline';
const DB_VERSION    = 1;

// ─── IndexedDB helpers (SW context — cannot use window.CenacoloIDB) ──────────

function swOpenDB() {
  return new Promise(function (resolve, reject) {
    var req = indexedDB.open(DB_NAME, DB_VERSION);
    req.onupgradeneeded = function (e) {
      var db = e.target.result;
      if (!db.objectStoreNames.contains('snapshot')) {
        db.createObjectStore('snapshot', { keyPath: ['type', 'id'] });
      }
      if (!db.objectStoreNames.contains('outbox')) {
        var os = db.createObjectStore('outbox', { keyPath: 'id' });
        os.createIndex('status',    'status',    { unique: false });
        os.createIndex('timestamp', 'timestamp', { unique: false });
      }
      if (!db.objectStoreNames.contains('conflicts')) {
        db.createObjectStore('conflicts', { keyPath: 'id' });
      }
    };
    req.onsuccess = function (e) { resolve(e.target.result); };
    req.onerror   = function (e) { reject(e.target.error); };
  });
}

function swIdbGetAll(storeName) {
  return swOpenDB().then(function (db) {
    return new Promise(function (resolve, reject) {
      var req = db.transaction(storeName, 'readonly').objectStore(storeName).getAll();
      req.onsuccess = function (e) { resolve(e.target.result); };
      req.onerror   = function (e) { reject(e.target.error); };
    });
  });
}

function swIdbPut(storeName, obj) {
  return swOpenDB().then(function (db) {
    return new Promise(function (resolve, reject) {
      var req = db.transaction(storeName, 'readwrite').objectStore(storeName).put(obj);
      req.onsuccess = function () { resolve(); };
      req.onerror   = function (e) { reject(e.target.error); };
    });
  });
}

function swIdbDelete(storeName, key) {
  return swOpenDB().then(function (db) {
    return new Promise(function (resolve, reject) {
      var req = db.transaction(storeName, 'readwrite').objectStore(storeName).delete(key);
      req.onsuccess = function () { resolve(); };
      req.onerror   = function (e) { reject(e.target.error); };
    });
  });
}

function swUuid() {
  if (self.crypto && self.crypto.randomUUID) {
    return self.crypto.randomUUID();
  }
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
    var r = Math.random() * 16 | 0;
    return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
  });
}

// ─── Pages to precache on install ────────────────────────────────────────────

var PRECACHE_URLS = [
  PORTAL_PREFIX + '/index.php',
  PORTAL_PREFIX + '/new-reservation.php',
  PORTAL_PREFIX + '/floorplan.php',
  PORTAL_PREFIX + '/commissions.php',
  PORTAL_PREFIX + '/js/idb.js',
  PORTAL_PREFIX + '/js/offline.js'
];

// ─── INSTALL ──────────────────────────────────────────────────────────────────

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(STATIC_CACHE).then(function (cache) {
      // allSettled so one missing file doesn't abort the install
      return Promise.allSettled(PRECACHE_URLS.map(function (url) {
        return cache.add(url);
      }));
    }).then(function () {
      return self.skipWaiting();
    })
  );
});

// ─── ACTIVATE ─────────────────────────────────────────────────────────────────

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(
        keys
          .filter(function (k) {
            return k.startsWith('cenacolo-') && !k.endsWith(SW_VERSION);
          })
          .map(function (k) { return caches.delete(k); })
      );
    }).then(function () {
      return self.clients.claim();
    })
  );
});

// ─── FETCH ────────────────────────────────────────────────────────────────────

self.addEventListener('fetch', function (event) {
  var request = event.request;
  var url     = new URL(request.url);
  var path    = url.pathname;

  // External (CDN, Google Fonts): cache-first with 3s network timeout
  if (url.origin !== self.location.origin) {
    event.respondWith(cacheFirstWithTimeout(request, 3000));
    return;
  }

  // POST to API: network or queue offline
  if (request.method === 'POST' && path.startsWith(API_PREFIX)) {
    event.respondWith(networkOrQueue(request));
    return;
  }

  // GET from API: network-first, cache fallback
  if (request.method === 'GET' && path.startsWith(API_PREFIX)) {
    event.respondWith(networkFirstWithCacheFallback(request));
    return;
  }

  // PHP portal pages: stale-while-revalidate
  if (path.startsWith(PORTAL_PREFIX) && path.endsWith('.php')) {
    event.respondWith(staleWhileRevalidate(request));
    return;
  }

  // JS/CSS assets: cache-first
  if (path.match(/\.(js|css)$/)) {
    event.respondWith(cacheFirst(request));
    return;
  }

  // Default: network
  event.respondWith(fetch(request));
});

// ─── FETCH STRATEGIES ─────────────────────────────────────────────────────────

function networkOrQueue(request) {
  // Clone before reading body — body can only be read once
  var cloned = request.clone();

  return fetch(request).catch(function () {
    // Offline path — read body and enqueue
    return cloned.text().then(function (body) {
      var payload = {};
      try { payload = JSON.parse(body); } catch (e) { console.warn('[SW] networkOrQueue: non-JSON body, queuing with empty payload', e); }

      var url    = cloned.url;
      var action = inferAction(new URL(url).pathname, payload);

      var entry = {
        id        : swUuid(),
        timestamp : Date.now(),
        action    : action,
        url       : url,
        method    : 'POST',
        payload   : payload,
        status    : 'pending'
      };

      return swIdbPut('outbox', entry).then(function () {
        return new Response(JSON.stringify({ queued: true, entry: entry }), {
          status  : 200,
          headers : { 'Content-Type': 'application/json' }
        });
      });
    });
  });
}

function inferAction(path, payload) {
  if (path.indexOf('reservations') !== -1) {
    if (payload.action === 'update_status') return 'update_reservation_status';
    return 'create_reservation';
  }
  if (path.indexOf('tables') !== -1) return 'assign_table';
  return 'unknown';
}

function networkFirstWithCacheFallback(request) {
  return fetch(request).then(function (response) {
    if (response.ok) {
      caches.open(API_CACHE).then(function (cache) { cache.put(request, response.clone()); });
    }
    return response;
  }).catch(function () {
    return caches.match(request).then(function (cached) {
      if (cached) return cached;
      // Nothing cached: return empty-success so page shows IDB data
      return new Response(
        JSON.stringify({ success: true, offline: true, data: [] }),
        { headers: { 'Content-Type': 'application/json' } }
      );
    });
  });
}

function staleWhileRevalidate(request) {
  return caches.open(STATIC_CACHE).then(function (cache) {
    return cache.match(request).then(function (cached) {
      var fetchPromise = fetch(request).then(function (response) {
        cache.put(request, response.clone());
        return response;
      }).catch(function () { return null; });

      return cached || fetchPromise;
    });
  });
}

function cacheFirst(request) {
  return caches.match(request).then(function (cached) {
    if (cached) return cached;
    return fetch(request).then(function (response) {
      caches.open(STATIC_CACHE).then(function (cache) { cache.put(request, response.clone()); });
      return response;
    });
  });
}

function cacheFirstWithTimeout(request, timeoutMs) {
  return caches.match(request).then(function (cached) {
    if (cached) return cached;
    var timeoutPromise = new Promise(function (_, reject) {
      setTimeout(function () { reject(new Error('timeout')); }, timeoutMs);
    });
    return Promise.race([fetch(request), timeoutPromise]).then(function (response) {
      caches.open(STATIC_CACHE).then(function (cache) { cache.put(request, response.clone()); });
      return response;
    }).catch(function () {
      return new Response('', { status: 504, statusText: 'Gateway Timeout' });
    });
  }).catch(function (err) {
    console.warn('[SW] flushOutbox failed:', err);
  });
}

// ─── MESSAGE (page → SW) ──────────────────────────────────────────────────────

self.addEventListener('message', function (event) {
  if (event.data && event.data.type === 'FLUSH_OUTBOX') {
    event.waitUntil(flushOutbox());
  }
});

// ─── BACKGROUND SYNC (Chrome/Edge only; Safari uses postMessage fallback) ─────

self.addEventListener('sync', function (event) {
  if (event.tag === 'cenacolo-outbox') {
    event.waitUntil(flushOutbox());
  }
});

// ─── FLUSH OUTBOX ─────────────────────────────────────────────────────────────

function flushOutbox() {
  return swIdbGetAll('outbox').then(function (entries) {
    // FIFO: sort by timestamp ascending
    entries.sort(function (a, b) { return a.timestamp - b.timestamp; });

    var conflictCount = 0;

    // Process sequentially with reduce
    return entries.reduce(function (chain, entry) {
      if (entry.status !== 'pending') return chain;

      return chain.then(function () {
        // Mark syncing
        return swIdbPut('outbox', Object.assign({}, entry, { status: 'syncing' }));
      }).then(function () {
        return fetch(entry.url, {
          method  : entry.method,
          headers : { 'Content-Type': 'application/json' },
          body    : JSON.stringify(entry.payload)
        });
      }).then(function (response) {
        if (response.status === 409) {
          return response.json().then(function (data) {
            conflictCount++;
            return swIdbPut('conflicts', Object.assign({}, entry, {
              server_data   : data.server_data || {},
              conflicted_at : Date.now(),
              status        : 'conflict'
            })).then(function () {
              return swIdbDelete('outbox', entry.id);
            });
          });
        } else if (response.ok) {
          return swIdbDelete('outbox', entry.id);
        } else {
          // Server error — reset to pending for next attempt
          return swIdbPut('outbox', Object.assign({}, entry, { status: 'pending' }));
        }
      }).catch(function () {
        // Network error — reset to pending
        return swIdbPut('outbox', Object.assign({}, entry, { status: 'pending' }));
      });
    }, Promise.resolve()).then(function () {
      // Notify all open tabs
      return self.clients.matchAll().then(function (clients) {
        clients.forEach(function (client) {
          client.postMessage({ type: 'SYNC_COMPLETE', conflicts: conflictCount });
        });
      });
    });
  });
}
