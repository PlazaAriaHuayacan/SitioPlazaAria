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
