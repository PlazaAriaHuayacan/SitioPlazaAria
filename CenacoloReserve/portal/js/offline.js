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
      CenacoloIDB.idbGetAll('outbox').then(function (entries) {
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
      });
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
              '<p class="text-xs text-slate-500 mt-2">' + escHtml(myTime) + '</p>',
            '</div>',
            '<div class="bg-slate-800 rounded-lg p-3">',
              '<p class="text-xs text-green-400 font-semibold uppercase tracking-wider mb-2">Versión del servidor</p>',
              '<pre class="text-xs text-slate-200 whitespace-pre-wrap overflow-auto max-h-40">' + escHtml(srvJson) + '</pre>',
              '<p class="text-xs text-slate-500 mt-2">' + escHtml(srvTime) + '</p>',
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
