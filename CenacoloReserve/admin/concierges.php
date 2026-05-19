<?php
/**
 * Cenacolo Reservas - Gestión de Concierges, Afiliados y Hoteles
 * CRUD completo con sistema de referidos y comisiones
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/layout.php';

$user = requireResAdmin();
logResAccess($user['id'], 'admin_concierges', 'staff');

$pdo = getResDB();

// Hoteles
$stmt = $pdo->query("SELECT * FROM hotels WHERE active = 1 ORDER BY name");
$hotels = $stmt->fetchAll();

// Todos los partners (concierges + afiliados)
$stmt = $pdo->query("
    SELECT c.*, h.name as hotel_name,
           (SELECT COUNT(*) FROM reservations WHERE concierge_id = c.id) as reservation_count,
           (SELECT COUNT(*) FROM reservations WHERE concierge_id = c.id AND status = 'completed') as completed_count
    FROM concierges c
    LEFT JOIN hotels h ON c.hotel_id = h.id
    WHERE c.active = 1
    ORDER BY c.type, c.name
");
$allPartners = $stmt->fetchAll();
$conciergesList = array_values(array_filter($allPartners, fn($c) => ($c['type'] ?? 'concierge') === 'concierge'));
$affiliatesList = array_values(array_filter($allPartners, fn($c) => ($c['type'] ?? 'concierge') === 'affiliate'));

renderHeader('Concierges & Socios', $user);
?>

<div class="space-y-6 fade-in">

    <!-- Link al panel de pagos -->
    <div class="flex justify-end mb-2">
        <a href="<?= resUrl('/admin/commissions.php') ?>"
           class="inline-flex items-center px-4 py-2 bg-dark-800 hover:bg-dark-700 border border-dark-600 text-slate-300 rounded-lg text-sm transition-colors">
            💳 Panel de Pagos
        </a>
    </div>

    <!-- Tab Navigation -->
    <div class="flex space-x-1 bg-dark-800 rounded-lg p-1">
        <button onclick="showTab('concierges')" id="tab-concierges"
                class="tab-btn flex-1 px-4 py-2.5 rounded-md text-sm font-semibold transition-colors bg-gold-500 text-dark-950">
            Concierges & Hoteles
        </button>
        <button onclick="showTab('affiliates')" id="tab-affiliates"
                class="tab-btn flex-1 px-4 py-2.5 rounded-md text-sm font-semibold transition-colors text-dark-400 hover:text-white">
            Afiliados
        </button>
    </div>

    <!-- ============================================= -->
    <!-- PANEL: CONCIERGES & HOTELES                   -->
    <!-- ============================================= -->
    <div id="panel-concierges">

        <!-- HOTELES -->
        <div class="bg-dark-900 rounded-xl border border-dark-700 mb-6">
            <div class="px-6 py-4 border-b border-dark-700 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-white uppercase tracking-wider">Hoteles</h3>
                    <p class="text-xs text-dark-400 mt-1"><?= count($hotels) ?> hoteles registrados</p>
                </div>
                <button onclick="toggleHotelForm()"
                        class="flex items-center px-3 py-1.5 bg-gold-500 text-dark-950 rounded-lg text-xs font-semibold hover:bg-gold-400 transition-colors">
                    <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Nuevo Hotel
                </button>
            </div>

            <!-- Form hotel -->
            <div id="hotelForm" class="hidden px-6 py-4 border-b border-dark-700 bg-dark-800/30">
                <h4 class="text-xs font-semibold text-dark-300 uppercase mb-3" id="hotelFormTitle">Crear Nuevo Hotel</h4>
                <input type="hidden" id="editHotelId" value="">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs text-dark-400 mb-1">Nombre *</label>
                        <input type="text" id="hotelName" placeholder="Nombre del hotel"
                               class="w-full bg-dark-900 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white focus:border-gold-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-dark-400 mb-1">Ciudad</label>
                        <input type="text" id="hotelCity" placeholder="Ciudad"
                               class="w-full bg-dark-900 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white focus:border-gold-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-dark-400 mb-1">Contacto</label>
                        <input type="text" id="hotelContact" placeholder="Nombre de contacto"
                               class="w-full bg-dark-900 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white focus:border-gold-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-dark-400 mb-1">Email contacto</label>
                        <input type="email" id="hotelContactEmail" placeholder="email@hotel.com"
                               class="w-full bg-dark-900 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white focus:border-gold-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-dark-400 mb-1">Teléfono</label>
                        <input type="text" id="hotelPhone" placeholder="+52 998..."
                               class="w-full bg-dark-900 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white focus:border-gold-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-dark-400 mb-1">Dirección</label>
                        <input type="text" id="hotelAddress" placeholder="Dirección"
                               class="w-full bg-dark-900 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white focus:border-gold-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-dark-400 mb-1">Comisión %</label>
                        <input type="number" id="hotelCommission" placeholder="0" min="0" max="100" step="0.5"
                               class="w-full bg-dark-900 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white focus:border-gold-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-dark-400 mb-1">Notas</label>
                        <input type="text" id="hotelNotes" placeholder="Notas adicionales"
                               class="w-full bg-dark-900 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white focus:border-gold-500 focus:outline-none">
                    </div>
                </div>
                <div class="mt-3 flex items-center space-x-3">
                    <button onclick="saveHotel()" class="px-4 py-2 bg-gold-500 text-dark-950 rounded-lg text-xs font-semibold hover:bg-gold-400 transition-colors">Guardar Hotel</button>
                    <button onclick="cancelHotelForm()" class="px-4 py-2 bg-dark-700 text-dark-300 rounded-lg text-xs hover:bg-dark-600 transition-colors">Cancelar</button>
                </div>
            </div>

            <!-- Tabla hoteles -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-dark-700 bg-dark-800/50">
                            <th class="text-left px-4 py-3 text-xs font-semibold text-dark-400 uppercase tracking-wider">Nombre</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-dark-400 uppercase tracking-wider">Ciudad</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-dark-400 uppercase tracking-wider">Contacto</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-dark-400 uppercase tracking-wider">Comisión</th>
                            <th class="text-right px-4 py-3 text-xs font-semibold text-dark-400 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($hotels)): ?>
                            <tr><td colspan="5" class="px-4 py-6 text-center text-dark-500">No hay hoteles registrados</td></tr>
                        <?php else: ?>
                            <?php foreach ($hotels as $h): ?>
                                <tr class="border-b border-dark-800 hover:bg-dark-800/30 transition-colors">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 rounded-lg bg-dark-700 flex items-center justify-center text-gold-500 mr-3">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                            </div>
                                            <span class="text-white font-medium"><?= resSanitize($h['name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-dark-300"><?= resSanitize($h['city'] ?? '-') ?></td>
                                    <td class="px-4 py-3">
                                        <p class="text-dark-300"><?= resSanitize($h['contact_name'] ?? '-') ?></p>
                                        <p class="text-xs text-dark-500"><?= resSanitize($h['contact_email'] ?? '') ?></p>
                                    </td>
                                    <td class="px-4 py-3"><span class="text-gold-500 font-semibold"><?= number_format($h['commission_percentage'] ?? 0, 1) ?>%</span></td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end space-x-2">
                                            <button onclick='editHotel(<?= json_encode($h, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="text-dark-400 hover:text-gold-500 transition-colors" title="Editar">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </button>
                                            <button onclick="deletePartner('hotel', <?= $h['id'] ?>, '<?= resSanitize($h['name']) ?>')" class="text-dark-400 hover:text-red-400 transition-colors" title="Desactivar">
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

        <!-- CONCIERGES -->
        <div class="bg-dark-900 rounded-xl border border-dark-700">
            <div class="px-6 py-4 border-b border-dark-700 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-white uppercase tracking-wider">Concierges</h3>
                    <p class="text-xs text-dark-400 mt-1"><?= count($conciergesList) ?> concierges registrados</p>
                </div>
                <button onclick="togglePartnerForm('concierge')"
                        class="flex items-center px-3 py-1.5 bg-gold-500 text-dark-950 rounded-lg text-xs font-semibold hover:bg-gold-400 transition-colors">
                    <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Nuevo Concierge
                </button>
            </div>

            <!-- Form concierge -->
            <div id="conciergeForm" class="hidden px-6 py-4 border-b border-dark-700 bg-dark-800/30">
                <h4 class="text-xs font-semibold text-dark-300 uppercase mb-3" id="conciergeFormTitle">Crear Nuevo Concierge</h4>
                <input type="hidden" id="editConciergeId" value="">
                <input type="hidden" id="conciergeType" value="concierge">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs text-dark-400 mb-1">Nombre *</label>
                        <input type="text" id="conciergeName" placeholder="Nombre completo"
                               class="w-full bg-dark-900 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white focus:border-gold-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-dark-400 mb-1">Email *</label>
                        <input type="email" id="conciergeEmail" placeholder="email@hotel.com"
                               class="w-full bg-dark-900 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white focus:border-gold-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-dark-400 mb-1" id="conciergePasswordLabel">Password *</label>
                        <input type="password" id="conciergePassword" placeholder="Contraseña"
                               class="w-full bg-dark-900 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white focus:border-gold-500 focus:outline-none">
                    </div>
                    <div id="conciergeHotelWrap">
                        <label class="block text-xs text-dark-400 mb-1">Hotel</label>
                        <select id="conciergeHotel"
                                class="w-full bg-dark-900 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white focus:border-gold-500 focus:outline-none">
                            <option value="">-- Sin hotel --</option>
                            <?php foreach ($hotels as $h): ?>
                                <option value="<?= $h['id'] ?>"><?= resSanitize($h['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-dark-400 mb-1">Teléfono</label>
                        <input type="text" id="conciergePhone" placeholder="+52 998..."
                               class="w-full bg-dark-900 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white focus:border-gold-500 focus:outline-none">
                    </div>
                    <div id="conciergeCompanyWrap" class="hidden">
                        <label class="block text-xs text-dark-400 mb-1">Empresa / Negocio</label>
                        <input type="text" id="conciergeCompany" placeholder="Nombre del negocio"
                               class="w-full bg-dark-900 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white focus:border-gold-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-dark-400 mb-1">Tipo de comisión</label>
                        <select id="conciergeCommType"
                                class="w-full bg-dark-900 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white focus:border-gold-500 focus:outline-none">
                            <option value="percentage">% del consumo</option>
                            <option value="fixed">Monto fijo por reserva</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-dark-400 mb-1" id="conciergeCommLabel">Comisión %</label>
                        <input type="number" id="conciergeCommValue" placeholder="0" min="0" step="0.5" value="0"
                               class="w-full bg-dark-900 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white focus:border-gold-500 focus:outline-none">
                    </div>
                </div>
                <!-- Referral link display (only when editing) -->
                <div id="referralLinkWrap" class="hidden mt-3 p-3 bg-dark-700/50 rounded-lg">
                    <label class="block text-xs text-dark-400 mb-1">Link de Referido</label>
                    <div class="flex items-center gap-2">
                        <code class="flex-1 text-xs text-gold-400 bg-dark-900 rounded px-3 py-2 overflow-hidden text-ellipsis" id="referralLinkDisplay"></code>
                        <button onclick="copyReferralLink()" class="px-3 py-2 bg-gold-500/20 text-gold-500 rounded-lg text-xs font-semibold hover:bg-gold-500/30 whitespace-nowrap">Copiar</button>
                    </div>
                </div>
                <!-- Portal link display (only when editing) -->
                <div id="portalLinkWrap" class="hidden mt-2 p-3 bg-dark-700/50 rounded-lg">
                    <label class="block text-xs text-dark-400 mb-1">Enlace al Portal</label>
                    <div class="flex items-center gap-2">
                        <code class="flex-1 text-xs text-gold-400 bg-dark-900 rounded px-3 py-2 overflow-hidden text-ellipsis" id="portalLinkDisplay"></code>
                        <button onclick="copyPortalLinkFromForm()" class="px-3 py-2 bg-gold-500/20 text-gold-500 rounded-lg text-xs font-semibold hover:bg-gold-500/30 whitespace-nowrap">Copiar</button>
                    </div>
                </div>
                <div class="mt-3 flex items-center space-x-3">
                    <button onclick="savePartner()" class="px-4 py-2 bg-gold-500 text-dark-950 rounded-lg text-xs font-semibold hover:bg-gold-400 transition-colors">Guardar</button>
                    <button onclick="cancelPartnerForm()" class="px-4 py-2 bg-dark-700 text-dark-300 rounded-lg text-xs hover:bg-dark-600 transition-colors">Cancelar</button>
                </div>
            </div>

            <!-- Tabla concierges -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-dark-700 bg-dark-800/50">
                            <th class="text-left px-4 py-3 text-xs font-semibold text-dark-400 uppercase tracking-wider">Nombre</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-dark-400 uppercase tracking-wider">Email</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-dark-400 uppercase tracking-wider">Hotel</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-dark-400 uppercase tracking-wider">Comisión</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-dark-400 uppercase tracking-wider">Reservas</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-dark-400 uppercase tracking-wider">Referido</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-dark-400 uppercase tracking-wider">Portal</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-dark-400 uppercase tracking-wider">Banco</th>
                            <th class="text-right px-4 py-3 text-xs font-semibold text-dark-400 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($conciergesList)): ?>
                            <tr><td colspan="9" class="px-4 py-6 text-center text-dark-500">No hay concierges registrados</td></tr>
                        <?php else: ?>
                            <?php foreach ($conciergesList as $c): ?>
                                <tr class="border-b border-dark-800 hover:bg-dark-800/30 transition-colors">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 rounded-full bg-gold-500/20 flex items-center justify-center text-gold-500 font-bold text-xs mr-3">
                                                <?= strtoupper(substr($c['name'], 0, 1)) ?>
                                            </div>
                                            <span class="text-white font-medium"><?= resSanitize($c['name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-dark-300 text-xs"><?= resSanitize($c['email']) ?></td>
                                    <td class="px-4 py-3">
                                        <?php if ($c['hotel_name']): ?>
                                            <span class="inline-block px-2 py-0.5 rounded bg-dark-700 text-dark-200 text-xs"><?= resSanitize($c['hotel_name']) ?></span>
                                        <?php else: ?>
                                            <span class="text-dark-500 text-xs">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        <?php
                                            $ct = $c['commission_type'] ?? 'percentage';
                                            $cv = floatval($c['commission_value'] ?? 0);
                                            if ($cv > 0) {
                                                echo $ct === 'fixed'
                                                    ? '<span class="text-green-400 font-semibold">$' . number_format($cv, 0) . ' /res</span>'
                                                    : '<span class="text-gold-500 font-semibold">' . number_format($cv, 1) . '%</span>';
                                            } else {
                                                echo '<span class="text-dark-500">-</span>';
                                            }
                                        ?>
                                    </td>
                                    <td class="px-4 py-3"><span class="text-white font-semibold"><?= number_format($c['reservation_count']) ?></span></td>
                                    <td class="px-4 py-3">
                                        <?php if ($c['referral_code']): ?>
                                            <button onclick="copyLink('<?= $c['referral_code'] ?>')" class="inline-flex items-center gap-1 px-2 py-1 bg-dark-700 hover:bg-dark-600 rounded text-xs text-gold-400 font-mono transition-colors" title="Click para copiar link">
                                                <?= $c['referral_code'] ?>
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-dark-500 text-xs">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-1">
                                            <button onclick="copyPortalLink('<?= resSanitize($c['email']) ?>')" class="inline-flex items-center gap-1 px-2 py-1 bg-dark-700 hover:bg-dark-600 rounded text-xs text-dark-300 transition-colors" title="Copiar enlace al portal">
                                                Portal
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                            </button>
                                            <button onclick="showQR('<?= resSanitize($c['name']) ?>', '<?= resSanitize($c['email']) ?>', '<?= $c['referral_code'] ?? '' ?>')" class="inline-flex items-center px-2 py-1 bg-dark-700 hover:bg-dark-600 rounded text-xs text-dark-300 transition-colors" title="Generar QR">
                                                QR
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if (!empty($c['bank_clabe'])): ?>
                                            <span class="inline-flex items-center gap-1 text-xs text-green-400">
                                                <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>
                                                <?= resSanitize($c['bank_name'] ?? 'Registrado') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 text-xs text-dark-500">
                                                <span class="w-1.5 h-1.5 rounded-full bg-dark-600"></span>
                                                Sin datos
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end space-x-2">
                                            <button onclick="openPerksModal(<?= $c['id'] ?>, '<?= resSanitize($c['name']) ?>')" class="text-dark-400 hover:text-green-400 transition-colors" title="Perks / Acuerdos">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>
                                            </button>
                                            <button onclick='editPartner(<?= json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="text-dark-400 hover:text-gold-500 transition-colors" title="Editar">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </button>
                                            <button onclick="deletePartner('concierge', <?= $c['id'] ?>, '<?= resSanitize($c['name']) ?>')" class="text-dark-400 hover:text-red-400 transition-colors" title="Desactivar">
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

    <!-- ============================================= -->
    <!-- PANEL: AFILIADOS                              -->
    <!-- ============================================= -->
    <div id="panel-affiliates" class="hidden">
        <div class="bg-dark-900 rounded-xl border border-dark-700">
            <div class="px-6 py-4 border-b border-dark-700 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-white uppercase tracking-wider">Afiliados</h3>
                    <p class="text-xs text-dark-400 mt-1"><?= count($affiliatesList) ?> afiliados &middot; Influencers, Gyms, Negocios, etc.</p>
                </div>
                <button onclick="togglePartnerForm('affiliate')"
                        class="flex items-center px-3 py-1.5 bg-purple-600 text-white rounded-lg text-xs font-semibold hover:bg-purple-500 transition-colors">
                    <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Nuevo Afiliado
                </button>
            </div>

            <!-- Form afiliado (clon del conciergeForm para que funcione dentro del panel affiliates) -->
            <div id="affiliateForm" class="hidden px-6 py-4 border-b border-dark-700 bg-dark-800/30">
                <h4 class="text-xs font-semibold text-dark-300 uppercase mb-3" id="affiliateFormTitle">Crear Nuevo Afiliado</h4>
                <input type="hidden" id="editAffiliateId" value="">
                <input type="hidden" id="affiliateType" value="affiliate">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs text-dark-400 mb-1">Nombre *</label>
                        <input type="text" id="affiliateName" placeholder="Nombre completo"
                               class="w-full bg-dark-900 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white focus:border-purple-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-dark-400 mb-1">Email *</label>
                        <input type="email" id="affiliateEmail" placeholder="email@negocio.com"
                               class="w-full bg-dark-900 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white focus:border-purple-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-dark-400 mb-1" id="affiliatePasswordLabel">Password *</label>
                        <input type="password" id="affiliatePassword" placeholder="Contraseña"
                               class="w-full bg-dark-900 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white focus:border-purple-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-dark-400 mb-1">Empresa / Negocio</label>
                        <input type="text" id="affiliateCompany" placeholder="Nombre del negocio"
                               class="w-full bg-dark-900 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white focus:border-purple-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-dark-400 mb-1">Teléfono</label>
                        <input type="text" id="affiliatePhone" placeholder="+52 998..."
                               class="w-full bg-dark-900 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white focus:border-purple-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-dark-400 mb-1">Tipo de comisión</label>
                        <select id="affiliateCommType"
                                class="w-full bg-dark-900 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white focus:border-purple-500 focus:outline-none">
                            <option value="percentage">% del consumo</option>
                            <option value="fixed">Monto fijo por reserva</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-dark-400 mb-1" id="affiliateCommLabel">Comisión %</label>
                        <input type="number" id="affiliateCommValue" placeholder="0" min="0" step="0.5" value="0"
                               class="w-full bg-dark-900 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white focus:border-purple-500 focus:outline-none">
                    </div>
                </div>
                <!-- Referral link display (only when editing) -->
                <div id="affiliateReferralWrap" class="hidden mt-3 p-3 bg-dark-700/50 rounded-lg">
                    <label class="block text-xs text-dark-400 mb-1">Link de Referido</label>
                    <div class="flex items-center gap-2">
                        <code class="flex-1 text-xs text-purple-400 bg-dark-900 rounded px-3 py-2 overflow-hidden text-ellipsis" id="affiliateReferralDisplay"></code>
                        <button onclick="copyAffiliateReferralLink()" class="px-3 py-2 bg-purple-500/20 text-purple-400 rounded-lg text-xs font-semibold hover:bg-purple-500/30 whitespace-nowrap">Copiar</button>
                    </div>
                </div>
                <!-- Portal link display (only when editing) -->
                <div id="affiliatePortalWrap" class="hidden mt-2 p-3 bg-dark-700/50 rounded-lg">
                    <label class="block text-xs text-dark-400 mb-1">Enlace al Portal</label>
                    <div class="flex items-center gap-2">
                        <code class="flex-1 text-xs text-purple-400 bg-dark-900 rounded px-3 py-2 overflow-hidden text-ellipsis" id="affiliatePortalDisplay"></code>
                        <button onclick="copyAffiliatePortalLink()" class="px-3 py-2 bg-purple-500/20 text-purple-400 rounded-lg text-xs font-semibold hover:bg-purple-500/30 whitespace-nowrap">Copiar</button>
                    </div>
                </div>
                <div class="mt-3 flex items-center space-x-3">
                    <button onclick="saveAffiliate()" class="px-4 py-2 bg-purple-600 text-white rounded-lg text-xs font-semibold hover:bg-purple-500 transition-colors">Guardar</button>
                    <button onclick="cancelAffiliateForm()" class="px-4 py-2 bg-dark-700 text-dark-300 rounded-lg text-xs hover:bg-dark-600 transition-colors">Cancelar</button>
                </div>
            </div>

            <!-- Tabla afiliados -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-dark-700 bg-dark-800/50">
                            <th class="text-left px-4 py-3 text-xs font-semibold text-dark-400 uppercase tracking-wider">Nombre</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-dark-400 uppercase tracking-wider">Empresa</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-dark-400 uppercase tracking-wider">Comisión</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-dark-400 uppercase tracking-wider">Reservas</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-dark-400 uppercase tracking-wider">Comisión Acum.</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-dark-400 uppercase tracking-wider">Link Referido</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-dark-400 uppercase tracking-wider">Portal</th>
                            <th class="text-right px-4 py-3 text-xs font-semibold text-dark-400 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($affiliatesList)): ?>
                            <tr><td colspan="8" class="px-4 py-6 text-center text-dark-500">No hay afiliados registrados. Crea uno para generar su link de referido.</td></tr>
                        <?php else: ?>
                            <?php foreach ($affiliatesList as $a): ?>
                                <tr class="border-b border-dark-800 hover:bg-dark-800/30 transition-colors">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 rounded-full bg-purple-500/20 flex items-center justify-center text-purple-400 font-bold text-xs mr-3">
                                                <?= strtoupper(substr($a['name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <span class="text-white font-medium"><?= resSanitize($a['name']) ?></span>
                                                <p class="text-xs text-dark-500"><?= resSanitize($a['email']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?= $a['company_name'] ? '<span class="text-dark-200 text-xs">' . resSanitize($a['company_name']) . '</span>' : '<span class="text-dark-500 text-xs">-</span>' ?>
                                    </td>
                                    <td class="px-4 py-3 text-xs">
                                        <?php
                                            $ct = $a['commission_type'] ?? 'percentage';
                                            $cv = floatval($a['commission_value'] ?? 0);
                                            if ($cv > 0) {
                                                echo $ct === 'fixed'
                                                    ? '<span class="text-green-400 font-semibold">$' . number_format($cv, 0) . ' MXN /res</span>'
                                                    : '<span class="text-purple-400 font-semibold">' . number_format($cv, 1) . '% consumo</span>';
                                            } else {
                                                echo '<span class="text-dark-500">Sin comisión</span>';
                                            }
                                        ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-white font-semibold"><?= number_format($a['reservation_count']) ?></span>
                                        <span class="text-dark-500 text-xs">(<?= $a['completed_count'] ?> comp.)</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-green-400 font-semibold">$<?= number_format($a['commission_earned'] ?? 0, 2) ?></span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if ($a['referral_code']): ?>
                                            <button onclick="copyLink('<?= $a['referral_code'] ?>')" class="inline-flex items-center gap-1 px-2 py-1 bg-purple-900/30 hover:bg-purple-900/50 rounded text-xs text-purple-400 font-mono transition-colors" title="Click para copiar link de reserva">
                                                <?= $a['referral_code'] ?>
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-1">
                                            <button onclick="copyPortalLink('<?= resSanitize($a['email']) ?>')" class="inline-flex items-center gap-1 px-2 py-1 bg-dark-700 hover:bg-dark-600 rounded text-xs text-dark-300 transition-colors" title="Copiar enlace al portal">
                                                Portal
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                            </button>
                                            <button onclick="showQR('<?= resSanitize($a['name']) ?>', '<?= resSanitize($a['email']) ?>', '<?= $a['referral_code'] ?? '' ?>')" class="inline-flex items-center px-2 py-1 bg-dark-700 hover:bg-dark-600 rounded text-xs text-dark-300 transition-colors" title="Generar QR">
                                                QR
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end space-x-2">
                                            <button onclick="openPerksModal(<?= $a['id'] ?>, '<?= resSanitize($a['name']) ?>')" class="text-dark-400 hover:text-green-400 transition-colors" title="Perks / Acuerdos">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>
                                            </button>
                                            <button onclick='editAffiliate(<?= json_encode($a, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="text-dark-400 hover:text-purple-400 transition-colors" title="Editar">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </button>
                                            <button onclick="deletePartner('concierge', <?= $a['id'] ?>, '<?= resSanitize($a['name']) ?>')" class="text-dark-400 hover:text-red-400 transition-colors" title="Desactivar">
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

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
const API_URL = '<?= resUrl('/api/admin.php') ?>';
const SITE_URL = '<?= RES_SITE_URL ?>';
let isEditing = false;
let currentPartnerType = 'concierge';

// ========================
// TABS
// ========================
function showTab(tab) {
    document.getElementById('panel-concierges').classList.toggle('hidden', tab !== 'concierges');
    document.getElementById('panel-affiliates').classList.toggle('hidden', tab !== 'affiliates');

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('bg-gold-500', 'text-dark-950', 'bg-purple-600', 'text-white');
        btn.classList.add('text-dark-400');
    });
    const activeBtn = document.getElementById('tab-' + tab);
    activeBtn.classList.remove('text-dark-400');
    if (tab === 'affiliates') {
        activeBtn.classList.add('bg-purple-600', 'text-white');
    } else {
        activeBtn.classList.add('bg-gold-500', 'text-dark-950');
    }
}

// ========================
// HOTEL FORM
// ========================
function toggleHotelForm() {
    const form = document.getElementById('hotelForm');
    if (form.classList.contains('hidden')) {
        resetHotelForm();
        form.classList.remove('hidden');
    } else {
        form.classList.add('hidden');
    }
}
function resetHotelForm() {
    document.getElementById('editHotelId').value = '';
    document.getElementById('hotelFormTitle').textContent = 'Crear Nuevo Hotel';
    ['hotelName','hotelCity','hotelContact','hotelContactEmail','hotelPhone','hotelAddress','hotelCommission','hotelNotes']
        .forEach(id => document.getElementById(id).value = '');
}
function cancelHotelForm() { document.getElementById('hotelForm').classList.add('hidden'); resetHotelForm(); }
function editHotel(d) {
    document.getElementById('hotelForm').classList.remove('hidden');
    document.getElementById('hotelFormTitle').textContent = 'Editar Hotel';
    document.getElementById('editHotelId').value = d.id;
    document.getElementById('hotelName').value = d.name || '';
    document.getElementById('hotelCity').value = d.city || '';
    document.getElementById('hotelContact').value = d.contact_name || '';
    document.getElementById('hotelContactEmail').value = d.contact_email || '';
    document.getElementById('hotelPhone').value = d.phone || '';
    document.getElementById('hotelAddress').value = d.address || '';
    document.getElementById('hotelCommission').value = d.commission_percentage || '';
    document.getElementById('hotelNotes').value = d.notes || '';
}
async function saveHotel() {
    const name = document.getElementById('hotelName').value.trim();
    if (!name) { showToast('Nombre requerido', 'error'); return; }
    const id = document.getElementById('editHotelId').value;
    const payload = {
        action: id ? 'update_hotel' : 'create_hotel',
        name, city: document.getElementById('hotelCity').value.trim(),
        contact_name: document.getElementById('hotelContact').value.trim(),
        contact_email: document.getElementById('hotelContactEmail').value.trim(),
        phone: document.getElementById('hotelPhone').value.trim(),
        address: document.getElementById('hotelAddress').value.trim(),
        commission_percentage: document.getElementById('hotelCommission').value || 0,
        notes: document.getElementById('hotelNotes').value.trim()
    };
    if (id) payload.id = id;
    try {
        const res = await fetch(API_URL, { method: id ? 'PUT' : 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
        const r = await res.json();
        if (r.success) { showToast(r.message); setTimeout(() => location.reload(), 800); }
        else showToast(r.error || 'Error', 'error');
    } catch(e) { showToast('Error de conexión', 'error'); }
}

// ========================
// PARTNER FORM (concierge + affiliate)
// ========================
function togglePartnerForm(type) {
    currentPartnerType = type;
    if (type === 'affiliate') {
        // Use affiliate-specific form in affiliates panel
        const form = document.getElementById('affiliateForm');
        if (form.classList.contains('hidden')) {
            resetAffiliateForm();
            isEditing = false;
            form.classList.remove('hidden');
            form.scrollIntoView({ behavior: 'smooth' });
        } else {
            form.classList.add('hidden');
        }
    } else {
        const form = document.getElementById('conciergeForm');
        if (form.classList.contains('hidden')) {
            resetPartnerForm();
            document.getElementById('conciergeType').value = type;
            document.getElementById('conciergeFormTitle').textContent = 'Crear Nuevo Concierge';
            document.getElementById('conciergeHotelWrap').classList.remove('hidden');
            document.getElementById('conciergeCompanyWrap').classList.add('hidden');
            document.getElementById('conciergePasswordLabel').textContent = 'Password *';
            document.getElementById('referralLinkWrap').classList.add('hidden');
            document.getElementById('portalLinkWrap').classList.add('hidden');
            isEditing = false;
            form.classList.remove('hidden');
            form.scrollIntoView({ behavior: 'smooth' });
        } else {
            form.classList.add('hidden');
        }
    }
}
function resetPartnerForm() {
    ['editConciergeId','conciergeName','conciergeEmail','conciergePassword','conciergePhone','conciergeCompany'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('conciergeHotel').value = '';
    document.getElementById('conciergeCommType').value = 'percentage';
    document.getElementById('conciergeCommValue').value = '0';
    document.getElementById('referralLinkWrap').classList.add('hidden');
    isEditing = false;
}
function cancelPartnerForm() { document.getElementById('conciergeForm').classList.add('hidden'); resetPartnerForm(); }

function editPartner(d) {
    isEditing = true;
    currentPartnerType = d.type || 'concierge';
    const isAffiliate = currentPartnerType === 'affiliate';

    // Show correct tab
    showTab(isAffiliate ? 'affiliates' : 'concierges');

    const form = document.getElementById('conciergeForm');
    form.classList.remove('hidden');
    document.getElementById('conciergeFormTitle').textContent = isAffiliate ? 'Editar Afiliado' : 'Editar Concierge';
    document.getElementById('conciergePasswordLabel').textContent = 'Password (vacío = no cambiar)';
    document.getElementById('conciergeHotelWrap').classList.toggle('hidden', isAffiliate);
    document.getElementById('conciergeCompanyWrap').classList.toggle('hidden', !isAffiliate);

    document.getElementById('editConciergeId').value = d.id;
    document.getElementById('conciergeType').value = d.type || 'concierge';
    document.getElementById('conciergeName').value = d.name || '';
    document.getElementById('conciergeEmail').value = d.email || '';
    document.getElementById('conciergePassword').value = '';
    document.getElementById('conciergeHotel').value = d.hotel_id || '';
    document.getElementById('conciergePhone').value = d.phone || '';
    document.getElementById('conciergeCompany').value = d.company_name || '';
    document.getElementById('conciergeCommType').value = d.commission_type || 'percentage';
    document.getElementById('conciergeCommValue').value = d.commission_value || 0;

    // Show referral link
    if (d.referral_code) {
        document.getElementById('referralLinkWrap').classList.remove('hidden');
        document.getElementById('referralLinkDisplay').textContent = SITE_URL + '/book.php?ref=' + d.referral_code;
    } else {
        document.getElementById('referralLinkWrap').classList.add('hidden');
    }

    // Show portal link
    if (d.email) {
        document.getElementById('portalLinkWrap').classList.remove('hidden');
        document.getElementById('portalLinkDisplay').textContent = SITE_URL + '/portal/';
    } else {
        document.getElementById('portalLinkWrap').classList.add('hidden');
    }

    form.scrollIntoView({ behavior: 'smooth' });
}

async function savePartner() {
    const name = document.getElementById('conciergeName').value.trim();
    const email = document.getElementById('conciergeEmail').value.trim();
    const password = document.getElementById('conciergePassword').value;
    const type = document.getElementById('conciergeType').value || currentPartnerType;

    if (!name || !email) { showToast('Nombre y email son requeridos', 'error'); return; }
    if (!isEditing && !password) { showToast('Password es requerido', 'error'); return; }

    const payload = {
        name, email, type,
        hotel_id: document.getElementById('conciergeHotel').value || null,
        phone: document.getElementById('conciergePhone').value.trim(),
        company_name: document.getElementById('conciergeCompany').value.trim() || null,
        commission_type: document.getElementById('conciergeCommType').value,
        commission_value: document.getElementById('conciergeCommValue').value || 0
    };

    const id = document.getElementById('editConciergeId').value;
    if (isEditing && id) {
        payload.action = 'update_concierge';
        payload.id = id;
        if (password) payload.password = password;
    } else {
        payload.action = 'create_concierge';
        payload.password = password;
    }

    try {
        const res = await fetch(API_URL, {
            method: isEditing ? 'PUT' : 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(payload)
        });
        const r = await res.json();
        if (r.success) {
            let msg = r.message || 'Guardado';
            if (r.referral_code) msg += ' | Código: ' + r.referral_code;
            showToast(msg);
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(r.error || 'Error', 'error');
        }
    } catch(e) { showToast('Error de conexión', 'error'); }
}

async function deletePartner(type, id, name) {
    if (!await confirmAction('Desactivar "' + name + '"?')) return;
    try {
        const res = await fetch(API_URL, { method: 'DELETE', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ type, id }) });
        const r = await res.json();
        if (r.success) { showToast('Desactivado'); setTimeout(() => location.reload(), 800); }
        else showToast(r.error || 'Error', 'error');
    } catch(e) { showToast('Error de conexión', 'error'); }
}

// ========================
// COPY LINK
// ========================
function copyLink(code) {
    const url = SITE_URL + '/book.php?ref=' + code;
    navigator.clipboard.writeText(url).then(() => {
        showToast('Link copiado: ' + url);
    }).catch(() => {
        // Fallback
        prompt('Copia este link:', url);
    });
}
function copyReferralLink() {
    const url = document.getElementById('referralLinkDisplay').textContent;
    navigator.clipboard.writeText(url).then(() => showToast('Link copiado'));
}

// Commission label update
document.getElementById('conciergeCommType')?.addEventListener('change', function() {
    document.getElementById('conciergeCommLabel').textContent = this.value === 'fixed' ? 'Monto fijo (MXN)' : 'Comisión %';
});
document.getElementById('affiliateCommType')?.addEventListener('change', function() {
    document.getElementById('affiliateCommLabel').textContent = this.value === 'fixed' ? 'Monto fijo (MXN)' : 'Comisión %';
});

// ========================
// PORTAL LINK COPY
// ========================
function copyPortalLink(email) {
    const url = SITE_URL + '/portal/';
    navigator.clipboard.writeText(url).then(() => {
        showToast('Enlace al portal copiado');
    }).catch(() => {
        prompt('Copia este enlace:', url);
    });
}
function copyPortalLinkFromForm() {
    const url = document.getElementById('portalLinkDisplay').textContent;
    navigator.clipboard.writeText(url).then(() => showToast('Enlace al portal copiado'));
}
function copyAffiliatePortalLink() {
    const url = document.getElementById('affiliatePortalDisplay').textContent;
    navigator.clipboard.writeText(url).then(() => showToast('Enlace al portal copiado'));
}
function copyAffiliateReferralLink() {
    const url = document.getElementById('affiliateReferralDisplay').textContent;
    navigator.clipboard.writeText(url).then(() => showToast('Link de referido copiado'));
}

// ========================
// AFFILIATE-SPECIFIC FORM
// ========================
let isEditingAffiliate = false;

function resetAffiliateForm() {
    ['editAffiliateId','affiliateName','affiliateEmail','affiliatePassword','affiliatePhone','affiliateCompany'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('affiliateCommType').value = 'percentage';
    document.getElementById('affiliateCommValue').value = '0';
    document.getElementById('affiliateReferralWrap').classList.add('hidden');
    document.getElementById('affiliatePortalWrap').classList.add('hidden');
    document.getElementById('affiliateFormTitle').textContent = 'Crear Nuevo Afiliado';
    document.getElementById('affiliatePasswordLabel').textContent = 'Password *';
    isEditingAffiliate = false;
}

function cancelAffiliateForm() {
    document.getElementById('affiliateForm').classList.add('hidden');
    resetAffiliateForm();
}

function editAffiliate(d) {
    isEditingAffiliate = true;
    const form = document.getElementById('affiliateForm');
    form.classList.remove('hidden');
    document.getElementById('affiliateFormTitle').textContent = 'Editar Afiliado';
    document.getElementById('affiliatePasswordLabel').textContent = 'Password (vacío = no cambiar)';
    document.getElementById('editAffiliateId').value = d.id;
    document.getElementById('affiliateName').value = d.name || '';
    document.getElementById('affiliateEmail').value = d.email || '';
    document.getElementById('affiliatePassword').value = '';
    document.getElementById('affiliatePhone').value = d.phone || '';
    document.getElementById('affiliateCompany').value = d.company_name || '';
    document.getElementById('affiliateCommType').value = d.commission_type || 'percentage';
    document.getElementById('affiliateCommValue').value = d.commission_value || 0;

    // Show referral link
    if (d.referral_code) {
        document.getElementById('affiliateReferralWrap').classList.remove('hidden');
        document.getElementById('affiliateReferralDisplay').textContent = SITE_URL + '/book.php?ref=' + d.referral_code;
    } else {
        document.getElementById('affiliateReferralWrap').classList.add('hidden');
    }

    // Show portal link
    if (d.email) {
        document.getElementById('affiliatePortalWrap').classList.remove('hidden');
        document.getElementById('affiliatePortalDisplay').textContent = SITE_URL + '/portal/';
    } else {
        document.getElementById('affiliatePortalWrap').classList.add('hidden');
    }

    form.scrollIntoView({ behavior: 'smooth' });
}

async function saveAffiliate() {
    const name = document.getElementById('affiliateName').value.trim();
    const email = document.getElementById('affiliateEmail').value.trim();
    const password = document.getElementById('affiliatePassword').value;

    if (!name || !email) { showToast('Nombre y email son requeridos', 'error'); return; }
    if (!isEditingAffiliate && !password) { showToast('Password es requerido', 'error'); return; }

    const payload = {
        name, email, type: 'affiliate',
        hotel_id: null,
        phone: document.getElementById('affiliatePhone').value.trim(),
        company_name: document.getElementById('affiliateCompany').value.trim() || null,
        commission_type: document.getElementById('affiliateCommType').value,
        commission_value: document.getElementById('affiliateCommValue').value || 0
    };

    const id = document.getElementById('editAffiliateId').value;
    if (isEditingAffiliate && id) {
        payload.action = 'update_concierge';
        payload.id = id;
        if (password) payload.password = password;
    } else {
        payload.action = 'create_concierge';
        payload.password = password;
    }

    try {
        const res = await fetch(API_URL, {
            method: isEditingAffiliate ? 'PUT' : 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(payload)
        });
        const r = await res.json();
        if (r.success) {
            let msg = r.message || 'Guardado';
            if (r.referral_code) msg += ' | Código: ' + r.referral_code;
            showToast(msg);
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(r.error || 'Error', 'error');
        }
    } catch(e) { showToast('Error de conexión', 'error'); }
}

// ========================
// PERKS MODAL
// ========================
let currentPerksId = null;
let currentPerksName = '';

async function openPerksModal(conciergeId, name) {
    currentPerksId = conciergeId;
    currentPerksName = name;

    // Fetch existing perks
    let perks = [];
    try {
        const res = await fetch(API_URL + '?action=perks&concierge_id=' + conciergeId);
        const data = await res.json();
        if (data.success) perks = data.data || [];
    } catch(e) { console.error(e); }

    const perkTypeLabels = { gift: 'Regalo', discount: 'Descuento', courtesy: 'Cortesía', agreement: 'Acuerdo' };
    const perkTypeColors = { gift: 'text-pink-400', discount: 'text-green-400', courtesy: 'text-blue-400', agreement: 'text-gold-400' };

    let existingHtml = '';
    if (perks.length === 0) {
        existingHtml = '<p class="text-dark-500 text-sm text-center py-4">Sin perks asignados</p>';
    } else {
        existingHtml = '<div class="space-y-2 max-h-60 overflow-y-auto">';
        perks.forEach(p => {
            existingHtml += `<div class="flex items-center justify-between p-3 bg-dark-800 rounded-lg border border-dark-700">
                <div class="flex-1">
                    <div class="flex items-center gap-2">
                        <span class="text-xs px-2 py-0.5 rounded ${perkTypeColors[p.perk_type] || 'text-dark-300'} bg-dark-700 font-semibold">${perkTypeLabels[p.perk_type] || p.perk_type}</span>
                        <span class="text-sm text-white font-medium">${p.name}</span>
                        ${p.value ? '<span class="text-xs text-gold-400 font-semibold">' + p.value + '</span>' : ''}
                    </div>
                    ${p.description ? '<p class="text-xs text-dark-400 mt-1">' + p.description + '</p>' : ''}
                </div>
                <button onclick="deletePerk(${p.id})" class="text-dark-500 hover:text-red-400 ml-3 flex-shrink-0" title="Eliminar">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>`;
        });
        existingHtml += '</div>';
    }

    // Create modal
    const existing = document.getElementById('perksModal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = 'perksModal';
    modal.className = 'fixed inset-0 z-50 flex items-center justify-center p-4';
    modal.innerHTML = `
        <div class="absolute inset-0 bg-black/60" onclick="closePerksModal()"></div>
        <div class="relative bg-dark-900 border border-dark-700 rounded-xl w-full max-w-lg shadow-2xl">
            <div class="px-6 py-4 border-b border-dark-700 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-white">Perks & Acuerdos</h3>
                    <p class="text-xs text-dark-400">${currentPerksName}</p>
                </div>
                <button onclick="closePerksModal()" class="w-8 h-8 rounded-full bg-dark-800 flex items-center justify-center text-dark-400 hover:text-white">&#10005;</button>
            </div>

            <div class="p-6">
                <!-- Existing perks -->
                <div class="mb-4" id="perksList">${existingHtml}</div>

                <!-- Add new perk form -->
                <div class="border-t border-dark-700 pt-4">
                    <h4 class="text-xs font-semibold text-dark-400 uppercase mb-3">Agregar Perk</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-dark-400 mb-1">Tipo</label>
                            <select id="newPerkType" class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white focus:border-gold-500 focus:outline-none">
                                <option value="gift">Regalo</option>
                                <option value="discount">Descuento</option>
                                <option value="courtesy">Cortesía</option>
                                <option value="agreement">Acuerdo</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-dark-400 mb-1">Nombre *</label>
                            <input type="text" id="newPerkName" placeholder="ej: Botella de vino"
                                   class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white focus:border-gold-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-dark-400 mb-1">Valor</label>
                            <input type="text" id="newPerkValue" placeholder="ej: 15%, $500, 2x1"
                                   class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white focus:border-gold-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs text-dark-400 mb-1">Descripción</label>
                            <input type="text" id="newPerkDesc" placeholder="Detalles..."
                                   class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2 text-sm text-white focus:border-gold-500 focus:outline-none">
                        </div>
                    </div>
                    <button onclick="savePerk()" class="mt-3 px-4 py-2 bg-gold-500 text-dark-950 rounded-lg text-xs font-semibold hover:bg-gold-400 transition-colors">
                        Agregar Perk
                    </button>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

function closePerksModal() {
    const modal = document.getElementById('perksModal');
    if (modal) modal.remove();
}

async function savePerk() {
    const perkType = document.getElementById('newPerkType').value;
    const name = document.getElementById('newPerkName').value.trim();
    const value = document.getElementById('newPerkValue').value.trim();
    const desc = document.getElementById('newPerkDesc').value.trim();

    if (!name) { showToast('El nombre del perk es requerido', 'error'); return; }

    try {
        const res = await fetch(API_URL, {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                action: 'create_perk',
                concierge_id: currentPerksId,
                perk_type: perkType,
                name: name,
                value: value || null,
                description: desc || null
            })
        });
        const data = await res.json();
        if (data.success) {
            showToast('Perk agregado');
            closePerksModal();
            openPerksModal(currentPerksId, currentPerksName);
        } else {
            showToast(data.error || 'Error', 'error');
        }
    } catch(e) { showToast('Error de conexión', 'error'); }
}

async function deletePerk(perkId) {
    if (!confirm('¿Eliminar este perk?')) return;
    try {
        const res = await fetch(API_URL, {
            method: 'DELETE',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ type: 'perk', id: perkId })
        });
        const data = await res.json();
        if (data.success) {
            showToast('Perk eliminado');
            closePerksModal();
            openPerksModal(currentPerksId, currentPerksName);
        } else {
            showToast(data.error || 'Error', 'error');
        }
    } catch(e) { showToast('Error de conexión', 'error'); }
}

// ========================
// QR CODE MODAL
// ========================
function showQR(name, email, referralCode) {
    const existing = document.getElementById('qrModal');
    if (existing) existing.remove();

    const portalUrl = SITE_URL + '/portal/login.php';
    const referralUrl = referralCode ? SITE_URL + '/book.php?ref=' + referralCode : '';

    const modal = document.createElement('div');
    modal.id = 'qrModal';
    modal.className = 'fixed inset-0 z-50 flex items-center justify-center p-4';
    modal.innerHTML = `
        <div class="absolute inset-0 bg-black/60" onclick="closeQRModal()"></div>
        <div class="relative bg-dark-900 border border-dark-700 rounded-xl w-full max-w-md shadow-2xl">
            <div class="px-6 py-4 border-b border-dark-700 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-white">Códigos QR</h3>
                    <p class="text-xs text-dark-400">${name}</p>
                </div>
                <button onclick="closeQRModal()" class="w-8 h-8 rounded-full bg-dark-800 flex items-center justify-center text-dark-400 hover:text-white">&#10005;</button>
            </div>
            <div class="p-6 space-y-6">
                <!-- Portal QR -->
                <div class="text-center">
                    <h4 class="text-xs font-semibold text-dark-400 uppercase mb-3">Portal de Concierge</h4>
                    <div id="qrPortal" class="inline-block bg-white p-3 rounded-lg"></div>
                    <p class="text-xs text-dark-500 mt-2 break-all">${portalUrl}</p>
                </div>
                ${referralCode ? `
                <!-- Referral QR -->
                <div class="text-center border-t border-dark-700 pt-6">
                    <h4 class="text-xs font-semibold text-dark-400 uppercase mb-3">Link de Referido</h4>
                    <div id="qrReferral" class="inline-block bg-white p-3 rounded-lg"></div>
                    <p class="text-xs text-dark-500 mt-2 break-all">${referralUrl}</p>
                </div>
                ` : ''}
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    // Generate QR codes
    setTimeout(() => {
        if (typeof QRCode !== 'undefined') {
            new QRCode(document.getElementById('qrPortal'), {
                text: portalUrl,
                width: 180, height: 180,
                colorDark: '#000000', colorLight: '#ffffff'
            });
            if (referralCode) {
                new QRCode(document.getElementById('qrReferral'), {
                    text: referralUrl,
                    width: 180, height: 180,
                    colorDark: '#000000', colorLight: '#ffffff'
                });
            }
        }
    }, 100);
}

function closeQRModal() {
    const modal = document.getElementById('qrModal');
    if (modal) modal.remove();
}
</script>

<?php renderFooter(); ?>
