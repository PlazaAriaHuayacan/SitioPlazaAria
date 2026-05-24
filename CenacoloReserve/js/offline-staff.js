/**
 * Cenacolo Staff Offline — page-side Service Worker manager + UX layer.
 * Must be loaded AFTER js/idb.js.
 * Uses window.CenacoloStaffIDB for IndexedDB access.
 * Uses showToast() from includes/layout.php (available via global scope at runtime).
 *
 * Exposes window.CenacoloStaffOffline = { refreshConnChip, updateOfflineBadges, showConflictModal }
 */
(function () {
  'use strict';

  var SW_PATH  = '/CenacoloReserve/sw-staff.js';
  var SW_SCOPE = '/CenacoloReserve/';

  // ── Register Service Worker ────────────────────────────────────────────────

  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(SW_PATH, { scope: SW_SCOPE })
        .then(function (reg) {
          if ('sync' in reg) {
            navigator.serviceWorker.ready
              .then(function (r) { return r.sync.register('cenacolo-staff-outbox'); })
              .catch(function () { /* not critical */ });
          }
        })
        .catch(function (err) {
          console.warn('[Cenacolo Staff SW] Registration failed:', err);
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
    toast('📶 Conexión restaurada. Sincronizando...', 'info');
    if (navigator.serviceWorker && navigator.serviceWorker.controller) {
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
    // Expose offline flag so page scripts can check it
    window._staffOfflineMode = !!show;
  }

  // ── Connection chip ────────────────────────────────────────────────────────

  function refreshConnChip() {
    var chip = document.getElementById('connStatus');
    if (!chip) return;

    CenacoloStaffIDB.idbGetAll('conflicts').then(function (conflicts) {
      if (conflicts.length > 0) {
        chip.className = 'flex items-center gap-1.5 text-xs font-medium text-red-400 cursor-pointer underline';
        chip.innerHTML = '<span class="w-2 h-2 rounded-full bg-red-400 animate-pulse inline-block"></span> ⚠️ ' + conflicts.length + ' conflicto(s)';
        chip.onclick   = function () { showConflictModal(conflicts); };
        return;
      }
      CenacoloStaffIDB.idbGetAll('outbox').then(function (entries) {
        var pending = entries.filter(function (e) {
          return e.status === 'pending' || e.status === 'syncing';
        }).length;

        if (!navigator.onLine) {
          chip.className = 'flex items-center gap-1.5 text-xs font-medium text-yellow-400';
          chip.innerHTML = '<span class="w-2 h-2 rounded-full border-2 border-yellow-400 inline-block"></span> Sin conexión' +
            (pending > 0 ? ' (' + pending + ' pendientes)' : '');
          chip.onclick   = null;
        } else {
          chip.className = 'flex items-center gap-1.5 text-xs font-medium text-green-400';
          chip.innerHTML = '<span class="w-2 h-2 rounded-full bg-green-400 inline-block"></span> En línea';
          chip.onclick   = null;
        }
      }).catch(function (err) {
        console.warn('[Cenacolo Staff] refreshConnChip outbox read failed:', err);
      });
    }).catch(function () {
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
    CenacoloStaffIDB.idbGetAll('outbox').then(function (entries) {
      var pendingIds = {};
      entries.forEach(function (e) {
        if ((e.status === 'pending' || e.status === 'syncing') && e.payload) {
          var id = e.payload.id || e.payload.reservation_id;
          if (id) pendingIds[id] = true;
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
    }).catch(function (err) {
      console.warn('[Cenacolo Staff] updateOfflineBadges failed:', err);
    });
  }

  // ── SW message handler ─────────────────────────────────────────────────────

  function handleSWMessage(event) {
    var data = event.data || {};
    if (data.type === 'SYNC_COMPLETE') {
      refreshConnChip();
      updateOfflineBadges();
      if (data.conflicts > 0) {
        CenacoloStaffIDB.idbGetAll('conflicts')
          .then(showConflictModal)
          .catch(function (err) { console.warn('[Cenacolo Staff] handleSWMessage conflicts read failed:', err); });
      } else if (data.synced > 0) {
        toast('✅ ' + data.synced + ' cambio(s) sincronizado(s)', 'success');
      }
    }
  }

  // ── Conflict modal ─────────────────────────────────────────────────────────

  var _modalOpen = false;

  function showConflictModal(conflicts) {
    if (!conflicts || conflicts.length === 0) return;
    if (_modalOpen) return;
    _modalOpen = true;

    var actionLabels = {
      update_reservation_status : 'Cambio de estado',
      assign_table              : 'Asignación de mesa',
      create_reservation        : 'Nueva reserva',
      update_table_status       : 'Estado de mesa'
    };

    function renderAt(idx) {
      var existing = document.getElementById('staffConflictModal');
      if (existing) existing.remove();

      var c           = conflicts[idx];
      var actionLabel = actionLabels[c.action] || c.action;
      var myJson      = JSON.stringify(c.payload,     null, 2);
      var srvJson     = JSON.stringify(c.server_data, null, 2);
      var myTime      = new Date(c.timestamp).toLocaleTimeString('es-MX');
      var srvTime     = new Date(c.conflicted_at).toLocaleTimeString('es-MX');

      var modal = document.createElement('div');
      modal.id        = 'staffConflictModal';
      modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm px-4';
      modal.innerHTML = [
        '<div class="bg-dark-900 border border-red-700/50 rounded-xl shadow-2xl max-w-lg w-full p-6">',
          '<div class="flex items-center gap-2 mb-4">',
            '<span class="text-red-400 text-xl">⚠️</span>',
            '<h3 class="text-white font-semibold text-lg flex-1">Conflicto: ' + escHtml(actionLabel) + '</h3>',
            '<span class="text-xs text-slate-400">' + (idx + 1) + ' de ' + conflicts.length + '</span>',
          '</div>',
          '<div class="grid grid-cols-2 gap-3 mb-5">',
            '<div class="bg-dark-800 rounded-lg p-3">',
              '<p class="text-xs text-yellow-400 font-semibold uppercase tracking-wider mb-2">Tu versión (offline)</p>',
              '<pre class="text-xs text-slate-200 whitespace-pre-wrap overflow-auto max-h-40">' + escHtml(myJson) + '</pre>',
              '<p class="text-xs text-slate-500 mt-2">' + escHtml(myTime) + '</p>',
            '</div>',
            '<div class="bg-dark-800 rounded-lg p-3">',
              '<p class="text-xs text-green-400 font-semibold uppercase tracking-wider mb-2">Versión del servidor</p>',
              '<pre class="text-xs text-slate-200 whitespace-pre-wrap overflow-auto max-h-40">' + escHtml(srvJson) + '</pre>',
              '<p class="text-xs text-slate-500 mt-2">' + escHtml(srvTime) + '</p>',
            '</div>',
          '</div>',
          '<div class="flex gap-3">',
            '<button id="btnStaffKeepMine"  class="flex-1 py-2.5 bg-yellow-900/40 border border-yellow-700/40 text-yellow-300 rounded-lg text-sm font-semibold hover:bg-yellow-900/60 transition-colors">Conservar la mía</button>',
            '<button id="btnStaffUseServer" class="flex-1 py-2.5 bg-green-900/40  border border-green-700/40  text-green-300  rounded-lg text-sm font-semibold hover:bg-green-900/60  transition-colors">Usar la del servidor</button>',
          '</div>',
        '</div>'
      ].join('');

      document.body.appendChild(modal);

      document.getElementById('btnStaffKeepMine').onclick = function () {
        var forcePayload = Object.assign({}, c.payload, { force: true });
        fetch(c.url, {
          method  : c.method,
          headers : { 'Content-Type': 'application/json' },
          body    : JSON.stringify(forcePayload)
        }).then(function (resp) {
          if (resp.ok) {
            CenacoloStaffIDB.idbDelete('conflicts', c.id)
              .then(function () {
                conflicts.splice(idx, 1);
                next();
              })
              .catch(function (err) {
                console.warn('[Cenacolo Staff] Failed to delete conflict from IDB:', err);
                toast('Error al limpiar el conflicto. Recarga la página.', 'error');
              });
          } else {
            toast('Error al forzar el cambio. Intenta más tarde.', 'error');
          }
        }).catch(function () {
          toast('Sin conexión. Intenta más tarde.', 'error');
        });
      };

      document.getElementById('btnStaffUseServer').onclick = function () {
        CenacoloStaffIDB.idbDelete('conflicts', c.id)
          .then(function () {
            conflicts.splice(idx, 1);
            next();
          })
          .catch(function (err) {
            console.warn('[Cenacolo Staff] Failed to delete conflict from IDB:', err);
            toast('Error al limpiar el conflicto. Recarga la página.', 'error');
          });
      };
    }

    function next() {
      var modal = document.getElementById('staffConflictModal');
      if (modal) modal.remove();
      if (conflicts.length === 0) {
        _modalOpen = false;
        toast('✅ Todos los conflictos resueltos', 'success');
        refreshConnChip();
      } else {
        renderAt(0);
      }
    }

    renderAt(0);
  }

  // ── Toast helper ───────────────────────────────────────────────────────────
  // Uses showToast() from layout.php global scripts (available at event-handler time).

  function toast(message, type) {
    if (typeof showToast === 'function') {
      showToast(message, type || 'success');
    } else {
      // Fallback if called before layout.php script loads (shouldn't happen)
      console.info('[Cenacolo Staff Toast]', type, message);
    }
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
    toggleOfflineBanner(!navigator.onLine);
    refreshConnChip();
    updateOfflineBadges();
  });

  // ── Public API ─────────────────────────────────────────────────────────────

  window.CenacoloStaffOffline = {
    refreshConnChip    : refreshConnChip,
    updateOfflineBadges: updateOfflineBadges,
    showConflictModal  : showConflictModal
  };

}());
