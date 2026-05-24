<?php
/**
 * Cenacolo Reservas - Portal de Concierges - Nueva Reserva
 * Formulario para que concierges creen reservas para sus huespedes
 */
require_once __DIR__ . '/../includes/config.php';

$concierge = requireConciergeLogin();
logResAccess($concierge['id'], 'portal_new_reservation', 'concierge');

$pdo = getResDB();

// Obtener restaurantes activos
$restaurants = getActiveRestaurants();

// Obtener secciones por restaurante
$sections = [];
$stmt = $pdo->query("
    SELECT rs.*, r.name as restaurant_name
    FROM restaurant_sections rs
    JOIN restaurants r ON rs.restaurant_id = r.id
    WHERE rs.active = 1
    ORDER BY rs.restaurant_id, rs.sort_order
");
$allSections = $stmt->fetchAll();
foreach ($allSections as $s) {
    $sections[$s['restaurant_id']][] = $s;
}

$conciergeName = resSanitize($concierge['name']);
$hotelName = resSanitize($concierge['hotel_name'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Reserva - Portal Concierge - Cenacolo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
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
        /* Flatpickr dark theme overrides */
        .flatpickr-calendar { background: #0f172a !important; border-color: #334155 !important; }
        .flatpickr-day { color: #e2e8f0 !important; }
        .flatpickr-day:hover { background: #334155 !important; }
        .flatpickr-day.selected { background: #D4AF37 !important; color: #020617 !important; border-color: #D4AF37 !important; }
        .flatpickr-months .flatpickr-month { color: #e2e8f0 !important; }
        .flatpickr-current-month .flatpickr-monthDropdown-months { color: #e2e8f0 !important; background: #0f172a !important; }
        .flatpickr-weekday { color: #94a3b8 !important; }
        .numInputWrapper span { border-color: #334155 !important; }
        .flatpickr-time input { color: #e2e8f0 !important; }
        .flatpickr-time .flatpickr-am-pm { color: #e2e8f0 !important; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #1e293b; }
        ::-webkit-scrollbar-thumb { background: #475569; border-radius: 3px; }
    </style>
</head>
<body class="bg-dark-950 text-dark-200 min-h-screen">

    <!-- Top Navbar -->
    <nav class="sticky top-0 z-30 bg-dark-900/90 backdrop-blur-md border-b border-dark-700">
        <div class="max-w-4xl mx-auto px-4 sm:px-6">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-3">
                    <h1 class="font-display text-xl text-gold-500 font-bold tracking-wide">Cenacolo</h1>
                    <span class="text-dark-500 text-xs">|</span>
                    <span class="text-dark-400 text-xs uppercase tracking-wider">Portal Concierge</span>
                </div>
                <div class="flex items-center space-x-4">
                    <!-- Connection status chip -->
                    <span id="connStatus" class="flex items-center gap-1.5 text-xs font-medium text-green-400 hidden sm:flex">
                        <span class="w-2 h-2 rounded-full bg-green-400 inline-block"></span> En línea
                    </span>
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

    <!-- Offline banner -->
    <div id="offlineBanner" class="hidden bg-yellow-900 border-b border-yellow-700 text-yellow-200 px-4 py-3 text-sm text-center">
        📵 <strong>Sin conexión</strong> — los cambios se guardan localmente y se sincronizarán automáticamente al reconectar.
    </div>

    <!-- Main Content -->
    <main class="max-w-4xl mx-auto px-4 sm:px-6 py-6">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6 fade-in">
            <div>
                <h2 class="text-xl font-semibold text-white">Nueva Reserva</h2>
                <p class="text-dark-400 text-sm mt-1">Crear reserva para huesped de <?= $hotelName ?></p>
            </div>
            <a href="<?= resUrl('/portal/index.php') ?>" class="text-sm text-dark-400 hover:text-white transition-colors">
                &larr; Volver al Dashboard
            </a>
        </div>

        <!-- Reservation Form -->
        <div class="bg-dark-900 rounded-xl border border-dark-700 fade-in" id="formContainer">
            <div class="px-6 py-4 border-b border-dark-700">
                <h3 class="text-sm font-semibold text-gold-500 uppercase tracking-wider">Datos de la Reserva</h3>
            </div>

            <form id="reservationForm" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- LEFT: Guest Info -->
                    <div>
                        <h4 class="text-sm font-semibold text-dark-300 uppercase tracking-wider mb-4">Datos del Huesped</h4>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm text-dark-400 mb-1">Nombre completo *</label>
                                <input type="text" name="guest_name" required
                                       class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2.5 text-white placeholder-dark-500 focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500 transition-colors"
                                       placeholder="Nombre del huesped">
                            </div>
                            <div>
                                <label class="block text-sm text-dark-400 mb-1">Telefono</label>
                                <input type="tel" name="guest_phone"
                                       class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2.5 text-white placeholder-dark-500 focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500 transition-colors"
                                       placeholder="+52 998 123 4567">
                            </div>
                            <div>
                                <label class="block text-sm text-dark-400 mb-1">Email</label>
                                <input type="email" name="guest_email"
                                       class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2.5 text-white placeholder-dark-500 focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500 transition-colors"
                                       placeholder="email@ejemplo.com">
                            </div>
                            <div>
                                <label class="block text-sm text-dark-400 mb-1">Numero de personas *</label>
                                <input type="number" name="party_size" min="1" max="50" required value="2"
                                       class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2.5 text-white focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500 transition-colors">
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT: Reservation Info -->
                    <div>
                        <h4 class="text-sm font-semibold text-dark-300 uppercase tracking-wider mb-4">Datos de la Reserva</h4>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm text-dark-400 mb-1">Restaurante *</label>
                                <select name="restaurant_id" id="restaurantSelect" required
                                        class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2.5 text-white focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500 transition-colors">
                                    <option value="">Seleccionar restaurante...</option>
                                    <?php foreach ($restaurants as $r): ?>
                                        <option value="<?= $r['id'] ?>"><?= resSanitize($r['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm text-dark-400 mb-1">Fecha *</label>
                                    <input type="text" name="reservation_date" id="datePicker" required
                                           class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2.5 text-white focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500 transition-colors"
                                           placeholder="Seleccionar fecha">
                                </div>
                                <div>
                                    <label class="block text-sm text-dark-400 mb-1">Hora *</label>
                                    <input type="text" name="reservation_time" id="timePicker" required
                                           class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2.5 text-white focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500 transition-colors"
                                           placeholder="Seleccionar hora">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm text-dark-400 mb-1">Preferencia de seccion</label>
                                <select name="section_preference" id="sectionSelect"
                                        class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2.5 text-white focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500 transition-colors">
                                    <option value="">Sin preferencia</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm text-dark-400 mb-1">Ocasion</label>
                                <select name="occasion"
                                        class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2.5 text-white focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500 transition-colors">
                                    <option value="">Sin ocasion especial</option>
                                    <option value="Cumpleanos">Cumpleanos</option>
                                    <option value="Aniversario">Aniversario</option>
                                    <option value="Negocios">Negocios</option>
                                    <option value="Celebracion">Celebracion</option>
                                    <option value="Romantica">Cena Romantica</option>
                                    <option value="Familiar">Familiar</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Details -->
                <div class="mt-6 pt-6 border-t border-dark-700">
                    <h4 class="text-sm font-semibold text-dark-300 uppercase tracking-wider mb-4">Detalles Adicionales</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-dark-400 mb-1">Alergias</label>
                            <input type="text" name="allergies" placeholder="Gluten, mariscos, lacteos, etc."
                                   class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2.5 text-white placeholder-dark-500 focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500 transition-colors">
                        </div>
                        <div>
                            <label class="block text-sm text-dark-400 mb-1">Solicitudes especiales</label>
                            <input type="text" name="special_requests" placeholder="Silla para bebe, vista al mar, etc."
                                   class="w-full bg-dark-800 border border-dark-600 rounded-lg px-3 py-2.5 text-white placeholder-dark-500 focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500 transition-colors">
                        </div>
                    </div>
                </div>

                <!-- Hidden fields auto-set for concierge -->
                <input type="hidden" name="channel" value="concierge">
                <input type="hidden" name="concierge_id" value="<?= $concierge['id'] ?>">
                <input type="hidden" name="source_details" value="<?= resSanitize($concierge['hotel_name'] ?? $concierge['name']) ?>">

                <!-- Submit -->
                <div class="mt-8 flex items-center justify-end space-x-3">
                    <a href="<?= resUrl('/portal/index.php') ?>"
                       class="px-5 py-2.5 border border-dark-600 text-dark-300 rounded-lg hover:bg-dark-800 transition-colors">
                        Cancelar
                    </a>
                    <button type="submit" id="submitBtn"
                            class="px-8 py-2.5 bg-gold-500 text-dark-950 rounded-lg font-semibold hover:bg-gold-400 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        Crear Reserva
                    </button>
                </div>
            </form>
        </div>

        <!-- Offline confirmation — shown when form is submitted without internet -->
        <div id="offlineContainer" class="hidden fade-in">
            <div class="bg-dark-900 rounded-xl border border-yellow-700/50 p-8 text-center max-w-lg mx-auto">
                <div class="w-16 h-16 rounded-full bg-yellow-500/20 flex items-center justify-center mx-auto mb-4">
                    <span class="text-3xl">⏳</span>
                </div>
                <h3 class="text-xl font-semibold text-white mb-2">Reserva guardada sin conexión</h3>
                <div id="offlineSummary" class="text-dark-300 text-sm mb-4"></div>
                <div class="bg-dark-800 rounded-lg p-4 mb-6 text-left text-sm text-yellow-200 border border-yellow-700/40">
                    <p class="mb-1">✅ La reserva está guardada en <strong>este dispositivo</strong>.</p>
                    <p class="mb-1">🔄 Se confirmará en el sistema cuando recuperes internet.</p>
                    <p class="text-yellow-400 font-semibold">⚠️ Avisa al manager si no recuperas conexión antes de que llegue el cliente.</p>
                </div>
                <div class="flex items-center justify-center space-x-3">
                    <a href="<?= resUrl('/portal/new-reservation.php') ?>"
                       class="px-6 py-2.5 bg-gold-500 text-dark-950 rounded-lg font-semibold hover:bg-gold-400 transition-colors">
                        Nueva Reserva
                    </a>
                    <a href="<?= resUrl('/portal/index.php') ?>"
                       class="px-6 py-2.5 border border-dark-600 text-dark-300 rounded-lg hover:bg-dark-800 transition-colors">
                        Volver al Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Success message (hidden by default) -->
        <div id="successContainer" class="hidden fade-in">
            <div class="bg-dark-900 rounded-xl border border-green-700/50 p-8 text-center">
                <div class="w-16 h-16 rounded-full bg-green-500/20 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <h3 class="text-xl font-semibold text-white mb-2">Reserva Creada Exitosamente</h3>
                <p class="text-dark-400 mb-4">La reserva ha sido confirmada.</p>
                <div class="bg-dark-800 rounded-lg p-4 inline-block mb-6">
                    <p class="text-xs text-dark-400 uppercase tracking-wider mb-1">Codigo de Confirmacion</p>
                    <p class="text-2xl font-mono font-bold text-gold-500" id="confirmationCode">---</p>
                </div>
                <div id="successDetails" class="text-sm text-dark-300 mb-6"></div>
                <div class="flex items-center justify-center space-x-3">
                    <a href="<?= resUrl('/portal/new-reservation.php') ?>"
                       class="px-6 py-2.5 bg-gold-500 text-dark-950 rounded-lg font-semibold hover:bg-gold-400 transition-colors">
                        Crear Otra Reserva
                    </a>
                    <a href="<?= resUrl('/portal/index.php') ?>"
                       class="px-6 py-2.5 border border-dark-600 text-dark-300 rounded-lg hover:bg-dark-800 transition-colors">
                        Volver al Dashboard
                    </a>
                </div>
            </div>
        </div>

    </main>

    <!-- Toast container -->
    <div id="toastContainer" class="fixed bottom-4 right-4 z-50"></div>

    <script>
        // Init flatpickr
        flatpickr.localize(flatpickr.l10ns.es);

        flatpickr('#datePicker', {
            dateFormat: 'Y-m-d',
            minDate: 'today',
            locale: 'es',
            disableMobile: true
        });

        flatpickr('#timePicker', {
            enableTime: true,
            noCalendar: true,
            dateFormat: 'H:i',
            altInput: true,
            altFormat: 'h:i K',
            time_24hr: false,
            minuteIncrement: 15,
            minTime: '10:00',
            maxTime: '23:00',
            disableMobile: true
        });

        // Sections data from PHP
        const allSections = <?= json_encode($sections) ?>;

        // Update sections when restaurant changes
        document.getElementById('restaurantSelect').addEventListener('change', function() {
            const sectionSelect = document.getElementById('sectionSelect');
            sectionSelect.innerHTML = '<option value="">Sin preferencia</option>';

            const restaurantId = this.value;
            if (allSections[restaurantId]) {
                allSections[restaurantId].forEach(s => {
                    const option = document.createElement('option');
                    option.value = s.name;
                    option.textContent = s.name;
                    sectionSelect.appendChild(option);
                });
            }
        });

        // Form submit
        document.getElementById('reservationForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creando reserva...';

            const formData = new FormData(this);
            const data = {};
            formData.forEach((value, key) => {
                if (value) data[key] = value;
            });

            try {
                const response = await fetch('<?= resUrl('/api/reservations.php') ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.queued === true) {
                    // Offline — show confirmation screen
                    document.getElementById('formContainer').classList.add('hidden');
                    document.getElementById('offlineContainer').classList.remove('hidden');
                    // Fill summary with reservation details from the submitted form data
                    const parts = [];
                    if (data.guest_name)       parts.push('<strong>Huésped:</strong> ' + escapeHtml(data.guest_name));
                    if (data.reservation_date) parts.push('<strong>Fecha:</strong> '   + escapeHtml(data.reservation_date));
                    if (data.reservation_time) parts.push('<strong>Hora:</strong> '    + escapeHtml(data.reservation_time));
                    if (data.party_size)       parts.push('<strong>Personas:</strong> '+ escapeHtml(String(data.party_size)));
                    document.getElementById('offlineSummary').innerHTML = parts.join(' &middot; ');

                } else if (result.success && result.data) {
                    // Online — show normal success screen
                    document.getElementById('formContainer').classList.add('hidden');
                    document.getElementById('successContainer').classList.remove('hidden');
                    document.getElementById('confirmationCode').textContent = result.data.confirmation_code || '---';

                    const details = [];
                    if (result.data.guest_name)       details.push('<strong>Huesped:</strong> '      + escapeHtml(result.data.guest_name));
                    if (result.data.restaurant_name)  details.push('<strong>Restaurante:</strong> '  + escapeHtml(result.data.restaurant_name));
                    if (result.data.reservation_date) details.push('<strong>Fecha:</strong> '         + result.data.reservation_date);
                    if (result.data.reservation_time) details.push('<strong>Hora:</strong> '          + formatTime(result.data.reservation_time));
                    if (result.data.party_size)       details.push('<strong>Personas:</strong> '      + result.data.party_size);
                    document.getElementById('successDetails').innerHTML = details.join(' &middot; ');

                    showToast('Reserva creada exitosamente', 'success');
                } else {
                    showToast(result.error || 'Error al crear la reserva', 'error');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Crear Reserva';
                }
            } catch (err) {
                console.error(err);
                showToast('Error inesperado. Intenta nuevamente.', 'error');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Crear Reserva';
            }
        });

        // Helpers
        function formatTime(timeStr) {
            if (!timeStr) return '';
            const parts = timeStr.split(':');
            const hour = parseInt(parts[0]);
            const min = parts[1];
            const ampm = hour >= 12 ? 'PM' : 'AM';
            const hour12 = hour % 12 || 12;
            return hour12 + ':' + min + ' ' + ampm;
        }

        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function showToast(message, type) {
            const container = document.getElementById('toastContainer');
            const colors = {
                success: 'bg-green-600',
                error: 'bg-red-600',
                warning: 'bg-yellow-600',
                info: 'bg-blue-600'
            };
            const toast = document.createElement('div');
            toast.className = `${colors[type] || colors.info} text-white px-6 py-3 rounded-lg shadow-lg text-sm font-medium mb-2 fade-in`;
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 5000);
        }
    </script>

    <script src="<?= resUrl('/portal/js/idb.js') ?>"></script>
    <script src="<?= resUrl('/portal/js/offline.js') ?>"></script>
</body>
</html>
