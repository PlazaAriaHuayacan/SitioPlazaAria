<?php
/**
 * Portal Concierge — Mis Datos Bancarios
 */
require_once __DIR__ . '/../includes/config.php';
$concierge = requireConciergeLogin();
logResAccess($concierge['id'], 'portal_bank_data', 'concierge');

$hasBank = !empty($concierge['bank_clabe']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datos Bancarios - Portal Concierge - Cenacolo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: { extend: { colors: {
                gold: { 400:'#FFCC33', 500:'#D4AF37', 600:'#B8960F' },
                dark: { 600:'#475569', 700:'#334155', 800:'#1e293b', 900:'#0f172a', 950:'#020617' }
            }}}
        }
    </script>
    <style>body { font-family: 'Inter', sans-serif; } .font-display { font-family: 'Playfair Display', serif; }</style>
</head>
<body class="bg-dark-950 text-slate-200 min-h-screen">

    <!-- Nav -->
    <nav class="sticky top-0 z-30 bg-dark-900/90 backdrop-blur-md border-b border-dark-700">
        <div class="max-w-2xl mx-auto px-4 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <h1 class="font-display text-xl text-gold-500 font-bold">Cenacolo</h1>
                <span class="text-slate-600 text-xs">|</span>
                <span class="text-slate-400 text-xs uppercase tracking-wider">Portal Concierge</span>
            </div>
            <a href="<?= resUrl('/portal/index.php') ?>" class="text-slate-400 hover:text-gold-400 text-sm transition-colors">
                &larr; Volver al dashboard
            </a>
        </div>
    </nav>

    <main class="max-w-2xl mx-auto px-4 py-8">

        <h2 class="text-2xl font-semibold text-white mb-2">Mis Datos Bancarios</h2>
        <p class="text-slate-400 text-sm mb-6">Registra tu CLABE para recibir tus comisiones vía SPEI.</p>

        <?php if (!$hasBank): ?>
        <div id="noBankWarning" class="bg-yellow-900/30 border border-yellow-600/40 rounded-xl px-5 py-4 mb-6 flex items-start gap-3">
            <svg class="w-5 h-5 text-yellow-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            <p class="text-yellow-300 text-sm font-medium">Sin datos bancarios no podremos procesar tu comisión.</p>
        </div>
        <?php endif; ?>

        <!-- Alerta de feedback -->
        <div id="alertBox" class="hidden mb-4 px-4 py-3 rounded-lg text-sm font-medium"></div>

        <div class="bg-dark-900 rounded-xl border border-dark-700 p-6">
            <form id="bankForm" class="space-y-5">

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Banco *</label>
                    <input type="text" id="bank_name" name="bank_name"
                           value="<?= resSanitize($concierge['bank_name'] ?? '') ?>"
                           placeholder="Ej: BBVA, Banamex, Banorte..."
                           maxlength="100"
                           required
                           class="w-full bg-dark-800 border border-dark-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-600 focus:outline-none focus:border-gold-500 transition-colors text-sm">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">CLABE Interbancaria * <span class="text-slate-600 font-normal normal-case">(18 dígitos)</span></label>
                    <input type="text" id="bank_clabe" name="bank_clabe"
                           value="<?= resSanitize($concierge['bank_clabe'] ?? '') ?>"
                           placeholder="000000000000000000"
                           maxlength="18"
                           pattern="\d{18}"
                           inputmode="numeric"
                           required
                           class="w-full bg-dark-800 border border-dark-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-600 focus:outline-none focus:border-gold-500 transition-colors text-sm font-mono tracking-widest">
                    <p id="clabeCounter" class="text-xs text-slate-600 mt-1"><span id="clabeLen">0</span>/18 dígitos</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Número de Cuenta <span class="text-slate-600 font-normal normal-case">(opcional)</span></label>
                    <input type="text" id="bank_account" name="bank_account"
                           value="<?= resSanitize($concierge['bank_account'] ?? '') ?>"
                           placeholder="Ej: 1234567890"
                           maxlength="30"
                           class="w-full bg-dark-800 border border-dark-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-600 focus:outline-none focus:border-gold-500 transition-colors text-sm">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Nombre del Titular *</label>
                    <input type="text" id="bank_holder" name="bank_holder"
                           value="<?= resSanitize($concierge['bank_holder'] ?? '') ?>"
                           placeholder="Nombre completo tal como aparece en la cuenta"
                           maxlength="255"
                           required
                           class="w-full bg-dark-800 border border-dark-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-600 focus:outline-none focus:border-gold-500 transition-colors text-sm">
                </div>

                <button type="submit" id="saveBtn"
                        class="w-full py-3 bg-gold-500 text-dark-950 rounded-lg font-semibold hover:bg-gold-400 transition-colors text-sm">
                    Guardar Datos Bancarios
                </button>

            </form>
        </div>

    </main>

    <script>
        // Contador de dígitos CLABE
        const clabeInput = document.getElementById('bank_clabe');
        const clabeLen   = document.getElementById('clabeLen');
        const updateClabeCounter = () => {
            const digits = clabeInput.value.replace(/\D/g, '');
            clabeInput.value = digits.slice(0, 18);
            clabeLen.textContent = clabeInput.value.length;
            clabeLen.className = clabeInput.value.length === 18 ? 'text-green-400' : 'text-slate-600';
        };
        clabeInput.addEventListener('input', updateClabeCounter);
        updateClabeCounter(); // seed counter for pre-populated values

        // Submit
        document.getElementById('bankForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('saveBtn');
            btn.disabled = true;
            btn.textContent = 'Guardando...';

            const alertBox = document.getElementById('alertBox');
            alertBox.className = 'hidden mb-4 px-4 py-3 rounded-lg text-sm font-medium';

            const payload = {
                bank_name:    document.getElementById('bank_name').value.trim(),
                bank_clabe:   document.getElementById('bank_clabe').value.trim(),
                bank_account: document.getElementById('bank_account').value.trim(),
                bank_holder:  document.getElementById('bank_holder').value.trim(),
            };

            if (!payload.bank_name || !payload.bank_holder) {
                alertBox.textContent = '✗ Banco y Nombre del Titular son requeridos.';
                alertBox.className = 'mb-4 px-4 py-3 rounded-lg text-sm font-medium bg-red-900/40 border border-red-600/40 text-red-300';
                btn.disabled = false;
                btn.textContent = 'Guardar Datos Bancarios';
                return;
            }
            if (payload.bank_clabe.length !== 18) {
                alertBox.textContent = '✗ La CLABE debe tener exactamente 18 dígitos.';
                alertBox.className = 'mb-4 px-4 py-3 rounded-lg text-sm font-medium bg-red-900/40 border border-red-600/40 text-red-300';
                btn.disabled = false;
                btn.textContent = 'Guardar Datos Bancarios';
                return;
            }

            try {
                const res  = await fetch('<?= resUrl('/api/bank-data.php') ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();

                if (json.success) {
                    alertBox.textContent = '✓ ' + json.message;
                    alertBox.className = 'mb-4 px-4 py-3 rounded-lg text-sm font-medium bg-green-900/40 border border-green-600/40 text-green-300';
                    const warn = document.getElementById('noBankWarning');
                    if (warn) warn.style.display = 'none';
                } else {
                    alertBox.textContent = '✗ ' + (json.error || 'Error desconocido');
                    alertBox.className = 'mb-4 px-4 py-3 rounded-lg text-sm font-medium bg-red-900/40 border border-red-600/40 text-red-300';
                }
            } catch {
                alertBox.textContent = '✗ Error de conexión. Intenta de nuevo.';
                alertBox.className = 'mb-4 px-4 py-3 rounded-lg text-sm font-medium bg-red-900/40 border border-red-600/40 text-red-300';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Guardar Datos Bancarios';
                alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    </script>

</body>
</html>
