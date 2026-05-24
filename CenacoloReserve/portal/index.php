<?php
/**
 * Cenacolo Reservas - Portal de Concierges - Dashboard
 * Vista principal del concierge: stats, reservas recientes, acceso rapido
 */
require_once __DIR__ . '/../includes/config.php';

$concierge = requireConciergeLogin();
logResAccess($concierge['id'], 'portal_dashboard', 'concierge');

$pdo = getResDB();
$conciergeId = $concierge['id'];
$currentMonth = date('Y-m-01');
$today = date('Y-m-d');

// === STATS: Total reservas historicas ===
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM reservations WHERE concierge_id = ?");
$stmt->execute([$conciergeId]);
$totalAllTime = $stmt->fetch()['total'];

// === STATS: Reservas este mes ===
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) as total,
        COALESCE(SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END), 0) as confirmed,
        COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) as completed,
        COALESCE(SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END), 0) as no_shows,
        COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END), 0) as cancelled,
        COALESCE(SUM(CASE WHEN status = 'seated' THEN 1 ELSE 0 END), 0) as seated,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending
    FROM reservations
    WHERE concierge_id = ? AND reservation_date >= ?
");
$stmt->execute([$conciergeId, $currentMonth]);
$monthStats = $stmt->fetch();

// === Reservas recientes (ultimas 20) ===
$stmt = $pdo->prepare("
    SELECT r.*, rest.name as restaurant_name
    FROM reservations r
    LEFT JOIN restaurants rest ON r.restaurant_id = rest.id
    WHERE r.concierge_id = ?
    ORDER BY r.created_at DESC
    LIMIT 20
");
$stmt->execute([$conciergeId]);
$recentReservations = $stmt->fetchAll();

// === Perks & Acuerdos ===
$perks = [];
try {
    $perkStmt = $pdo->prepare("SELECT * FROM concierge_perks WHERE concierge_id = ? AND active = 1 ORDER BY perk_type, name");
    $perkStmt->execute([$conciergeId]);
    $perks = $perkStmt->fetchAll();
} catch (Exception $e) { /* table may not exist yet */ }

// === STATS: Commission info ===
$commissionType = $concierge['commission_type'] ?? 'percentage';
$commissionValue = floatval($concierge['commission_value'] ?? 0);
$commissionEarned = floatval($concierge['commission_earned'] ?? 0);
$referralCode = $concierge['referral_code'] ?? '';

// Totales desde tabla commissions (reemplaza commission_earned impreciso)
$stmtPending = $pdo->prepare("SELECT COALESCE(SUM(commission_amount), 0) FROM commissions WHERE concierge_id = ? AND status = 'pending'");
$stmtPending->execute([$conciergeId]);
$pendingCommissions = floatval($stmtPending->fetchColumn());

$stmtPaid = $pdo->prepare("SELECT COALESCE(SUM(commission_amount), 0) FROM commissions WHERE concierge_id = ? AND status = 'paid'");
$stmtPaid->execute([$conciergeId]);
$paidCommissions = floatval($stmtPaid->fetchColumn());

$hasBankData = !empty($concierge['bank_clabe']);
$referralUrl = $referralCode ? getReferralUrl($referralCode) : '';
$partnerType = $concierge['type'] ?? 'concierge';

// Nombre a mostrar
$conciergeName = resSanitize($concierge['name']);
$hotelName = resSanitize($concierge['hotel_name'] ?? ($concierge['company_name'] ?? 'Sin hotel asignado'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Portal Concierge - Cenacolo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        gold: {
                            50: '#FFF9E6', 100: '#FFF2CC', 200: '#FFE599', 300: '#FFD966',
                            400: '#FFCC33', 500: '#D4AF37', 600: '#B8960F', 700: '#8C7200',
                            800: '#5F4D00', 900: '#332900'
                        },
                        dark: {
                            50: '#f8fafc', 100: '#f1f5f9', 200: '#e2e8f0', 300: '#cbd5e1',
                            400: '#94a3b8', 500: '#64748b', 600: '#475569', 700: '#334155',
                            800: '#1e293b', 900: '#0f172a', 950: '#020617'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-display { font-family: 'Playfair Display', serif; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn 0.3s ease-out; }
        .status-badge { font-size: 0.7rem; padding: 2px 8px; border-radius: 9999px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
        .status-pending { background: #FEF3C7; color: #92400E; }
        .status-confirmed { background: #D1FAE5; color: #065F46; }
        .status-seated { background: #DBEAFE; color: #1E40AF; }
        .status-completed { background: #E0E7FF; color: #3730A3; }
        .status-no_show { background: #FEE2E2; color: #991B1B; }
        .status-cancelled { background: #F3F4F6; color: #374151; }
        .status-waitlist { background: #FDE68A; color: #78350F; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #1e293b; }
        ::-webkit-scrollbar-thumb { background: #475569; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #64748b; }
    </style>
</head>
<body class="bg-dark-950 text-dark-200 min-h-screen">

    <!-- Top Navbar -->
    <nav class="sticky top-0 z-30 bg-dark-900/90 backdrop-blur-md border-b border-dark-700">
        <div class="max-w-6xl mx-auto px-4 sm:px-6">
            <div class="flex items-center justify-between h-16">
                <!-- Logo -->
                <div class="flex items-center space-x-3">
                    <h1 class="font-display text-xl text-gold-500 font-bold tracking-wide">Cenacolo</h1>
                    <span class="text-dark-500 text-xs">|</span>
                    <span class="text-dark-400 text-xs uppercase tracking-wider">Portal Concierge</span>
                </div>
                <!-- User + Logout -->
                <div class="flex items-center space-x-4">
                    <!-- Connection status chip -->
                    <span id="connStatus" class="flex items-center gap-1.5 text-xs font-medium text-green-400 hidden sm:flex">
                        <span class="w-2 h-2 rounded-full bg-green-400 inline-block"></span> En línea
                    </span>
                    <a href="<?= resUrl('/portal/commissions.php') ?>" class="text-dark-400 hover:text-gold-400 text-sm hidden sm:block transition-colors">Mis Comisiones</a>
                    <a href="<?= resUrl('/portal/bank-data.php') ?>" class="text-dark-400 hover:text-gold-400 text-sm hidden sm:block transition-colors">Datos Bancarios</a>
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-medium text-white"><?= $conciergeName ?></p>
                        <p class="text-xs text-dark-400"><?= $hotelName ?></p>
                    </div>
                    <div class="w-9 h-9 rounded-full bg-gold-500 flex items-center justify-center text-dark-950 font-bold text-sm">
                        <?= strtoupper(substr($concierge['name'], 0, 1)) ?>
                    </div>
                    <a href="<?= resUrl('/portal/logout.php') ?>" class="text-dark-400 hover:text-red-400 transition-colors" title="Cerrar sesion">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Offline banner — shown by offline.js when navigator.onLine === false -->
    <div id="offlineBanner" class="hidden bg-yellow-900 border-b border-yellow-700 text-yellow-200 px-4 py-3 text-sm text-center">
        📵 <strong>Sin conexión</strong> — los cambios se guardan localmente y se sincronizarán automáticamente al reconectar.
    </div>

    <!-- Main Content -->
    <main class="max-w-6xl mx-auto px-4 sm:px-6 py-6">

        <!-- Welcome + CTA -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8 fade-in">
            <div>
                <h2 class="text-2xl font-semibold text-white">Bienvenido, <?= $conciergeName ?></h2>
                <p class="text-dark-400 text-sm mt-1"><?= $hotelName ?> &middot; <?= date('l, d \d\e F Y') ?></p>
            </div>
            <a href="<?= resUrl('/portal/new-reservation.php') ?>"
               class="mt-4 sm:mt-0 inline-flex items-center px-6 py-3 bg-gold-500 text-dark-950 rounded-lg font-semibold hover:bg-gold-400 transition-colors shadow-lg shadow-gold-500/20">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nueva Reserva
            </a>
        </div>

        <?php if (!$hasBankData): ?>
        <div class="bg-yellow-900/30 border border-yellow-600/40 rounded-xl px-5 py-4 mb-6 flex items-center justify-between fade-in">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-yellow-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                <p class="text-yellow-300 text-sm">Sin datos bancarios — no podremos procesar tu comisión hasta que los registres.</p>
            </div>
            <a href="<?= resUrl('/portal/bank-data.php') ?>"
               class="shrink-0 ml-4 px-4 py-2 bg-yellow-500/20 border border-yellow-500/40 text-yellow-300 rounded-lg text-xs font-semibold hover:bg-yellow-500/30 transition-colors whitespace-nowrap">
                Registrar ahora
            </a>
        </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8 fade-in">
            <!-- Total este mes -->
            <div class="bg-dark-900 rounded-xl border border-dark-700 p-5">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs text-dark-400 uppercase tracking-wider">Este mes</span>
                    <svg class="w-5 h-5 text-gold-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <p class="text-3xl font-bold text-white"><?= $monthStats['total'] ?></p>
                <p class="text-xs text-dark-400 mt-1">reservas</p>
            </div>

            <!-- Confirmadas -->
            <div class="bg-dark-900 rounded-xl border border-dark-700 p-5">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs text-dark-400 uppercase tracking-wider">Confirmadas</span>
                    <div class="w-2.5 h-2.5 rounded-full bg-green-500"></div>
                </div>
                <p class="text-3xl font-bold text-green-400"><?= $monthStats['confirmed'] + $monthStats['seated'] + $monthStats['pending'] ?></p>
                <p class="text-xs text-dark-400 mt-1">activas este mes</p>
            </div>

            <!-- No Shows -->
            <div class="bg-dark-900 rounded-xl border border-dark-700 p-5">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs text-dark-400 uppercase tracking-wider">No Shows</span>
                    <div class="w-2.5 h-2.5 rounded-full bg-red-500"></div>
                </div>
                <p class="text-3xl font-bold text-red-400"><?= $monthStats['no_shows'] ?></p>
                <p class="text-xs text-dark-400 mt-1">este mes</p>
            </div>

            <!-- Total historico -->
            <div class="bg-dark-900 rounded-xl border border-dark-700 p-5">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs text-dark-400 uppercase tracking-wider">Total historico</span>
                    <svg class="w-5 h-5 text-gold-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </div>
                <p class="text-3xl font-bold text-gold-500"><?= $totalAllTime ?></p>
                <p class="text-xs text-dark-400 mt-1">reservas totales</p>
            </div>
        </div>

        <!-- Referral Link & Commission Info -->
        <?php if ($referralCode): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8 fade-in">
            <!-- Referral Link Card -->
            <div class="bg-dark-900 rounded-xl border border-dark-700 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-semibold text-dark-400 uppercase tracking-wider">Tu Link de Referido</h3>
                    <span class="px-2 py-0.5 rounded text-xs font-semibold <?= $partnerType === 'affiliate' ? 'bg-purple-500/20 text-purple-400' : 'bg-gold-500/20 text-gold-400' ?>">
                        <?= $partnerType === 'affiliate' ? 'Afiliado' : 'Concierge' ?>
                    </span>
                </div>
                <div class="flex items-center gap-2 mb-3">
                    <code class="flex-1 text-xs text-gold-400 bg-dark-800 rounded-lg px-3 py-2.5 overflow-hidden text-ellipsis whitespace-nowrap block"><?= resSanitize($referralUrl) ?></code>
                    <button onclick="copyRefLink()" id="copyBtn"
                            class="px-4 py-2.5 bg-gold-500/20 text-gold-500 rounded-lg text-xs font-semibold hover:bg-gold-500/30 transition-colors whitespace-nowrap">
                        Copiar
                    </button>
                </div>
                <p class="text-xs text-dark-500">Comparte este link con tus clientes. Las reservas hechas a traves de el se asociaran automaticamente a tu cuenta.</p>
                <div class="flex items-center gap-3 mt-3">
                    <p class="text-xs text-dark-500">Codigo: <span class="font-mono text-gold-500"><?= resSanitize($referralCode) ?></span></p>
                    <button onclick="toggleQR()" class="text-xs text-gold-400 hover:text-gold-300 underline">Ver QR</button>
                </div>
                <div id="qrContainer" class="hidden mt-3 text-center">
                    <div id="qrReferralCode" class="inline-block bg-white p-2 rounded-lg"></div>
                    <p class="text-xs text-dark-500 mt-1">Escanea para reservar</p>
                </div>
            </div>

            <!-- Commission Card -->
            <div class="bg-dark-900 rounded-xl border border-dark-700 p-5">
                <h3 class="text-xs font-semibold text-dark-400 uppercase tracking-wider mb-3">Comisiones</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-dark-500 mb-1">Tu tarifa</p>
                        <?php if ($commissionValue > 0): ?>
                            <p class="text-2xl font-bold <?= $partnerType === 'affiliate' ? 'text-purple-400' : 'text-gold-500' ?>">
                                <?= $commissionType === 'fixed'
                                    ? '$' . number_format($commissionValue, 0) . ' MXN'
                                    : number_format($commissionValue, 1) . '%' ?>
                            </p>
                            <p class="text-xs text-dark-500"><?= $commissionType === 'fixed' ? 'por reserva completada' : 'del consumo' ?></p>
                        <?php else: ?>
                            <p class="text-lg text-dark-500">No configurada</p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="text-xs text-dark-500 mb-1">Pendiente de cobro</p>
                        <p class="text-2xl font-bold text-yellow-400">$<?= number_format($pendingCommissions, 2) ?></p>
                        <p class="text-xs text-dark-500">por depositar</p>
                    </div>
                </div>
                <?php
                    $completedThisMonth = 0;
                    foreach ($recentReservations as $r) {
                        if ($r['status'] === 'completed' && strtotime($r['reservation_date']) >= strtotime($currentMonth)) {
                            $completedThisMonth++;
                        }
                    }
                ?>
                <div class="mt-4 pt-3 border-t border-dark-700">
                    <div class="flex justify-between text-xs">
                        <span class="text-dark-400">Total cobrado:</span>
                        <span class="text-green-400 font-semibold">$<?= number_format($paidCommissions, 2) ?></span>
                    </div>
                    <div class="flex justify-between text-xs mt-1">
                        <span class="text-dark-400">Completadas este mes:</span>
                        <span class="text-white font-semibold"><?= $completedThisMonth ?></span>
                    </div>
                </div>
                <a href="<?= resUrl('/portal/commissions.php') ?>" class="block text-center text-xs text-gold-400 hover:text-gold-300 mt-3 pt-2 border-t border-dark-700">
                    Ver historial completo &rarr;
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Perks & Acuerdos -->
        <?php if (!empty($perks)): ?>
        <div class="bg-dark-900 rounded-xl border border-dark-700 fade-in">
            <div class="px-5 py-4 border-b border-dark-700">
                <h3 class="text-sm font-semibold text-white uppercase tracking-wider">Tus Perks & Acuerdos</h3>
                <p class="text-xs text-dark-400 mt-1">Beneficios exclusivos asignados</p>
            </div>
            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?php
                    $perkTypeLabels = ['gift' => 'Regalo', 'discount' => 'Descuento', 'courtesy' => 'Cortesía', 'agreement' => 'Acuerdo'];
                    $perkTypeIcons = ['gift' => '🎁', 'discount' => '💰', 'courtesy' => '🥂', 'agreement' => '📋'];
                    $perkTypeBgs = ['gift' => 'bg-pink-500/10 border-pink-500/20', 'discount' => 'bg-green-500/10 border-green-500/20', 'courtesy' => 'bg-blue-500/10 border-blue-500/20', 'agreement' => 'bg-gold-500/10 border-gold-500/20'];
                ?>
                <?php foreach ($perks as $perk): ?>
                    <div class="p-4 rounded-lg border <?= $perkTypeBgs[$perk['perk_type']] ?? 'bg-dark-800 border-dark-700' ?>">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-lg"><?= $perkTypeIcons[$perk['perk_type']] ?? '🏷️' ?></span>
                            <span class="text-xs font-semibold text-dark-400 uppercase"><?= $perkTypeLabels[$perk['perk_type']] ?? $perk['perk_type'] ?></span>
                            <?php if ($perk['value']): ?>
                                <span class="text-xs font-bold text-gold-400 ml-auto"><?= resSanitize($perk['value']) ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="text-sm text-white font-medium"><?= resSanitize($perk['name']) ?></p>
                        <?php if ($perk['description']): ?>
                            <p class="text-xs text-dark-400 mt-1"><?= resSanitize($perk['description']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recent Reservations -->
        <div class="bg-dark-900 rounded-xl border border-dark-700 fade-in">
            <div class="px-5 py-4 border-b border-dark-700 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-white uppercase tracking-wider">Mis Reservas Recientes</h3>
                <span class="text-xs text-dark-400"><?= count($recentReservations) ?> ultimas</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-dark-700">
                            <th class="px-4 py-3 text-left text-xs font-semibold text-dark-400 uppercase tracking-wider">Codigo</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-dark-400 uppercase tracking-wider">Fecha</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-dark-400 uppercase tracking-wider">Hora</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-dark-400 uppercase tracking-wider">Huesped</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-dark-400 uppercase tracking-wider">Pax</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-dark-400 uppercase tracking-wider">Restaurante</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-dark-400 uppercase tracking-wider">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-dark-800">
                        <?php if (empty($recentReservations)): ?>
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-dark-500">
                                    <svg class="w-12 h-12 mx-auto mb-3 text-dark-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <p class="text-sm">Aun no tienes reservas</p>
                                    <a href="<?= resUrl('/portal/new-reservation.php') ?>" class="text-gold-500 hover:text-gold-400 text-sm mt-2 inline-block">Crear tu primera reserva</a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentReservations as $res): ?>
                                <tr class="hover:bg-dark-800/50 transition-colors" data-reservation-id="<?= $res['id'] ?>">
                                    <td class="px-4 py-3">
                                        <span class="text-xs font-mono text-gold-500"><?= resSanitize($res['confirmation_code']) ?></span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-sm text-white"><?= formatDate($res['reservation_date']) ?></span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-sm font-mono text-white"><?= formatTime($res['reservation_time']) ?></span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-sm text-white font-medium"><?= resSanitize($res['guest_name']) ?></span>
                                        <?php if ($res['guest_phone']): ?>
                                            <br><span class="text-xs text-dark-500"><?= resSanitize($res['guest_phone']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-sm font-semibold text-white"><?= $res['party_size'] ?></span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-xs text-dark-300"><?= resSanitize($res['restaurant_name'] ?? '') ?></span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="offline-badge hidden text-xs px-2 py-0.5 rounded-full bg-yellow-900/40 text-yellow-300 font-semibold mr-1">⏳ Sin sincronizar</span>
                                        <span class="status-badge status-<?= $res['status'] ?>">
                                            <?= ucfirst(str_replace('_', ' ', $res['status'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Footer -->
    <footer class="max-w-6xl mx-auto px-4 sm:px-6 py-6 text-center">
        <p class="text-dark-600 text-xs">
            Cenacolo Reservas &middot; Portal de Concierges &middot;
            Powered by <a href="https://somossinergia.com" class="text-gold-500 hover:text-gold-400 transition-colors">SinergIA</a>
        </p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
        function copyRefLink() {
            const url = '<?= $referralUrl ? addslashes($referralUrl) : '' ?>';
            if (!url) return;
            navigator.clipboard.writeText(url).then(() => {
                const btn = document.getElementById('copyBtn');
                btn.textContent = 'Copiado!';
                btn.classList.add('bg-green-500/20', 'text-green-400');
                setTimeout(() => {
                    btn.textContent = 'Copiar';
                    btn.classList.remove('bg-green-500/20', 'text-green-400');
                }, 2000);
            }).catch(() => {
                prompt('Copia este link:', url);
            });
        }

        let qrGenerated = false;
        function toggleQR() {
            const container = document.getElementById('qrContainer');
            if (!container) return;
            container.classList.toggle('hidden');
            if (!qrGenerated && typeof QRCode !== 'undefined') {
                const url = '<?= $referralUrl ? addslashes($referralUrl) : '' ?>';
                if (url) {
                    new QRCode(document.getElementById('qrReferralCode'), {
                        text: url,
                        width: 160, height: 160,
                        colorDark: '#000000', colorLight: '#ffffff'
                    });
                    qrGenerated = true;
                }
            }
        }
    </script>

    <script src="<?= resUrl('/portal/js/idb.js') ?>"></script>
    <script src="<?= resUrl('/portal/js/offline.js') ?>"></script>
</body>
</html>
