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
