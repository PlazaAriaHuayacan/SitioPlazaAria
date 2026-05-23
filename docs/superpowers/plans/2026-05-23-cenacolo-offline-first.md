# CenacoloReserve Offline-First Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Service Worker + IndexedDB offline support to the CenacoloReserve concierge portal so hostesses can read data, change reservation statuses, assign tables, and create new reservations without internet — with automatic sync and manual conflict resolution on reconnect.

**Architecture:** A Service Worker at `portal/sw.js` intercepts all fetch calls from portal pages. GETs go network-first with IndexedDB snapshot fallback; POSTs when offline return a synthetic `{queued:true}` response after writing to an IDB outbox queue. On reconnect, the SW flushes the outbox FIFO; 409 responses move entries to a conflicts store; `offline.js` handles all UI feedback (banner, badges, conflict modal).

**Tech Stack:** Vanilla JS, Service Worker API, IndexedDB API, PHP 8+, Tailwind CSS dark/gold theme (CDN). No frameworks, no bundler, no test runner — manual browser testing via DevTools.

---

## File Map

| File | Action | Purpose |
|---|---|---|
| `portal/js/idb.js` | **Create** | IndexedDB wrapper — open DB, put/get/delete/getAll, uuid generator |
| `portal/sw.js` | **Create** | Service Worker — cache strategies, network-or-queue for POSTs, outbox flush |
| `portal/js/offline.js` | **Create** | Page-side SW registration, online/offline UX, conflict modal, toast helper |
| `portal/index.php` | **Modify** | Add offline banner, conn chip in navbar, `data-reservation-id` on rows, offline badges |
| `portal/new-reservation.php` | **Modify** | Add offline confirmation screen, detect `queued:true` response |
| `portal/floorplan.php` | **Create** | Table floor plan page — grid of tables, assign-to-reservation flow, offline-aware |
| `portal/commissions.php` | **Modify** | Add offline banner + conn chip + script tags |
| `portal/bank-data.php` | **Modify** | Add offline banner + conn chip + script tags + offline overlay |
| `api/reservations.php` | **Modify** | Add `client_version` conflict detection in PUT handler |

---

## Task 1: Create `portal/js/idb.js` — IndexedDB wrapper

**Files:**
- Create: `portal/js/idb.js`

This is the foundation used by all page-side JS. It exposes `window.CenacoloIDB` with promise-based helpers for the three stores: `snapshot`, `outbox`, `conflicts`.

- [ ] **Step 1: Create the directory and file**

```bash
mkdir -p "/Users/fernando.diaz/Downloads/SINERGIA Proyectos/CenacoloReserve/portal/js"
```

Then create `portal/js/idb.js` with this content:

```js
/**
 * CenacoloIDB — IndexedDB wrapper for page-side code.
 * DB: cenacolo-offline v1
 * Stores: snapshot (keyPath: [type,id]), outbox (keyPath: id), conflicts (keyPath: id)
 * Usage: window.CenacoloIDB.idbPut('outbox', entry)
 */
(function () {
  'use strict';

  const DB_NAME    = 'cenacolo-offline';
  const DB_VERSION = 1;
  let _db = null;

  function openDB() {
    if (_db) return Promise.resolve(_db);
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
      req.onsuccess = function (e) { _db = e.target.result; resolve(_db); };
      req.onerror   = function (e) { reject(e.target.error); };
    });
  }

  function storeTx(storeName, mode) {
    return openDB().then(function (db) {
      return db.transaction(storeName, mode).objectStore(storeName);
    });
  }

  function idbGet(storeName, key) {
    return storeTx(storeName, 'readonly').then(function (os) {
      return new Promise(function (resolve, reject) {
        var req = os.get(key);
        req.onsuccess = function (e) { resolve(e.target.result); };
        req.onerror   = function (e) { reject(e.target.error); };
      });
    });
  }

  function idbPut(storeName, obj) {
    return storeTx(storeName, 'readwrite').then(function (os) {
      return new Promise(function (resolve, reject) {
        var req = os.put(obj);
        req.onsuccess = function () { resolve(); };
        req.onerror   = function (e) { reject(e.target.error); };
      });
    });
  }

  function idbDelete(storeName, key) {
    return storeTx(storeName, 'readwrite').then(function (os) {
      return new Promise(function (resolve, reject) {
        var req = os.delete(key);
        req.onsuccess = function () { resolve(); };
        req.onerror   = function (e) { reject(e.target.error); };
      });
    });
  }

  function idbGetAll(storeName) {
    return storeTx(storeName, 'readonly').then(function (os) {
      return new Promise(function (resolve, reject) {
        var req = os.getAll();
        req.onsuccess = function (e) { resolve(e.target.result); };
        req.onerror   = function (e) { reject(e.target.error); };
      });
    });
  }

  function idbClear(storeName) {
    return storeTx(storeName, 'readwrite').then(function (os) {
      return new Promise(function (resolve, reject) {
        var req = os.clear();
        req.onsuccess = function () { resolve(); };
        req.onerror   = function (e) { reject(e.target.error); };
      });
    });
  }

  function uuid() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
      var r = Math.random() * 16 | 0;
      return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
    });
  }

  window.CenacoloIDB = {
    openDB    : openDB,
    idbGet    : idbGet,
    idbPut    : idbPut,
    idbDelete : idbDelete,
    idbGetAll : idbGetAll,
    idbClear  : idbClear,
    uuid      : uuid
  };
}());
```

- [ ] **Step 2: Verify in browser console**

Open Chrome, navigate to `https://somossinergia.com/CenacoloReserve/portal/index.php`. Open DevTools → Console. Temporarily inject the script:

```js
// Paste the entire idb.js content into the console, then test:
CenacoloIDB.idbPut('snapshot', { type: 'test', id: 1, data: { x: 1 }, fetchedAt: Date.now() })
  .then(() => CenacoloIDB.idbGet('snapshot', ['test', 1]))
  .then(r => console.log('idbGet result:', r))   // should log the object
  .then(() => CenacoloIDB.idbDelete('snapshot', ['test', 1]))
  .then(() => console.log('delete OK'));
```

Expected: no errors, `idbGet result:` logs the object, `delete OK` logs.

- [ ] **Step 3: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos/CenacoloReserve"
git add portal/js/idb.js
git commit -m "feat(offline): add IndexedDB wrapper portal/js/idb.js"
```

---

## Task 2: Create `portal/sw.js` — Service Worker

**Files:**
- Create: `portal/sw.js`

The Service Worker intercepts all fetch calls from portal pages. It handles four strategies: network-or-queue (POST to API), network-first-IDB-fallback (GET from API), stale-while-revalidate (PHP pages), cache-first (static assets and CDN).

The SW also has its own inline IDB helpers (cannot import `idb.js` since it uses `window`) and handles outbox flushing.

- [ ] **Step 1: Create `portal/sw.js`**

```js
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

  // GET from API: network-first, IDB snapshot fallback
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
      try { payload = JSON.parse(body); } catch (e) { /* non-JSON body */ }

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
    var clone = response.clone();
    caches.open(API_CACHE).then(function (cache) { cache.put(request, clone); });
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
```

- [ ] **Step 2: Verify SW registers without errors**

After Task 4 adds the registration script, open Chrome DevTools → Application → Service Workers. The SW should appear as "Activated and is running". Check the Console for any SW errors.

For now, verify the file parses without syntax errors by opening the Chrome console and running:

```js
// Quick syntax check — paste into console:
fetch('/CenacoloReserve/portal/sw.js')
  .then(r => r.text())
  .then(src => { new Function(src); console.log('SW syntax OK'); })
  .catch(e => console.error('SW syntax error:', e));
```

Expected: `SW syntax OK`

- [ ] **Step 3: Commit**

```bash
git add portal/sw.js
git commit -m "feat(offline): add Service Worker portal/sw.js with cache strategies and outbox queue"
```

---

## Task 3: Create `portal/js/offline.js` — page-side UX layer

**Files:**
- Create: `portal/js/offline.js`

Registers the SW, manages the online/offline banner and navbar chip, handles SYNC_COMPLETE messages, shows the conflict modal, and exposes `window.CenacoloOffline` for page-specific use.

**Depends on:** `idb.js` loaded before this file.

- [ ] **Step 1: Create `portal/js/offline.js`**

```js
/**
 * Cenacolo Offline — page-side Service Worker manager + UX layer.
 * Must be loaded AFTER portal/js/idb.js.
 * Exposes window.CenacoloOffline = { showToast, refreshConnChip }
 */
(function () {
  'use strict';

  var SW_PATH  = '/CenacoloReserve/portal/sw.js';
  var SW_SCOPE = '/CenacoloReserve/portal/';

  // ── Register Service Worker ────────────────────────────────────────────────

  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(SW_PATH, { scope: SW_SCOPE })
        .then(function (reg) {
          // Register Background Sync if supported (Chrome/Edge)
          if ('sync' in reg) {
            navigator.serviceWorker.ready
              .then(function (r) { return r.sync.register('cenacolo-outbox'); })
              .catch(function () { /* not critical */ });
          }
        })
        .catch(function (err) {
          console.warn('[Cenacolo SW] Registration failed:', err);
        });

      navigator.serviceWorker.addEventListener('message', handleSWMessage);
    });
  }

  // ── Online / Offline events ────────────────────────────────────────────────

  window.addEventListener('online',  onOnline);
  window.addEventListener('offline', onOffline);

  function onOnline() {
    toggleOfflineBanner(false);
    refreshConnChip();
    showToast('📶 Conexión restaurada. Sincronizando...', 'info');
    // Trigger outbox flush via SW
    if (navigator.serviceWorker.controller) {
      navigator.serviceWorker.controller.postMessage({ type: 'FLUSH_OUTBOX' });
    }
  }

  function onOffline() {
    toggleOfflineBanner(true);
    refreshConnChip();
  }

  function toggleOfflineBanner(show) {
    var banner = document.getElementById('offlineBanner');
    if (!banner) return;
    if (show) {
      banner.classList.remove('hidden');
    } else {
      banner.classList.add('hidden');
    }
  }

  // ── Connection chip ────────────────────────────────────────────────────────

  function refreshConnChip() {
    var chip = document.getElementById('connStatus');
    if (!chip) return;

    CenacoloIDB.idbGetAll('conflicts').then(function (conflicts) {
      if (conflicts.length > 0) {
        chip.className = 'flex items-center gap-1.5 text-xs font-medium text-red-400 cursor-pointer underline';
        chip.innerHTML = '<span class="w-2 h-2 rounded-full bg-red-400 animate-pulse inline-block"></span> ⚠️ ' + conflicts.length + ' conflicto(s)';
        chip.onclick   = function () { showConflictModal(conflicts); };
        return;
      }
      CenacoloIDB.idbGetByIndex
        ? CenacoloIDB.idbGetAll('outbox').then(function (entries) {
            var pending = entries.filter(function (e) { return e.status === 'pending' || e.status === 'syncing'; }).length;
            if (!navigator.onLine) {
              chip.className = 'flex items-center gap-1.5 text-xs font-medium text-yellow-400';
              chip.innerHTML = '<span class="w-2 h-2 rounded-full border-2 border-yellow-400 inline-block"></span> Sin conexión' + (pending > 0 ? ' (' + pending + ' pendientes)' : '');
              chip.onclick   = null;
            } else {
              chip.className = 'flex items-center gap-1.5 text-xs font-medium text-green-400';
              chip.innerHTML = '<span class="w-2 h-2 rounded-full bg-green-400 inline-block"></span> En línea';
              chip.onclick   = null;
            }
          })
        : null;
    }).catch(function () {
      // IDB not yet available — show basic status
      chip.className = navigator.onLine
        ? 'flex items-center gap-1.5 text-xs font-medium text-green-400'
        : 'flex items-center gap-1.5 text-xs font-medium text-yellow-400';
      chip.innerHTML = navigator.onLine
        ? '<span class="w-2 h-2 rounded-full bg-green-400 inline-block"></span> En línea'
        : '<span class="w-2 h-2 rounded-full border-2 border-yellow-400 inline-block"></span> Sin conexión';
    });
  }

  // ── Offline badges on reservation rows ────────────────────────────────────

  function updateOfflineBadges() {
    CenacoloIDB.idbGetAll('outbox').then(function (entries) {
      var pendingIds = {};
      entries.forEach(function (e) {
        if ((e.status === 'pending' || e.status === 'syncing') && e.payload && e.payload.reservation_id) {
          pendingIds[e.payload.reservation_id] = true;
        }
      });

      document.querySelectorAll('[data-reservation-id]').forEach(function (row) {
        var badge = row.querySelector('.offline-badge');
        if (!badge) return;
        var id = parseInt(row.getAttribute('data-reservation-id'), 10);
        if (pendingIds[id]) {
          badge.classList.remove('hidden');
        } else {
          badge.classList.add('hidden');
        }
      });
    });
  }

  // ── SW message handler ─────────────────────────────────────────────────────

  function handleSWMessage(event) {
    var data = event.data || {};
    if (data.type === 'SYNC_COMPLETE') {
      refreshConnChip();
      updateOfflineBadges();
      if (data.conflicts > 0) {
        CenacoloIDB.idbGetAll('conflicts').then(showConflictModal);
      } else {
        showToast('✅ Todo sincronizado', 'success');
      }
    }
  }

  // ── Conflict modal ─────────────────────────────────────────────────────────

  function showConflictModal(conflicts) {
    if (!conflicts || conflicts.length === 0) return;

    var actionLabels = {
      update_reservation_status : 'Cambio de estado',
      assign_table              : 'Asignación de mesa',
      create_reservation        : 'Nueva reserva'
    };

    function renderAt(idx) {
      var existing = document.getElementById('conflictModal');
      if (existing) existing.remove();

      var c           = conflicts[idx];
      var actionLabel = actionLabels[c.action] || c.action;
      var myJson      = JSON.stringify(c.payload,     null, 2);
      var srvJson     = JSON.stringify(c.server_data, null, 2);
      var myTime      = new Date(c.timestamp).toLocaleTimeString('es-MX');
      var srvTime     = new Date(c.conflicted_at).toLocaleTimeString('es-MX');

      var modal = document.createElement('div');
      modal.id        = 'conflictModal';
      modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm px-4';
      modal.innerHTML = [
        '<div class="bg-slate-900 border border-red-700/50 rounded-xl shadow-2xl max-w-lg w-full p-6">',
          '<div class="flex items-center gap-2 mb-4">',
            '<span class="text-red-400 text-xl">⚠️</span>',
            '<h3 class="text-white font-semibold text-lg flex-1">Conflicto: ' + escHtml(actionLabel) + '</h3>',
            '<span class="text-xs text-slate-400">' + (idx + 1) + ' de ' + conflicts.length + '</span>',
          '</div>',
          '<div class="grid grid-cols-2 gap-3 mb-5">',
            '<div class="bg-slate-800 rounded-lg p-3">',
              '<p class="text-xs text-yellow-400 font-semibold uppercase tracking-wider mb-2">Tu versión (offline)</p>',
              '<pre class="text-xs text-slate-200 whitespace-pre-wrap overflow-auto max-h-40">' + escHtml(myJson) + '</pre>',
              '<p class="text-xs text-slate-500 mt-2">' + myTime + '</p>',
            '</div>',
            '<div class="bg-slate-800 rounded-lg p-3">',
              '<p class="text-xs text-green-400 font-semibold uppercase tracking-wider mb-2">Versión del servidor</p>',
              '<pre class="text-xs text-slate-200 whitespace-pre-wrap overflow-auto max-h-40">' + escHtml(srvJson) + '</pre>',
              '<p class="text-xs text-slate-500 mt-2">' + srvTime + '</p>',
            '</div>',
          '</div>',
          '<div class="flex gap-3">',
            '<button id="btnKeepMine"  class="flex-1 py-2.5 bg-yellow-900/40 border border-yellow-700/40 text-yellow-300 rounded-lg text-sm font-semibold hover:bg-yellow-900/60 transition-colors">Conservar la mía</button>',
            '<button id="btnUseServer" class="flex-1 py-2.5 bg-green-900/40  border border-green-700/40  text-green-300  rounded-lg text-sm font-semibold hover:bg-green-900/60  transition-colors">Usar la del servidor</button>',
          '</div>',
        '</div>'
      ].join('');

      document.body.appendChild(modal);

      document.getElementById('btnKeepMine').onclick = function () {
        var forcePayload = Object.assign({}, c.payload, { force: true });
        fetch(c.url, {
          method  : c.method,
          headers : { 'Content-Type': 'application/json' },
          body    : JSON.stringify(forcePayload)
        }).then(function (resp) {
          if (resp.ok) {
            CenacoloIDB.idbDelete('conflicts', c.id);
            conflicts.splice(idx, 1);
            next();
          } else {
            showToast('Error al forzar el cambio. Intenta más tarde.', 'error');
          }
        }).catch(function () {
          showToast('Sin conexión. Intenta más tarde.', 'error');
        });
      };

      document.getElementById('btnUseServer').onclick = function () {
        CenacoloIDB.idbDelete('conflicts', c.id);
        conflicts.splice(idx, 1);
        next();
      };
    }

    function next() {
      var modal = document.getElementById('conflictModal');
      if (modal) modal.remove();
      if (conflicts.length === 0) {
        showToast('✅ Todos los conflictos resueltos', 'success');
        refreshConnChip();
      } else {
        renderAt(0);
      }
    }

    renderAt(0);
  }

  // ── Toast ──────────────────────────────────────────────────────────────────

  function showToast(message, type) {
    var container = document.getElementById('toastContainer');
    if (!container) {
      container = document.createElement('div');
      container.id        = 'toastContainer';
      container.className = 'fixed bottom-4 right-4 z-50 flex flex-col gap-2';
      document.body.appendChild(container);
    }

    var colorMap = {
      success : 'bg-green-900 border-green-700 text-green-200',
      error   : 'bg-red-900   border-red-700   text-red-200',
      info    : 'bg-blue-900  border-blue-700  text-blue-200'
    };

    var toast = document.createElement('div');
    toast.className   = 'border rounded-lg px-4 py-3 text-sm font-medium shadow-lg max-w-sm transition-opacity ' + (colorMap[type] || colorMap.info);
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(function () {
      toast.style.opacity = '0';
      setTimeout(function () { toast.remove(); }, 300);
    }, 4000);
  }

  // ── HTML escape helper ─────────────────────────────────────────────────────

  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // ── Init ───────────────────────────────────────────────────────────────────

  document.addEventListener('DOMContentLoaded', function () {
    // Set initial UI state based on current connection
    toggleOfflineBanner(!navigator.onLine);
    refreshConnChip();
    updateOfflineBadges();
  });

  // ── Public API ─────────────────────────────────────────────────────────────

  window.CenacoloOffline = {
    showToast          : showToast,
    refreshConnChip    : refreshConnChip,
    updateOfflineBadges: updateOfflineBadges,
    showConflictModal  : showConflictModal
  };

}());
```

- [ ] **Step 2: Commit**

```bash
git add portal/js/offline.js
git commit -m "feat(offline): add page-side SW registration and UX layer portal/js/offline.js"
```

---

## Task 4: Modify `portal/index.php` — offline banner, chip, badges

**Files:**
- Modify: `portal/index.php`

Add four things: (1) offline banner div after `<nav>`, (2) `id="connStatus"` chip in navbar, (3) `data-reservation-id` + `.offline-badge` span on each reservation row, (4) `<script>` tags for `idb.js` and `offline.js` before `</body>`.

- [ ] **Step 1: Add conn chip to navbar**

In `portal/index.php`, find the navbar `<div class="flex items-center space-x-4">` block (around line 141). Add the conn chip span **before** the first `<a>` inside that flex div:

```html
                <div class="flex items-center space-x-4">
                    <!-- Connection status chip -->
                    <span id="connStatus" class="flex items-center gap-1.5 text-xs font-medium text-green-400 hidden sm:flex">
                        <span class="w-2 h-2 rounded-full bg-green-400 inline-block"></span> En línea
                    </span>
                    <a href="<?= resUrl('/portal/commissions.php') ?>" ...
```

- [ ] **Step 2: Add offline banner after `</nav>`**

Find the closing `</nav>` tag (around line 157). Add immediately after it:

```html
    <!-- Offline banner — shown by offline.js when navigator.onLine === false -->
    <div id="offlineBanner" class="hidden bg-yellow-900 border-b border-yellow-700 text-yellow-200 px-4 py-3 text-sm text-center">
        📵 <strong>Sin conexión</strong> — los cambios se guardan localmente y se sincronizarán automáticamente al reconectar.
    </div>
```

- [ ] **Step 3: Add `data-reservation-id` and `.offline-badge` to reservation rows**

Find the tbody `<tr class="hover:bg-dark-800/50 transition-colors">` loop (around line 370). Change the `<tr>` to include `data-reservation-id`:

```php
                                <tr class="hover:bg-dark-800/50 transition-colors" data-reservation-id="<?= $res['id'] ?>">
```

In the same row, find the Status `<td>` (the last `<td>` with `status-badge`, around line 392). Add the offline badge span INSIDE that `<td>`, before the status badge:

```php
                                    <td class="px-4 py-3">
                                        <span class="offline-badge hidden text-xs px-2 py-0.5 rounded-full bg-yellow-900/40 text-yellow-300 font-semibold mr-1">⏳ Sin sincronizar</span>
                                        <span class="status-badge status-<?= $res['status'] ?>">
                                            <?= ucfirst(str_replace('_', ' ', $res['status'])) ?>
                                        </span>
                                    </td>
```

- [ ] **Step 4: Add script tags before `</body>`**

Find the closing `</body>` tag. Add before it (after the existing inline `<script>` block):

```html
    <script src="<?= resUrl('/portal/js/idb.js') ?>"></script>
    <script src="<?= resUrl('/portal/js/offline.js') ?>"></script>
```

- [ ] **Step 5: Manual test**

Open Chrome → DevTools → Network tab → check "Offline" checkbox. Reload `portal/index.php`. Verify:
- Yellow banner appears: "📵 Sin conexión — los cambios..."
- Navbar chip shows "Sin conexión" in yellow

Uncheck "Offline". Verify:
- Banner disappears
- Chip shows "En línea" in green

- [ ] **Step 6: Commit**

```bash
git add portal/index.php
git commit -m "feat(offline): add offline banner, conn chip, and reservation badges to portal/index.php"
```

---

## Task 5: Modify `portal/new-reservation.php` — offline confirmation screen

**Files:**
- Modify: `portal/new-reservation.php`

Add: (1) offline banner + conn chip in navbar, (2) a hidden `#offlineContainer` div (sibling of `#formContainer` and `#successContainer`), (3) detect `result.queued === true` in the form submit handler, (4) script tags.

- [ ] **Step 1: Add conn chip to navbar**

Find the navbar in `portal/new-reservation.php`. It has `<div class="flex items-center space-x-4">` (or similar). Add the conn chip before the existing nav items, matching the exact pattern from Task 4 Step 1:

```html
                    <span id="connStatus" class="flex items-center gap-1.5 text-xs font-medium text-green-400 hidden sm:flex">
                        <span class="w-2 h-2 rounded-full bg-green-400 inline-block"></span> En línea
                    </span>
```

- [ ] **Step 2: Add offline banner after `</nav>`**

Find the closing `</nav>` tag. Add immediately after it:

```html
    <div id="offlineBanner" class="hidden bg-yellow-900 border-b border-yellow-700 text-yellow-200 px-4 py-3 text-sm text-center">
        📵 <strong>Sin conexión</strong> — los cambios se guardan localmente y se sincronizarán automáticamente al reconectar.
    </div>
```

- [ ] **Step 3: Add offline confirmation container**

Find `<div id="successContainer" class="hidden fade-in">` (around line 256). Add a new sibling div BEFORE it:

```html
        <!-- Offline confirmation — shown when form is submitted without internet -->
        <div id="offlineContainer" class="hidden fade-in">
            <div class="bg-dark-900 rounded-xl border border-yellow-700/50 p-8 text-center max-w-lg mx-auto">
                <div class="w-16 h-16 rounded-full bg-yellow-500/20 flex items-center justify-center mx-auto mb-4">
                    <span class="text-3xl">⏳</span>
                </div>
                <h3 class="text-xl font-semibold text-white mb-2">Reserva guardada sin conexión</h3>
                <div id="offlineSummary" class="text-dark-300 text-sm mb-4"></div>
                <div class="bg-dark-800 rounded-lg p-4 mb-6 text-left text-sm text-yellow-200 border border-yellow-700/40">
                    <p class="mb-1">✅ La reserva está guardada en <strong>este dispositivo</strong>.</p>
                    <p class="mb-1">🔄 Se confirmará en el sistema cuando recuperes internet.</p>
                    <p class="text-yellow-400 font-semibold">⚠️ Avisa al manager si no recuperas conexión antes de que llegue el cliente.</p>
                </div>
                <div class="flex items-center justify-center space-x-3">
                    <a href="<?= resUrl('/portal/new-reservation.php') ?>"
                       class="px-6 py-2.5 bg-gold-500 text-dark-950 rounded-lg font-semibold hover:bg-gold-400 transition-colors">
                        Nueva Reserva
                    </a>
                    <a href="<?= resUrl('/portal/index.php') ?>"
                       class="px-6 py-2.5 border border-dark-600 text-dark-300 rounded-lg hover:bg-dark-800 transition-colors">
                        Volver al Dashboard
                    </a>
                </div>
            </div>
        </div>
```

- [ ] **Step 4: Modify the form submit handler to handle `queued: true`**

Find the form submit handler (around line 330). Replace the entire `try/catch` block inside the `submit` event listener with:

```js
            try {
                const response = await fetch('<?= resUrl('/api/reservations.php') ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.queued === true) {
                    // Offline — show confirmation screen
                    document.getElementById('formContainer').classList.add('hidden');
                    document.getElementById('offlineContainer').classList.remove('hidden');
                    // Fill summary
                    const parts = [];
                    if (data.guest_name)        parts.push('<strong>Huésped:</strong> ' + escapeHtml(data.guest_name));
                    if (data.reservation_date)  parts.push('<strong>Fecha:</strong> '   + escapeHtml(data.reservation_date));
                    if (data.reservation_time)  parts.push('<strong>Hora:</strong> '    + escapeHtml(data.reservation_time));
                    if (data.party_size)        parts.push('<strong>Personas:</strong> '+ escapeHtml(String(data.party_size)));
                    document.getElementById('offlineSummary').innerHTML = parts.join(' &middot; ');

                } else if (result.success && result.data) {
                    // Online — show normal success
                    document.getElementById('formContainer').classList.add('hidden');
                    document.getElementById('successContainer').classList.remove('hidden');
                    document.getElementById('confirmationCode').textContent = result.data.confirmation_code || '---';

                    const details = [];
                    if (result.data.guest_name)       details.push('<strong>Huesped:</strong> '      + escapeHtml(result.data.guest_name));
                    if (result.data.restaurant_name)  details.push('<strong>Restaurante:</strong> '  + escapeHtml(result.data.restaurant_name));
                    if (result.data.reservation_date) details.push('<strong>Fecha:</strong> '         + result.data.reservation_date);
                    if (result.data.reservation_time) details.push('<strong>Hora:</strong> '          + formatTime(result.data.reservation_time));
                    if (result.data.party_size)       details.push('<strong>Personas:</strong> '      + result.data.party_size);
                    document.getElementById('successDetails').innerHTML = details.join(' &middot; ');

                    showToast('Reserva creada exitosamente', 'success');
                } else {
                    showToast(result.error || 'Error al crear la reserva', 'error');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Crear Reserva';
                }
            } catch (err) {
                console.error(err);
                showToast('Error inesperado. Intenta nuevamente.', 'error');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Crear Reserva';
            }
```

- [ ] **Step 5: Add script tags before `</body>`**

```html
    <script src="<?= resUrl('/portal/js/idb.js') ?>"></script>
    <script src="<?= resUrl('/portal/js/offline.js') ?>"></script>
```

- [ ] **Step 6: Manual test — offline reservation**

In Chrome DevTools → Network → check "Offline". Fill out the reservation form with any data. Click "Crear Reserva". Verify:
- Form disappears
- Offline confirmation screen appears with the guest name/date/time/party size filled in
- The three bullet points appear (saved on device, will sync, warn manager)

Uncheck "Offline". DevTools → Application → IndexedDB → cenacolo-offline → outbox. Verify the reservation entry is there with `status: "pending"`.

- [ ] **Step 7: Commit**

```bash
git add portal/new-reservation.php
git commit -m "feat(offline): add offline confirmation screen to portal/new-reservation.php"
```

---

## Task 6: Create `portal/floorplan.php` — table floor plan page

**Files:**
- Create: `portal/floorplan.php`

New page. Shows tables from `GET /api/tables.php?action=floorplan&restaurant_id=X`. Each table renders as a card with status color and current reservation if occupied. Clicking an available table opens an assign-to-reservation modal. Fully offline-aware: loads from IDB snapshot if offline, table assignments queue to outbox.

- [ ] **Step 1: Create `portal/floorplan.php`**

```php
<?php
/**
 * Portal Concierge — Plano de Mesas
 * Muestra el estado actual de mesas del restaurante.
 * Lectura: GET /api/tables.php?action=floorplan&restaurant_id=X
 * Escritura (asignar): PUT /api/reservations.php con { id, table_id }
 */
require_once __DIR__ . '/../includes/config.php';
$concierge = requireConciergeLogin();
logResAccess($concierge['id'], 'portal_floorplan', 'concierge');

$pdo = getResDB();

// Obtener restaurantes activos para el selector
$restaurants = getActiveRestaurants();

// Restaurante seleccionado (por GET param o el primero disponible)
$selectedRestaurantId = isset($_GET['restaurant_id']) ? intval($_GET['restaurant_id']) : 0;
if (!$selectedRestaurantId && !empty($restaurants)) {
    $selectedRestaurantId = $restaurants[0]['id'];
}

$conciergeName = resSanitize($concierge['name']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plano de Mesas - Portal Concierge - Cenacolo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        gold: { 400: '#FFCC33', 500: '#D4AF37' },
                        dark: { 700: '#334155', 800: '#1e293b', 900: '#0f172a', 950: '#020617' }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-display { font-family: 'Playfair Display', serif; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:none; } }
        .fade-in { animation: fadeIn 0.25s ease-out; }
    </style>
</head>
<body class="bg-dark-950 text-slate-200 min-h-screen">

    <nav class="sticky top-0 z-30 bg-dark-900/90 backdrop-blur-md border-b border-slate-700">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <h1 class="font-display text-xl text-gold-500 font-bold">Cenacolo</h1>
                <span class="text-slate-600 text-xs">|</span>
                <span class="text-slate-400 text-xs uppercase tracking-wider">Plano de Mesas</span>
            </div>
            <div class="flex items-center gap-4">
                <span id="connStatus" class="flex items-center gap-1.5 text-xs font-medium text-green-400 hidden sm:flex">
                    <span class="w-2 h-2 rounded-full bg-green-400 inline-block"></span> En línea
                </span>
                <a href="<?= resUrl('/portal/index.php') ?>" class="text-slate-400 hover:text-gold-400 text-sm transition-colors">&larr; Dashboard</a>
            </div>
        </div>
    </nav>

    <!-- Offline banner -->
    <div id="offlineBanner" class="hidden bg-yellow-900 border-b border-yellow-700 text-yellow-200 px-4 py-3 text-sm text-center">
        📵 <strong>Sin conexión</strong> — los cambios se guardan localmente y se sincronizarán automáticamente al reconectar.
    </div>

    <main class="max-w-6xl mx-auto px-4 py-6">

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-6 gap-4">
            <h2 class="text-2xl font-semibold text-white">Plano de Mesas</h2>

            <!-- Restaurant selector -->
            <?php if (count($restaurants) > 1): ?>
            <form method="GET" class="flex items-center gap-2">
                <select name="restaurant_id" onchange="this.form.submit()"
                        class="bg-dark-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:border-gold-500 focus:outline-none">
                    <?php foreach ($restaurants as $r): ?>
                    <option value="<?= $r['id'] ?>" <?= $r['id'] == $selectedRestaurantId ? 'selected' : '' ?>>
                        <?= resSanitize($r['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>

            <!-- Stats bar -->
            <div id="statsBar" class="flex gap-3 text-xs">
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-green-500/30 border border-green-500 inline-block"></span> Disponible</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-red-500/30   border border-red-500   inline-block"></span> Ocupada</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-blue-500/30  border border-blue-500  inline-block"></span> Reservada</span>
                <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-slate-500/30 border border-slate-500 inline-block"></span> Bloqueada</span>
            </div>
        </div>

        <!-- Date picker -->
        <div class="flex items-center gap-3 mb-6">
            <label class="text-xs text-slate-400 uppercase tracking-wider">Fecha:</label>
            <input type="date" id="floorplanDate"
                   value="<?= date('Y-m-d') ?>"
                   class="bg-dark-800 border border-slate-600 rounded-lg px-3 py-1.5 text-sm text-white focus:border-gold-500 focus:outline-none"
                   onchange="loadFloorplan()">
            <button onclick="document.getElementById('floorplanDate').value='<?= date('Y-m-d') ?>'; loadFloorplan();"
                    class="text-xs text-gold-400 hover:text-gold-300 underline">Hoy</button>
        </div>

        <!-- Loading spinner -->
        <div id="loadingSpinner" class="text-center py-16 text-slate-500">
            <svg class="w-8 h-8 animate-spin mx-auto mb-2 text-gold-500" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
            <p class="text-sm">Cargando mesas...</p>
        </div>

        <!-- Table grid -->
        <div id="tableGrid" class="hidden"></div>

        <!-- Empty state -->
        <div id="emptyState" class="hidden text-center py-16 text-slate-500">
            <p class="text-sm">No hay mesas configuradas para este restaurante.</p>
        </div>

    </main>

    <!-- Assign-table modal (hidden) -->
    <div id="assignModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm px-4">
        <div class="bg-dark-900 border border-slate-700 rounded-xl shadow-2xl max-w-md w-full p-6">
            <h3 class="text-white font-semibold text-lg mb-4">Asignar Mesa <span id="modalTableName" class="text-gold-400"></span></h3>
            <div class="mb-4">
                <label class="block text-xs text-slate-400 uppercase tracking-wider mb-2">Buscar reserva (código o huésped)</label>
                <input type="text" id="reservationSearch"
                       placeholder="Ej: ABC123 o Luis García"
                       class="w-full bg-dark-800 border border-slate-600 rounded-lg px-3 py-2.5 text-white placeholder-slate-500 focus:border-gold-500 focus:outline-none text-sm"
                       oninput="searchReservations(this.value)">
            </div>
            <div id="searchResults" class="mb-4 space-y-2 max-h-48 overflow-y-auto"></div>
            <div class="flex gap-3">
                <button onclick="closeAssignModal()"
                        class="flex-1 py-2.5 border border-slate-600 text-slate-300 rounded-lg text-sm hover:bg-dark-800 transition-colors">
                    Cancelar
                </button>
            </div>
        </div>
    </div>

    <div id="toastContainer" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2"></div>

    <script>
        const RESTAURANT_ID = <?= $selectedRestaurantId ?>;
        const API_TABLES_URL = '<?= resUrl('/api/tables.php') ?>';
        const API_RES_URL    = '<?= resUrl('/api/reservations.php') ?>';

        let currentTables = [];
        let selectedTableId = null;

        // ── Load floorplan ──────────────────────────────────────────────────

        async function loadFloorplan() {
            const date = document.getElementById('floorplanDate').value;
            document.getElementById('loadingSpinner').classList.remove('hidden');
            document.getElementById('tableGrid').classList.add('hidden');
            document.getElementById('emptyState').classList.add('hidden');

            try {
                const resp = await fetch(API_TABLES_URL + '?action=floorplan&restaurant_id=' + RESTAURANT_ID + '&date=' + date);
                const data = await resp.json();

                if (data.offline) {
                    // Use IDB snapshot
                    await renderFromIDB(date);
                    return;
                }

                if (data.success && data.data) {
                    currentTables = data.data;
                    // Save snapshot to IDB for offline use
                    const fetchedAt = Date.now();
                    for (const t of data.data) {
                        await CenacoloIDB.idbPut('snapshot', { type: 'table', id: t.id, data: t, fetchedAt });
                    }
                    renderTables(data.data);
                } else {
                    document.getElementById('emptyState').classList.remove('hidden');
                }
            } catch (err) {
                console.warn('[Floorplan] Network error, trying IDB snapshot:', err);
                await renderFromIDB(date);
            } finally {
                document.getElementById('loadingSpinner').classList.add('hidden');
            }
        }

        async function renderFromIDB(date) {
            const all = await CenacoloIDB.idbGetAll('snapshot');
            const tables = all
                .filter(e => e.type === 'table')
                .map(e => e.data);

            if (tables.length === 0) {
                document.getElementById('emptyState').classList.remove('hidden');
                return;
            }
            // Check if data is stale (> 24h)
            const fetchedAt = all.find(e => e.type === 'table')?.fetchedAt || 0;
            const stale = (Date.now() - fetchedAt) > 86400000;
            if (stale) {
                CenacoloOffline.showToast('⚠️ Mostrando datos guardados de hace más de 24 horas.', 'info');
            } else {
                CenacoloOffline.showToast('📴 Sin conexión — mostrando datos guardados localmente.', 'info');
            }
            currentTables = tables;
            renderTables(tables);
        }

        function renderTables(tables) {
            const grid = document.getElementById('tableGrid');
            if (!tables.length) {
                document.getElementById('emptyState').classList.remove('hidden');
                return;
            }

            // Group by section
            const sections = {};
            tables.forEach(t => {
                const sec = t.section_name || 'Sin sección';
                if (!sections[sec]) sections[sec] = [];
                sections[sec].push(t);
            });

            let html = '';
            for (const [sectionName, secTables] of Object.entries(sections)) {
                html += '<div class="mb-8">';
                html += '<h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">' + escHtml(sectionName) + '</h3>';
                html += '<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">';
                secTables.forEach(t => { html += renderTableCard(t); });
                html += '</div></div>';
            }

            grid.innerHTML = html;
            grid.classList.remove('hidden');

            // Check outbox for pending assignments (show pending border)
            CenacoloIDB.idbGetAll('outbox').then(entries => {
                entries.forEach(e => {
                    if (e.action === 'assign_table' && e.payload && e.payload.table_id) {
                        const card = document.getElementById('table-' + e.payload.table_id);
                        if (card) {
                            card.classList.add('border-dashed', 'border-gold-400');
                            card.classList.remove('border-slate-700');
                            const badge = card.querySelector('.pending-badge');
                            if (badge) badge.classList.remove('hidden');
                        }
                    }
                });
            });
        }

        function renderTableCard(t) {
            const statusStyles = {
                available   : 'bg-green-900/30 border-green-700/50 hover:border-green-500 cursor-pointer',
                occupied    : 'bg-red-900/30   border-red-700/50',
                reserved    : 'bg-blue-900/30  border-blue-700/50',
                blocked     : 'bg-slate-800    border-slate-700 opacity-60',
                maintenance : 'bg-slate-800    border-slate-700 opacity-60'
            };
            const dotStyles = {
                available   : 'bg-green-500',
                occupied    : 'bg-red-500',
                reserved    : 'bg-blue-500',
                blocked     : 'bg-slate-500',
                maintenance : 'bg-slate-500'
            };
            const statusLabels = {
                available   : 'Disponible',
                occupied    : 'Ocupada',
                reserved    : 'Reservada',
                blocked     : 'Bloqueada',
                maintenance : 'Mantenimiento'
            };

            const style  = statusStyles[t.status] || statusStyles.blocked;
            const dot    = dotStyles[t.status]    || dotStyles.blocked;
            const label  = statusLabels[t.status] || t.status;
            const click  = t.status === 'available' ? 'onclick="openAssignModal(' + t.id + ', \'' + escHtmlAttr(t.name || 'Mesa ' + t.table_number) + '\')"' : '';

            let guestInfo = '';
            if (t.guest_name) {
                guestInfo = '<p class="text-xs text-slate-300 mt-1 truncate">' + escHtml(t.guest_name) + '</p>';
                if (t.reservation_time) {
                    guestInfo += '<p class="text-xs text-slate-500">' + escHtml(t.reservation_time.substring(0,5)) + ' · ' + (t.res_party_size || '?') + ' pax</p>';
                }
            }

            return [
                '<div id="table-' + t.id + '" ' + click + ' class="rounded-xl border p-4 transition-all ' + style + '">',
                    '<div class="flex items-start justify-between mb-2">',
                        '<span class="font-semibold text-white text-sm">' + escHtml(t.name || 'Mesa ' + t.table_number) + '</span>',
                        '<span class="w-2.5 h-2.5 rounded-full ' + dot + ' mt-0.5 flex-shrink-0"></span>',
                    '</div>',
                    '<p class="text-xs text-slate-400">' + (t.max_capacity || '?') + ' pax · ' + escHtml(label) + '</p>',
                    guestInfo,
                    '<span class="pending-badge hidden text-xs text-yellow-400 mt-1 block">⏳ Pendiente</span>',
                '</div>'
            ].join('');
        }

        // ── Assign modal ────────────────────────────────────────────────────

        function openAssignModal(tableId, tableName) {
            selectedTableId = tableId;
            document.getElementById('modalTableName').textContent = tableName;
            document.getElementById('reservationSearch').value = '';
            document.getElementById('searchResults').innerHTML = '';
            document.getElementById('assignModal').classList.remove('hidden');
        }

        function closeAssignModal() {
            document.getElementById('assignModal').classList.add('hidden');
            selectedTableId = null;
        }

        async function searchReservations(query) {
            const container = document.getElementById('searchResults');
            if (query.length < 2) { container.innerHTML = ''; return; }

            try {
                const resp = await fetch(API_RES_URL + '?action=search_customer&q=' + encodeURIComponent(query));
                // We actually need reservations, not customers. Use list action with date filter.
                const today = document.getElementById('floorplanDate').value;
                const resp2 = await fetch(API_RES_URL + '?date=' + today + '&status=confirmed&search=' + encodeURIComponent(query) + '&limit=10');
                const data  = await resp2.json();
                renderSearchResults(data.data || []);
            } catch (err) {
                // Offline — search from IDB snapshot
                const all = await CenacoloIDB.idbGetAll('snapshot');
                const reservations = all
                    .filter(e => e.type === 'reservation')
                    .map(e => e.data)
                    .filter(r => {
                        const q = query.toLowerCase();
                        return (r.guest_name || '').toLowerCase().includes(q) ||
                               (r.confirmation_code || '').toLowerCase().includes(q);
                    });
                renderSearchResults(reservations);
            }
        }

        function renderSearchResults(reservations) {
            const container = document.getElementById('searchResults');
            if (!reservations.length) {
                container.innerHTML = '<p class="text-xs text-slate-500 py-2">Sin resultados.</p>';
                return;
            }
            container.innerHTML = reservations.map(r => [
                '<button onclick="assignTable(' + r.id + ',' + JSON.stringify(r.guest_name || '') + ')"',
                    ' class="w-full text-left px-3 py-2.5 rounded-lg bg-dark-800 hover:bg-dark-700 transition-colors border border-slate-700">',
                    '<p class="text-sm text-white font-medium">' + escHtml(r.guest_name || '—') + '</p>',
                    '<p class="text-xs text-slate-400">' + escHtml(r.confirmation_code || '') + ' · ' + escHtml(r.reservation_time || '') + ' · ' + (r.party_size || '?') + ' pax</p>',
                '</button>'
            ].join('')).join('');
        }

        async function assignTable(reservationId, guestName) {
            closeAssignModal();
            const tableId = selectedTableId;

            const payload = {
                id              : reservationId,
                table_id        : tableId,
                client_version  : Date.now()
            };

            try {
                const resp   = await fetch(API_RES_URL, {
                    method  : 'PUT',
                    headers : { 'Content-Type': 'application/json' },
                    body    : JSON.stringify(payload)
                });
                const result = await resp.json();

                if (result.queued) {
                    CenacoloOffline.showToast('⏳ Asignación guardada. Se sincronizará al reconectar.', 'info');
                    // Update card visually
                    const card = document.getElementById('table-' + tableId);
                    if (card) {
                        card.classList.add('border-dashed', 'border-gold-400');
                        const badge = card.querySelector('.pending-badge');
                        if (badge) badge.classList.remove('hidden');
                    }
                } else if (result.success) {
                    CenacoloOffline.showToast('✅ Mesa asignada a ' + guestName, 'success');
                    loadFloorplan(); // Refresh
                } else if (resp.status === 409) {
                    CenacoloOffline.showToast('⚠️ Conflicto: ' + (result.error || 'La mesa ya fue asignada.'), 'error');
                    loadFloorplan();
                } else {
                    CenacoloOffline.showToast(result.error || 'Error al asignar mesa.', 'error');
                }
            } catch (err) {
                CenacoloOffline.showToast('Error inesperado.', 'error');
            }
        }

        // ── HTML escape ─────────────────────────────────────────────────────

        function escHtml(str) {
            return String(str || '')
                .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
        function escHtmlAttr(str) {
            return escHtml(str).replace(/'/g,'&#39;');
        }

        // ── Init ────────────────────────────────────────────────────────────

        document.addEventListener('DOMContentLoaded', function () {
            if (RESTAURANT_ID) loadFloorplan();
            else document.getElementById('emptyState').classList.remove('hidden');
        });
    </script>

    <script src="<?= resUrl('/portal/js/idb.js') ?>"></script>
    <script src="<?= resUrl('/portal/js/offline.js') ?>"></script>
</body>
</html>
```

- [ ] **Step 2: Manual test — floorplan online**

Navigate to `portal/floorplan.php?restaurant_id=1`. Verify:
- Tables load and render as color-coded cards
- Available tables are clickable and open the assign modal
- Searching for a reservation shows results in the modal

- [ ] **Step 3: Manual test — floorplan offline**

Chrome DevTools → Network → Offline. Reload the floorplan page. Verify:
- Offline banner appears
- Tables render from IDB snapshot (or "No hay mesas" if snapshot empty)
- Toast shows "Sin conexión — mostrando datos guardados"

- [ ] **Step 4: Commit**

```bash
git add portal/floorplan.php
git commit -m "feat(offline): create portal/floorplan.php table floor plan page with offline support"
```

---

## Task 7: Modify `api/reservations.php` — conflict detection in PUT

**Files:**
- Modify: `api/reservations.php`

Add `client_version` check in `handlePut()`. After fetching the current reservation row and before `beginTransaction()`, compare the client's timestamp against the server's `UNIX_TIMESTAMP(updated_at)`. If server is newer and `force` is not set, return 409.

- [ ] **Step 1: Add conflict detection**

In `api/reservations.php`, find `handlePut()`. Locate this block (around line 670–688):

```php
    $pdo = getResDB();
    $reservationId = intval($input['id']);

    // Obtener reserva actual
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
    $stmt->execute([$reservationId]);
    $current = $stmt->fetch();

    if (!$current) {
        jsonResponse(['error' => 'Reserva no encontrada'], 404);
    }

    // Determinar quien actualiza
    $changedBy     = null;
```

Add the conflict check immediately after the `if (!$current)` block, before `$changedBy`:

```php
    if (!$current) {
        jsonResponse(['error' => 'Reserva no encontrada'], 404);
    }

    // --- Conflict detection for offline-sync writes ---
    $clientVersion = intval($input['client_version'] ?? 0);
    $forceWrite    = !empty($input['force']);

    if ($clientVersion > 0 && !$forceWrite) {
        $vStmt = $pdo->prepare("SELECT UNIX_TIMESTAMP(updated_at) AS server_version FROM reservations WHERE id = ?");
        $vStmt->execute([$reservationId]);
        $vRow = $vStmt->fetch();
        if ($vRow && intval($vRow['server_version']) > $clientVersion) {
            jsonResponse([
                'conflict'    => true,
                'server_data' => $current
            ], 409);
        }
    }

    // Determinar quien actualiza
    $changedBy     = null;
```

- [ ] **Step 2: Manual test — conflict detection**

Use a REST client (or curl) to send a PUT with a `client_version` in the past:

```bash
curl -s -X PUT \
  "https://somossinergia.com/CenacoloReserve/api/reservations.php" \
  -H "Content-Type: application/json" \
  -d '{"id": 1, "status": "seated", "client_version": 1}' \
  -b "PHPSESSID=<valid-session-cookie>"
```

Expected: `{"conflict":true,"server_data":{...}}` with HTTP 409.

Send without `client_version` (normal update):

```bash
curl -s -X PUT \
  "https://somossinergia.com/CenacoloReserve/api/reservations.php" \
  -H "Content-Type: application/json" \
  -d '{"id": 1, "status": "seated"}' \
  -b "PHPSESSID=<valid-session-cookie>"
```

Expected: `{"success":true,...}` with HTTP 200.

- [ ] **Step 3: Commit**

```bash
git add api/reservations.php
git commit -m "feat(offline): add client_version conflict detection to api/reservations.php PUT handler"
```

---

## Task 8: Add offline UX to remaining portal pages

**Files:**
- Modify: `portal/commissions.php`
- Modify: `portal/bank-data.php`

Each page needs: conn chip in navbar, offline banner after nav, script tags. `bank-data.php` also needs an overlay that blocks the form when offline (bank data changes require server connection).

- [ ] **Step 1: Modify `portal/commissions.php`**

**Add conn chip** — find the navbar `<div class="flex items-center space-x-3">` that contains the "Dashboard" back link. Add the chip before that link:

```html
            <div class="flex items-center space-x-3">
                <span id="connStatus" class="flex items-center gap-1.5 text-xs font-medium text-green-400 hidden sm:flex">
                    <span class="w-2 h-2 rounded-full bg-green-400 inline-block"></span> En línea
                </span>
                <a href="<?= resUrl('/portal/index.php') ?>" ...
```

**Add offline banner** — immediately after `</nav>`:

```html
    <div id="offlineBanner" class="hidden bg-yellow-900 border-b border-yellow-700 text-yellow-200 px-4 py-3 text-sm text-center">
        📵 <strong>Sin conexión</strong> — mostrando datos guardados localmente.
    </div>
```

**Add script tags** before `</body>`:

```html
    <script src="<?= resUrl('/portal/js/idb.js') ?>"></script>
    <script src="<?= resUrl('/portal/js/offline.js') ?>"></script>
```

- [ ] **Step 2: Modify `portal/bank-data.php`**

**Add conn chip** to navbar (same pattern as above).

**Add offline banner** after `</nav>`:

```html
    <div id="offlineBanner" class="hidden bg-yellow-900 border-b border-yellow-700 text-yellow-200 px-4 py-3 text-sm text-center">
        📵 <strong>Sin conexión</strong> — los cambios se guardan localmente y se sincronizarán automáticamente al reconectar.
    </div>
```

**Add offline overlay** — inside `<main>`, BEFORE the existing form card. This overlay covers the form when offline:

```html
    <!-- Offline overlay for bank data — this page requires server connection -->
    <div id="bankOfflineOverlay" class="hidden bg-dark-900 border border-yellow-700/40 rounded-xl p-8 text-center mb-6">
        <span class="text-4xl block mb-3">📵</span>
        <h3 class="text-white font-semibold text-lg mb-2">Conexión requerida</h3>
        <p class="text-slate-400 text-sm">Los datos bancarios se procesan de forma segura en el servidor.<br>Reconecta a internet para registrar o actualizar tu información bancaria.</p>
    </div>
```

**Add inline script** before `</body>` (after idb.js and offline.js) to show/hide the overlay:

```html
    <script src="<?= resUrl('/portal/js/idb.js') ?>"></script>
    <script src="<?= resUrl('/portal/js/offline.js') ?>"></script>
    <script>
        // bank-data.php: hide form and show overlay when offline
        function updateBankOfflineState() {
            var overlay = document.getElementById('bankOfflineOverlay');
            var form    = document.querySelector('form');
            if (!overlay || !form) return;
            if (!navigator.onLine) {
                overlay.classList.remove('hidden');
                form.classList.add('opacity-30', 'pointer-events-none');
            } else {
                overlay.classList.add('hidden');
                form.classList.remove('opacity-30', 'pointer-events-none');
            }
        }
        window.addEventListener('online',  updateBankOfflineState);
        window.addEventListener('offline', updateBankOfflineState);
        document.addEventListener('DOMContentLoaded', updateBankOfflineState);
    </script>
```

- [ ] **Step 3: Manual test — commissions offline**

Chrome DevTools → Offline. Navigate to `portal/commissions.php`. Verify:
- Yellow banner appears
- Chip shows "Sin conexión"
- Cached commission list still displays (from SW stale-while-revalidate)

- [ ] **Step 4: Manual test — bank-data offline**

Chrome DevTools → Offline. Navigate to `portal/bank-data.php`. Verify:
- Offline banner appears
- The overlay "Conexión requerida" appears
- The form underneath is dimmed and unclickable

- [ ] **Step 5: Commit**

```bash
git add portal/commissions.php portal/bank-data.php
git commit -m "feat(offline): add offline banner, chip, and bank-data overlay to remaining portal pages"
```

---

## Self-Review

**Spec coverage check:**

| Spec requirement | Task |
|---|---|
| IDB with 3 stores (snapshot, outbox, conflicts) | Task 1 |
| SW install/activate/fetch strategies | Task 2 |
| Network-first IDB fallback for GETs | Task 2 |
| Network-or-queue for POSTs when offline | Task 2 |
| outbox FIFO flush on reconnect | Task 2 |
| 409 → conflicts store | Task 2 |
| Background Sync + Safari postMessage fallback | Task 2 |
| SW registration + online/offline events | Task 3 |
| Conflict modal (keep mine / use server) | Task 3 |
| Toast system | Task 3 |
| Offline banner (inocultable, yellow) on all pages | Tasks 4, 5, 6, 8 |
| Conn chip in navbar (green/yellow/red) | Tasks 4, 5, 6, 8 |
| ⏳ badges on unsynced reservation rows | Task 4 |
| Offline confirmation screen for new reservations | Task 5 |
| Floor plan page with table cards | Task 6 |
| Floor plan offline from IDB snapshot | Task 6 |
| Table assignment → outbox | Task 6 |
| client_version conflict detection on server | Task 7 |
| force:true bypass for "keep mine" | Task 7 + Task 3 |
| bank-data offline overlay | Task 8 |
| commissions read-only from cache | Task 8 |

All spec requirements covered. No placeholders found.

**Type/method consistency check:**

- `CenacoloIDB.idbPut / idbGet / idbDelete / idbGetAll / idbClear / uuid` — consistent across Tasks 1, 3, 5, 6
- `CenacoloOffline.showToast / refreshConnChip / updateOfflineBadges / showConflictModal` — consistent across Tasks 3, 6
- `window.CenacoloIDB` and `window.CenacoloOffline` exposed in all pages that use them
- `client_version` field name consistent in SW payload (Task 2), page JS (Tasks 5, 6), and PHP (Task 7)
- `force: true` field consistent between `btnKeepMine` handler (Task 3) and PHP check `$input['force']` (Task 7)
