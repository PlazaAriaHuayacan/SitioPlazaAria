<?php
/**
 * Portal Concierge — Mis Comisiones
 */
require_once __DIR__ . '/../includes/config.php';
$concierge = requireConciergeLogin();
logResAccess($concierge['id'], 'portal_commissions', 'concierge');

$pdo = getResDB();
$conciergeId = $concierge['id'];
$filterStatus = $_GET['status'] ?? 'all';

// Totales
$stmtTotals = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN status = 'pending' THEN commission_amount ELSE 0 END), 0) AS pending_total,
        COALESCE(SUM(CASE WHEN status = 'paid'    THEN commission_amount ELSE 0 END), 0) AS paid_total
    FROM commissions WHERE concierge_id = ?
");
$stmtTotals->execute([$conciergeId]);
$totals = $stmtTotals->fetch();

// Lista filtrada
$where = ['cm.concierge_id = ?'];
$params = [$conciergeId];
if ($filterStatus === 'pending') { $where[] = "cm.status = 'pending'"; }
elseif ($filterStatus === 'paid') { $where[] = "cm.status = 'paid'"; }

$stmt = $pdo->prepare("
    SELECT cm.*, r.reservation_date, r.reservation_time, r.guest_name, r.party_size,
           rest.name AS restaurant_name, u.name AS paid_by_name
    FROM commissions cm
    JOIN reservations r ON r.id = cm.reservation_id
    JOIN restaurants rest ON rest.id = r.restaurant_id
    LEFT JOIN res_users u ON u.id = cm.paid_by_user_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY cm.created_at DESC
");
$stmt->execute($params);
$commissions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Comisiones - Portal Concierge - Cenacolo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{colors:{gold:{400:'#FFCC33',500:'#D4AF37'},dark:{700:'#334155',800:'#1e293b',900:'#0f172a',950:'#020617'}}}}}</script>
    <style>body{font-family:'Inter',sans-serif;}.font-display{font-family:'Playfair Display',serif;}</style>
</head>
<body class="bg-dark-950 text-slate-200 min-h-screen">

    <nav class="sticky top-0 z-30 bg-dark-900/90 backdrop-blur-md border-b border-slate-700">
        <div class="max-w-5xl mx-auto px-4 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <h1 class="font-display text-xl text-gold-500 font-bold">Cenacolo</h1>
                <span class="text-slate-600 text-xs">|</span>
                <span class="text-slate-400 text-xs uppercase tracking-wider">Portal Concierge</span>
            </div>
            <a href="<?= resUrl('/portal/index.php') ?>" class="text-slate-400 hover:text-gold-400 text-sm transition-colors">&larr; Dashboard</a>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 py-8">

        <h2 class="text-2xl font-semibold text-white mb-6">Mis Comisiones</h2>

        <!-- Totales -->
        <div class="grid grid-cols-2 gap-4 mb-6">
            <div class="bg-dark-900 rounded-xl border border-slate-700 p-5">
                <p class="text-xs text-slate-400 uppercase tracking-wider mb-1">Pendiente de cobro</p>
                <p class="text-3xl font-bold text-yellow-400">$<?= number_format($totals['pending_total'], 2) ?> MXN</p>
            </div>
            <div class="bg-dark-900 rounded-xl border border-slate-700 p-5">
                <p class="text-xs text-slate-400 uppercase tracking-wider mb-1">Total cobrado</p>
                <p class="text-3xl font-bold text-green-400">$<?= number_format($totals['paid_total'], 2) ?> MXN</p>
            </div>
        </div>

        <!-- Filtros -->
        <div class="flex gap-2 mb-4">
            <?php foreach (['all' => 'Todas', 'pending' => 'Pendientes', 'paid' => 'Pagadas'] as $val => $label): ?>
            <a href="?status=<?= $val ?>"
               class="px-4 py-1.5 rounded-lg text-xs font-semibold transition-colors <?= $filterStatus === $val ? 'bg-gold-500 text-dark-950' : 'bg-dark-800 text-slate-400 hover:bg-dark-700' ?>">
                <?= $label ?>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Tabla -->
        <div class="bg-dark-900 rounded-xl border border-slate-700 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Fecha reserva</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Restaurante</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Huésped</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Tipo</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Monto</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Estado</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Pagado el</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    <?php if (empty($commissions)): ?>
                    <tr><td colspan="7" class="px-4 py-12 text-center text-slate-500">Sin comisiones registradas</td></tr>
                    <?php else: ?>
                    <?php foreach ($commissions as $c): ?>
                    <tr class="hover:bg-dark-800/50">
                        <td class="px-4 py-3 text-white"><?= formatDate($c['reservation_date']) ?> <?= formatTime($c['reservation_time']) ?></td>
                        <td class="px-4 py-3 text-slate-300"><?= resSanitize($c['restaurant_name']) ?></td>
                        <td class="px-4 py-3 text-white font-medium"><?= resSanitize($c['guest_name']) ?></td>
                        <td class="px-4 py-3">
                            <span class="text-xs px-2 py-0.5 rounded-full <?= $c['commission_type'] === 'fixed' ? 'bg-blue-900/40 text-blue-300' : 'bg-purple-900/40 text-purple-300' ?>">
                                <?= $c['commission_type'] === 'fixed' ? 'Fija' : 'Porcentaje' ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 font-semibold <?= $c['commission_amount'] !== null ? 'text-white' : 'text-slate-500 italic' ?>">
                            <?= $c['commission_amount'] !== null ? '$' . number_format($c['commission_amount'], 2) : 'Por liquidar' ?>
                        </td>
                        <td class="px-4 py-3">
                            <?php if ($c['status'] === 'paid'): ?>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-green-900/40 text-green-300 font-semibold">Pagada</span>
                            <?php else: ?>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-yellow-900/40 text-yellow-300 font-semibold">Pendiente</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-slate-400 text-xs">
                            <?= $c['paid_at'] ? date('d/m/Y H:i', strtotime($c['paid_at'])) : '—' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>
</body>
</html>
