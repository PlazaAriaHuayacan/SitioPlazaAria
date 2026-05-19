# CenacoloReserve — Datos Bancarios y Pagos a Concierges

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir que concierges registren sus datos bancarios, generar un registro de comisión por reserva completada, y dar a la administración de Cenacolo un panel para marcar pagos realizados con indicador de urgencia de 24h.

**Architecture:** Tabla nueva `commissions` (tracking por reserva), 4 columnas bancarias en `concierges`, un endpoint nuevo `api/bank-data.php`, una acción nueva en `api/admin.php`, y tres páginas nuevas (portal y admin). El trigger de comisión reemplaza la lógica parcial existente en el case `completed` de `api/reservations.php` líneas 759-776.

**Tech Stack:** PHP 8+, PDO/MySQL, Tailwind CSS via CDN (dark theme: dark-950/900/800/700, gold-500), vanilla JS con `fetch()` para llamadas API. Sin frameworks ni librerías adicionales.

---

## Mapa de archivos

| Archivo | Acción |
|---------|--------|
| `migrate_commissions.php` | Crear — migración DB (correr una vez, luego eliminar) |
| `api/bank-data.php` | Crear — endpoint POST para guardar datos bancarios |
| `api/admin.php` | Modificar — agregar acción `mark_commission_paid` |
| `api/reservations.php` | Modificar — reemplazar trigger comisión en case `completed` (líneas ~759-776) |
| `portal/bank-data.php` | Crear — formulario de datos bancarios para concierge |
| `portal/commissions.php` | Crear — historial de comisiones (concierge, solo lectura) |
| `admin/commissions.php` | Crear — panel de pagos pendientes con urgencia (admin) |
| `portal/index.php` | Modificar — banner sin datos bancarios + link comisiones + total pendiente |
| `admin/concierges.php` | Modificar — columna datos bancarios + link al panel |

---

## Task 1: Migración de base de datos

**Archivo:** `migrate_commissions.php` (en raíz del proyecto)

- [ ] **Crear el archivo de migración**

```php
<?php
/**
 * Migración: Datos bancarios + tabla commissions
 * Correr una vez vía browser. Eliminar después.
 */
require_once __DIR__ . '/includes/config.php';
$pdo = getResDB();
$results = [];

try {
    // 1. Columna bank_name
    $stmt = $pdo->query("SHOW COLUMNS FROM concierges LIKE 'bank_name'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE concierges ADD COLUMN bank_name VARCHAR(100) NULL AFTER company_name");
        $results[] = "OK: Added bank_name";
    } else { $results[] = "SKIP: bank_name exists"; }

    // 2. Columna bank_clabe
    $stmt = $pdo->query("SHOW COLUMNS FROM concierges LIKE 'bank_clabe'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE concierges ADD COLUMN bank_clabe CHAR(18) NULL AFTER bank_name");
        $results[] = "OK: Added bank_clabe";
    } else { $results[] = "SKIP: bank_clabe exists"; }

    // 3. Columna bank_account
    $stmt = $pdo->query("SHOW COLUMNS FROM concierges LIKE 'bank_account'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE concierges ADD COLUMN bank_account VARCHAR(30) NULL AFTER bank_clabe");
        $results[] = "OK: Added bank_account";
    } else { $results[] = "SKIP: bank_account exists"; }

    // 4. Columna bank_holder
    $stmt = $pdo->query("SHOW COLUMNS FROM concierges LIKE 'bank_holder'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE concierges ADD COLUMN bank_holder VARCHAR(255) NULL AFTER bank_account");
        $results[] = "OK: Added bank_holder";
    } else { $results[] = "SKIP: bank_holder exists"; }

    // 5. Tabla commissions
    $stmt = $pdo->query("SHOW TABLES LIKE 'commissions'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("
            CREATE TABLE commissions (
                id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                concierge_id       INT UNSIGNED NOT NULL,
                reservation_id     INT UNSIGNED NOT NULL,
                commission_type    ENUM('fixed','percentage') NOT NULL,
                commission_rate    DECIMAL(10,2) NOT NULL DEFAULT 0,
                consumption_total  DECIMAL(10,2) NULL COMMENT 'Monto del cheque, viene del POS',
                commission_amount  DECIMAL(10,2) NULL COMMENT 'NULL hasta tener consumption_total si porcentaje',
                status             ENUM('pending','paid') NOT NULL DEFAULT 'pending',
                due_by             DATETIME NOT NULL COMMENT 'reservation_datetime + 24h',
                paid_at            DATETIME NULL,
                paid_by_user_id    INT UNSIGNED NULL,
                notes              TEXT NULL,
                created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_reservation (reservation_id),
                KEY idx_concierge_status (concierge_id, status),
                KEY idx_due_by (due_by)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $results[] = "OK: Created commissions table";
    } else { $results[] = "SKIP: commissions table exists"; }

    // 6. Backfill: reservas completed existentes con concierge_id
    $stmt = $pdo->query("
        SELECT r.id as reservation_id, r.reservation_date, r.reservation_time,
               r.concierge_id, c.commission_type, c.commission_value
        FROM reservations r
        JOIN concierges c ON c.id = r.concierge_id
        WHERE r.status = 'completed'
          AND r.concierge_id IS NOT NULL
          AND COALESCE(c.commission_value, 0) > 0
    ");
    $completed = $stmt->fetchAll();
    $backfilled = 0;
    foreach ($completed as $row) {
        $type   = $row['commission_type'];
        $rate   = floatval($row['commission_value']);
        $amount = ($type === 'fixed') ? $rate : null;
        $dt     = $row['reservation_date'] . ' ' . $row['reservation_time'];
        $dueBy  = date('Y-m-d H:i:s', strtotime($dt . ' +24 hours'));
        $ins = $pdo->prepare("
            INSERT IGNORE INTO commissions
                (concierge_id, reservation_id, commission_type, commission_rate, commission_amount, due_by, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        $ins->execute([$row['concierge_id'], $row['reservation_id'], $type, $rate, $amount, $dueBy]);
        if ($ins->rowCount() > 0) $backfilled++;
    }
    $results[] = "OK: Backfilled {$backfilled} historical commissions";

} catch (Exception $e) {
    $results[] = "ERROR: " . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode(['results' => $results], JSON_PRETTY_PRINT);
```

- [ ] **Correr la migración vía browser**

Navegar a: `https://somossinergia.com/CenacoloReserve/migrate_commissions.php`

Respuesta esperada:
```json
{
  "results": [
    "OK: Added bank_name",
    "OK: Added bank_clabe",
    "OK: Added bank_account",
    "OK: Added bank_holder",
    "OK: Created commissions table",
    "OK: Backfilled N historical commissions"
  ]
}
```

Si algún paso muestra `ERROR:`, corregir antes de continuar. Correr de nuevo es seguro (los pasos están protegidos con SHOW COLUMNS / SHOW TABLES / INSERT IGNORE).

- [ ] **Commit**

```bash
git add migrate_commissions.php
git commit -m "feat: add commissions migration (bank data columns + commissions table)"
```

---

## Task 2: API — endpoint de datos bancarios

**Archivo:** `api/bank-data.php` (nuevo)

- [ ] **Crear el archivo**

```php
<?php
/**
 * API: Guardar datos bancarios del concierge
 * POST /api/bank-data.php
 * Auth: sesión de concierge activa
 */
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

if (!isConciergeLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST; // fallback form submit
}

$bankName    = trim($data['bank_name']    ?? '');
$bankClabe   = trim($data['bank_clabe']   ?? '');
$bankAccount = trim($data['bank_account'] ?? '');
$bankHolder  = trim($data['bank_holder']  ?? '');

// Validaciones
$errors = [];

if (empty($bankName)) {
    $errors[] = 'El nombre del banco es requerido';
} elseif (mb_strlen($bankName) > 100) {
    $errors[] = 'Nombre del banco máximo 100 caracteres';
}

if (empty($bankClabe)) {
    $errors[] = 'La CLABE interbancaria es requerida';
} elseif (!preg_match('/^\d{18}$/', $bankClabe)) {
    $errors[] = 'La CLABE debe tener exactamente 18 dígitos numéricos';
}

if (empty($bankHolder)) {
    $errors[] = 'El nombre del titular es requerido';
} elseif (mb_strlen($bankHolder) > 255) {
    $errors[] = 'Nombre del titular máximo 255 caracteres';
}

if (!empty($bankAccount) && mb_strlen($bankAccount) > 30) {
    $errors[] = 'Número de cuenta máximo 30 caracteres';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['error' => implode('. ', $errors)]);
    exit;
}

$pdo = getResDB();
$conciergeId = $_SESSION['concierge_id'];

$stmt = $pdo->prepare("
    UPDATE concierges
    SET bank_name = ?, bank_clabe = ?, bank_account = ?, bank_holder = ?
    WHERE id = ? AND active = 1
");
$stmt->execute([$bankName, $bankClabe, $bankAccount ?: null, $bankHolder, $conciergeId]);

echo json_encode(['success' => true, 'message' => 'Datos bancarios guardados correctamente']);
```

- [ ] **Verificar el endpoint con curl o Postman**

Con sesión activa de concierge (cookie), POST:
```json
{
  "bank_name": "BBVA",
  "bank_clabe": "012345678901234567",
  "bank_account": "1234567890",
  "bank_holder": "Juan Pérez"
}
```
Respuesta esperada: `{"success":true,"message":"Datos bancarios guardados correctamente"}`

Verificar con CLABE inválida (17 dígitos): respuesta `{"error":"La CLABE debe tener exactamente 18 dígitos numéricos"}`

- [ ] **Commit**

```bash
git add api/bank-data.php
git commit -m "feat: add bank data API endpoint for concierge portal"
```

---

## Task 3: API — acción mark_commission_paid en admin.php

**Archivo:** `api/admin.php` (modificar)

- [ ] **Leer el archivo para ubicar dónde agregar la nueva acción**

Abrir `api/admin.php`. Buscar el bloque que maneja `update_concierge` (alrededor de línea 124). Agregar el bloque `mark_commission_paid` inmediatamente antes del cierre del bloque de acciones POST (antes de la línea que hace el catch o el final del if POST).

- [ ] **Agregar la acción en `api/admin.php`**

Buscar el último bloque de acción POST (por ejemplo, después de `update_hotel` o del último `if ($action === ...)`) y agregar:

```php
        // Marcar comisión como pagada
        if ($action === 'mark_commission_paid') {
            $commissionId = intval($data['commission_id'] ?? 0);
            if (!$commissionId) {
                jsonResponse(['error' => 'commission_id requerido'], 400);
            }

            // Verificar que la comisión existe, está pendiente y el concierge tiene CLABE
            $stmt = $pdo->prepare("
                SELECT cm.id, cm.status, c.bank_clabe
                FROM commissions cm
                JOIN concierges c ON c.id = cm.concierge_id
                WHERE cm.id = ?
            ");
            $stmt->execute([$commissionId]);
            $commission = $stmt->fetch();

            if (!$commission) {
                jsonResponse(['error' => 'Comisión no encontrada'], 404);
            }
            if ($commission['status'] !== 'pending') {
                jsonResponse(['error' => 'Esta comisión ya fue pagada'], 409);
            }
            if (empty($commission['bank_clabe'])) {
                jsonResponse(['error' => 'El concierge no tiene CLABE registrada'], 422);
            }

            $userId = $_SESSION['res_user_id'];
            $stmt = $pdo->prepare("
                UPDATE commissions
                SET status = 'paid', paid_at = NOW(), paid_by_user_id = ?
                WHERE id = ? AND status = 'pending'
            ");
            $stmt->execute([$userId, $commissionId]);

            jsonResponse(['success' => true, 'message' => 'Comisión marcada como pagada']);
        }
```

- [ ] **Verificar: marcar una comisión pendiente existente**

POST a `api/admin.php` con sesión admin:
```json
{ "action": "mark_commission_paid", "commission_id": 1 }
```
Respuesta esperada: `{"success":true,"message":"Comisión marcada como pagada"}`

Repetir el mismo POST: respuesta esperada `{"error":"Esta comisión ya fue pagada"}`

- [ ] **Commit**

```bash
git add api/admin.php
git commit -m "feat: add mark_commission_paid action to admin API"
```

---

## Task 4: Trigger de comisión en api/reservations.php

**Archivo:** `api/reservations.php` (modificar)

El bloque a reemplazar está en el `case 'completed':` alrededor de líneas 759-776. El código existente hace un `UPDATE concierges SET commission_earned = commission_earned + ?`. Vamos a reemplazar ese bloque con un `INSERT IGNORE` en `commissions` y mantener también la actualización de `commission_earned` para no romper el dashboard actual.

- [ ] **Reemplazar el bloque de comisión en el case `completed`**

Encontrar este bloque exacto (líneas ~759-776):

```php
                    // --- Calcular comision para concierge/afiliado ---
                    if ($current['concierge_id']) {
                        $stmtC = $pdo->prepare("SELECT commission_type, commission_value FROM concierges WHERE id = ? AND active = 1");
                        $stmtC->execute([$current['concierge_id']]);
                        $partner = $stmtC->fetch();
                        if ($partner && floatval($partner['commission_value']) > 0) {
                            $commValue = floatval($partner['commission_value']);
                            // For fixed: add the fixed amount per reservation
                            // For percentage: we'd need the bill amount which we don't track yet,
                            // so for now we just count completed reservations (commission calculated at payout time)
                            if ($partner['commission_type'] === 'fixed') {
                                $pdo->prepare("UPDATE concierges SET commission_earned = commission_earned + ? WHERE id = ?")
                                    ->execute([$commValue, $current['concierge_id']]);
                            }
                            // For percentage: increment a counter; actual $ calculated when bill amount is known
                            // (For now, also increment by a flat tracking amount of 0 to avoid confusion)
                        }
                    }
```

Reemplazar con:

```php
                    // --- Registrar comision en tabla commissions ---
                    if ($current['concierge_id']) {
                        $stmtC = $pdo->prepare("SELECT commission_type, commission_value FROM concierges WHERE id = ? AND active = 1");
                        $stmtC->execute([$current['concierge_id']]);
                        $partner = $stmtC->fetch();
                        if ($partner && floatval($partner['commission_value']) > 0) {
                            $commType   = $partner['commission_type'];
                            $commRate   = floatval($partner['commission_value']);
                            $commAmount = ($commType === 'fixed') ? $commRate : null;
                            $resDt      = $current['reservation_date'] . ' ' . $current['reservation_time'];
                            $dueBy      = date('Y-m-d H:i:s', strtotime($resDt . ' +24 hours'));

                            $pdo->prepare("
                                INSERT IGNORE INTO commissions
                                    (concierge_id, reservation_id, commission_type, commission_rate, commission_amount, due_by)
                                VALUES (?, ?, ?, ?, ?, ?)
                            ")->execute([
                                $current['concierge_id'], $reservationId,
                                $commType, $commRate, $commAmount, $dueBy
                            ]);

                            // Mantener commission_earned para compatibilidad con dashboard actual
                            if ($commType === 'fixed') {
                                $pdo->prepare("UPDATE concierges SET commission_earned = commission_earned + ? WHERE id = ?")
                                    ->execute([$commRate, $current['concierge_id']]);
                            }
                        }
                    }
```

Nota: `$reservationId` es la variable que contiene el ID de la reserva que se está actualizando. Verificar que esta variable existe en ese scope; si no, usar `$current['id']` (que viene del fetch inicial de la reserva).

- [ ] **Verificar: marcar una reserva con concierge como completed**

Desde el admin de reservas, cambiar el status de una reserva que tenga concierge asignado a `completed`. Luego verificar en DB:
```sql
SELECT * FROM commissions WHERE reservation_id = <id>;
```
Debe aparecer una fila con `status = 'pending'` y `due_by = reservation_datetime + 24h`.

Marcar la misma reserva como completed de nuevo: no debe crear fila duplicada (INSERT IGNORE).

- [ ] **Commit**

```bash
git add api/reservations.php
git commit -m "feat: insert commission record on reservation completed"
```

---

## Task 5: Portal — formulario de datos bancarios

**Archivo:** `portal/bank-data.php` (nuevo)

- [ ] **Crear el archivo**

```php
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
        <div class="bg-yellow-900/30 border border-yellow-600/40 rounded-xl px-5 py-4 mb-6 flex items-start gap-3">
            <svg class="w-5 h-5 text-yellow-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            <div>
                <p class="text-yellow-300 font-semibold text-sm">Sin datos bancarios</p>
                <p class="text-yellow-400/80 text-xs mt-0.5">Sin tu CLABE no podremos procesar tu comisión. Regístrala a continuación.</p>
            </div>
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
        clabeInput.addEventListener('input', () => {
            const digits = clabeInput.value.replace(/\D/g, '');
            clabeInput.value = digits.slice(0, 18);
            clabeLen.textContent = clabeInput.value.length;
            clabeLen.className = clabeInput.value.length === 18 ? 'text-green-400' : 'text-slate-600';
        });

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
                    // Ocultar banner de advertencia si existía
                    const warn = document.querySelector('.bg-yellow-900\\/30');
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
```

- [ ] **Verificar en browser**

Navegar a `https://somossinergia.com/CenacoloReserve/portal/bank-data.php` con sesión de concierge.

Verificar:
1. Concierge sin datos ve el banner amarillo de advertencia
2. El contador de dígitos CLABE actualiza en tiempo real
3. Ingresar CLABE con 17 dígitos → API devuelve error de validación
4. Ingresar datos válidos → mensaje de éxito en verde

- [ ] **Commit**

```bash
git add portal/bank-data.php
git commit -m "feat: add bank data form to concierge portal"
```

---

## Task 6: Portal — historial de comisiones del concierge

**Archivo:** `portal/commissions.php` (nuevo)

- [ ] **Crear el archivo**

```php
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
$totals = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN status = 'pending' THEN commission_amount ELSE 0 END), 0) AS pending_total,
        COALESCE(SUM(CASE WHEN status = 'paid'    THEN commission_amount ELSE 0 END), 0) AS paid_total
    FROM commissions WHERE concierge_id = ?
");
$totals->execute([$conciergeId]);
$totals = $totals->fetch();

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
                <p class="text-3xl font-bold text-yellow-400">
                    <?= $totals['pending_total'] !== null ? '$' . number_format($totals['pending_total'], 2) . ' MXN' : '—' ?>
                </p>
            </div>
            <div class="bg-dark-900 rounded-xl border border-slate-700 p-5">
                <p class="text-xs text-slate-400 uppercase tracking-wider mb-1">Total cobrado</p>
                <p class="text-3xl font-bold text-green-400">
                    $<?= number_format($totals['paid_total'], 2) ?> MXN
                </p>
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
```

- [ ] **Verificar en browser**

Navegar a `portal/commissions.php` con sesión de concierge. Verificar:
1. Totales de pendiente y pagado se muestran correctamente
2. Filtros de estado funcionan (cambian la URL y filtran filas)
3. Comisiones con `commission_amount = NULL` muestran "Por liquidar" en cursiva

- [ ] **Commit**

```bash
git add portal/commissions.php
git commit -m "feat: add concierge commissions history page"
```

---

## Task 7: Admin — panel de pagos pendientes

**Archivo:** `admin/commissions.php` (nuevo)

- [ ] **Crear el archivo**

```php
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

$history = $pdo->prepare("
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
$history->execute($histParams);
$history = $history->fetchAll();

$now = time();

function urgencyClass(string $dueBy, int $now): string {
    $diff = strtotime($dueBy) - $now;
    if ($diff < 0)       return 'red';
    if ($diff < 7200)    return 'red';    // < 2h
    if ($diff < 28800)   return 'yellow'; // < 8h
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
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider w-32">Urgencia</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Concierge</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Reserva</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Monto</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Banco / CLABE</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-slate-400 uppercase tracking-wider">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                <?php foreach ($pending as $c):
                    $hasBank = !empty($c['bank_clabe']);
                    $urg     = $hasBank ? urgencyClass($c['due_by'], $now) : 'gray';
                    $urgBg   = ['red' => 'bg-red-500/20 text-red-300', 'yellow' => 'bg-yellow-500/20 text-yellow-300', 'green' => 'bg-green-500/20 text-green-300', 'gray' => 'bg-slate-700/50 text-slate-500'];
                    $rowBg   = $hasBank ? '' : 'opacity-60';
                ?>
                <tr class="hover:bg-dark-800/40 <?= $rowBg ?>">
                    <td class="px-4 py-3">
                        <?php if ($hasBank): ?>
                        <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg text-xs font-semibold <?= $urgBg[$urg] ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?= ['red'=>'bg-red-400','yellow'=>'bg-yellow-400','green'=>'bg-green-400'][$urg] ?>"></span>
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
                        <button onclick="markPaid(<?= $c['id'] ?>, this)"
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
                    <option value="<?= $cl['id'] ?>" <?= $filterConcierge === $cl['id'] ? 'selected' : '' ?>><?= resSanitize($cl['name']) ?></option>
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
                    <td class="px-4 py-3 text-slate-400 text-xs"><?= $h['paid_at'] ? date('d/m/Y H:i', strtotime($h['paid_at'])) : '—' ?></td>
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
                    // Remover fila de la tabla
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
```

- [ ] **Verificar en browser**

Navegar a `admin/commissions.php` con sesión admin. Verificar:
1. Tab Pendientes muestra comisiones ordenadas por urgencia
2. Indicadores de color son correctos según tiempo restante (rojo/amarillo/verde)
3. Concierges sin CLABE aparecen en gris sin botón de acción
4. Clic en "Marcar pagado" → modal de confirmación aparece
5. Confirmar → fila desaparece con animación
6. Tab Historial muestra comisiones pagadas con filtros funcionales

- [ ] **Commit**

```bash
git add admin/commissions.php
git commit -m "feat: add admin commissions panel with urgency indicators"
```

---

## Task 8: Actualizar páginas existentes

### 8a. `portal/index.php` — banner sin datos bancarios + link comisiones + total pendiente

- [ ] **Leer la sección del nav de portal/index.php**

Abrir `portal/index.php`. Localizar:
1. El `<nav>` superior — agregar link "Mis Comisiones"
2. La sección de stats cards — reemplazar `commission_earned` con total desde `commissions`
3. Antes del bloque `<!-- Stats Cards -->` — agregar banner si no tiene CLABE

- [ ] **Modificar el query de comisiones en portal/index.php**

En la sección PHP inicial (antes del HTML), agregar después de donde se obtiene `$commissionEarned`:

```php
// Total pendiente desde tabla commissions (reemplaza commission_earned impreciso)
$stmtPending = $pdo->prepare("
    SELECT COALESCE(SUM(commission_amount), 0)
    FROM commissions
    WHERE concierge_id = ? AND status = 'pending'
");
$stmtPending->execute([$conciergeId]);
$pendingCommissions = floatval($stmtPending->fetchColumn());

$stmtPaid = $pdo->prepare("
    SELECT COALESCE(SUM(commission_amount), 0)
    FROM commissions
    WHERE concierge_id = ? AND status = 'paid'
");
$stmtPaid->execute([$conciergeId]);
$paidCommissions = floatval($stmtPaid->fetchColumn());

$hasBankData = !empty($concierge['bank_clabe']);
```

- [ ] **Agregar link en nav del portal**

En el nav de `portal/index.php`, dentro del bloque de links (si existe) o junto al botón "Nueva Reserva", agregar:

```html
<a href="<?= resUrl('/portal/commissions.php') ?>"
   class="text-dark-400 hover:text-gold-400 text-sm transition-colors hidden sm:block">
    Mis Comisiones
</a>
```

- [ ] **Agregar banner sin datos bancarios**

Inmediatamente antes del bloque `<!-- Stats Cards -->`, agregar:

```php
<?php if (!$hasBankData): ?>
<div class="bg-yellow-900/30 border border-yellow-600/40 rounded-xl px-5 py-4 mb-6 flex items-center justify-between fade-in">
    <div class="flex items-center gap-3">
        <svg class="w-5 h-5 text-yellow-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        <p class="text-yellow-300 text-sm">Sin datos bancarios — no podremos procesar tu comisión hasta que los registres.</p>
    </div>
    <a href="<?= resUrl('/portal/bank-data.php') ?>"
       class="shrink-0 ml-4 px-4 py-2 bg-yellow-500/20 border border-yellow-500/40 text-yellow-300 rounded-lg text-xs font-semibold hover:bg-yellow-500/30 transition-colors">
        Registrar ahora
    </a>
</div>
<?php endif; ?>
```

- [ ] **Actualizar tarjeta de comisiones en portal/index.php**

Localizar la tarjeta que muestra `$commissionEarned`. Reemplazar su contenido para mostrar el total pendiente desde `commissions`:

```php
// En la tarjeta "Acumulado" del bloque Commission Card, cambiar:
// <p class="text-2xl font-bold text-green-400">$<?= number_format($commissionEarned, 2) ?></p>
// <p class="text-xs text-dark-500">comision ganada</p>
// Por:
?>
<p class="text-2xl font-bold text-yellow-400">$<?= number_format($pendingCommissions, 2) ?></p>
<p class="text-xs text-dark-500">pendiente de cobro</p>
<?php
// Y agregar debajo:
?>
<div class="flex justify-between text-xs mt-2 pt-2 border-t border-dark-700">
    <span class="text-dark-400">Total cobrado:</span>
    <span class="text-green-400 font-semibold">$<?= number_format($paidCommissions, 2) ?></span>
</div>
<a href="<?= resUrl('/portal/commissions.php') ?>" class="block text-center text-xs text-gold-400 hover:text-gold-300 mt-3">
    Ver historial completo &rarr;
</a>
```

- [ ] **Verificar en browser**

Navegar a `portal/index.php` con concierge sin datos bancarios → debe aparecer el banner amarillo.
Navegar con concierge con datos bancarios → no debe aparecer el banner.
Verificar que los totales de comisión son correctos.

### 8b. `admin/concierges.php` — columna datos bancarios + link al panel

- [ ] **Agregar link al panel de comisiones en el nav del admin**

En `admin/concierges.php`, en la zona de navegación o encabezado de la página, agregar:

```html
<a href="<?= resUrl('/admin/commissions.php') ?>"
   class="inline-flex items-center px-4 py-2 bg-dark-800 hover:bg-dark-700 border border-dark-600 text-slate-300 rounded-lg text-sm transition-colors">
    💳 Panel de Pagos
</a>
```

- [ ] **Agregar columna "Banco" en la tabla de concierges**

Localizar el `<thead>` de la tabla de concierges. Agregar `<th>` con "Banco":

```html
<th class="px-4 py-3 text-left text-xs font-semibold text-dark-400 uppercase tracking-wider">Banco</th>
```

En las filas `<tbody>`, agregar la celda correspondiente:

```php
<td class="px-4 py-3">
    <?php if (!empty($c['bank_clabe'])): ?>
        <span class="inline-flex items-center gap-1 text-xs text-green-400">
            <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>
            <?= resSanitize($c['bank_name'] ?? 'Registrado') ?>
        </span>
    <?php else: ?>
        <span class="inline-flex items-center gap-1 text-xs text-slate-500">
            <span class="w-1.5 h-1.5 rounded-full bg-slate-600"></span>
            Sin datos
        </span>
    <?php endif; ?>
</td>
```

Nota: la query que carga los concierges en `admin/concierges.php` ya usa `SELECT *`, por lo que `bank_name` y `bank_clabe` estarán disponibles después de correr la migración.

- [ ] **Verificar en browser**

Navegar a `admin/concierges.php`. Verificar:
1. Columna "Banco" muestra punto verde + nombre del banco para los que tienen CLABE
2. Muestra punto gris + "Sin datos" para los que no
3. Link "Panel de Pagos" navega a `admin/commissions.php`

- [ ] **Commit final de Task 8**

```bash
git add portal/index.php admin/concierges.php
git commit -m "feat: add bank data indicators and commissions links to existing pages"
```

---

## Verificación final integral

- [ ] **Flujo completo de extremo a extremo**

1. Login como concierge sin datos bancarios → ver banner en dashboard
2. Ir a "Datos Bancarios" → ingresar CLABE con 17 dígitos → ver error
3. Ingresar CLABE válida de 18 dígitos + datos completos → guardar → ver éxito
4. Volver al dashboard → banner desaparecido
5. Desde admin: cambiar status de una reserva con ese concierge a `completed`
6. Verificar en DB: `SELECT * FROM commissions WHERE reservation_id = X`
7. Login admin → `admin/commissions.php` → ver la comisión en Pendientes con indicador de urgencia
8. Clic "Marcar pagado" → confirmar → fila desaparece
9. Tab Historial → ver la comisión registrada con fecha y quién la pagó
10. Login concierge → `portal/commissions.php` → ver comisión en estado Pagada

- [ ] **Eliminar el archivo de migración del servidor**

```bash
# En Hostinger File Manager o via SSH:
rm migrate_commissions.php
```

```bash
git rm migrate_commissions.php
git commit -m "chore: remove one-time migration script after execution"
```
