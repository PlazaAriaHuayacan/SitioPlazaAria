<?php
/**
 * Cenacolo Reservas - Dashboard Principal
 * Vista de reservas de hoy con KPIs en tiempo real
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/layout.php';

$user = requireResLogin();
logResAccess($user['id'], 'dashboard', 'staff');

$pdo = getResDB();
$today = date('Y-m-d');
$currentRestaurant = $_SESSION['res_current_restaurant'] ?? 'all';

// ======= QUERIES PARA DASHBOARD =======

// Filtro de restaurante
$restaurantFilter = '';
$params = [];
if ($currentRestaurant !== 'all') {
    $restaurantFilter = ' AND r.restaurant_id = ?';
    $params[] = $currentRestaurant;
}

// KPIs de hoy
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) as total_reservations,
        COALESCE(SUM(r.party_size), 0) as total_covers,
        COALESCE(SUM(CASE WHEN r.status = 'confirmed' THEN 1 ELSE 0 END), 0) as confirmed,
        COALESCE(SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END), 0) as pending,
        COALESCE(SUM(CASE WHEN r.status = 'seated' THEN 1 ELSE 0 END), 0) as seated,
        COALESCE(SUM(CASE WHEN r.status = 'completed' THEN 1 ELSE 0 END), 0) as completed,
        COALESCE(SUM(CASE WHEN r.status = 'no_show' THEN 1 ELSE 0 END), 0) as no_shows,
        COALESCE(SUM(CASE WHEN r.status = 'cancelled' THEN 1 ELSE 0 END), 0) as cancelled,
        COALESCE(SUM(CASE WHEN r.channel = 'walkin' THEN 1 ELSE 0 END), 0) as walkins,
        COALESCE(AVG(r.party_size), 0) as avg_party_size
    FROM reservations r
    WHERE r.reservation_date = ? {$restaurantFilter}
");
$stmt->execute(array_merge([$today], $params));
$kpis = $stmt->fetch();

// Reservas de hoy (lista completa)
$stmt = $pdo->prepare("
    SELECT r.*, rest.name as restaurant_name, rest.slug as restaurant_slug,
           t.table_number, s.name as section_name,
           c.first_name as customer_first_name, c.last_name as customer_last_name,
           c.vip_level, c.total_visits as customer_visits
    FROM reservations r
    LEFT JOIN restaurants rest ON r.restaurant_id = rest.id
    LEFT JOIN tables t ON r.table_id = t.id
    LEFT JOIN restaurant_sections s ON t.section_id = s.id
    LEFT JOIN customers c ON r.customer_id = c.id
    WHERE r.reservation_date = ? {$restaurantFilter}
    ORDER BY r.reservation_time ASC
");
$stmt->execute(array_merge([$today], $params));
$todayReservations = $stmt->fetchAll();

// Reservas por hora (para el gráfico)
$stmt = $pdo->prepare("
    SELECT HOUR(r.reservation_time) as hour, COUNT(*) as count, SUM(r.party_size) as covers
    FROM reservations r
    WHERE r.reservation_date = ? AND r.status NOT IN ('cancelled') {$restaurantFilter}
    GROUP BY HOUR(r.reservation_time)
    ORDER BY hour
");
$stmt->execute(array_merge([$today], $params));
$hourlyData = $stmt->fetchAll();

// Reservas por canal hoy
$stmt = $pdo->prepare("
    SELECT r.channel, COUNT(*) as count
    FROM reservations r
    WHERE r.reservation_date = ? AND r.status NOT IN ('cancelled') {$restaurantFilter}
    GROUP BY r.channel
    ORDER BY count DESC
");
$stmt->execute(array_merge([$today], $params));
$channelData = $stmt->fetchAll();

// Próximas reservas (siguiente hora)
$nextHour = date('H:i:s', strtotime('+1 hour'));
$currentTime = date('H:i:s');
$stmt = $pdo->prepare("
    SELECT r.*, rest.name as restaurant_name, t.table_number
    FROM reservations r
    LEFT JOIN restaurants rest ON r.restaurant_id = rest.id
    LEFT JOIN tables t ON r.table_id = t.id
    WHERE r.reservation_date = ?
      AND r.reservation_time BETWEEN ? AND ?
      AND r.status IN ('confirmed', 'pending')
      {$restaurantFilter}
    ORDER BY r.reservation_time ASC
    LIMIT 10
");
$stmt->execute(array_merge([$today, $currentTime, $nextHour], $params));
$upcomingReservations = $stmt->fetchAll();

// Reservas de mañana (preview)
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count, COALESCE(SUM(party_size), 0) as covers
    FROM reservations r
    WHERE r.reservation_date = ? AND r.status NOT IN ('cancelled') {$restaurantFilter}
");
$stmt->execute(array_merge([$tomorrow], $params));
$tomorrowPreview = $stmt->fetch();

// Capacity per shift (for current restaurant)
$capacityShifts = [];
if ($currentRestaurant !== 'all') {
    $capStmt = $pdo->prepare("SELECT * FROM restaurant_capacity WHERE restaurant_id = ? AND override_date IS NULL AND active = 1 ORDER BY shift_start");
    $capStmt->execute([$currentRestaurant]);
    $defaultShifts = $capStmt->fetchAll();
    foreach ($defaultShifts as $ds) {
        $cap = checkCapacity($pdo, $currentRestaurant, $today, $ds['shift_start'], 0);
        $capacityShifts[] = [
            'shift_name' => $ds['shift_name'],
            'max_covers' => $cap['max_covers'],
            'current_covers' => $cap['current_covers'],
            'remaining' => $cap['remaining'],
            'is_override' => $cap['is_override']
        ];
    }
}

// Last OpenTable sync timestamp
$lastSync = null;
try {
    $syncStmt = $pdo->prepare("SELECT last_sync_at FROM restaurant_integrations WHERE platform = 'opentable' AND active = 1 ORDER BY last_sync_at DESC LIMIT 1");
    $syncStmt->execute();
    $lastSync = $syncStmt->fetchColumn();
} catch (Exception $e) {}

renderHeader('Dashboard', $user);
?>

<!-- Sync Status Bar -->
<?php if ($lastSync): ?>
<div class="flex items-center justify-between mb-3 px-1 fade-in">
    <span class="text-xs text-dark-500">
        🔄 Último sync OpenTable: <span class="text-dark-400 font-medium"><?= date('H:i', strtotime($lastSync)) ?></span>
        <span class="text-dark-600 ml-1">(<?= date('d/m', strtotime($lastSync)) ?>)</span>
    </span>
    <span class="text-xs text-dark-600" id="autoRefreshLabel">Auto-refresh: 60s</span>
</div>
<?php endif; ?>

<!-- KPI Cards -->
<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 lg:gap-4 mb-6 fade-in">
    <!-- Total Reservas -->
    <div class="bg-dark-900 rounded-xl border border-dark-700 p-4">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs text-dark-400 uppercase tracking-wider">Reservas Hoy</span>
            <svg class="w-5 h-5 text-gold-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </div>
        <p class="text-2xl font-bold text-white"><?= $kpis['total_reservations'] ?></p>
        <p class="text-xs text-dark-400 mt-1"><?= $kpis['total_covers'] ?> personas</p>
    </div>

    <!-- Confirmadas -->
    <div class="bg-dark-900 rounded-xl border border-dark-700 p-4">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs text-dark-400 uppercase tracking-wider">Confirmadas</span>
            <div class="w-2 h-2 rounded-full bg-green-500"></div>
        </div>
        <p class="text-2xl font-bold text-green-400"><?= $kpis['confirmed'] ?></p>
        <p class="text-xs text-dark-400 mt-1">Por llegar</p>
    </div>

    <!-- Sentados -->
    <div class="bg-dark-900 rounded-xl border border-dark-700 p-4">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs text-dark-400 uppercase tracking-wider">Sentados</span>
            <div class="w-2 h-2 rounded-full bg-blue-500"></div>
        </div>
        <p class="text-2xl font-bold text-blue-400"><?= $kpis['seated'] ?></p>
        <p class="text-xs text-dark-400 mt-1">En mesa ahora</p>
    </div>

    <!-- Completadas -->
    <div class="bg-dark-900 rounded-xl border border-dark-700 p-4">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs text-dark-400 uppercase tracking-wider">Completadas</span>
            <div class="w-2 h-2 rounded-full bg-indigo-500"></div>
        </div>
        <p class="text-2xl font-bold text-indigo-400"><?= $kpis['completed'] ?></p>
        <p class="text-xs text-dark-400 mt-1">Servicio completado</p>
    </div>

    <!-- No Shows -->
    <div class="bg-dark-900 rounded-xl border border-dark-700 p-4">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs text-dark-400 uppercase tracking-wider">No Shows</span>
            <div class="w-2 h-2 rounded-full bg-red-500"></div>
        </div>
        <p class="text-2xl font-bold text-red-400"><?= $kpis['no_shows'] ?></p>
        <p class="text-xs text-dark-400 mt-1">No se presentaron</p>
    </div>

    <!-- Walk-ins -->
    <div class="bg-dark-900 rounded-xl border border-dark-700 p-4">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs text-dark-400 uppercase tracking-wider">Walk-ins</span>
            <div class="w-2 h-2 rounded-full bg-purple-500"></div>
        </div>
        <p class="text-2xl font-bold text-purple-400"><?= $kpis['walkins'] ?></p>
        <p class="text-xs text-dark-400 mt-1">Sin reserva</p>
    </div>
</div>

<!-- Capacity Bars -->
<?php if (!empty($capacityShifts)): ?>
<div class="bg-dark-900 rounded-xl border border-dark-700 p-4 mb-6 fade-in">
    <div class="flex items-center gap-2 mb-3">
        <svg class="w-4 h-4 text-gold-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
        <span class="text-xs text-dark-400 uppercase tracking-wider font-semibold">Capacidad Hoy</span>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-<?= count($capacityShifts) ?> gap-4">
        <?php foreach ($capacityShifts as $cs):
            $pct = $cs['max_covers'] > 0 ? min(round(($cs['current_covers'] / $cs['max_covers']) * 100), 100) : 0;
            $barColor = $pct >= 90 ? 'bg-red-500' : ($pct >= 70 ? 'bg-yellow-500' : 'bg-emerald-500');
            $textColor = $pct >= 90 ? 'text-red-400' : ($pct >= 70 ? 'text-yellow-400' : 'text-emerald-400');
        ?>
        <div>
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-sm text-white font-medium capitalize"><?= resSanitize($cs['shift_name']) ?></span>
                <span class="text-sm font-mono <?= $textColor ?> font-bold"><?= $cs['current_covers'] ?>/<?= $cs['max_covers'] ?></span>
            </div>
            <div class="h-2.5 bg-dark-700 rounded-full overflow-hidden">
                <div class="h-full <?= $barColor ?> rounded-full transition-all duration-500" style="width:<?= $pct ?>%"></div>
            </div>
            <div class="flex items-center justify-between mt-1">
                <span class="text-[10px] text-dark-500"><?= $pct ?>% ocupado</span>
                <?php if ($cs['is_override']): ?>
                    <span class="text-[10px] text-gold-400 font-semibold">Override</span>
                <?php else: ?>
                    <span class="text-[10px] text-dark-600"><?= $cs['remaining'] ?> disponibles</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Main Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 fade-in">

    <!-- LEFT: Reservas de Hoy (Timeline) -->
    <div class="lg:col-span-2">
        <div class="bg-dark-900 rounded-xl border border-dark-700">
            <div class="px-5 py-4 border-b border-dark-700 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-white uppercase tracking-wider">Reservas de Hoy</h3>
                <div class="flex items-center space-x-2">
                    <span class="text-xs text-dark-400"><?= count($todayReservations) ?> reservas</span>
                    <?php if ($currentRestaurant !== 'all'): ?>
                    <button onclick="openWalkinModal()" class="flex items-center gap-1 px-3 py-1 bg-purple-900/40 border border-purple-700/40 text-purple-300 rounded-lg text-xs font-semibold hover:bg-purple-900/60 transition-colors touch-btn" title="Registrar walk-in rápido">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Walk-in
                    </button>
                    <?php endif; ?>
                    <a href="<?= resUrl('/reservations.php?date=' . $today) ?>" class="text-xs text-gold-500 hover:text-gold-400">Ver todas &rarr;</a>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-dark-700">
                            <th class="px-4 py-3 text-left text-xs font-semibold text-dark-400 uppercase tracking-wider">Hora</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-dark-400 uppercase tracking-wider">Huésped</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-dark-400 uppercase tracking-wider">Pax</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-dark-400 uppercase tracking-wider">Mesa</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-dark-400 uppercase tracking-wider">Canal</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-dark-400 uppercase tracking-wider">Estado</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-dark-400 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-800">
                        <?php if (empty($todayReservations)): ?>
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-dark-500">
                                    <svg class="w-12 h-12 mx-auto mb-3 text-dark-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    No hay reservas para hoy
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($todayReservations as $res): ?>
                                <?php
                                    $isPast = strtotime($res['reservation_time']) < strtotime(date('H:i:s'));
                                    $rowClass = $isPast && $res['status'] === 'confirmed' ? 'bg-yellow-900/10' : '';
                                    $vipBadge = '';
                                    if ($res['vip_level'] === 'vip') $vipBadge = '<span class="ml-1 text-gold-500 text-xs" title="VIP">★</span>';
                                    if ($res['vip_level'] === 'vvip') $vipBadge = '<span class="ml-1 text-purple-400 text-xs" title="VVIP">★★</span>';
                                ?>
                                <tr class="hover:bg-dark-800/50 transition-colors <?= $rowClass ?>" data-reservation-id="<?= (int)$res['id'] ?>">
                                    <td class="px-3 lg:px-4 py-3">
                                        <span class="text-sm font-mono text-white"><?= formatTime($res['reservation_time']) ?></span>
                                        <?php if ($currentRestaurant === 'all'): ?>
                                            <br><span class="text-xs text-dark-500"><?= resSanitize($res['restaurant_name']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 lg:px-4 py-3">
                                        <div class="flex items-center">
                                            <button onclick="showCustomerInfo(<?= $res['customer_id'] ? $res['customer_id'] : 'null' ?>, '<?= addslashes(resSanitize($res['guest_name'])) ?>', '<?= addslashes(resSanitize($res['guest_phone'] ?? '')) ?>', '<?= addslashes(resSanitize($res['guest_email'] ?? '')) ?>', <?= $res['id'] ?>)"
                                                    class="text-sm text-gold-400 hover:text-gold-300 font-medium text-left underline-offset-2 hover:underline touch-btn">
                                                <?= resSanitize($res['guest_name']) ?>
                                            </button>
                                            <?= $vipBadge ?>
                                        </div>
                                        <?php if ($res['guest_phone']): ?>
                                            <a href="tel:<?= resSanitize($res['guest_phone']) ?>" class="text-xs text-dark-500 hover:text-dark-300"><?= resSanitize($res['guest_phone']) ?></a>
                                        <?php endif; ?>
                                        <?php if ($res['occasion']): ?>
                                            <span class="text-xs text-gold-500 block">🎉 <?= resSanitize($res['occasion']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 lg:px-4 py-3">
                                        <span class="text-sm text-white font-semibold"><?= $res['party_size'] ?></span>
                                    </td>
                                    <td class="px-3 lg:px-4 py-3">
                                        <?php if ($res['table_number']): ?>
                                            <span class="text-sm text-white">Mesa <?= resSanitize($res['table_number']) ?></span>
                                            <?php if ($res['section_name']): ?>
                                                <br><span class="text-xs text-dark-500"><?= resSanitize($res['section_name']) ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-xs text-dark-500">Sin asignar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 lg:px-4 py-3">
                                        <span class="channel-badge channel-<?= $res['channel'] ?>"><?= ucfirst($res['channel']) ?></span>
                                    </td>
                                    <td class="px-3 lg:px-4 py-3">
                                        <span class="offline-badge hidden text-xs px-1.5 py-0.5 rounded-full bg-yellow-900/60 text-yellow-300 border border-yellow-700/40 mr-1" title="Cambio pendiente de sincronizar">⏳</span>
                                        <span class="status-badge status-<?= $res['status'] ?>"><?= ucfirst(str_replace('_', ' ', $res['status'])) ?></span>
                                    </td>
                                    <td class="px-3 lg:px-4 py-3">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <?php if ($res['status'] === 'confirmed' || $res['status'] === 'pending'): ?>
                                                <?php if ($res['table_id']): ?>
                                                    <button onclick="confirmSeat(<?= $res['id'] ?>, <?= $res['table_id'] ?>)" class="action-btn action-btn-seat text-xs touch-btn" title="Sentar en Mesa <?= resSanitize($res['table_number'] ?? '') ?>">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                        <span class="hidden sm:inline">Sentar</span>
                                                    </button>
                                                <?php else: ?>
                                                    <button onclick="assignTableOnly(<?= $res['id'] ?>, <?= $res['party_size'] ?>, <?= $res['restaurant_id'] ?>, '<?= $res['reservation_time'] ?>')" class="action-btn action-btn-assign text-xs touch-btn" title="Asignar Mesa">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                                        <span class="hidden sm:inline">Asignar</span>
                                                    </button>
                                                    <button onclick="seatWithTable(<?= $res['id'] ?>, <?= $res['party_size'] ?>, <?= $res['restaurant_id'] ?>, '<?= $res['reservation_time'] ?>')" class="action-btn action-btn-seat text-xs touch-btn" title="Sentar ahora">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                    </button>
                                                <?php endif; ?>
                                                <button onclick="updateStatus(<?= $res['id'] ?>, 'no_show')" class="action-btn action-btn-noshow text-xs touch-btn" title="No Show">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            <?php elseif ($res['status'] === 'seated'): ?>
                                                <button onclick="updateStatus(<?= $res['id'] ?>, 'completed')" class="action-btn action-btn-complete text-xs touch-btn" title="Completar">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                    <span class="hidden sm:inline">Completar</span>
                                                </button>
                                            <?php endif; ?>
                                            <a href="<?= resUrl('/reservations.php?action=edit&id=' . $res['id']) ?>" class="action-btn action-btn-edit text-xs touch-btn" title="Editar">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </a>
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

    <!-- RIGHT: Side panels -->
    <div class="space-y-6">

        <!-- Próximas en llegar -->
        <div class="bg-dark-900 rounded-xl border border-dark-700">
            <div class="px-5 py-4 border-b border-dark-700">
                <h3 class="text-sm font-semibold text-white uppercase tracking-wider">Próximas en llegar</h3>
                <p class="text-xs text-dark-400 mt-1">Siguiente hora</p>
            </div>
            <div class="p-4 space-y-3">
                <?php if (empty($upcomingReservations)): ?>
                    <p class="text-sm text-dark-500 text-center py-4">Sin reservas próximas</p>
                <?php else: ?>
                    <?php foreach ($upcomingReservations as $up): ?>
                        <div class="flex items-center justify-between p-3 rounded-lg bg-dark-800/50 hover:bg-dark-800 transition-colors">
                            <div>
                                <p class="text-sm font-medium text-white"><?= resSanitize($up['guest_name']) ?></p>
                                <p class="text-xs text-dark-400"><?= formatTime($up['reservation_time']) ?> · <?= $up['party_size'] ?> pax</p>
                            </div>
                            <div class="text-right">
                                <?php if ($up['table_number']): ?>
                                    <span class="text-xs text-gold-500">Mesa <?= $up['table_number'] ?></span>
                                <?php endif; ?>
                                <span class="channel-badge channel-<?= $up['channel'] ?> block mt-1"><?= ucfirst($up['channel']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Reservas por canal -->
        <div class="bg-dark-900 rounded-xl border border-dark-700">
            <div class="px-5 py-4 border-b border-dark-700">
                <h3 class="text-sm font-semibold text-white uppercase tracking-wider">Por Canal</h3>
            </div>
            <div class="p-4">
                <div id="channelChart"></div>
                <?php if (empty($channelData)): ?>
                    <p class="text-sm text-dark-500 text-center py-4">Sin datos</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Preview de mañana -->
        <div class="bg-dark-900 rounded-xl border border-dark-700 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-dark-400 uppercase tracking-wider">Mañana</p>
                    <p class="text-xl font-bold text-white mt-1"><?= $tomorrowPreview['count'] ?> reservas</p>
                    <p class="text-xs text-dark-400"><?= $tomorrowPreview['covers'] ?> personas</p>
                </div>
                <div class="w-12 h-12 rounded-full bg-gold-500/10 flex items-center justify-center">
                    <svg class="w-6 h-6 text-gold-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </div>
            </div>
        </div>

        <!-- Ocupación por hora -->
        <div class="bg-dark-900 rounded-xl border border-dark-700">
            <div class="px-5 py-4 border-b border-dark-700">
                <h3 class="text-sm font-semibold text-white uppercase tracking-wider">Ocupación por Hora</h3>
            </div>
            <div class="p-4">
                <div id="hourlyChart"></div>
            </div>
        </div>
    </div>
</div>

<script>
// ── Status update (handles offline queued response) ────────────────────────
async function updateStatus(reservationId, newStatus) {
    const labels = {
        seated: '¿Sentar al huésped?',
        completed: '¿Marcar como completada?',
        no_show: '¿Marcar como No Show?',
        cancelled: '¿Cancelar esta reserva?'
    };

    if (!await confirmAction(labels[newStatus] || '¿Confirmar cambio?')) return;

    try {
        const res = await fetch('<?= resUrl('/api/reservations.php') ?>', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: reservationId, status: newStatus, client_version: Date.now() })
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
            setTimeout(() => window.location.reload(), 500);
        } else {
            showToast(data.error || 'Error al actualizar', 'error');
        }
    } catch (e) {
        showToast('Error de conexión', 'error');
    }
}

// ── Quick walk-in modal ────────────────────────────────────────────────────
var WALKIN_RESTAURANT_ID = <?= (int)($currentRestaurant !== 'all' ? $currentRestaurant : 0) ?>;

function openWalkinModal() {
    var existing = document.getElementById('walkinModal');
    if (existing) existing.remove();

    var now = new Date();
    var hh  = String(now.getHours()).padStart(2, '0');
    var mm  = String(now.getMinutes()).padStart(2, '0');
    var defaultTime = hh + ':' + mm;

    var modal = document.createElement('div');
    modal.id        = 'walkinModal';
    modal.className = 'customer-modal';
    modal.innerHTML = [
        '<div class="modal-overlay absolute inset-0" onclick="closeWalkinModal()"></div>',
        '<div class="customer-modal-content p-6 relative">',
            '<div class="flex items-center justify-between mb-5">',
                '<div>',
                    '<h3 class="text-lg font-semibold text-white">Walk-in Rápido</h3>',
                    '<p class="text-xs text-dark-400 mt-0.5">La reserva se creará' + (!navigator.onLine ? ' cuando regrese la conexión' : '') + '</p>',
                '</div>',
                '<button onclick="closeWalkinModal()" class="w-8 h-8 rounded-full bg-dark-800 flex items-center justify-center text-dark-400 hover:text-white touch-btn">✕</button>',
            '</div>',
            '<div id="walkinAlert" class="hidden mb-4 px-4 py-3 rounded-lg text-sm font-medium"></div>',
            '<div class="space-y-4">',
                '<div>',
                    '<label class="block text-xs font-semibold text-dark-400 uppercase tracking-wider mb-1.5">Nombre del huésped <span class="font-normal normal-case text-dark-600">(opcional)</span></label>',
                    '<input type="text" id="walkinName" placeholder="Ej: García" maxlength="100"',
                    '       class="w-full bg-dark-800 border border-dark-600 rounded-lg px-4 py-2.5 text-white placeholder-dark-500 focus:outline-none focus:border-gold-500 text-sm">',
                '</div>',
                '<div class="grid grid-cols-2 gap-4">',
                    '<div>',
                        '<label class="block text-xs font-semibold text-dark-400 uppercase tracking-wider mb-1.5">Personas *</label>',
                        '<input type="number" id="walkinParty" min="1" max="30" value="2"',
                        '       class="w-full bg-dark-800 border border-dark-600 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-gold-500 text-sm">',
                    '</div>',
                    '<div>',
                        '<label class="block text-xs font-semibold text-dark-400 uppercase tracking-wider mb-1.5">Hora *</label>',
                        '<input type="time" id="walkinTime" value="' + defaultTime + '"',
                        '       class="w-full bg-dark-800 border border-dark-600 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-gold-500 text-sm">',
                    '</div>',
                '</div>',
                '<button onclick="submitWalkin()" class="w-full py-3 bg-purple-700 text-white rounded-lg font-semibold hover:bg-purple-600 transition-colors text-sm touch-btn" id="walkinSubmitBtn">',
                    'Registrar Walk-in',
                '</button>',
            '</div>',
        '</div>'
    ].join('');
    document.body.appendChild(modal);
    setTimeout(function() { document.getElementById('walkinName').focus(); }, 50);
}

function closeWalkinModal() {
    var modal = document.getElementById('walkinModal');
    if (modal) modal.remove();
}

async function submitWalkin() {
    var name  = (document.getElementById('walkinName').value || '').trim();
    var party = parseInt(document.getElementById('walkinParty').value, 10);
    var time  = document.getElementById('walkinTime').value;
    var btn   = document.getElementById('walkinSubmitBtn');
    var alert = document.getElementById('walkinAlert');

    if (!party || party < 1) {
        alert.textContent = 'Ingresa el número de personas.';
        alert.className = 'mb-4 px-4 py-3 rounded-lg text-sm font-medium bg-red-900/40 border border-red-700/40 text-red-300';
        return;
    }
    if (!time) {
        alert.textContent = 'Ingresa la hora del walk-in.';
        alert.className = 'mb-4 px-4 py-3 rounded-lg text-sm font-medium bg-red-900/40 border border-red-700/40 text-red-300';
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Registrando...';
    alert.className = 'hidden mb-4 px-4 py-3 rounded-lg text-sm font-medium';

    var today = '<?= $today ?>';
    try {
        var res = await fetch('<?= resUrl('/api/reservations.php') ?>', {
            method : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body   : JSON.stringify({
                restaurant_id    : WALKIN_RESTAURANT_ID,
                guest_name       : name || 'Walk-in',
                party_size       : party,
                reservation_date : today,
                reservation_time : time + ':00',
                channel          : 'walkin',
                status           : 'confirmed'
            })
        });
        var data = await res.json();

        if (data.queued) {
            closeWalkinModal();
            showToast('📴 Walk-in guardado offline — se sincronizará al reconectar', 'warning');
            if (window.CenacoloStaffOffline) {
                window.CenacoloStaffOffline.refreshConnChip();
            }
        } else if (data.success) {
            closeWalkinModal();
            showToast('Walk-in registrado correctamente');
            setTimeout(() => window.location.reload(), 500);
        } else {
            alert.textContent = data.error || 'Error al registrar.';
            alert.className = 'mb-4 px-4 py-3 rounded-lg text-sm font-medium bg-red-900/40 border border-red-700/40 text-red-300';
            btn.disabled = false;
            btn.textContent = 'Registrar Walk-in';
        }
    } catch (e) {
        alert.textContent = 'Error de conexión.';
        alert.className = 'mb-4 px-4 py-3 rounded-lg text-sm font-medium bg-red-900/40 border border-red-700/40 text-red-300';
        btn.disabled = false;
        btn.textContent = 'Registrar Walk-in';
    }
}

// ── Charts ─────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    // Channel chart
    const channelData = <?= json_encode($channelData) ?>;
    if (channelData.length > 0) {
        const channelColors = {
            opentable: '#DA3743', website: '#3B82F6', whatsapp: '#25D366',
            manual: '#6B7280', walkin: '#8B5CF6', concierge: '#D4AF37',
            instagram: '#E1306C', facebook: '#1877F2', google: '#4285F4', phone: '#059669'
        };
        new ApexCharts(document.querySelector('#channelChart'), {
            series: channelData.map(d => d.count),
            chart: { type: 'donut', height: 220, background: 'transparent' },
            labels: channelData.map(d => channelLabels[d.channel] || d.channel),
            colors: channelData.map(d => channelColors[d.channel] || '#6B7280'),
            legend: { position: 'bottom', labels: { colors: '#94a3b8' }, fontSize: '11px' },
            plotOptions: { pie: { donut: { size: '60%', labels: { show: true, total: { show: true, label: 'Total', color: '#94a3b8', formatter: function(w) { return w.globals.seriesTotals.reduce((a,b) => a+b, 0); } } } } } },
            dataLabels: { enabled: false },
            stroke: { width: 0 },
            theme: { mode: 'dark' }
        }).render();
    }

    // Hourly chart
    const hourlyData = <?= json_encode($hourlyData) ?>;
    if (hourlyData.length > 0) {
        const hours = Array.from({length: 14}, (_, i) => i + 10); // 10am to 23pm
        const counts = hours.map(h => {
            const found = hourlyData.find(d => d.hour == h);
            return found ? found.count : 0;
        });
        const covers = hours.map(h => {
            const found = hourlyData.find(d => d.hour == h);
            return found ? found.covers : 0;
        });

        new ApexCharts(document.querySelector('#hourlyChart'), {
            series: [
                { name: 'Reservas', data: counts },
                { name: 'Personas', data: covers }
            ],
            chart: { type: 'bar', height: 200, background: 'transparent', toolbar: { show: false } },
            plotOptions: { bar: { borderRadius: 3, columnWidth: '60%' } },
            colors: ['#D4AF37', '#3B82F6'],
            xaxis: { categories: hours.map(h => h + ':00'), labels: { style: { colors: '#64748b', fontSize: '10px' }, rotate: -45 } },
            yaxis: { labels: { style: { colors: '#64748b' } } },
            legend: { position: 'top', labels: { colors: '#94a3b8' }, fontSize: '11px' },
            grid: { borderColor: '#1e293b' },
            dataLabels: { enabled: false },
            tooltip: { theme: 'dark' },
            theme: { mode: 'dark' }
        }).render();
    }

    // Auto-refresh every 60 seconds — skip if offline
    setTimeout(function () {
        if (navigator.onLine && !window._staffOfflineMode) {
            window.location.reload();
        }
    }, 60000);
});
</script>

<script src="<?= resUrl('/js/idb.js') ?>"></script>
<script src="<?= resUrl('/js/offline-staff.js') ?>"></script>
<?php renderFooter(); ?>
