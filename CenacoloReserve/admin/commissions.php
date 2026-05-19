<?php
/**
 * Admin — Panel de Comisiones y Pagos a Concierges
 */
require_once __DIR__ . '/../includes/config.php';
$user = requireResAdmin();
logResAccess($user['id'], 'admin_commissions', 'staff');

$pdo = getResDB();
$tab = $_GET['tab'] ?? 'pending';

// --- PENDIENTES ---
$pending = $pdo->query("
    SELECT cm.*,
           c.name AS concierge_name, c.type AS concierge_type,
           c.bank_name, c.bank_clabe, c.bank_holder,
           r.reservation_date, r.reservation_time, r.guest_name, r.party_size,
           rest.name AS restaurant_name
    FROM commissions cm
    JOIN concierges c ON c.id = cm.concierge_id
    JOIN reservations r ON r.id = cm.reservation_id
    JOIN restaurants rest ON rest.id = r.restaurant_id
    WHERE cm.status = 'pending'
    ORDER BY cm.due_by ASC
")->fetchAll();

// --- HISTORIAL ---
$conciergeList = $pdo->query("SELECT id, name FROM concierges WHERE active = 1 ORDER BY name")->fetchAll();
$filterConcierge = intval($_GET['concierge_id'] ?? 0);
$filterFrom      = $_GET['from'] ?? '';
$filterTo        = $_GET['to']   ?? '';

$histWhere  = ["cm.status = 'paid'"];
$histParams = [];
if ($filterConcierge) { $histWhere[] = 'cm.concierge_id = ?'; $histParams[] = $filterConcierge; }
if ($filterFrom)      { $histWhere[] = 'r.reservation_date >= ?'; $histParams[] = $filterFrom; }
if ($filterTo)        { $histWhere[] = 'r.reservation_date <= ?'; $histParams[] = $filterTo; }

$histStmt = $pdo->prepare("
    SELECT cm.*,
           c.name AS concierge_name,
           r.reservation_date, r.reservation_time, r.guest_name,
           rest.name AS restaurant_name,
           u.name AS paid_by_name
    FROM commissions cm
    JOIN concierges c ON c.id = cm.concierge_id
    JOIN reservations r ON r.id = cm.reservation_id
    JOIN restaurants rest ON rest.id = r.restaurant_id
    LEFT JOIN res_users u ON u.id = cm.paid_by_user_id
    WHERE " . implode(' AND ', $histWhere) . "
    ORDER BY cm.paid_at DESC
    LIMIT 200
");
$histStmt->execute($histParams);
$history = $histStmt->fetchAll();

$now = time();

function urgencyClass(string $dueBy, int $now): string {
    $diff = strtotime($dueBy) - $now;
    if ($diff < 0)     return 'red';
    if ($diff < 7200)  return 'red';
    if ($diff < 28800) return 'yellow';
    return 'green';
}

function urgencyLabel(string $dueBy, int $now): string {
    $diff = strtotime($dueBy) - $now;
    if ($diff < 0) {
        $h = abs(intdiv($diff, 3600));
        return "Vencida hace {$h}h";
    }
    $h = intdiv($diff, 3600);
    $m = intdiv($diff % 3600, 60);
    return $h > 0 ? "Vence en {$h}h {$m}m" : "Vence en {$m}m";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comisiones — Admin Cenacolo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{colors:{gold:{400:'#FFCC33',500:'#D4AF37'},dark:{700:'#334155',800:'#1e293b',900:'#0f172a',950:'#020617'}}}}}</script>
    <style>body{font-family:'Inter',sans-serif;}.font-display{font-family:'Playfair Display',serif;}</style>
</head>
<body class="bg-dark-950 text-slate-200 min-h-screen">

    <nav class="sticky top-0 z-30 bg-dark-900/90 backdrop-blur-md border-b border-slate-700">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <h1 class="font-display text-xl text-gold-500 font-bold">Cenacolo</h1>
                <span class="text-slate-600 text-xs">|</span>
                <span class="text-slate-400 text-xs uppercase tracking-wider">Comisiones</span>
            </div>
            <a href="<?= resUrl('/dashboard.php') ?>" class="text-slate-400 hover:text-gold-400 text-sm transition-colors">&larr; Dashboard</a>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-8">

        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-semibold text-white">Pagos a Concierges</h2>
            <span class="text-xs text-slate-500">Compromiso: pagar en 24h desde la visita</span>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 mb-6 border-b border-slate-700">
            <a href="?tab=pending" class="px-5 py-2.5 text-sm font-semibold border-b-2 transition-colors <?= $tab === 'pending' ? 'border-gold-500 text-gold-400' : 'border-transparent text-slate-400 hover:text-slate-200' ?>">
                Pendientes <span class="ml-1.5 px-1.5 py-0.5 rounded text-xs bg-dark-800"><?= count($pending) ?></span>
            </a>
            <a href="?tab=history" class="px-5 py-2.5 text-sm font-semibold border-b-2 transition-colors <?= $tab === 'history' ? 'border-gold-500 text-gold-400' : 'border-transparent text-slate-400 hover:text-slate-200' ?>">
                Historial
            </a>
        </div>

        <?php if ($tab === 'pending'): ?>

        <?php if (empty($pending)): ?>
            <div class="text-center py-16 text-slate-500">
                <p class="text-lg">Sin comisiones pendientes</p>
                <p class="text-sm mt-1">Todas las comisiones han sido pagadas.</p>
            </div>
        <?php else: ?>
        <div class="bg-dark-900 rounded-xl border border-slate-700 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider w-36">Urgencia</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Concierge</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Reserva</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Monto</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Banco / CLABE</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-slate-400 uppercase tracking-wider">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                <?php
                    $urgBgMap = ['red' => 'bg-red-500/20 text-red-300', 'yellow' => 'bg-yellow-500/20 text-yellow-300', 'green' => 'bg-green-500/20 text-green-300'];
                    $urgDotMap = ['red' => 'bg-red-400', 'yellow' => 'bg-yellow-400', 'green' => 'bg-green-400'];
                    foreach ($pending as $c):
                        $hasBank = !empty($c['bank_clabe']);
                        $urg     = $hasBank ? urgencyClass($c['due_by'], $now) : 'gray';
                ?>
                <tr class="hover:bg-dark-800/40 <?= $hasBank ? '' : 'opacity-60' ?>">
                    <td class="px-4 py-3">
                        <?php if ($hasBank): ?>
                        <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg text-xs font-semibold <?= $urgBgMap[$urg] ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?= $urgDotMap[$urg] ?>"></span>
                            <?= urgencyLabel($c['due_by'], $now) ?>
                        </span>
                        <?php else: ?>
                        <span class="text-xs text-slate-500 italic">Sin datos</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <p class="font-semibold text-white"><?= resSanitize($c['concierge_name']) ?></p>
                        <p class="text-xs text-slate-500"><?= $c['concierge_type'] === 'affiliate' ? 'Afiliado' : 'Concierge' ?></p>
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-white"><?= resSanitize($c['guest_name']) ?></p>
                        <p class="text-xs text-slate-500"><?= formatDate($c['reservation_date']) ?> <?= formatTime($c['reservation_time']) ?> &middot; <?= resSanitize($c['restaurant_name']) ?></p>
                    </td>
                    <td class="px-4 py-3 font-semibold">
                        <?php if ($c['commission_amount'] !== null): ?>
                            <span class="text-gold-400">$<?= number_format($c['commission_amount'], 2) ?> MXN</span>
                        <?php else: ?>
                            <span class="text-slate-500 italic text-xs">Por liquidar</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <?php if ($hasBank): ?>
                            <p class="text-white text-xs"><?= resSanitize($c['bank_name']) ?></p>
                            <p class="font-mono text-gold-400 text-xs tracking-widest"><?= resSanitize($c['bank_clabe']) ?></p>
                            <p class="text-slate-500 text-xs"><?= resSanitize($c['bank_holder']) ?></p>
                        <?php else: ?>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-700 text-slate-400">Sin datos bancarios</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($hasBank): ?>
                        <button onclick="markPaid(<?= (int)$c['id'] ?>, this)"
                                class="px-4 py-1.5 bg-green-700/60 hover:bg-green-600/70 text-green-200 rounded-lg text-xs font-semibold transition-colors">
                            Marcar pagado
                        </button>
                        <?php else: ?>
                        <span class="text-slate-600 text-xs">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php else: // tab = history ?>

        <!-- Filtros historial -->
        <form method="GET" class="flex flex-wrap gap-3 mb-4 items-end">
            <input type="hidden" name="tab" value="history">
            <div>
                <label class="block text-xs text-slate-400 mb-1">Concierge</label>
                <select name="concierge_id" class="bg-dark-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-gold-500">
                    <option value="">Todos</option>
                    <?php foreach ($conciergeList as $cl): ?>
                    <option value="<?= (int)$cl['id'] ?>" <?= $filterConcierge === (int)$cl['id'] ? 'selected' : '' ?>><?= resSanitize($cl['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Desde</label>
                <input type="date" name="from" value="<?= resSanitize($filterFrom) ?>" class="bg-dark-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-gold-500">
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Hasta</label>
                <input type="date" name="to" value="<?= resSanitize($filterTo) ?>" class="bg-dark-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-gold-500">
            </div>
            <button type="submit" class="px-4 py-2 bg-gold-500 text-dark-950 rounded-lg text-sm font-semibold hover:bg-gold-400 transition-colors">Filtrar</button>
        </form>

        <div class="bg-dark-900 rounded-xl border border-slate-700 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Fecha reserva</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Concierge</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Huésped</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Monto</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Pagado el</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Pagado por</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                <?php if (empty($history)): ?>
                    <tr><td colspan="6" class="px-4 py-12 text-center text-slate-500">Sin pagos registrados</td></tr>
                <?php else: ?>
                <?php foreach ($history as $h): ?>
                <tr class="hover:bg-dark-800/40">
                    <td class="px-4 py-3 text-white"><?= formatDate($h['reservation_date']) ?> <?= formatTime($h['reservation_time']) ?></td>
                    <td class="px-4 py-3 text-slate-200"><?= resSanitize($h['concierge_name']) ?></td>
                    <td class="px-4 py-3 text-slate-200"><?= resSanitize($h['guest_name']) ?></td>
                    <td class="px-4 py-3 text-green-400 font-semibold">
                        <?= $h['commission_amount'] !== null ? '$' . number_format($h['commission_amount'], 2) . ' MXN' : '—' ?>
                    </td>
                    <td class="px-4 py-3 text-slate-400 text-xs"><?= !empty($h['paid_at']) ? date('d/m/Y H:i', strtotime($h['paid_at'])) : '—' ?></td>
                    <td class="px-4 py-3 text-slate-400 text-xs"><?= resSanitize($h['paid_by_name'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php endif; ?>

    </main>

    <!-- Modal confirmación pago -->
    <div id="confirmModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center">
        <div class="bg-dark-900 border border-slate-700 rounded-2xl p-6 max-w-sm w-full mx-4">
            <h3 class="text-lg font-semibold text-white mb-2">Confirmar pago</h3>
            <p class="text-slate-400 text-sm mb-6">¿Confirmas que realizaste el depósito a este concierge? Esta acción no se puede deshacer.</p>
            <div class="flex gap-3">
                <button onclick="cancelModal()" class="flex-1 py-2.5 bg-dark-800 text-slate-300 rounded-lg text-sm font-semibold hover:bg-dark-700">Cancelar</button>
                <button id="confirmBtn" onclick="confirmPayment()" class="flex-1 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-500">Sí, está pagado</button>
            </div>
        </div>
    </div>

    <script>
        let pendingCommissionId = null;
        let pendingRowBtn = null;

        function markPaid(commissionId, btn) {
            pendingCommissionId = commissionId;
            pendingRowBtn = btn;
            document.getElementById('confirmModal').classList.remove('hidden');
        }

        function cancelModal() {
            document.getElementById('confirmModal').classList.add('hidden');
            pendingCommissionId = null;
            pendingRowBtn = null;
        }

        async function confirmPayment() {
            if (!pendingCommissionId) return;
            const confirmBtn = document.getElementById('confirmBtn');
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Procesando...';

            try {
                const res  = await fetch('<?= resUrl('/api/admin.php') ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'mark_commission_paid', commission_id: pendingCommissionId })
                });
                const json = await res.json();
                if (json.success) {
                    const row = pendingRowBtn.closest('tr');
                    row.style.transition = 'opacity 0.3s';
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 300);
                    cancelModal();
                } else {
                    alert('Error: ' + (json.error || 'No se pudo procesar'));
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = 'Sí, está pagado';
                }
            } catch {
                alert('Error de conexión. Intenta de nuevo.');
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Sí, está pagado';
            }
        }
    </script>

</body>
</html>