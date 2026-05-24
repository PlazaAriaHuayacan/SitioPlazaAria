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
                    <option value="<?= (int) $r['id'] ?>" <?= $r['id'] == $selectedRestaurantId ? 'selected' : '' ?>>
                        <?= resSanitize($r['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>

            <!-- Stats legend -->
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
        var RESTAURANT_ID    = <?= (int) $selectedRestaurantId ?>;
        var API_TABLES_URL   = '<?= resUrl('/api/tables.php') ?>';
        var API_RES_URL      = '<?= resUrl('/api/reservations.php') ?>';

        var currentTables   = [];
        var selectedTableId = null;

        // ── Load floorplan ──────────────────────────────────────────────────────

        function loadFloorplan() {
            var date = document.getElementById('floorplanDate').value;
            document.getElementById('loadingSpinner').classList.remove('hidden');
            document.getElementById('tableGrid').classList.add('hidden');
            document.getElementById('emptyState').classList.add('hidden');

            fetch(API_TABLES_URL + '?action=floorplan&restaurant_id=' + RESTAURANT_ID + '&date=' + encodeURIComponent(date))
                .then(function (resp) { return resp.json(); })
                .then(function (data) {
                    if (data.offline) {
                        return renderFromIDB();
                    }
                    if (data.success && data.data) {
                        currentTables = data.data;
                        // Save snapshot to IDB for offline use
                        var fetchedAt = Date.now();
                        var chain = Promise.resolve();
                        data.data.forEach(function (t) {
                            chain = chain.then(function () {
                                return CenacoloIDB.idbPut('snapshot', { type: 'table', id: t.id, data: t, fetchedAt: fetchedAt });
                            });
                        });
                        return chain.then(function () { renderTables(data.data); });
                    } else {
                        document.getElementById('emptyState').classList.remove('hidden');
                    }
                })
                .catch(function (err) {
                    console.warn('[Floorplan] Network error, trying IDB snapshot:', err);
                    return renderFromIDB();
                })
                .finally(function () {
                    document.getElementById('loadingSpinner').classList.add('hidden');
                });
        }

        function renderFromIDB() {
            return CenacoloIDB.idbGetAll('snapshot').then(function (all) {
                var tables = all
                    .filter(function (e) { return e.type === 'table'; })
                    .map(function (e) { return e.data; });

                if (tables.length === 0) {
                    document.getElementById('emptyState').classList.remove('hidden');
                    return;
                }

                // Check if data is stale (> 24h)
                var entry    = all.find(function (e) { return e.type === 'table'; });
                var fetchedAt = entry ? entry.fetchedAt : 0;
                var stale    = (Date.now() - fetchedAt) > 86400000;

                if (stale) {
                    CenacoloOffline.showToast('⚠️ Mostrando datos guardados de hace más de 24 horas.', 'info');
                } else {
                    CenacoloOffline.showToast('📴 Sin conexión — mostrando datos guardados localmente.', 'info');
                }
                currentTables = tables;
                renderTables(tables);
            });
        }

        function renderTables(tables) {
            var grid = document.getElementById('tableGrid');
            if (!tables.length) {
                document.getElementById('emptyState').classList.remove('hidden');
                return;
            }

            // Group by section
            var sections = {};
            tables.forEach(function (t) {
                var sec = t.section_name || 'Sin sección';
                if (!sections[sec]) sections[sec] = [];
                sections[sec].push(t);
            });

            var html = '';
            Object.keys(sections).forEach(function (sectionName) {
                var secTables = sections[sectionName];
                html += '<div class="mb-8">';
                html += '<h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">' + escHtml(sectionName) + '</h3>';
                html += '<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">';
                secTables.forEach(function (t) { html += renderTableCard(t); });
                html += '</div></div>';
            });

            grid.innerHTML = html;
            grid.classList.remove('hidden');

            // Mark pending assignments from outbox with dashed border
            CenacoloIDB.idbGetAll('outbox').then(function (entries) {
                entries.forEach(function (e) {
                    if (e.action === 'assign_table' && e.payload && e.payload.table_id) {
                        var card = document.getElementById('table-' + e.payload.table_id);
                        if (card) {
                            card.classList.add('border-dashed', 'border-gold-400');
                            card.classList.remove('border-slate-700');
                            var badge = card.querySelector('.pending-badge');
                            if (badge) badge.classList.remove('hidden');
                        }
                    }
                });
            }).catch(function (err) {
                console.warn('[Floorplan] Could not read outbox for pending marks:', err);
            });
        }

        function renderTableCard(t) {
            var statusStyles = {
                available   : 'bg-green-900/30 border-green-700/50 hover:border-green-500 cursor-pointer',
                occupied    : 'bg-red-900/30   border-red-700/50',
                reserved    : 'bg-blue-900/30  border-blue-700/50',
                blocked     : 'bg-slate-800    border-slate-700 opacity-60',
                maintenance : 'bg-slate-800    border-slate-700 opacity-60'
            };
            var dotStyles = {
                available   : 'bg-green-500',
                occupied    : 'bg-red-500',
                reserved    : 'bg-blue-500',
                blocked     : 'bg-slate-500',
                maintenance : 'bg-slate-500'
            };
            var statusLabels = {
                available   : 'Disponible',
                occupied    : 'Ocupada',
                reserved    : 'Reservada',
                blocked     : 'Bloqueada',
                maintenance : 'Mantenimiento'
            };

            var style = statusStyles[t.status] || statusStyles.blocked;
            var dot   = dotStyles[t.status]    || dotStyles.blocked;
            var label = statusLabels[t.status] || escHtml(t.status);
            var tableName = t.name || ('Mesa ' + t.table_number);

            // onclick only for available tables — using data attributes to avoid inline JS injection
            var clickAttr = t.status === 'available'
                ? 'onclick="openAssignModal(' + (int(t.id)) + ', \'' + escHtmlAttr(tableName) + '\')"'
                : '';

            var guestInfo = '';
            if (t.guest_name) {
                guestInfo += '<p class="text-xs text-slate-300 mt-1 truncate">' + escHtml(t.guest_name) + '</p>';
                if (t.reservation_time) {
                    guestInfo += '<p class="text-xs text-slate-500">' + escHtml(t.reservation_time.substring(0, 5)) + ' &middot; ' + escHtml(String(t.res_party_size || '?')) + ' pax</p>';
                }
            }

            return [
                '<div id="table-' + (int(t.id)) + '" ' + clickAttr + ' class="rounded-xl border p-4 transition-all ' + style + '">',
                    '<div class="flex items-start justify-between mb-2">',
                        '<span class="font-semibold text-white text-sm">' + escHtml(tableName) + '</span>',
                        '<span class="w-2.5 h-2.5 rounded-full ' + dot + ' mt-0.5 flex-shrink-0"></span>',
                    '</div>',
                    '<p class="text-xs text-slate-400">' + escHtml(String(t.max_capacity || '?')) + ' pax &middot; ' + escHtml(label) + '</p>',
                    guestInfo,
                    '<span class="pending-badge hidden text-xs text-yellow-400 mt-1 block">⏳ Pendiente</span>',
                '</div>'
            ].join('');
        }

        // Integer helper used in template string context (no PHP available here)
        function int(v) { return parseInt(v, 10) || 0; }

        // ── Assign modal ─────────────────────────────────────────────────────────

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

        function searchReservations(query) {
            var container = document.getElementById('searchResults');
            if (query.length < 2) { container.innerHTML = ''; return; }

            var date = document.getElementById('floorplanDate').value;

            fetch(API_RES_URL + '?date=' + encodeURIComponent(date) + '&status=confirmed&search=' + encodeURIComponent(query) + '&limit=10')
                .then(function (resp) { return resp.json(); })
                .then(function (data) { renderSearchResults(data.data || []); })
                .catch(function () {
                    // Offline — search from IDB snapshot
                    CenacoloIDB.idbGetAll('snapshot').then(function (all) {
                        var q = query.toLowerCase();
                        var reservations = all
                            .filter(function (e) { return e.type === 'reservation'; })
                            .map(function (e) { return e.data; })
                            .filter(function (r) {
                                return (r.guest_name || '').toLowerCase().indexOf(q) !== -1 ||
                                       (r.confirmation_code || '').toLowerCase().indexOf(q) !== -1;
                            });
                        renderSearchResults(reservations);
                    }).catch(function (err) {
                        console.warn('[Floorplan] IDB search failed:', err);
                        container.innerHTML = '<p class="text-xs text-slate-500 py-2">Sin resultados (sin conexión).</p>';
                    });
                });
        }

        function renderSearchResults(reservations) {
            var container = document.getElementById('searchResults');
            if (!reservations.length) {
                container.innerHTML = '<p class="text-xs text-slate-500 py-2">Sin resultados.</p>';
                return;
            }
            container.innerHTML = reservations.map(function (r) {
                return [
                    '<button onclick="assignTable(' + int(r.id) + ', ' + JSON.stringify(escHtml(r.guest_name || '')) + ')"',
                        ' class="w-full text-left px-3 py-2.5 rounded-lg bg-dark-800 hover:bg-dark-700 transition-colors border border-slate-700">',
                        '<p class="text-sm text-white font-medium">' + escHtml(r.guest_name || '—') + '</p>',
                        '<p class="text-xs text-slate-400">' + escHtml(r.confirmation_code || '') + ' &middot; ' + escHtml(r.reservation_time || '') + ' &middot; ' + int(r.party_size) + ' pax</p>',
                    '</button>'
                ].join('');
            }).join('');
        }

        function assignTable(reservationId, guestName) {
            closeAssignModal();
            var tableId = selectedTableId;

            var payload = {
                id             : reservationId,
                table_id       : tableId,
                client_version : Date.now()
            };

            fetch(API_RES_URL, {
                method  : 'PUT',
                headers : { 'Content-Type': 'application/json' },
                body    : JSON.stringify(payload)
            })
            .then(function (resp) {
                return resp.json().then(function (result) {
                    return { status: resp.status, result: result };
                });
            })
            .then(function (obj) {
                var result = obj.result;
                var status = obj.status;

                if (result.queued) {
                    CenacoloOffline.showToast('⏳ Asignación guardada. Se sincronizará al reconectar.', 'info');
                    // Update card visually to show pending state
                    var card = document.getElementById('table-' + tableId);
                    if (card) {
                        card.classList.add('border-dashed', 'border-gold-400');
                        var badge = card.querySelector('.pending-badge');
                        if (badge) badge.classList.remove('hidden');
                    }
                } else if (status === 409) {
                    CenacoloOffline.showToast('⚠️ Conflicto: ' + (result.error || 'La mesa ya fue asignada.'), 'error');
                    loadFloorplan();
                } else if (result.success) {
                    CenacoloOffline.showToast('✅ Mesa asignada a ' + guestName, 'success');
                    loadFloorplan();
                } else {
                    CenacoloOffline.showToast(result.error || 'Error al asignar mesa.', 'error');
                }
            })
            .catch(function (err) {
                console.warn('[Floorplan] assignTable failed:', err);
                CenacoloOffline.showToast('Error inesperado al asignar mesa.', 'error');
            });
        }

        // ── HTML escape helpers ──────────────────────────────────────────────────

        function escHtml(str) {
            return String(str || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function escHtmlAttr(str) {
            return escHtml(str).replace(/'/g, '&#39;');
        }

        // ── Init ─────────────────────────────────────────────────────────────────

        document.addEventListener('DOMContentLoaded', function () {
            if (RESTAURANT_ID) {
                loadFloorplan();
            } else {
                document.getElementById('loadingSpinner').classList.add('hidden');
                document.getElementById('emptyState').classList.remove('hidden');
            }
        });
    </script>

    <script src="<?= resUrl('/portal/js/idb.js') ?>"></script>
    <script src="<?= resUrl('/portal/js/offline.js') ?>"></script>
</body>
</html>
