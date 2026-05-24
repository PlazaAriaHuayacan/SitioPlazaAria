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
    if (!window.indexedDB) {
      return Promise.reject(new Error('IndexedDB not available in this browser'));
    }
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
    if (window.crypto && window.crypto.randomUUID) {
      return window.crypto.randomUUID();
    }
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
