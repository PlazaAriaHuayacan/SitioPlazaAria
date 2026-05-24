<?php
/**
 * Cenacolo Reservas - Floor Plan / Gestion de Mesas v1.0
 * Vista visual interactiva del plano del restaurante
 * Administracion de secciones y mesas
 *
 * Modes:
 *   - Default (visual): Floor plan con mesas posicionadas, estados en tiempo real
 *   - ?view=manage:     Admin CRUD de secciones y mesas
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/layout.php';

$user = requireResLogin();
logResAccess($user['id'], 'floorplan', 'staff');

$pdo = getResDB();
$today = date('Y-m-d');
// Soporte para navegación de fecha (hasta 30 días adelante, 7 atrás)
$selectedDate = isset($_GET['date']) ? $_GET['date'] : $today;
$maxDate = date('Y-m-d', strtotime('+30 days'));
$minDate = date('Y-m-d', strtotime('-7 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) || $selectedDate > $maxDate || $selectedDate < $minDate) {
    $selectedDate = $today;
}
$currentRestaurant = $_SESSION['res_current_restaurant'] ?? 'all';
$view = $_GET['view'] ?? 'visual';
$isAdmin = isResAdmin();

// If "manage" view, require admin
if ($view === 'manage' && !$isAdmin) {
    header("Location: " . resUrl("/floorplan.php"));
    exit;
}

// ======= DATA QUERIES =======

$restaurantFilter = '';
$params = [];
if ($currentRestaurant !== 'all') {
    $restaurantFilter = ' AND t.restaurant_id = ?';
    $params[] = $currentRestaurant;
}

// Get all tables
$tableQuery = "
    SELECT t.id, t.restaurant_id, t.section_id, t.table_number, t.name AS table_name,
           t.min_capacity, t.max_capacity, t.is_combinable, t.combine_group,
           t.pos_x, t.pos_y, t.rotation, t.shape, t.size, t.status, t.active,
           rs.name AS section_name, rs.slug AS section_slug,
           rest.name AS restaurant_name
    FROM tables t
    LEFT JOIN restaurant_sections rs ON t.section_id = rs.id
    LEFT JOIN restaurants rest ON t.restaurant_id = rest.id
    WHERE t.active = 1 {$restaurantFilter}
    ORDER BY rs.sort_order ASC, t.table_number ASC
";
$stmt = $pdo->prepare($tableQuery);
$stmt->execute($params);
$allTables = $stmt->fetchAll();

// Get ALL reservations with table assignments for selected date
$tableResFilter = str_replace('t.restaurant_id', 'r.restaurant_id', $restaurantFilter);
$resStmt = $pdo->prepare("
    SELECT r.id AS reservation_id, r.table_id, r.guest_name, r.party_size AS res_party_size,
           r.reservation_time, r.end_time, r.status AS res_status, r.confirmation_code,
           r.occasion, r.special_requests, r.guest_phone, r.guest_email, r.customer_id
    FROM reservations r
    WHERE r.reservation_date = ?
      AND r.status IN ('confirmed', 'seated', 'pending')
      AND r.table_id IS NOT NULL
      {$tableResFilter}
    ORDER BY r.reservation_time ASC
");
$resStmt->execute(array_merge([$selectedDate], $params));
$allTableReservations = $resStmt->fetchAll();

// Group reservations by table_id
$reservationsByTable = [];
foreach ($allTableReservations as $tr) {
    $reservationsByTable[$tr['table_id']][] = $tr;
}

// Attach reservation data to each table (primary = first active/seated, all = full list)
foreach ($allTables as &$tbl) {
    $tbl['table_reservations'] = $reservationsByTable[$tbl['id']] ?? [];
    // Find primary reservation (seated first, then current/next confirmed)
    $primary = null;
    foreach ($tbl['table_reservations'] as $tr) {
        if ($tr['res_status'] === 'seated') { $primary = $tr; break; }
    }
    if (!$primary) {
        foreach ($tbl['table_reservations'] as $tr) {
            if (in_array($tr['res_status'], ['confirmed', 'pending'])) { $primary = $tr; break; }
        }
    }
    if ($primary) {
        $tbl['reservation_id'] = $primary['reservation_id'];
        $tbl['guest_name'] = $primary['guest_name'];
        $tbl['res_party_size'] = $primary['res_party_size'];
        $tbl['reservation_time'] = $primary['reservation_time'];
        $tbl['res_status'] = $primary['res_status'];
        $tbl['confirmation_code'] = $primary['confirmation_code'];
        $tbl['occasion'] = $primary['occasion'];
        $tbl['special_requests'] = $primary['special_requests'];
        $tbl['guest_phone'] = $primary['guest_phone'];
    } else {
        $tbl['reservation_id'] = null;
        $tbl['guest_name'] = null;
        $tbl['res_party_size'] = null;
        $tbl['reservation_time'] = null;
        $tbl['res_status'] = null;
        $tbl['confirmation_code'] = null;
        $tbl['occasion'] = null;
        $tbl['special_requests'] = null;
        $tbl['guest_phone'] = null;
    }
}
unset($tbl);

// Get sections
$sectionFilter = '';
$sectionParams = [];
if ($currentRestaurant !== 'all') {
    $sectionFilter = ' WHERE rs.restaurant_id = ?';
    $sectionParams[] = $currentRestaurant;
}
$stmt = $pdo->prepare("
    SELECT rs.*, rest.name AS restaurant_name
    FROM restaurant_sections rs
    JOIN restaurants rest ON rs.restaurant_id = rest.id
    {$sectionFilter}
    ORDER BY rs.restaurant_id, rs.sort_order
");
$stmt->execute($sectionParams);
$allSections = $stmt->fetchAll();

// Occupancy stats
$statsFilter = '';
$statsParams = [$selectedDate];
if ($currentRestaurant !== 'all') {
    $statsFilter = ' AND t.restaurant_id = ?';
    $statsParams[] = $currentRestaurant;
}
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_tables,
        COALESCE(SUM(CASE WHEN t.status = 'available' THEN 1 ELSE 0 END), 0) AS available,
        COALESCE(SUM(CASE WHEN t.status = 'occupied' THEN 1 ELSE 0 END), 0) AS occupied,
        COALESCE(SUM(CASE WHEN t.status = 'reserved' THEN 1 ELSE 0 END), 0) AS reserved,
        COALESCE(SUM(CASE WHEN t.status = 'blocked' THEN 1 ELSE 0 END), 0) AS blocked,
        COALESCE(SUM(CASE WHEN t.status = 'maintenance' THEN 1 ELSE 0 END), 0) AS maintenance,
        COALESCE(SUM(t.max_capacity), 0) AS total_capacity,
        COALESCE((SELECT SUM(rv.party_size) FROM reservations rv
                  LEFT JOIN tables tb ON rv.table_id = tb.id
                  WHERE rv.reservation_date = ?
                    AND rv.status IN ('seated')
                    {$statsFilter}), 0) AS current_covers
    FROM tables t
    WHERE t.active = 1 {$statsFilter}
");
$stmt->execute(array_merge($statsParams, $currentRestaurant !== 'all' ? [$currentRestaurant] : []));
$stats = $stmt->fetch();

// Capacity per shift for current time indicator
$floorCapacity = null;
if ($currentRestaurant !== 'all') {
    $now = date('H:i:s');
    $floorCapacity = checkCapacity($pdo, $currentRestaurant, $selectedDate, $now, 0);
}

// Restaurants list (for manage mode)
$restaurants = getActiveRestaurants();

// Reservations for the left panel (visual mode) - uses selectedDate
$todayResList = [];
if ($currentRestaurant !== 'all') {
    $todayResQuery = $pdo->prepare("
        SELECT r.id, r.guest_name, r.guest_phone, r.guest_email, r.party_size,
               r.reservation_time, r.end_time, r.status, r.channel, r.table_id,
               r.confirmation_code, r.occasion, r.special_requests, r.customer_id,
               r.restaurant_id,
               t.table_number, s.name as section_name,
               c.vip_level, c.total_visits
        FROM reservations r
        LEFT JOIN tables t ON r.table_id = t.id
        LEFT JOIN restaurant_sections s ON t.section_id = s.id
        LEFT JOIN customers c ON r.customer_id = c.id
        WHERE r.reservation_date = ? AND r.restaurant_id = ?
          AND r.status NOT IN ('cancelled')
        ORDER BY r.reservation_time ASC
    ");
    $todayResQuery->execute([$selectedDate, $currentRestaurant]);
    $todayResList = $todayResQuery->fetchAll();
}

renderHeader('Floor Plan', $user);
?>

<?php if ($currentRestaurant === 'all'): ?>
<!-- Prompt to select a restaurant -->
<div class="flex items-center justify-center min-h-[60vh] fade-in">
    <div class="text-center max-w-md">
        <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-gold-500/10 flex items-center justify-center">
            <svg class="w-10 h-10 text-gold-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
            </svg>
        </div>
        <h3 class="text-xl font-display text-white font-semibold mb-2">Selecciona un Restaurante</h3>
        <p class="text-dark-400 text-sm mb-6">El Floor Plan muestra las mesas de un restaurante especifico. Selecciona uno desde el menu lateral para continuar.</p>
        <div class="space-y-2">
            <?php foreach ($restaurants as $r): ?>
                <button onclick="switchRestaurant(<?= $r['id'] ?>)"
                        class="w-full px-4 py-3 bg-dark-800 border border-dark-600 rounded-lg text-white hover:border-gold-500 hover:bg-dark-700 transition-all text-sm font-medium">
                    <?= resSanitize($r['name']) ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php elseif ($view === 'manage'): ?>
<!-- ============================================= -->
<!-- MODE 2: TABLE MANAGEMENT (Admin Only)         -->
<!-- ============================================= -->
<div class="fade-in">
    <!-- Header with back link -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-lg font-semibold text-white">Administrar Secciones y Mesas</h3>
            <p class="text-sm text-dark-400 mt-1">Configura las secciones y mesas del restaurante</p>
        </div>
        <a href="<?= resUrl('/floorplan.php') ?>" class="px-4 py-2 border border-dark-600 text-dark-300 rounded-lg text-sm hover:bg-dark-800 transition-colors">
            &larr; Volver al Floor Plan
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- SECTIONS PANEL -->
        <div class="bg-dark-900 rounded-xl border border-dark-700">
            <div class="px-5 py-4 border-b border-dark-700 flex items-center justify-between">
                <h4 class="text-sm font-semibold text-white uppercase tracking-wider">Secciones</h4>
                <button onclick="openSectionModal()" class="px-3 py-1.5 bg-gold-500 text-dark-950 rounded-lg text-xs font-semibold hover:bg-gold-400 transition-colors">
                    + Nueva Seccion
                </button>
            </div>
            <div class="divide-y divide-dark-800" id="sectionsList">
                <?php if (empty($allSections)): ?>
                    <div class="p-6 text-center text-dark-500 text-sm">No hay secciones configuradas</div>
                <?php else: ?>
                    <?php foreach ($allSections as $sec): ?>
                        <div class="flex items-center justify-between px-5 py-3 hover:bg-dark-800/50 transition-colors">
                            <div>
                                <p class="text-sm text-white font-medium"><?= resSanitize($sec['name']) ?></p>
                                <p class="text-xs text-dark-400">
                                    <?= resSanitize($sec['restaurant_name']) ?> &middot;
                                    Capacidad: <?= $sec['capacity'] ?> &middot;
                                    Orden: <?= $sec['sort_order'] ?>
                                    <?php if (!$sec['active']): ?>
                                        <span class="text-red-400 ml-1">(Inactiva)</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button onclick='openSectionModal(<?= json_encode($sec) ?>)' class="p-1.5 rounded hover:bg-dark-700 text-dark-400 hover:text-white" title="Editar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button onclick="deleteSection(<?= $sec['id'] ?>)" class="p-1.5 rounded hover:bg-red-900/30 text-dark-400 hover:text-red-400" title="Eliminar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- TABLES PANEL -->
        <div class="bg-dark-900 rounded-xl border border-dark-700">
            <div class="px-5 py-4 border-b border-dark-700 flex items-center justify-between">
                <h4 class="text-sm font-semibold text-white uppercase tracking-wider">Mesas</h4>
                <button onclick="openTableModal()" class="px-3 py-1.5 bg-gold-500 text-dark-950 rounded-lg text-xs font-semibold hover:bg-gold-400 transition-colors">
                    + Nueva Mesa
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-dark-700">
                            <th class="px-4 py-2 text-left text-xs font-semibold text-dark-400 uppercase">Mesa</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-dark-400 uppercase">Seccion</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-dark-400 uppercase">Capacidad</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-dark-400 uppercase">Forma</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-dark-400 uppercase">Comb.</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-dark-400 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-800" id="tablesList">
                        <?php if (empty($allTables)): ?>
                            <tr><td colspan="6" class="px-4 py-6 text-center text-dark-500 text-sm">No hay mesas configuradas</td></tr>
                        <?php else: ?>
                            <?php foreach ($allTables as $tbl): ?>
                                <tr class="hover:bg-dark-800/50 transition-colors">
                                    <td class="px-4 py-2.5">
                                        <span class="text-sm text-white font-medium">Mesa <?= resSanitize($tbl['table_number']) ?></span>
                                        <?php if ($tbl['table_name']): ?>
                                            <span class="text-xs text-dark-400 block"><?= resSanitize($tbl['table_name']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2.5 text-xs text-dark-300"><?= resSanitize($tbl['section_name']) ?></td>
                                    <td class="px-4 py-2.5 text-xs text-dark-300"><?= $tbl['min_capacity'] ?>-<?= $tbl['max_capacity'] ?></td>
                                    <td class="px-4 py-2.5">
                                        <span class="text-xs px-2 py-0.5 rounded bg-dark-700 text-dark-300"><?= ucfirst($tbl['shape']) ?></span>
                                        <?php if (($tbl['size'] ?? 'small') === 'large'): ?>
                                            <span class="text-xs px-1.5 py-0.5 rounded bg-blue-900/30 text-blue-400 ml-1">G</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <?php if ($tbl['is_combinable']): ?>
                                            <span class="text-xs text-green-400">Si</span>
                                        <?php else: ?>
                                            <span class="text-xs text-dark-500">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center space-x-1">
                                            <button onclick='openTableModal(<?= json_encode($tbl) ?>)' class="p-1 rounded hover:bg-dark-700 text-dark-400 hover:text-white" title="Editar">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </button>
                                            <button onclick="deleteTable(<?= $tbl['id'] ?>)" class="p-1 rounded hover:bg-red-900/30 text-dark-400 hover:text-red-400" title="Eliminar">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Section Modal -->
<div id="sectionModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-overlay absolute inset-0" onclick="closeSectionModal()"></div>
    <div class="relative z-10 flex items-center justify-center min-h-screen p-4">
        <div class="bg-dark-900 border border-dark-700 rounded-xl w-full max-w-md shadow-2xl fade-in">
            <div class="px-6 py-4 border-b border-dark-700 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-white" id="sectionModalTitle">Nueva Seccion</h3>
                <button onclick="closeSectionModal()" class="text-dark-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form id="sectionForm" class="p-6 space-y-4">
                <input type="hidden" name="id" id="sectionId">
                <input type="hidden" name="entity" value="section">
                <div>
                    <label class="block text-sm text-dark-400 mb-1">Restaurante *</label>
                    <select name="restaurant_id" id="sectionRestaurantId" required
                            class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2 text-white focus:border-gold-500 focus:outline-none text-sm">
                        <?php foreach ($restaurants as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= $currentRestaurant == $r['id'] ? 'selected' : '' ?>><?= resSanitize($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-dark-400 mb-1">Nombre *</label>
                    <input type="text" name="name" id="sectionName" required placeholder="Ej: Terraza, Interior, Privado..."
                           class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-dark-500 focus:border-gold-500 focus:outline-none text-sm">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-dark-400 mb-1">Capacidad total</label>
                        <input type="number" name="capacity" id="sectionCapacity" min="0" value="0"
                               class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2 text-white focus:border-gold-500 focus:outline-none text-sm">
                    </div>
                    <div>
                        <label class="block text-sm text-dark-400 mb-1">Orden</label>
                        <input type="number" name="sort_order" id="sectionSortOrder" min="0" value="0"
                               class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2 text-white focus:border-gold-500 focus:outline-none text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-sm text-dark-400 mb-1">Descripcion</label>
                    <input type="text" name="description" id="sectionDescription" placeholder="Descripcion opcional"
                           class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-dark-500 focus:border-gold-500 focus:outline-none text-sm">
                </div>
                <div class="flex justify-end space-x-3 pt-2">
                    <button type="button" onclick="closeSectionModal()" class="px-4 py-2 border border-dark-600 text-dark-300 rounded-lg text-sm hover:bg-dark-800">Cancelar</button>
                    <button type="submit" class="px-5 py-2 bg-gold-500 text-dark-950 rounded-lg text-sm font-semibold hover:bg-gold-400">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Table Modal -->
<div id="tableModal" class="fixed inset-0 z-50 hidden">
    <div class="modal-overlay absolute inset-0" onclick="closeTableModal()"></div>
    <div class="relative z-10 flex items-center justify-center min-h-screen p-4">
        <div class="bg-dark-900 border border-dark-700 rounded-xl w-full max-w-md shadow-2xl fade-in">
            <div class="px-6 py-4 border-b border-dark-700 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-white" id="tableModalTitle">Nueva Mesa</h3>
                <button onclick="closeTableModal()" class="text-dark-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form id="tableForm" class="p-6 space-y-4">
                <input type="hidden" name="id" id="tableId">
                <input type="hidden" name="entity" value="table">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-dark-400 mb-1">Restaurante *</label>
                        <select name="restaurant_id" id="tableRestaurantId" required onchange="loadSectionsForSelect(this.value)"
                                class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2 text-white focus:border-gold-500 focus:outline-none text-sm">
                            <?php foreach ($restaurants as $r): ?>
                                <option value="<?= $r['id'] ?>" <?= $currentRestaurant == $r['id'] ? 'selected' : '' ?>><?= resSanitize($r['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-dark-400 mb-1">Seccion *</label>
                        <select name="section_id" id="tableSectionId" required
                                class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2 text-white focus:border-gold-500 focus:outline-none text-sm">
                            <option value="">Seleccionar...</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-dark-400 mb-1">Numero de mesa *</label>
                        <input type="text" name="table_number" id="tableNumber" required placeholder="Ej: 1, A1, T5..."
                               class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-dark-500 focus:border-gold-500 focus:outline-none text-sm">
                    </div>
                    <div>
                        <label class="block text-sm text-dark-400 mb-1">Forma</label>
                        <select name="shape" id="tableShape"
                                class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2 text-white focus:border-gold-500 focus:outline-none text-sm">
                            <option value="square">Cuadrada</option>
                            <option value="round">Redonda</option>
                            <option value="rectangle">Rectangular</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-dark-400 mb-1">Tamaño</label>
                        <select name="size" id="tableSize"
                                class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2 text-white focus:border-gold-500 focus:outline-none text-sm">
                            <option value="small">Pequeña</option>
                            <option value="large">Grande</option>
                        </select>
                    </div>
                    <div></div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-dark-400 mb-1">Capacidad min</label>
                        <input type="number" name="min_capacity" id="tableMinCap" min="1" value="1"
                               class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2 text-white focus:border-gold-500 focus:outline-none text-sm">
                    </div>
                    <div>
                        <label class="block text-sm text-dark-400 mb-1">Capacidad max</label>
                        <input type="number" name="max_capacity" id="tableMaxCap" min="1" value="4"
                               class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2 text-white focus:border-gold-500 focus:outline-none text-sm">
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <label class="flex items-center space-x-2 cursor-pointer">
                        <input type="checkbox" name="is_combinable" id="tableCombinable" value="1"
                               class="w-4 h-4 rounded border-dark-600 bg-dark-800 text-gold-500 focus:ring-gold-500">
                        <span class="text-sm text-dark-300">Combinable con otra mesa</span>
                    </label>
                </div>
                <div class="flex justify-end space-x-3 pt-2">
                    <button type="button" onclick="closeTableModal()" class="px-4 py-2 border border-dark-600 text-dark-300 rounded-lg text-sm hover:bg-dark-800">Cancelar</button>
                    <button type="submit" class="px-5 py-2 bg-gold-500 text-dark-950 rounded-lg text-sm font-semibold hover:bg-gold-400">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const API_URL = '<?= resUrl('/api/tables.php') ?>';
const allSectionsData = <?= json_encode($allSections) ?>;

// ------- SECTION MODAL -------
function openSectionModal(section = null) {
    document.getElementById('sectionModal').classList.remove('hidden');
    const form = document.getElementById('sectionForm');
    form.reset();

    if (section) {
        document.getElementById('sectionModalTitle').textContent = 'Editar Seccion';
        document.getElementById('sectionId').value = section.id;
        document.getElementById('sectionRestaurantId').value = section.restaurant_id;
        document.getElementById('sectionName').value = section.name;
        document.getElementById('sectionCapacity').value = section.capacity || 0;
        document.getElementById('sectionSortOrder').value = section.sort_order || 0;
        document.getElementById('sectionDescription').value = section.description || '';
    } else {
        document.getElementById('sectionModalTitle').textContent = 'Nueva Seccion';
        document.getElementById('sectionId').value = '';
    }
}

function closeSectionModal() {
    document.getElementById('sectionModal').classList.add('hidden');
}

document.getElementById('sectionForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const data = {};
    formData.forEach((v, k) => { if (v !== '') data[k] = v; });

    // Validate required fields
    if (!data.restaurant_id) { showToast('Selecciona un restaurante', 'error'); return; }
    if (!data.name) { showToast('Ingresa el nombre de la sección', 'error'); return; }

    const isEdit = !!data.id;
    const method = isEdit ? 'PUT' : 'POST';

    try {
        const res = await fetch(API_URL, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            showToast(isEdit ? 'Seccion actualizada' : 'Seccion creada');
            closeSectionModal();
            setTimeout(() => window.location.reload(), 500);
        } else {
            showToast(result.error || 'Error al guardar', 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    }
});

async function deleteSection(id) {
    if (!await confirmAction('Desactivar esta seccion? Las mesas asociadas tambien seran desactivadas.')) return;
    try {
        const res = await fetch(API_URL, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, entity: 'section' })
        });
        const result = await res.json();
        if (result.success) {
            showToast('Seccion desactivada');
            setTimeout(() => window.location.reload(), 500);
        } else {
            showToast(result.error || 'Error', 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    }
}

// ------- TABLE MODAL -------
function openTableModal(table = null) {
    document.getElementById('tableModal').classList.remove('hidden');
    const form = document.getElementById('tableForm');
    form.reset();

    if (table) {
        document.getElementById('tableModalTitle').textContent = 'Editar Mesa';
        document.getElementById('tableId').value = table.id;
        document.getElementById('tableRestaurantId').value = table.restaurant_id;
        loadSectionsForSelect(table.restaurant_id, table.section_id);
        document.getElementById('tableNumber').value = table.table_number;
        document.getElementById('tableShape').value = table.shape || 'square';
        document.getElementById('tableSize').value = table.size || 'small';
        document.getElementById('tableMinCap').value = table.min_capacity || 1;
        document.getElementById('tableMaxCap').value = table.max_capacity || 4;
        document.getElementById('tableCombinable').checked = !!parseInt(table.is_combinable);
    } else {
        document.getElementById('tableModalTitle').textContent = 'Nueva Mesa';
        document.getElementById('tableId').value = '';
        const restId = document.getElementById('tableRestaurantId').value;
        if (restId) loadSectionsForSelect(restId);
    }
}

function closeTableModal() {
    document.getElementById('tableModal').classList.add('hidden');
}

function loadSectionsForSelect(restaurantId, selectedId = null) {
    const select = document.getElementById('tableSectionId');
    select.innerHTML = '<option value="">Seleccionar...</option>';
    allSectionsData
        .filter(s => s.restaurant_id == restaurantId && s.active == 1)
        .forEach(s => {
            select.innerHTML += `<option value="${s.id}" ${selectedId == s.id ? 'selected' : ''}>${escapeHtml(s.name)}</option>`;
        });
}

document.getElementById('tableForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const data = {};
    formData.forEach((v, k) => { if (v !== '') data[k] = v; });
    data.is_combinable = document.getElementById('tableCombinable').checked ? 1 : 0;

    // Validate required fields before sending
    if (!data.restaurant_id) { showToast('Selecciona un restaurante', 'error'); return; }
    if (!data.section_id) { showToast('Selecciona una sección. Si no hay secciones, créalas primero.', 'error'); return; }
    if (!data.table_number) { showToast('Ingresa el número de mesa', 'error'); return; }

    const isEdit = !!data.id;
    const method = isEdit ? 'PUT' : 'POST';

    try {
        const res = await fetch(API_URL, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await res.json();
        if (result.success) {
            showToast(isEdit ? 'Mesa actualizada' : 'Mesa creada');
            closeTableModal();
            setTimeout(() => window.location.reload(), 500);
        } else {
            showToast(result.error || 'Error al guardar', 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    }
});

async function deleteTable(id) {
    if (!await confirmAction('Desactivar esta mesa?')) return;
    try {
        const res = await fetch(API_URL, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, entity: 'table' })
        });
        const result = await res.json();
        if (result.success) {
            showToast('Mesa desactivada');
            setTimeout(() => window.location.reload(), 500);
        } else {
            showToast(result.error || 'Error', 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    }
}

// Init section selects
const initRestId = document.getElementById('tableRestaurantId')?.value;
if (initRestId) loadSectionsForSelect(initRestId);

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php else: ?>
<!-- ============================================= -->
<!-- MODE 1: VISUAL FLOOR PLAN (Default)           -->
<!-- ============================================= -->

<style>
    .floor-canvas {
        position: relative;
        min-height: 700px;
        min-width: 900px;
        background-color: #0c1222;
        background-image:
            radial-gradient(circle, #182035 1px, transparent 1px);
        background-size: 20px 20px;
        border: 1px solid #334155;
        border-radius: 12px;
        overflow: visible;
    }

    /* ===== TABLE GROUP: wrapper for table + chairs ===== */
    .table-group {
        position: absolute;
        cursor: pointer;
        user-select: none;
        z-index: 10;
        transition: filter 0.2s;
    }
    .table-group:hover { filter: brightness(1.2); z-index: 20; }
    .table-group.selected { filter: brightness(1.3); z-index: 25; }
    .table-group.dragging { opacity: 0.7; z-index: 30; cursor: grabbing; }

    /* ===== TABLE SURFACE ===== */
    .table-surface {
        position: absolute;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        border: 2px solid rgba(255,255,255,0.15);
        transition: border-color 0.2s;
    }
    .table-group.selected .table-surface { border-color: #D4AF37; box-shadow: 0 0 12px rgba(212,175,55,0.3); }
    .shape-round .table-surface { border-radius: 50%; }
    .shape-square .table-surface { border-radius: 6px; }
    .shape-rectangle .table-surface { border-radius: 6px; }

    /* Status colors */
    .ts-available { background: rgba(34,197,94,0.2); border-color: rgba(34,197,94,0.5); }
    .ts-reserved { background: rgba(212,175,55,0.2); border-color: rgba(212,175,55,0.5); }
    .ts-assigned { background: rgba(245,158,11,0.25); border-color: rgba(245,158,11,0.6); }
    .ts-occupied { background: rgba(59,130,246,0.25); border-color: rgba(59,130,246,0.5); }
    .ts-blocked { background: rgba(239,68,68,0.2); border-color: rgba(239,68,68,0.4); }
    .ts-maintenance { background: rgba(107,114,128,0.2); border-color: rgba(107,114,128,0.4); }

    .table-number { font-size: 0.7rem; font-weight: 700; color: #fff; line-height: 1; }
    .table-guest { font-size: 0.5rem; color: #fbbf24; margin-top: 1px; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding: 0 3px; font-weight: 500; text-align: center; }
    .table-times { font-size: 0.45rem; color: #94a3b8; margin-top: 1px; max-width: 100%; text-align: center; line-height: 1.3; letter-spacing: 0.02em; }
    .table-times .time-slot { display: inline-block; padding: 0 1px; }
    .table-times .time-active { color: #fbbf24; font-weight: 600; }
    .table-times .time-future { color: #64748b; }

    /* ===== CHAIRS ===== */
    .chair {
        position: absolute;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        border: 1.5px solid rgba(255,255,255,0.15);
        background: rgba(30,41,59,0.8);
        transition: background 0.2s, border-color 0.2s;
    }
    .chair-filled {
        background: rgba(59,130,246,0.4);
        border-color: rgba(59,130,246,0.6);
    }
    .ts-available ~ .chair { border-color: rgba(34,197,94,0.3); }
    .ts-reserved ~ .chair { border-color: rgba(212,175,55,0.3); }
    .ts-assigned ~ .chair { border-color: rgba(245,158,11,0.3); }
    .ts-occupied ~ .chair { border-color: rgba(59,130,246,0.3); }
    .ts-blocked ~ .chair { border-color: rgba(239,68,68,0.3); }

    /* Section labels on canvas */
    .section-label {
        position: absolute;
        font-size: 0.6rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: rgba(148, 163, 184, 0.4);
        pointer-events: none;
        z-index: 1;
    }

    /* ===== ZOOM WRAPPER ===== */
    .zoom-wrapper {
        transform-origin: 0 0;
        transition: transform 0.15s ease-out;
    }

    /* ===== ZOOM CONTROLS ===== */
    .zoom-controls {
        position: absolute;
        bottom: 12px;
        right: 12px;
        display: flex;
        flex-direction: column;
        gap: 4px;
        z-index: 30;
    }
    .zoom-btn {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: rgba(15,23,42,0.9);
        border: 1px solid #334155;
        color: #cbd5e1;
        font-size: 18px;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background 0.15s, color 0.15s;
        backdrop-filter: blur(4px);
    }
    .zoom-btn:hover { background: rgba(30,41,59,0.95); color: #fff; }
    .zoom-btn:active { background: rgba(212,175,55,0.2); color: #D4AF37; }
    .zoom-level {
        text-align: center;
        font-size: 0.6rem;
        color: #64748b;
        padding: 2px 0;
    }

    /* ===== ROTATE BUTTON (appears on selected table in drag mode) ===== */
    .rotate-handle {
        position: absolute;
        top: -22px;
        right: -22px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #D4AF37;
        border: 2px solid #0c1222;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 35;
        font-size: 10px;
        color: #0c1222;
        font-weight: 700;
        transition: transform 0.15s;
    }
    .rotate-handle:hover { transform: scale(1.2); }
</style>

<div class="fade-in">
    <!-- Compact Stats + Toolbar -->
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2 flex-wrap">
            <div class="flex items-center gap-1.5 text-xs bg-dark-900 rounded-lg border border-dark-700 px-3 py-2">
                <span class="w-2.5 h-2.5 rounded-sm bg-green-500/50 border border-green-500"></span><span class="text-green-400 font-semibold" id="statAvailable"><?= $stats['available'] ?></span>
                <span class="w-2.5 h-2.5 rounded-sm bg-gold-500/50 border border-gold-500 ml-2"></span><span class="text-gold-400 font-semibold" id="statReserved"><?= $stats['reserved'] ?></span>
                <span class="w-2.5 h-2.5 rounded-sm bg-blue-500/50 border border-blue-500 ml-2"></span><span class="text-blue-400 font-semibold" id="statOccupied"><?= $stats['occupied'] ?></span>
                <span class="w-2.5 h-2.5 rounded-sm bg-red-500/50 border border-red-500 ml-2"></span><span class="text-red-400 font-semibold" id="statBlocked"><?= $stats['blocked'] ?></span>
                <span class="ml-2 text-dark-400">|</span>
                <span class="text-dark-300"><span id="statCovers"><?= $stats['current_covers'] ?></span>/<?= $stats['total_capacity'] ?> covers</span>
                <?php if ($floorCapacity && $floorCapacity['shift_name']): ?>
                <span class="ml-2 text-dark-400">|</span>
                <?php
                    $capPct = $floorCapacity['max_covers'] > 0 ? min(round(($floorCapacity['current_covers'] / $floorCapacity['max_covers']) * 100), 100) : 0;
                    $capColor = $capPct >= 90 ? 'text-red-400' : ($capPct >= 70 ? 'text-yellow-400' : 'text-emerald-400');
                ?>
                <span class="<?= $capColor ?> font-semibold" title="Capacidad turno <?= resSanitize($floorCapacity['shift_name']) ?>">
                    <?= $floorCapacity['current_covers'] ?>/<?= $floorCapacity['max_covers'] ?> <?= resSanitize($floorCapacity['shift_name']) ?>
                </span>
                <?php if ($floorCapacity['is_override']): ?>
                    <span class="text-gold-400 text-[10px]">★</span>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <!-- Section Filter Tabs -->
            <div class="flex items-center gap-1" id="sectionTabs">
                <button onclick="filterSection('all')" class="section-tab active px-3 py-1.5 rounded-lg text-xs font-semibold bg-gold-500/20 text-gold-400 border border-gold-500/30 touch-btn" data-section="all">Todas</button>
                <?php foreach ($allSections as $sec): ?>
                    <button onclick="filterSection('<?= addslashes($sec['name']) ?>')" class="section-tab px-3 py-1.5 rounded-lg text-xs font-semibold bg-dark-800 text-dark-400 border border-dark-700 hover:text-white touch-btn" data-section="<?= resSanitize($sec['name']) ?>"><?= resSanitize($sec['name']) ?></button>
                <?php endforeach; ?>
            </div>
            <?php if ($isAdmin): ?>
                <button onclick="toggleDragMode()" id="dragToggle"
                        class="px-3 py-1.5 border border-dark-600 text-dark-300 rounded-lg text-xs hover:bg-dark-800 transition-colors touch-btn">
                    Mover
                </button>
                <a href="<?= resUrl('/floorplan.php?view=manage') ?>"
                   class="px-3 py-1.5 border border-dark-600 text-dark-300 rounded-lg text-xs hover:bg-dark-800 transition-colors touch-btn">
                    ⚙ Mesas
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- TWO-PANEL LAYOUT: Reservations List + Floor Plan Canvas -->
    <div class="flex gap-4" style="height: calc(100vh - 200px); min-height: 500px;">

        <!-- LEFT PANEL: Reservations List -->
        <div class="w-80 lg:w-96 flex-shrink-0 bg-dark-900 rounded-xl border border-dark-700 flex flex-col overflow-hidden">
            <!-- Date Navigation -->
            <div class="px-4 py-2 border-b border-dark-700 flex items-center justify-between flex-shrink-0 bg-dark-800/50">
                <button onclick="navigateFloorplanDate(-1)" class="w-8 h-8 rounded-lg bg-dark-700 border border-dark-600 text-dark-300 hover:text-white flex items-center justify-center touch-btn">&larr;</button>
                <div class="flex items-center gap-2">
                    <input type="text" id="floorplanDatePicker" value="<?= $selectedDate ?>"
                           class="bg-transparent border-none text-center text-sm text-white font-semibold focus:outline-none cursor-pointer w-32" readonly>
                    <?php if ($selectedDate !== $today): ?>
                        <button onclick="navigateFloorplanDate(0)" class="px-2 py-0.5 bg-gold-500/20 text-gold-400 rounded text-xs font-semibold hover:bg-gold-500/30 touch-btn">Hoy</button>
                    <?php endif; ?>
                </div>
                <button onclick="navigateFloorplanDate(1)" class="w-8 h-8 rounded-lg bg-dark-700 border border-dark-600 text-dark-300 hover:text-white flex items-center justify-center touch-btn">&rarr;</button>
            </div>
            <div class="px-4 py-2 border-b border-dark-700 flex items-center justify-between flex-shrink-0">
                <div>
                    <h4 class="text-sm font-semibold text-white">Reservas <?= $selectedDate === $today ? 'Hoy' : date('d M', strtotime($selectedDate)) ?></h4>
                    <p class="text-xs text-dark-400"><?= count($todayResList) ?> reservas</p>
                </div>
                <div class="flex items-center gap-2">
                    <button id="syncRefreshBtn" onclick="fullSyncRefresh()" class="flex items-center gap-1.5 px-3 py-1.5 bg-dark-800 border border-dark-600 rounded-lg text-xs text-dark-300 hover:text-white hover:border-gold-500 transition-all touch-btn" title="Sincronizar y refrescar">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Sync
                    </button>
                    <a href="<?= resUrl('/reservations.php?action=new') ?>" class="w-8 h-8 rounded-lg bg-gold-500 text-dark-950 flex items-center justify-center font-bold text-lg touch-btn" title="Nueva Reserva">+</a>
                </div>
            </div>
            <div class="flex-1 overflow-y-auto" id="reservationsList">
                <?php if (empty($todayResList)): ?>
                    <div class="p-6 text-center text-dark-500 text-sm">Sin reservas hoy</div>
                <?php else: ?>
                    <?php foreach ($todayResList as $rv): ?>
                        <?php
                            $rvStatusClass = 'border-l-dark-600';
                            if ($rv['status'] === 'confirmed') $rvStatusClass = 'border-l-green-500';
                            elseif ($rv['status'] === 'pending') $rvStatusClass = 'border-l-yellow-500';
                            elseif ($rv['status'] === 'seated') $rvStatusClass = 'border-l-blue-500';
                            elseif ($rv['status'] === 'completed') $rvStatusClass = 'border-l-indigo-500';
                            elseif ($rv['status'] === 'no_show') $rvStatusClass = 'border-l-red-500';
                            $vipStar = '';
                            if ($rv['vip_level'] === 'vip') $vipStar = ' ★';
                            if ($rv['vip_level'] === 'vvip') $vipStar = ' ★★';
                        ?>
                        <div class="res-list-item px-4 py-3 border-b border-dark-800 border-l-4 <?= $rvStatusClass ?> hover:bg-dark-800/50 cursor-pointer transition-colors"
                             data-res-id="<?= $rv['id'] ?>" data-table-id="<?= $rv['table_id'] ?>" data-reservation-id="<?= (int)$rv['id'] ?>"
                             onclick="highlightReservation(<?= $rv['id'] ?>, <?= $rv['table_id'] ?: 'null' ?>)">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-mono text-gold-400 font-semibold"><?= formatTime($rv['reservation_time']) ?></span>
                                <div class="flex items-center gap-1.5">
                                    <span class="offline-badge hidden text-xs px-1.5 py-0.5 rounded-full bg-yellow-900/60 text-yellow-300 border border-yellow-700/40" title="Cambio pendiente de sincronizar">⏳</span>
                                    <span class="status-badge status-<?= $rv['status'] ?>"><?= ucfirst(str_replace('_',' ',$rv['status'])) ?></span>
                                    <span class="channel-badge channel-<?= $rv['channel'] ?>"><?= ucfirst($rv['channel']) ?></span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between mt-1.5">
                                <div class="min-w-0">
                                    <button onclick="event.stopPropagation(); showCustomerInfo(<?= $rv['customer_id'] ?: 'null' ?>, '<?= addslashes(resSanitize($rv['guest_name'])) ?>', '<?= addslashes(resSanitize($rv['guest_phone'] ?? '')) ?>', '<?= addslashes(resSanitize($rv['guest_email'] ?? '')) ?>', <?= $rv['id'] ?>)"
                                            class="text-sm text-white font-medium hover:text-gold-400 truncate block text-left">
                                        <?= resSanitize($rv['guest_name']) ?><?= $vipStar ? "<span class='text-gold-500 text-xs'>$vipStar</span>" : '' ?>
                                    </button>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0 text-xs text-dark-400">
                                    <span class="font-semibold text-white"><?= $rv['party_size'] ?>p</span>
                                    <?php if ($rv['table_number']): ?>
                                        <span class="px-1.5 py-0.5 rounded text-xs font-semibold <?= $rv['status'] === 'seated' ? 'bg-blue-500/20 text-blue-400' : 'bg-amber-500/20 text-amber-400' ?>">Mesa <?= resSanitize($rv['table_number']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($rv['occasion']): ?>
                                <p class="text-xs text-gold-500 mt-1">🎉 <?= resSanitize($rv['occasion']) ?></p>
                            <?php endif; ?>
                            <!-- Quick action buttons -->
                            <?php if ($rv['status'] === 'confirmed' || $rv['status'] === 'pending'): ?>
                                <div class="flex gap-2 mt-2">
                                    <?php if ($rv['table_id']): ?>
                                        <!-- Ya tiene mesa asignada: sentar con un click -->
                                        <button onclick="event.stopPropagation(); confirmSeat(<?= $rv['id'] ?>, <?= $rv['table_id'] ?>)" class="action-btn action-btn-seat text-xs flex-1 touch-btn">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            Sentar (Mesa <?= resSanitize($rv['table_number']) ?>)
                                        </button>
                                        <!-- Cambiar mesa -->
                                        <button onclick="event.stopPropagation(); assignTableOnly(<?= $rv['id'] ?>, <?= $rv['party_size'] ?>, <?= $rv['restaurant_id'] ?>, '<?= $rv['reservation_time'] ?>')" class="action-btn action-btn-assign text-xs touch-btn" style="padding: 8px 12px;" title="Cambiar Mesa">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                        </button>
                                    <?php else: ?>
                                        <!-- Sin mesa: botón para asignar mesa (sin sentar) -->
                                        <button onclick="event.stopPropagation(); assignTableOnly(<?= $rv['id'] ?>, <?= $rv['party_size'] ?>, <?= $rv['restaurant_id'] ?>, '<?= $rv['reservation_time'] ?>')" class="action-btn action-btn-assign text-xs flex-1 touch-btn">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                            Asignar Mesa
                                        </button>
                                        <!-- Sentar sin mesa previa (abre picker + sienta) -->
                                        <button onclick="event.stopPropagation(); seatWithTable(<?= $rv['id'] ?>, <?= $rv['party_size'] ?>, <?= $rv['restaurant_id'] ?>, '<?= $rv['reservation_time'] ?>')" class="action-btn action-btn-seat text-xs touch-btn" style="padding: 8px 12px;" title="Sentar ahora">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        </button>
                                    <?php endif; ?>
                                    <button onclick="event.stopPropagation(); updateResStatus(<?= $rv['id'] ?>, 'no_show')" class="action-btn action-btn-noshow text-xs touch-btn" style="padding: 8px 12px;">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            <?php elseif ($rv['status'] === 'seated'): ?>
                                <div class="flex gap-2 mt-2">
                                    <button onclick="event.stopPropagation(); updateResStatus(<?= $rv['id'] ?>, 'completed')" class="action-btn action-btn-complete text-xs flex-1 touch-btn">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Completar
                                    </button>
                                    <button onclick="event.stopPropagation(); reassignTable(<?= $rv['id'] ?>, <?= $rv['party_size'] ?>, <?= $rv['restaurant_id'] ?>, '<?= $rv['reservation_time'] ?>')" class="action-btn action-btn-assign text-xs touch-btn" style="padding: 8px 12px;" title="Cambiar Mesa">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT PANEL: Floor Plan Canvas + Table Detail -->
        <div class="flex-1 flex flex-col gap-4 min-w-0">
            <!-- Canvas with zoom -->
            <div class="flex-1 bg-dark-900 rounded-xl border border-dark-700 p-2 overflow-auto relative" id="canvasViewport">
                <div class="zoom-wrapper" id="zoomWrapper">
                    <div class="floor-canvas" id="floorCanvas">
                        <!-- Tables rendered by JS -->
                    </div>
                </div>
                <!-- Zoom controls -->
                <div class="zoom-controls">
                    <button class="zoom-btn" onclick="zoomIn()" title="Acercar">+</button>
                    <div class="zoom-level" id="zoomLevel">100%</div>
                    <button class="zoom-btn" onclick="zoomOut()" title="Alejar">−</button>
                    <button class="zoom-btn" onclick="zoomReset()" title="Restablecer" style="font-size:12px; margin-top:4px">↺</button>
                </div>
            </div>

            <!-- Bottom Detail Panel (shows when table selected) -->
            <div id="tableDetail" class="bg-dark-900 rounded-xl border border-dark-700 hidden flex-shrink-0">
                <div class="p-4 flex flex-wrap items-center gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-3">
                            <h4 class="text-base font-bold text-white" id="detailTitle">Mesa --</h4>
                            <span id="detailStatus" class="status-badge">--</span>
                            <span class="text-xs text-dark-400" id="detailSection">--</span>
                            <span class="text-xs text-dark-400" id="detailCapacity">--</span>
                        </div>
                        <div id="detailReservation" class="hidden mt-2">
                            <div class="flex items-center gap-4 text-sm">
                                <span class="text-white font-medium" id="detailGuestName">--</span>
                                <span class="text-dark-400" id="detailPartySize">--</span>
                                <span class="text-white font-mono text-xs" id="detailTime">--</span>
                                <span class="text-gold-500 font-mono text-xs" id="detailCode">--</span>
                                <span id="detailOccasionWrap" class="hidden text-gold-400 text-xs">🎉 <span id="detailOccasion"></span></span>
                            </div>
                            <div id="detailRequestsWrap" class="hidden"><p class="text-xs text-dark-300 mt-1" id="detailRequests"></p></div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2" id="detailActions">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const API_URL = '<?= resUrl('/api/tables.php') ?>';
const RESERVATIONS_API = '<?= resUrl('/api/reservations.php') ?>';
const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
const currentRestaurant = '<?= $currentRestaurant ?>';
let dragMode = false;
let selectedTable = null;
let refreshInterval = null;
let activeSection = 'all';
let currentZoom = 1;
const ZOOM_MIN = 0.3;
const ZOOM_MAX = 2.0;
const ZOOM_STEP = 0.15;

// Table data from PHP
let tablesData = <?= json_encode($allTables) ?>;
let todayReservations = <?= json_encode($todayResList) ?>;

// ------- TABLE DIMENSIONS & CHAIR LAYOUT -------
// Sizes keyed as "shape" or "shape_size" (defaults to small)
const TABLE_SIZES = {
    round:           { w: 48, h: 48 },
    round_small:     { w: 48, h: 48 },
    round_large:     { w: 72, h: 72 },
    square:          { w: 48, h: 48 },
    square_small:    { w: 48, h: 48 },
    square_large:    { w: 72, h: 72 },
    rectangle:       { w: 72, h: 44 },
    rectangle_small: { w: 72, h: 44 },
    rectangle_large: { w: 108, h: 60 }
};

function getTableSizeKey(shape, size) {
    if (size && size !== 'small') return shape + '_' + size;
    return shape;
}
const CHAIR_SIZE = 12;
const CHAIR_GAP = 3; // gap between chair edge and table edge

// Generate chair positions around a table
function getChairPositions(shape, w, h, numChairs) {
    const chairs = [];
    const offset = CHAIR_SIZE + CHAIR_GAP;

    if (shape === 'round') {
        // Distribute chairs in a circle around the table
        const cx = w / 2, cy = h / 2;
        const radius = (w / 2) + offset - 2;
        for (let i = 0; i < numChairs; i++) {
            const angle = (2 * Math.PI * i / numChairs) - Math.PI / 2;
            chairs.push({
                x: cx + radius * Math.cos(angle) - CHAIR_SIZE / 2,
                y: cy + radius * Math.sin(angle) - CHAIR_SIZE / 2
            });
        }
    } else {
        // Rectangle/square: distribute chairs along 4 sides
        // Top, Bottom priority; then Left, Right
        const sides = [];
        if (shape === 'rectangle') {
            // Long sides (top/bottom) get more chairs
            const longSide = Math.ceil(numChairs * 0.35);
            const shortSide = Math.floor(numChairs * 0.15);
            sides.push({ count: longSide, side: 'top' });
            sides.push({ count: longSide, side: 'bottom' });
            sides.push({ count: shortSide, side: 'left' });
            sides.push({ count: Math.max(0, numChairs - longSide * 2 - shortSide), side: 'right' });
        } else {
            // Square: evenly distribute
            const perSide = Math.floor(numChairs / 4);
            let remainder = numChairs - perSide * 4;
            sides.push({ count: perSide + (remainder > 0 ? 1 : 0), side: 'top' });
            remainder = Math.max(0, remainder - 1);
            sides.push({ count: perSide + (remainder > 0 ? 1 : 0), side: 'bottom' });
            remainder = Math.max(0, remainder - 1);
            sides.push({ count: perSide + (remainder > 0 ? 1 : 0), side: 'left' });
            remainder = Math.max(0, remainder - 1);
            sides.push({ count: perSide, side: 'right' });
        }

        sides.forEach(({ count, side }) => {
            for (let i = 0; i < count; i++) {
                const frac = (i + 1) / (count + 1);
                let cx, cy;
                switch (side) {
                    case 'top':
                        cx = w * frac - CHAIR_SIZE / 2;
                        cy = -offset;
                        break;
                    case 'bottom':
                        cx = w * frac - CHAIR_SIZE / 2;
                        cy = h + CHAIR_GAP;
                        break;
                    case 'left':
                        cx = -offset;
                        cy = h * frac - CHAIR_SIZE / 2;
                        break;
                    case 'right':
                        cx = w + CHAIR_GAP;
                        cy = h * frac - CHAIR_SIZE / 2;
                        break;
                }
                chairs.push({ x: cx, y: cy });
            }
        });
    }
    return chairs;
}

// ------- RENDER FLOOR PLAN -------
function renderFloorPlan() {
    const canvas = document.getElementById('floorCanvas');
    canvas.innerHTML = '';

    const sectionPositions = {};
    const filteredTables = activeSection === 'all'
        ? tablesData
        : tablesData.filter(t => t.section_name === activeSection);

    let autoIdx = 0;
    const padding = CHAIR_SIZE + CHAIR_GAP + 4; // extra space for chairs around each table

    filteredTables.forEach(t => {
        let displayStatus = t.status;
        if (t.res_status === 'seated') displayStatus = 'occupied';
        else if ((t.res_status === 'confirmed' || t.res_status === 'pending') && t.reservation_id) {
            // reservation_id exists means this reservation IS linked to this table (JOIN condition)
            // confirmed = pre-assigned (amber), pending = just reserved (gold)
            displayStatus = (t.res_status === 'confirmed') ? 'assigned' : 'reserved';
        }

        let posX = parseFloat(t.pos_x) || 0;
        let posY = parseFloat(t.pos_y) || 0;

        if (posX === 0 && posY === 0) {
            const cols = 8;
            posX = (autoIdx % cols) * 100 + 30;
            posY = Math.floor(autoIdx / cols) * 100 + 30;
            autoIdx++;
        }

        if (t.section_name && !sectionPositions[t.section_name]) {
            sectionPositions[t.section_name] = { x: posX, y: posY };
        } else if (t.section_name && sectionPositions[t.section_name]) {
            if (posX < sectionPositions[t.section_name].x) sectionPositions[t.section_name].x = posX;
            if (posY < sectionPositions[t.section_name].y) sectionPositions[t.section_name].y = posY;
        }

        const sizeKey = getTableSizeKey(t.shape, t.size);
        const size = TABLE_SIZES[sizeKey] || TABLE_SIZES[t.shape] || TABLE_SIZES.square;
        const tw = size.w, th = size.h;
        const numChairs = parseInt(t.max_capacity) || 4;
        const chairPositions = getChairPositions(t.shape, tw, th, numChairs);

        const rotation = parseInt(t.rotation) || 0;

        // Group container — positioned at table pos, chairs extend beyond
        const group = document.createElement('div');
        group.className = `table-group shape-${t.shape}`;
        group.style.left = posX + 'px';
        group.style.top = posY + 'px';
        group.style.width = tw + 'px';
        group.style.height = th + 'px';
        if (rotation) group.style.transform = `rotate(${rotation}deg)`;
        group.dataset.tableId = t.id;
        group.dataset.section = t.section_name || '';
        group.dataset.posX = posX;
        group.dataset.posY = posY;
        group.dataset.rotation = rotation;

        if (selectedTable && selectedTable.id == t.id) {
            group.classList.add('selected');
        }

        // Table surface
        const surface = document.createElement('div');
        surface.className = `table-surface ts-${displayStatus}`;
        surface.style.left = '0';
        surface.style.top = '0';
        surface.style.width = tw + 'px';
        surface.style.height = th + 'px';

        let guestHtml = '';
        if (t.guest_name) {
            const shortName = t.guest_name.length > 8 ? t.guest_name.substring(0, 8) + '..' : t.guest_name;
            guestHtml = `<span class="table-guest">${escapeHtml(shortName)}</span>`;
        }

        // Build time slots display for tables with reservations
        let timesHtml = '';
        const tableRes = (t.table_reservations || []);
        if (tableRes.length > 0) {
            const now = new Date();
            const nowMinutes = now.getHours() * 60 + now.getMinutes();
            const timeSlots = tableRes.map(r => {
                const timeParts = (r.reservation_time || '').split(':');
                const hh = parseInt(timeParts[0]) || 0;
                const mm = parseInt(timeParts[1]) || 0;
                const resMinutes = hh * 60 + mm;
                const isActive = r.res_status === 'seated' || (Math.abs(resMinutes - nowMinutes) <= 30 && r.res_status !== 'seated');
                const isCurrent = r.reservation_id == t.reservation_id;
                const timeStr = (hh > 12 ? hh - 12 : hh) + ':' + String(mm).padStart(2, '0');
                const cls = r.res_status === 'seated' ? 'time-active' : (isCurrent ? 'time-active' : 'time-future');
                return `<span class="time-slot ${cls}">${timeStr}</span>`;
            });
            timesHtml = `<span class="table-times">${timeSlots.join(' ')}</span>`;
        }

        // Wrap text in an inner div that counter-rotates so text stays readable
        // but the surface shape itself rotates with the group
        const textWrap = document.createElement('div');
        textWrap.style.cssText = 'display:flex;flex-direction:column;align-items:center;justify-content:center;width:100%;height:100%;overflow:hidden;';
        if (rotation) textWrap.style.transform = `rotate(${-rotation}deg)`;
        textWrap.innerHTML = `<span class="table-number">${escapeHtml(t.table_number)}</span>${guestHtml}${timesHtml}`;
        surface.appendChild(textWrap);
        group.appendChild(surface);

        // Chairs
        const isSeated = (t.res_status === 'seated');
        const seatedCount = isSeated ? (parseInt(t.res_party_size) || 0) : 0;
        chairPositions.forEach((pos, idx) => {
            const chair = document.createElement('div');
            chair.className = 'chair' + (idx < seatedCount ? ' chair-filled' : '');
            chair.style.left = pos.x + 'px';
            chair.style.top = pos.y + 'px';
            group.appendChild(chair);
        });

        // Rotate handle (only in drag mode for selected table)
        if (isAdmin && dragMode) {
            const rotateBtn = document.createElement('div');
            rotateBtn.className = 'rotate-handle';
            rotateBtn.innerHTML = '↻';
            rotateBtn.title = 'Rotar 45°';
            rotateBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                rotateTable(t, group);
            });
            rotateBtn.addEventListener('touchend', (e) => {
                e.stopPropagation();
                e.preventDefault();
                rotateTable(t, group);
            });
            group.appendChild(rotateBtn);
        }

        // Click
        group.addEventListener('click', (e) => {
            if (!group.classList.contains('dragging')) selectTable(t);
        });

        // Drag
        if (isAdmin && dragMode) {
            group.style.cursor = 'grab';
            enableDrag(group, t);
        }

        canvas.appendChild(group);
    });

    // Section labels
    if (activeSection === 'all') {
        Object.keys(sectionPositions).forEach(name => {
            const lbl = document.createElement('div');
            lbl.className = 'section-label';
            lbl.style.left = sectionPositions[name].x + 'px';
            lbl.style.top = (sectionPositions[name].y - 20) + 'px';
            lbl.textContent = name;
            canvas.appendChild(lbl);
        });
    }
}

// ------- DRAG & DROP (mouse + touch) -------
function enableDrag(node, tableData) {
    let isDragging = false;
    let startX, startY, origX, origY;

    function onStart(clientX, clientY) {
        if (!dragMode) return false;
        isDragging = true;
        node.classList.add('dragging');
        startX = clientX;
        startY = clientY;
        origX = parseFloat(node.style.left) || 0;
        origY = parseFloat(node.style.top) || 0;
        return true;
    }

    function onMove(clientX, clientY) {
        if (!isDragging) return;
        // Compensate for zoom level so drag feels natural
        const dx = (clientX - startX) / currentZoom;
        const dy = (clientY - startY) / currentZoom;
        node.style.left = Math.max(0, origX + dx) + 'px';
        node.style.top = Math.max(0, origY + dy) + 'px';
    }

    async function onEnd() {
        if (!isDragging) return;
        isDragging = false;
        node.classList.remove('dragging');

        const newX = parseFloat(node.style.left);
        const newY = parseFloat(node.style.top);

        // Snap to grid (20px)
        const snappedX = Math.round(newX / 20) * 20;
        const snappedY = Math.round(newY / 20) * 20;
        node.style.left = snappedX + 'px';
        node.style.top = snappedY + 'px';

        // Save position via API
        try {
            await fetch(API_URL, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: tableData.id,
                    entity: 'table',
                    pos_x: snappedX,
                    pos_y: snappedY
                })
            });
            tableData.pos_x = snappedX;
            tableData.pos_y = snappedY;
            node.dataset.posX = snappedX;
            node.dataset.posY = snappedY;
        } catch (e) {
            showToast('Error al guardar posicion', 'error');
        }
    }

    // Mouse events
    node.addEventListener('mousedown', (e) => {
        if (!onStart(e.clientX, e.clientY)) return;
        e.preventDefault();

        const onMouseMove = (ev) => onMove(ev.clientX, ev.clientY);
        const onMouseUp = () => {
            onEnd();
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
        };
        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
    });

    // Touch events (for tablet)
    node.addEventListener('touchstart', (e) => {
        const touch = e.touches[0];
        if (!onStart(touch.clientX, touch.clientY)) return;
        e.preventDefault();
    }, { passive: false });

    node.addEventListener('touchmove', (e) => {
        if (!isDragging) return;
        e.preventDefault();
        const touch = e.touches[0];
        onMove(touch.clientX, touch.clientY);
    }, { passive: false });

    node.addEventListener('touchend', () => onEnd());
    node.addEventListener('touchcancel', () => onEnd());
}

function toggleDragMode() {
    dragMode = !dragMode;
    const btn = document.getElementById('dragToggle');
    if (dragMode) {
        btn.textContent = 'Mover: ON';
        btn.classList.add('bg-gold-500/20', 'border-gold-500', 'text-gold-400');
        btn.classList.remove('border-dark-600', 'text-dark-300');
        showToast('Arrastra las mesas para reposicionarlas', 'info');
    } else {
        btn.textContent = 'Mover';
        btn.classList.remove('bg-gold-500/20', 'border-gold-500', 'text-gold-400');
        btn.classList.add('border-dark-600', 'text-dark-300');
    }
    renderFloorPlan();
}

// ------- ZOOM -------
function applyZoom() {
    const wrapper = document.getElementById('zoomWrapper');
    if (wrapper) {
        wrapper.style.transform = `scale(${currentZoom})`;
        document.getElementById('zoomLevel').textContent = Math.round(currentZoom * 100) + '%';
    }
}
function zoomIn() {
    currentZoom = Math.min(ZOOM_MAX, currentZoom + ZOOM_STEP);
    applyZoom();
}
function zoomOut() {
    currentZoom = Math.max(ZOOM_MIN, currentZoom - ZOOM_STEP);
    applyZoom();
}
function zoomReset() {
    currentZoom = 1;
    applyZoom();
}

// Mouse wheel zoom on canvas viewport
document.addEventListener('DOMContentLoaded', () => {
    const viewport = document.getElementById('canvasViewport');
    if (viewport) {
        viewport.addEventListener('wheel', (e) => {
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                if (e.deltaY < 0) zoomIn();
                else zoomOut();
            }
        }, { passive: false });

        // Pinch-to-zoom for tablet
        let lastPinchDist = 0;
        viewport.addEventListener('touchstart', (e) => {
            if (e.touches.length === 2) {
                lastPinchDist = Math.hypot(
                    e.touches[0].clientX - e.touches[1].clientX,
                    e.touches[0].clientY - e.touches[1].clientY
                );
            }
        }, { passive: true });
        viewport.addEventListener('touchmove', (e) => {
            if (e.touches.length === 2) {
                const dist = Math.hypot(
                    e.touches[0].clientX - e.touches[1].clientX,
                    e.touches[0].clientY - e.touches[1].clientY
                );
                if (lastPinchDist > 0) {
                    const delta = dist - lastPinchDist;
                    if (Math.abs(delta) > 8) {
                        if (delta > 0) currentZoom = Math.min(ZOOM_MAX, currentZoom + 0.05);
                        else currentZoom = Math.max(ZOOM_MIN, currentZoom - 0.05);
                        applyZoom();
                        lastPinchDist = dist;
                    }
                }
            }
        }, { passive: true });
        viewport.addEventListener('touchend', () => { lastPinchDist = 0; });
    }
});

// ------- ROTATE TABLE -------
async function rotateTable(tableData, groupEl) {
    const current = parseInt(tableData.rotation) || 0;
    const newRotation = (current + 45) % 360;

    // Update visually immediately
    groupEl.style.transform = `rotate(${newRotation}deg)`;
    groupEl.dataset.rotation = newRotation;
    tableData.rotation = newRotation;

    // Counter-rotate only the text wrapper so the surface shape stays rotated
    const textWrap = groupEl.querySelector('.table-surface > div');
    if (textWrap) textWrap.style.transform = `rotate(${-newRotation}deg)`;

    // Save to server
    try {
        await fetch(API_URL, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: tableData.id,
                entity: 'table',
                rotation: newRotation
            })
        });
    } catch (e) {
        showToast('Error al guardar rotación', 'error');
    }
}

// ------- SECTION FILTER -------
function filterSection(sectionName) {
    activeSection = sectionName;

    // Update tab styles
    document.querySelectorAll('.section-tab').forEach(tab => {
        if (tab.dataset.section === sectionName) {
            tab.classList.add('bg-gold-500/20', 'text-gold-400', 'border-gold-500/30');
            tab.classList.remove('bg-dark-800', 'text-dark-400', 'border-dark-700');
        } else {
            tab.classList.remove('bg-gold-500/20', 'text-gold-400', 'border-gold-500/30');
            tab.classList.add('bg-dark-800', 'text-dark-400', 'border-dark-700');
        }
    });

    // Also filter the reservation list on the left
    document.querySelectorAll('.res-list-item').forEach(item => {
        if (sectionName === 'all') {
            item.style.display = '';
        } else {
            // Check if reservation's table is in the filtered section
            const tableId = item.dataset.tableId;
            if (tableId) {
                const table = tablesData.find(t => t.id == tableId);
                item.style.display = (table && table.section_name === sectionName) ? '' : 'none';
            } else {
                // Reservations without table assigned — show in "all" only
                item.style.display = 'none';
            }
        }
    });

    renderFloorPlan();
}

// ------- SELECT TABLE -------
function selectTable(t) {
    selectedTable = t;

    // Highlight on canvas
    document.querySelectorAll('.table-group').forEach(n => n.classList.remove('selected'));
    const node = document.querySelector(`.table-group[data-table-id="${t.id}"]`);
    if (node) node.classList.add('selected');

    // Show bottom detail panel
    const detailPanel = document.getElementById('tableDetail');
    detailPanel.classList.remove('hidden');

    document.getElementById('detailTitle').textContent = 'Mesa ' + t.table_number;
    document.getElementById('detailSection').textContent = t.section_name || 'Sin sección';
    document.getElementById('detailCapacity').textContent = t.min_capacity + '-' + t.max_capacity + 'p';

    // Determine effective status
    let effectiveStatus = t.status;
    let statusLabel = statusDisplayLabel(effectiveStatus);
    if (t.res_status === 'seated') { effectiveStatus = 'occupied'; statusLabel = 'Ocupada'; }
    else if ((t.res_status === 'confirmed' || t.res_status === 'pending') && t.reservation_id) {
        if (t.res_status === 'confirmed') {
            effectiveStatus = 'assigned'; statusLabel = 'Asignada';
        } else {
            effectiveStatus = 'reserved'; statusLabel = 'Reservada';
        }
    }

    const statusEl = document.getElementById('detailStatus');
    statusEl.textContent = statusLabel;
    const statusColorMap = {
        available: 'bg-green-500/20 text-green-400',
        reserved: 'bg-gold-500/20 text-gold-400',
        assigned: 'bg-amber-500/20 text-amber-400',
        occupied: 'bg-blue-500/20 text-blue-400',
        blocked: 'bg-red-500/20 text-red-400',
        maintenance: 'bg-gray-500/20 text-gray-400'
    };
    statusEl.className = `inline-block px-2 py-0.5 rounded-full text-xs font-semibold ${statusColorMap[effectiveStatus] || 'bg-dark-700 text-dark-300'}`;

    // Reservation info — show all reservations for this table
    const resDiv = document.getElementById('detailReservation');
    const tableRes = t.table_reservations || [];
    if (t.reservation_id || tableRes.length > 0) {
        resDiv.classList.remove('hidden');

        if (tableRes.length > 1) {
            // Multiple reservations — show a mini-list
            let multiHtml = '';
            tableRes.forEach(r => {
                const time = r.reservation_time ? r.reservation_time.substring(0, 5) : '--';
                const statusCls = r.res_status === 'seated' ? 'text-blue-400' : (r.res_status === 'confirmed' ? 'text-green-400' : 'text-yellow-400');
                multiHtml += `<div class="flex items-center gap-3 text-sm py-1">
                    <span class="text-white font-mono text-xs w-12">${time}</span>
                    <span class="text-white font-medium flex-1 truncate">${r.guest_name || '--'}</span>
                    <span class="text-dark-400 text-xs">${r.res_party_size || '--'}p</span>
                    <span class="${statusCls} text-xs font-semibold">${r.res_status}</span>
                </div>`;
            });
            document.getElementById('detailGuestName').innerHTML = `<span class="text-dark-400 text-xs">${tableRes.length} reservas hoy</span>`;
            document.getElementById('detailPartySize').textContent = '';
            document.getElementById('detailTime').textContent = '';
            document.getElementById('detailCode').textContent = '';
            document.getElementById('detailOccasionWrap').classList.add('hidden');
            document.getElementById('detailRequestsWrap').classList.remove('hidden');
            document.getElementById('detailRequests').innerHTML = multiHtml;
        } else {
            document.getElementById('detailGuestName').textContent = t.guest_name || '--';
            document.getElementById('detailPartySize').textContent = (t.res_party_size || '--') + 'p';
            document.getElementById('detailTime').textContent = t.reservation_time ? t.reservation_time.substring(0, 5) : '--';
            document.getElementById('detailCode').textContent = t.confirmation_code || '';

            if (t.occasion) {
                document.getElementById('detailOccasionWrap').classList.remove('hidden');
                document.getElementById('detailOccasion').textContent = t.occasion;
            } else {
                document.getElementById('detailOccasionWrap').classList.add('hidden');
            }

            if (t.special_requests) {
                document.getElementById('detailRequestsWrap').classList.remove('hidden');
                document.getElementById('detailRequests').textContent = t.special_requests;
            } else {
                document.getElementById('detailRequestsWrap').classList.add('hidden');
            }
        }
    } else {
        resDiv.classList.add('hidden');
    }

    // Quick actions — using action-btn classes for tablet-friendly buttons
    const actionsDiv = document.getElementById('detailActions');
    let actionsHtml = '';

    if ((t.res_status === 'confirmed' || t.res_status === 'pending') && t.reservation_id) {
        // Table already has this reservation linked (via JOIN) — sentar con un click
        actionsHtml += `<button onclick="confirmSeat(${t.reservation_id}, ${t.id})" class="action-btn action-btn-seat touch-btn">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Sentar</button>`;
        actionsHtml += `<button onclick="assignTableOnly(${t.reservation_id}, ${t.res_party_size || 2}, ${t.restaurant_id}, '${t.reservation_time || ''}')" class="action-btn action-btn-assign touch-btn">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
            Cambiar Mesa</button>`;
        actionsHtml += `<button onclick="quickAction('no_show', ${t.reservation_id})" class="action-btn action-btn-noshow touch-btn">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            No Show</button>`;
    }
    if (t.res_status === 'seated' && t.reservation_id) {
        actionsHtml += `<button onclick="quickAction('complete', ${t.reservation_id})" class="action-btn action-btn-complete touch-btn">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Completar</button>`;
        actionsHtml += `<button onclick="reassignTable(${t.reservation_id}, ${t.res_party_size || 2}, ${t.restaurant_id}, '${t.reservation_time || ''}')" class="action-btn action-btn-assign touch-btn">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
            Cambiar Mesa</button>`;
    }
    if (!t.reservation_id && t.status === 'available') {
        actionsHtml += `<button onclick="showAssignReservationModal(${t.id}, ${t.max_capacity})" class="action-btn action-btn-edit touch-btn">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            Asignar Reserva</button>`;
    }
    if (isAdmin && t.status !== 'blocked' && !t.reservation_id) {
        actionsHtml += `<button onclick="setTableStatus(${t.id}, 'blocked')" class="action-btn bg-dark-800 border border-dark-600 text-dark-300 touch-btn">Bloquear</button>`;
    }
    if (isAdmin && t.status === 'blocked') {
        actionsHtml += `<button onclick="setTableStatus(${t.id}, 'available')" class="action-btn action-btn-complete touch-btn">Desbloquear</button>`;
    }
    if (isAdmin && t.status === 'maintenance') {
        actionsHtml += `<button onclick="setTableStatus(${t.id}, 'available')" class="action-btn action-btn-complete touch-btn">Activar</button>`;
    }
    if (isAdmin && t.status !== 'maintenance' && !t.reservation_id && t.status !== 'blocked') {
        actionsHtml += `<button onclick="setTableStatus(${t.id}, 'maintenance')" class="action-btn bg-dark-800 border border-dark-600 text-dark-300 touch-btn">Mantenimiento</button>`;
    }

    if (!actionsHtml) {
        actionsHtml = '<span class="text-xs text-dark-500 px-2">Sin acciones</span>';
    }
    actionsDiv.innerHTML = actionsHtml;

    // Also highlight corresponding reservation in left list
    document.querySelectorAll('.res-list-item').forEach(item => {
        item.classList.remove('bg-gold-500/5', 'ring-1', 'ring-gold-500/30');
    });
    if (t.reservation_id) {
        const resItem = document.querySelector(`.res-list-item[data-res-id="${t.reservation_id}"]`);
        if (resItem) {
            resItem.classList.add('bg-gold-500/5', 'ring-1', 'ring-gold-500/30');
            resItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }
}

function statusDisplayLabel(status) {
    const labels = {
        available: 'Disponible',
        occupied: 'Ocupada',
        reserved: 'Reservada',
        assigned: 'Asignada',
        blocked: 'Bloqueada',
        maintenance: 'Mantenimiento'
    };
    return labels[status] || status;
}

// ------- HIGHLIGHT RESERVATION (from left list) -------
function highlightReservation(resId, tableId) {
    // Highlight the reservation item
    document.querySelectorAll('.res-list-item').forEach(item => {
        item.classList.remove('bg-gold-500/5', 'ring-1', 'ring-gold-500/30');
    });
    const resItem = document.querySelector(`.res-list-item[data-res-id="${resId}"]`);
    if (resItem) {
        resItem.classList.add('bg-gold-500/5', 'ring-1', 'ring-gold-500/30');
    }

    // If has table assigned, highlight it on canvas and select it
    if (tableId) {
        const table = tablesData.find(t => t.id == tableId);
        if (table) {
            selectTable(table);
            // Scroll canvas to make the table visible
            const node = document.querySelector(`[data-table-id="${tableId}"]`);
            if (node) node.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
        }
    } else {
        // No table assigned — show mini info in bottom detail
        const detailPanel = document.getElementById('tableDetail');
        detailPanel.classList.remove('hidden');

        document.getElementById('detailTitle').textContent = 'Sin mesa asignada';
        document.getElementById('detailStatus').textContent = 'Pendiente';
        document.getElementById('detailStatus').className = 'inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-500/20 text-yellow-400';
        document.getElementById('detailSection').textContent = '';
        document.getElementById('detailCapacity').textContent = '';
        document.getElementById('detailReservation').classList.add('hidden');
        document.getElementById('detailActions').innerHTML = '';

        // Clear table highlight
        document.querySelectorAll('.table-group').forEach(n => n.classList.remove('selected'));
        selectedTable = null;
    }
}

// ------- UPDATE RESERVATION STATUS (from left panel buttons) -------
async function updateResStatus(resId, newStatus) {
    const labels = {
        seated: 'Sentar al huésped?',
        completed: 'Marcar como completada?',
        no_show: 'Marcar como No Show?'
    };
    if (!await confirmAction(labels[newStatus] || 'Confirmar?')) return;

    try {
        const res = await fetch(RESERVATIONS_API, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: resId, status: newStatus, client_version: Date.now() })
        });
        const data = await res.json();
        if (data.queued) {
            showToast('📴 Guardado offline — se sincronizará al reconectar', 'warning');
            if (window.CenacoloStaffOffline) {
                window.CenacoloStaffOffline.refreshConnChip();
                window.CenacoloStaffOffline.updateOfflineBadges();
            }
        } else if (data.success) {
            showToast('Estado actualizado');
            // Full page refresh to update both panels
            setTimeout(() => window.location.reload(), 600);
        } else {
            showToast(data.error || 'Error al actualizar', 'error');
        }
    } catch (e) {
        showToast('Error de conexión', 'error');
    }
}

// ------- QUICK ACTIONS (from bottom detail panel) -------
async function quickAction(action, reservationId) {
    const labels = {
        complete: 'Marcar como completada?',
        no_show: 'Marcar como No Show?'
    };
    if (!await confirmAction(labels[action] || 'Confirmar?')) return;

    const statusMap = { complete: 'completed', no_show: 'no_show' };

    try {
        const res = await fetch(RESERVATIONS_API, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: reservationId, status: statusMap[action], client_version: Date.now() })
        });
        const data = await res.json();
        if (data.queued) {
            showToast('📴 Guardado offline — se sincronizará al reconectar', 'warning');
            if (window.CenacoloStaffOffline) {
                window.CenacoloStaffOffline.refreshConnChip();
                window.CenacoloStaffOffline.updateOfflineBadges();
            }
        } else if (data.success) {
            showToast('Estado actualizado correctamente');
            // Full refresh to update both panels
            setTimeout(() => window.location.reload(), 600);
        } else {
            showToast(data.error || 'Error al actualizar', 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    }
}

async function setTableStatus(tableId, newStatus) {
    const labels = {
        blocked: 'Bloquear esta mesa?',
        available: 'Marcar como disponible?',
        maintenance: 'Poner en mantenimiento?'
    };
    if (!await confirmAction(labels[newStatus] || 'Confirmar?')) return;

    try {
        const res = await fetch(API_URL, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: tableId, entity: 'table', status: newStatus })
        });
        const data = await res.json();
        if (data.success) {
            showToast('Estado de mesa actualizado');
            refreshFloorPlan();
        } else {
            showToast(data.error || 'Error', 'error');
        }
    } catch (e) {
        showToast('Error de conexion', 'error');
    }
}

// ------- REFRESH -------
async function refreshFloorPlan() {
    try {
        const params = new URLSearchParams();
        params.set('action', 'floorplan');
        params.set('restaurant_id', currentRestaurant);
        if (fpSelectedDate && fpSelectedDate !== fpToday) {
            params.set('date', fpSelectedDate);
        }

        const res = await fetch(API_URL + '?' + params.toString());
        const data = await res.json();

        if (data.success && data.data) {
            tablesData = data.data;
            renderFloorPlan();

            // Update compact stats bar
            if (data.stats) {
                const el = (id) => document.getElementById(id);
                if (el('statAvailable')) el('statAvailable').textContent = data.stats.available || 0;
                if (el('statReserved')) el('statReserved').textContent = data.stats.reserved || 0;
                if (el('statOccupied')) el('statOccupied').textContent = data.stats.occupied || 0;
                if (el('statBlocked')) el('statBlocked').textContent = data.stats.blocked || 0;
                if (el('statCovers')) el('statCovers').textContent = data.stats.current_covers || 0;
            }

            // Re-select table if one was selected
            if (selectedTable) {
                const updated = tablesData.find(t => t.id == selectedTable.id);
                if (updated) selectTable(updated);
                else {
                    // Table no longer in data (status changed), hide detail
                    document.getElementById('tableDetail').classList.add('hidden');
                    selectedTable = null;
                }
            }
        }
    } catch (e) {
        console.error('Refresh error:', e);
    }
}

// ------- ASSIGN RESERVATION TO TABLE MODAL -------
async function showAssignReservationModal(tableId, tableCapacity) {
    // Get today's unassigned reservations from the todayReservations data
    const unassigned = todayReservations.filter(r =>
        !r.table_id && r.status !== 'cancelled' && r.status !== 'completed' && r.status !== 'no_show'
    );

    const modal = document.createElement('div');
    modal.className = 'customer-modal';
    modal.id = 'assignResModal';

    let listHtml = '';
    if (unassigned.length === 0) {
        listHtml = '<p class="text-dark-400 text-center py-6">No hay reservas sin mesa asignada</p>';
    } else {
        listHtml = '<div class="space-y-2 max-h-[60vh] overflow-y-auto">';
        unassigned.forEach(r => {
            const time = r.reservation_time ? r.reservation_time.substring(0, 5) : '--:--';
            const hh = parseInt(time.split(':')[0]) || 0;
            const mm = time.split(':')[1] || '00';
            const timeDisplay = (hh > 12 ? hh - 12 : hh) + ':' + mm + (hh >= 12 ? ' PM' : ' AM');
            const fits = r.party_size <= tableCapacity;
            const statusCls = r.status === 'confirmed' ? 'text-green-400' : (r.status === 'seated' ? 'text-blue-400' : 'text-yellow-400');

            listHtml += `<button onclick="assignReservationToTable(${r.id}, ${tableId})"
                class="w-full flex items-center gap-3 p-3 rounded-lg border ${fits ? 'border-dark-600 hover:border-gold-500 hover:bg-dark-800' : 'border-dark-700 opacity-50'} bg-dark-900 transition-all text-left touch-btn">
                <span class="text-gold-400 font-mono text-sm font-semibold w-16 flex-shrink-0">${timeDisplay}</span>
                <div class="flex-1 min-w-0">
                    <p class="text-white font-medium text-sm truncate">${escapeHtml(r.guest_name || 'Sin nombre')}</p>
                    <p class="text-xs text-dark-400">${r.party_size}p · <span class="${statusCls}">${r.status}</span>${r.channel ? ' · ' + r.channel : ''}</p>
                </div>
                <span class="text-xs text-dark-500 flex-shrink-0">${r.party_size}p</span>
            </button>`;
        });
        listHtml += '</div>';
    }

    modal.innerHTML = `
        <div class="modal-overlay absolute inset-0" onclick="closeAssignResModal()"></div>
        <div class="customer-modal-content p-6 relative">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-white">Asignar Reserva a Mesa</h3>
                    <p class="text-xs text-dark-400">${unassigned.length} reservas sin mesa · Capacidad: ${tableCapacity}p</p>
                </div>
                <button onclick="closeAssignResModal()" class="w-8 h-8 rounded-full bg-dark-800 flex items-center justify-center text-dark-400 hover:text-white touch-btn">✕</button>
            </div>
            ${listHtml}
        </div>
    `;
    document.body.appendChild(modal);
}

async function assignReservationToTable(reservationId, tableId) {
    closeAssignResModal();
    try {
        const res = await fetch(RESERVATIONS_API, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: reservationId, table_id: tableId })
        });
        const data = await res.json();
        if (data.success) {
            showToast('Reserva asignada a la mesa');
            setTimeout(() => {
                const url = new URL(window.location.href);
                if (fpSelectedDate && fpSelectedDate !== fpToday) url.searchParams.set('date', fpSelectedDate);
                window.location.href = url.toString();
            }, 500);
        } else {
            showToast(data.error || 'Error al asignar', 'error');
        }
    } catch(e) {
        showToast('Error de conexión', 'error');
    }
}

function closeAssignResModal() {
    const modal = document.getElementById('assignResModal');
    if (modal) modal.remove();
}

// ------- SYNC / REFRESH BUTTON -------
async function fullSyncRefresh() {
    const btn = document.getElementById('syncRefreshBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Sincronizando...';
    }

    try {
        // Trigger OpenTable sync via web endpoint with API key
        const syncRes = await fetch('<?= resUrl('/cron/sync-opentable-web.php') ?>?key=CenaSync2026_OT');
        const syncData = await syncRes.json();
        if (syncData.status === 'completed') {
            const t = syncData.totals || {};
            showToast(`Sync: ${t.synced || 0} reservas (${t.created || 0} nuevas, ${t.updated || 0} actualizadas)`, 'success');
        }
    } catch(e) {
        console.warn('Sync request failed:', e);
    }

    // Reload the page to get fresh data (preserve date param)
    showToast('Sincronización completada, recargando...', 'info');
    setTimeout(() => {
        const url = new URL(window.location.href);
        if (fpSelectedDate && fpSelectedDate !== fpToday) {
            url.searchParams.set('date', fpSelectedDate);
        }
        window.location.href = url.toString();
    }, 800);
}

// ------- DATE NAVIGATION -------
const fpSelectedDate = '<?= $selectedDate ?>';
const fpToday = '<?= $today ?>';
const fpMaxDate = '<?= $maxDate ?>';
const fpMinDate = '<?= $minDate ?>';

function navigateFloorplanDate(direction) {
    let targetDate;
    if (direction === 0) {
        targetDate = fpToday;
    } else {
        const current = new Date(fpSelectedDate + 'T12:00:00');
        current.setDate(current.getDate() + direction);
        targetDate = current.toISOString().split('T')[0];
    }
    // Enforce bounds
    if (targetDate < fpMinDate || targetDate > fpMaxDate) return;
    // Navigate (preserve current restaurant context)
    const url = new URL(window.location.href);
    if (targetDate === fpToday) {
        url.searchParams.delete('date');
    } else {
        url.searchParams.set('date', targetDate);
    }
    window.location.href = url.toString();
}

// ------- HELPERS -------
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ------- INIT -------
document.addEventListener('DOMContentLoaded', function() {
    renderFloorPlan();

    // Init flatpickr date picker for date navigation
    const fpDateEl = document.getElementById('floorplanDatePicker');
    if (fpDateEl && typeof flatpickr !== 'undefined') {
        flatpickr(fpDateEl, {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'D d M Y',
            defaultDate: fpSelectedDate,
            minDate: fpMinDate,
            maxDate: fpMaxDate,
            locale: { firstDayOfWeek: 1 },
            onChange: function(selectedDates, dateStr) {
                if (dateStr && dateStr !== fpSelectedDate) {
                    const url = new URL(window.location.href);
                    if (dateStr === fpToday) {
                        url.searchParams.delete('date');
                    } else {
                        url.searchParams.set('date', dateStr);
                    }
                    window.location.href = url.toString();
                }
            }
        });
    }

    // Auto-refresh every 60 seconds (only for today, only when online)
    if (fpSelectedDate === fpToday) {
        refreshInterval = setInterval(function() {
            if (navigator.onLine && !window._staffOfflineMode) {
                refreshFloorPlan();
            }
        }, 60000);
    }

    // Make left panel hide/show on small screens via swipe or toggle
    const leftPanel = document.querySelector('.w-80');
    if (leftPanel && window.innerWidth < 768) {
        leftPanel.style.display = 'none';
        // Add a toggle button for mobile
        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'fixed bottom-4 left-4 z-30 w-12 h-12 rounded-full bg-gold-500 text-dark-950 flex items-center justify-center shadow-lg touch-btn';
        toggleBtn.innerHTML = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>';
        toggleBtn.onclick = () => {
            const isHidden = leftPanel.style.display === 'none';
            leftPanel.style.display = isHidden ? '' : 'none';
            leftPanel.style.position = isHidden ? 'fixed' : '';
            leftPanel.style.zIndex = isHidden ? '40' : '';
            leftPanel.style.top = isHidden ? '0' : '';
            leftPanel.style.left = isHidden ? '0' : '';
            leftPanel.style.bottom = isHidden ? '0' : '';
            leftPanel.style.width = isHidden ? '85vw' : '';
            leftPanel.style.maxWidth = isHidden ? '380px' : '';
        };
        document.body.appendChild(toggleBtn);
    }
});
</script>

<?php endif; ?>

<script src="<?= resUrl('/js/idb.js') ?>"></script>
<script src="<?= resUrl('/js/offline-staff.js') ?>"></script>
<?php renderFooter(); ?>
