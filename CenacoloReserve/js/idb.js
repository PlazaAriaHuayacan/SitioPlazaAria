/**
 * Cenacolo Staff — IndexedDB helper (page-side).
 * Exposes window.CenacoloStaffIDB = { openDB, idbGet, idbPut, idbDelete, idbGetAll, idbClear, uuid }
 *
 * Uses a separate DB name ('cenacolo-offline-staff') from the concierge portal
 * so the two audiences never share outbox entries.
 */
(function () {
  'use strict';

  var DB_NAME    = 'cenacolo-offline-staff';
  var DB_VERSION = 1;
  var _db        = null;

  function openDB() {
    if (_db) return Promise.resolve(_db);

    if (!window.indexedDB) {
      return Promise.reject(new Error('IndexedDB not available'));
    }

    return new Promise(function (resolve, reject) {
      var req = window.indexedDB.open(DB_NAME, DB_VERSION);

      req.onupgradeneeded = function (event) {
        var db = event.target.result;

        // snapshot: last-known-good API responses keyed by [type, id]
        if (!db.objectStoreNames.contains('snapshot')) {
          db.createObjectStore('snapshot', { keyPath: ['type', 'id'] });
        }

        // outbox: pending writes (status: pending | syncing | done | failed)
        if (!db.objectStoreNames.contains('outbox')) {
          var ob = db.createObjectStore('outbox', { keyPath: 'id' });
          ob.createIndex('by_status',    'status',    { unique: false });
          ob.createIndex('by_timestamp', 'timestamp', { unique: false });
        }

        // conflicts: 409 server responses awaiting resolution
        if (!db.objectStoreNames.contains('conflicts')) {
          db.createObjectStore('conflicts', { keyPath: 'id' });
        }
      };

      req.onsuccess = function (event) {
        _db = event.target.result;
        resolve(_db);
      };

      req.onerror = function (event) {
        reject(event.target.error);
      };
    });
  }

  function idbGet(storeName, key) {
    return openDB().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx  = db.transaction(storeName, 'readonly');
        var req = tx.objectStore(storeName).get(key);
        req.onsuccess = function () { resolve(req.result || null); };
        req.onerror   = function () { reject(req.error); };
      });
    });
  }

  function idbPut(storeName, value) {
    return openDB().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx  = db.transaction(storeName, 'readwrite');
        var req = tx.objectStore(storeName).put(value);
        req.onsuccess = function () { resolve(req.result); };
        req.onerror   = function () { reject(req.error); };
      });
    });
  }

  function idbDelete(storeName, key) {
    return openDB().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx  = db.transaction(storeName, 'readwrite');
        var req = tx.objectStore(storeName).delete(key);
        req.onsuccess = function () { resolve(); };
        req.onerror   = function () { reject(req.error); };
      });
    });
  }

  function idbGetAll(storeName) {
    return openDB().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx  = db.transaction(storeName, 'readonly');
        var req = tx.objectStore(storeName).getAll();
        req.onsuccess = function () { resolve(req.result || []); };
        req.onerror   = function () { reject(req.error); };
      });
    });
  }

  function idbClear(storeName) {
    return openDB().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx  = db.transaction(storeName, 'readwrite');
        var req = tx.objectStore(storeName).clear();
        req.onsuccess = function () { resolve(); };
        req.onerror   = function () { reject(req.error); };
      });
    });
  }

  function uuid() {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
      return crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
      var r = Math.random() * 16 | 0;
      var v = c === 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
    });
  }

  window.CenacoloStaffIDB = {
    openDB    : openDB,
    idbGet    : idbGet,
    idbPut    : idbPut,
    idbDelete : idbDelete,
    idbGetAll : idbGetAll,
    idbClear  : idbClear,
    uuid      : uuid
  };

}());
