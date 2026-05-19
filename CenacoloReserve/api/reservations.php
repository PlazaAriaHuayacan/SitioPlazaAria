<?php
/**
 * Cenacolo Reservas - API de Reservaciones v1.0
 * REST API completa para CRUD de reservas
 *
 * GET    - Listar/buscar reservas, buscar clientes, obtener stats
 * POST   - Crear nueva reserva
 * PUT    - Actualizar reserva (estado, mesa, datos)
 * DELETE - Cancelar reserva (soft delete via status)
 */

require_once __DIR__ . '/../includes/config.php';

// === CORS Headers ===
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
header('Access-Control-Max-Age: 86400');

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// === Main Router ===
try {
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            handleGet();
            break;
        case 'POST':
            handlePost();
            break;
        case 'PUT':
            handlePut();
            break;
        case 'DELETE':
            handleDelete();
            break;
        default:
            jsonResponse(['error' => 'Metodo no permitido'], 405);
    }
} catch (PDOException $e) {
    jsonResponse([
        'error' => 'Error de base de datos',
        'message' => $e->getMessage()
    ], 500);
} catch (Exception $e) {
    jsonResponse([
        'error' => 'Error interno',
        'message' => $e->getMessage()
    ], 500);
}

// =====================================================
// GET - Listar reservas, buscar clientes, stats
// =====================================================
function handleGet() {
    // Single reservation by ID (for customer modal)
    if (isset($_GET['id']) && !isset($_GET['action'])) {
        handleGetSingle();
        return;
    }

    $action = $_GET['action'] ?? 'list';

    switch ($action) {
        case 'search_customer':
            handleSearchCustomer();
            break;
        case 'stats':
            handleStats();
            break;
        case 'list':
        default:
            handleList();
            break;
    }
}

function handleGetSingle() {
    if (!isResLoggedIn() && !isConciergeLoggedIn()) {
        jsonResponse(['error' => 'No autorizado'], 401);
    }

    $pdo = getResDB();
    $id = intval($_GET['id']);

    $stmt = $pdo->prepare("
        SELECT r.*, rest.name as restaurant_name,
               t.table_number, s.name as section_name,
               con.name as concierge_name, con.type as concierge_type,
               con.company_name as concierge_company,
               con.commission_type as concierge_comm_type,
               con.commission_value as concierge_comm_value,
               h.name as concierge_hotel
        FROM reservations r
        LEFT JOIN restaurants rest ON r.restaurant_id = rest.id
        LEFT JOIN tables t ON r.table_id = t.id
        LEFT JOIN restaurant_sections s ON t.section_id = s.id
        LEFT JOIN concierges con ON r.concierge_id = con.id
        LEFT JOIN hotels h ON con.hotel_id = h.id
        WHERE r.id = ?
    ");
    $stmt->execute([$id]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        jsonResponse(['error' => 'Reserva no encontrada'], 404);
    }

    // Format commission info
    $commInfo = '';
    if ($reservation['concierge_comm_value'] && floatval($reservation['concierge_comm_value']) > 0) {
        $commInfo = $reservation['concierge_comm_type'] === 'fixed'
            ? '$' . number_format(floatval($reservation['concierge_comm_value']), 0) . ' MXN/res'
            : number_format(floatval($reservation['concierge_comm_value']), 1) . '% consumo';
    }
    $reservation['concierge_commission'] = $commInfo;

    jsonResponse($reservation);
}

/**
 * GET ?action=list - Listar/buscar reservas con filtros
 * Params: date, restaurant_id, status, channel, search, page, limit
 */
function handleList() {
    if (!isResLoggedIn() && !isConciergeLoggedIn()) {
        jsonResponse(['error' => 'No autorizado'], 401);
    }

    $pdo = getResDB();

    // Parametros de filtro
    $date         = $_GET['date'] ?? null;
    $restaurantId = $_GET['restaurant_id'] ?? null;
    $status       = $_GET['status'] ?? null;
    $channel      = $_GET['channel'] ?? null;
    $search       = $_GET['search'] ?? null;
    $page         = max(1, intval($_GET['page'] ?? 1));
    $limit        = min(100, max(1, intval($_GET['limit'] ?? 50)));
    $offset       = ($page - 1) * $limit;

    // Construir query dinamicamente
    $where  = [];
    $params = [];

    if ($date) {
        $where[]  = 'r.reservation_date = ?';
        $params[] = $date;
    }

    if ($restaurantId && $restaurantId !== 'all') {
        $where[]  = 'r.restaurant_id = ?';
        $params[] = intval($restaurantId);
    }

    if ($status) {
        $where[]  = 'r.status = ?';
        $params[] = $status;
    }

    if ($channel) {
        $where[]  = 'r.channel = ?';
        $params[] = $channel;
    }

    if ($search) {
        $where[]  = '(r.guest_name LIKE ? OR r.guest_phone LIKE ? OR r.guest_email LIKE ? OR r.confirmation_code LIKE ?)';
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // Si es concierge, solo ver sus reservas
    if (isConciergeLoggedIn() && !isResLoggedIn()) {
        $where[]  = 'r.concierge_id = ?';
        $params[] = $_SESSION['concierge_id'];
    }

    $whereClause = '';
    if (!empty($where)) {
        $whereClause = 'WHERE ' . implode(' AND ', $where);
    }

    // Count total para paginacion
    $countSql = "SELECT COUNT(*) as total FROM reservations r {$whereClause}";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];

    // Query principal
    $sql = "
        SELECT r.*,
               rest.name AS restaurant_name,
               rest.slug AS restaurant_slug,
               t.table_number,
               t.name AS table_name,
               sec.name AS section_name,
               c.first_name AS customer_first_name,
               c.last_name AS customer_last_name,
               c.vip_level,
               c.total_visits AS customer_visits,
               c.total_no_shows AS customer_no_shows,
               c.allergies AS customer_allergies,
               con.name AS concierge_name,
               h.name AS hotel_name
        FROM reservations r
        LEFT JOIN restaurants rest ON r.restaurant_id = rest.id
        LEFT JOIN tables t ON r.table_id = t.id
        LEFT JOIN restaurant_sections sec ON t.section_id = sec.id
        LEFT JOIN customers c ON r.customer_id = c.id
        LEFT JOIN concierges con ON r.concierge_id = con.id
        LEFT JOIN hotels h ON con.hotel_id = h.id
        {$whereClause}
        ORDER BY r.reservation_date ASC, r.reservation_time ASC
        LIMIT ? OFFSET ?
    ";

    $queryParams = array_merge($params, [$limit, $offset]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $reservations = $stmt->fetchAll();

    jsonResponse([
        'success'      => true,
        'data'         => $reservations,
        'pagination'   => [
            'total'        => intval($total),
            'page'         => $page,
            'limit'        => $limit,
            'total_pages'  => ceil($total / $limit)
        ]
    ]);
}

/**
 * GET ?action=search_customer&q=term - Busqueda rapida de clientes
 * Retorna top 10 matches con datos de visita
 */
function handleSearchCustomer() {
    if (!isResLoggedIn()) {
        jsonResponse(['error' => 'No autorizado'], 401);
    }

    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) {
        jsonResponse(['error' => 'El termino de busqueda debe tener al menos 2 caracteres'], 400);
    }

    $pdo = getResDB();
    $searchTerm = '%' . $q . '%';

    $stmt = $pdo->prepare("
        SELECT c.id, c.first_name, c.last_name, c.email, c.phone,
               c.vip_level, c.total_visits, c.total_no_shows,
               c.last_visit_date, c.allergies, c.dietary_preferences,
               c.preferred_section, c.preferred_table, c.notes
        FROM customers c
        WHERE c.first_name LIKE ?
           OR c.last_name LIKE ?
           OR CONCAT(c.first_name, ' ', c.last_name) LIKE ?
           OR c.phone LIKE ?
           OR c.email LIKE ?
        ORDER BY c.total_visits DESC, c.last_name ASC
        LIMIT 10
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $customers = $stmt->fetchAll();

    jsonResponse([
        'success' => true,
        'data'    => $customers
    ]);
}

/**
 * GET ?action=stats&date=YYYY-MM-DD - KPIs del dia
 * Opcionalmente acepta restaurant_id
 */
function handleStats() {
    if (!isResLoggedIn()) {
        jsonResponse(['error' => 'No autorizado'], 401);
    }

    $date = $_GET['date'] ?? date('Y-m-d');
    $restaurantId = $_GET['restaurant_id'] ?? null;

    $pdo = getResDB();

    // Filtro de restaurante
    $restaurantFilter = '';
    $params = [$date];
    if ($restaurantId && $restaurantId !== 'all') {
        $restaurantFilter = ' AND r.restaurant_id = ?';
        $params[] = intval($restaurantId);
    }

    // KPIs principales
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_reservations,
            COALESCE(SUM(r.party_size), 0) AS total_covers,
            COALESCE(SUM(CASE WHEN r.status = 'confirmed' THEN 1 ELSE 0 END), 0) AS confirmed,
            COALESCE(SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END), 0) AS pending,
            COALESCE(SUM(CASE WHEN r.status = 'seated' THEN 1 ELSE 0 END), 0) AS seated,
            COALESCE(SUM(CASE WHEN r.status = 'completed' THEN 1 ELSE 0 END), 0) AS completed,
            COALESCE(SUM(CASE WHEN r.status = 'no_show' THEN 1 ELSE 0 END), 0) AS no_shows,
            COALESCE(SUM(CASE WHEN r.status = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled,
            COALESCE(SUM(CASE WHEN r.status = 'waitlist' THEN 1 ELSE 0 END), 0) AS waitlist,
            COALESCE(SUM(CASE WHEN r.channel = 'walkin' THEN 1 ELSE 0 END), 0) AS walkins,
            COALESCE(AVG(r.party_size), 0) AS avg_party_size
        FROM reservations r
        WHERE r.reservation_date = ? {$restaurantFilter}
    ");
    $stmt->execute($params);
    $kpis = $stmt->fetch();

    // Reservas por hora
    $stmt = $pdo->prepare("
        SELECT HOUR(r.reservation_time) AS hour,
               COUNT(*) AS count,
               SUM(r.party_size) AS covers
        FROM reservations r
        WHERE r.reservation_date = ?
          AND r.status NOT IN ('cancelled')
          {$restaurantFilter}
        GROUP BY HOUR(r.reservation_time)
        ORDER BY hour
    ");
    $stmt->execute($params);
    $hourlyData = $stmt->fetchAll();

    // Reservas por canal
    $stmt = $pdo->prepare("
        SELECT r.channel, COUNT(*) AS count
        FROM reservations r
        WHERE r.reservation_date = ?
          AND r.status NOT IN ('cancelled')
          {$restaurantFilter}
        GROUP BY r.channel
        ORDER BY count DESC
    ");
    $stmt->execute($params);
    $channelData = $stmt->fetchAll();

    // Reservas por seccion
    $stmt = $pdo->prepare("
        SELECT COALESCE(sec.name, 'Sin asignar') AS section,
               COUNT(*) AS count
        FROM reservations r
        LEFT JOIN tables t ON r.table_id = t.id
        LEFT JOIN restaurant_sections sec ON t.section_id = sec.id
        WHERE r.reservation_date = ?
          AND r.status NOT IN ('cancelled')
          {$restaurantFilter}
        GROUP BY sec.name
        ORDER BY count DESC
    ");
    $stmt->execute($params);
    $sectionData = $stmt->fetchAll();

    // Preview manana
    $tomorrow = date('Y-m-d', strtotime($date . ' +1 day'));
    $tomorrowParams = [$tomorrow];
    if ($restaurantId && $restaurantId !== 'all') {
        $tomorrowParams[] = intval($restaurantId);
    }
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS count,
               COALESCE(SUM(party_size), 0) AS covers
        FROM reservations r
        WHERE r.reservation_date = ?
          AND r.status NOT IN ('cancelled')
          {$restaurantFilter}
    ");
    $stmt->execute($tomorrowParams);
    $tomorrowPreview = $stmt->fetch();

    // Hora pico
    $peakHour = null;
    $peakCount = 0;
    foreach ($hourlyData as $h) {
        if ($h['count'] > $peakCount) {
            $peakCount = $h['count'];
            $peakHour  = $h['hour'] . ':00';
        }
    }

    jsonResponse([
        'success' => true,
        'data'    => [
            'date'             => $date,
            'kpis'             => $kpis,
            'hourly'           => $hourlyData,
            'by_channel'       => $channelData,
            'by_section'       => $sectionData,
            'peak_hour'        => $peakHour,
            'tomorrow_preview' => $tomorrowPreview
        ]
    ]);
}

// =====================================================
// POST - Crear nueva reserva
// =====================================================
function handlePost() {
    // Allow public booking from book.php (no auth required)
    $isPublicBooking = isset($_GET['public']) && $_GET['public'] == '1';

    if (!$isPublicBooking && !isResLoggedIn() && !isConciergeLoggedIn()) {
        jsonResponse(['error' => 'No autorizado'], 401);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        jsonResponse(['error' => 'JSON invalido o vacio'], 400);
    }

    // Validaciones requeridas
    $required = ['restaurant_id', 'guest_name', 'party_size', 'reservation_date', 'reservation_time'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            jsonResponse(['error' => "El campo '{$field}' es requerido"], 400);
        }
    }

    $pdo = getResDB();
    $pdo->beginTransaction();

    try {
        // --- Buscar o crear cliente ---
        $customerId = null;
        $guestPhone = trim($input['guest_phone'] ?? '');
        $guestEmail = trim($input['guest_email'] ?? '');
        $guestName  = trim($input['guest_name']);

        // Si ya viene un customer_id válido (preselección desde formulario), usarlo directamente
        if (!empty($input['customer_id'])) {
            $customerId = intval($input['customer_id']);
            // Verificar que existe
            $chk = $pdo->prepare("SELECT id FROM customers WHERE id = ?");
            $chk->execute([$customerId]);
            if (!$chk->fetch()) {
                $customerId = null; // ID inválido, intentar crear/buscar
            }
        }

        if (!$customerId && ($guestPhone || $guestEmail)) {
            $customerId = findOrCreateCustomer($pdo, $guestName, $guestPhone, $guestEmail, $input);
        }

        // --- Calcular end_time basado en duracion del restaurante ---
        $restaurantId = intval($input['restaurant_id']);
        $stmt = $pdo->prepare("SELECT default_dining_duration FROM restaurants WHERE id = ? AND active = 1");
        $stmt->execute([$restaurantId]);
        $restaurant = $stmt->fetch();

        if (!$restaurant) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Restaurante no encontrado o inactivo'], 404);
        }

        // --- Verificar disponibilidad (bloqueos) ---
        $blockCheck = $pdo->prepare("
            SELECT id, start_time, end_time, reason FROM restaurant_blocked_periods
            WHERE restaurant_id = ? AND block_date = ?
        ");
        $blockCheck->execute([$restaurantId, $input['reservation_date']]);
        $blocks = $blockCheck->fetchAll();
        if ($blocks) {
            $resTime = $input['reservation_time'];
            foreach ($blocks as $block) {
                // Full day block (no start/end time)
                if (!$block['start_time'] && !$block['end_time']) {
                    $pdo->rollBack();
                    $reason = $block['reason'] ? " ({$block['reason']})" : '';
                    jsonResponse(['error' => "El restaurante no está disponible en esta fecha{$reason}"], 409);
                }
                // Partial block - check if reservation time falls within blocked range
                if ($block['start_time'] && $block['end_time']) {
                    if ($resTime >= $block['start_time'] && $resTime < $block['end_time']) {
                        $pdo->rollBack();
                        $reason = $block['reason'] ? " ({$block['reason']})" : '';
                        jsonResponse(['error' => "El horario seleccionado no está disponible{$reason}"], 409);
                    }
                }
            }
        }

        // --- Verificar capacidad por turno ---
        $capacityWarning = null;
        $capacity = checkCapacity($pdo, $restaurantId, $input['reservation_date'], $input['reservation_time'], intval($input['party_size']));

        if ($capacity['shift_name'] && !$capacity['available']) {
            $capMsg = "Capacidad excedida para {$capacity['shift_name']}: {$capacity['current_covers']}/{$capacity['max_covers']} covers";

            // Hard block for external channels (public booking, concierge portal)
            if ($isPublicBooking || isConciergeLoggedIn()) {
                $pdo->rollBack();
                jsonResponse([
                    'error' => "Sin disponibilidad para turno de {$capacity['shift_name']}. Capacidad: {$capacity['current_covers']}/{$capacity['max_covers']} covers"
                ], 409);
            }

            // Soft block for staff — allow with warning
            $capacityWarning = $capMsg;
        }

        $duration = $restaurant['default_dining_duration'] ?? 90;
        $reservationTime = $input['reservation_time'];
        $endTime = date('H:i:s', strtotime($reservationTime) + ($duration * 60));

        // --- Generar codigo de confirmacion ---
        $confirmationCode = generateReservationCode();

        // Verificar unicidad del codigo (reintento en caso de colision)
        $maxRetries = 5;
        for ($i = 0; $i < $maxRetries; $i++) {
            $stmt = $pdo->prepare("SELECT id FROM reservations WHERE confirmation_code = ?");
            $stmt->execute([$confirmationCode]);
            if (!$stmt->fetch()) {
                break;
            }
            $confirmationCode = generateReservationCode();
        }

        // --- Resolver referral code si viene ---
        $referralConciergeId = null;
        if (!empty($input['ref_code'])) {
            $referrer = resolveReferralCode($input['ref_code']);
            if ($referrer) {
                $referralConciergeId = $referrer['id'];
                // Auto-set channel based on referrer type
                if (empty($input['channel']) || $input['channel'] === 'website') {
                    $input['channel'] = ($referrer['type'] ?? 'concierge') === 'affiliate' ? 'affiliate' : 'concierge';
                }
                if (empty($input['concierge_id'])) {
                    $input['concierge_id'] = $referrer['id'];
                }
                if (empty($input['source_details'])) {
                    $input['source_details'] = 'Referral: ' . $input['ref_code'] . ' (' . ($referrer['company_name'] ?? $referrer['name']) . ')';
                }
            }
        }

        // --- Determinar quien crea ---
        $createdBy     = null;
        $createdByType = $isPublicBooking ? 'guest' : 'staff';
        if (isResLoggedIn()) {
            $createdBy     = $_SESSION['res_user_id'];
            $createdByType = 'staff';
        } elseif (isConciergeLoggedIn()) {
            $createdBy     = $_SESSION['concierge_id'];
            $createdByType = 'concierge';
        }

        // --- Canal ---
        $channel = $input['channel'] ?? ($isPublicBooking ? 'website' : 'manual');

        // --- Insertar reserva ---
        $stmt = $pdo->prepare("
            INSERT INTO reservations (
                confirmation_code, restaurant_id, customer_id,
                guest_name, guest_phone, guest_email,
                party_size, reservation_date, reservation_time, end_time,
                table_id, section_preference,
                status, channel,
                occasion, special_requests, allergies, internal_notes,
                concierge_id, source_details,
                created_by, created_by_type, confirmed_at
            ) VALUES (
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?,
                'confirmed', ?,
                ?, ?, ?, ?,
                ?, ?,
                ?, ?, NOW()
            )
        ");

        $stmt->execute([
            $confirmationCode,
            $restaurantId,
            $customerId,
            $guestName,
            $guestPhone ?: null,
            $guestEmail ?: null,
            intval($input['party_size']),
            $input['reservation_date'],
            $reservationTime,
            $endTime,
            !empty($input['table_id']) ? intval($input['table_id']) : null,
            $input['section_preference'] ?? null,
            $channel,
            $input['occasion'] ?? null,
            $input['special_requests'] ?? null,
            $input['allergies'] ?? null,
            $input['internal_notes'] ?? null,
            !empty($input['concierge_id']) ? intval($input['concierge_id']) : ($createdByType === 'concierge' ? $createdBy : null),
            $input['source_details'] ?? null,
            $createdBy,
            $createdByType
        ]);

        $reservationId = $pdo->lastInsertId();

        // --- Registrar en historial ---
        logReservationHistory($pdo, $reservationId, 'created', null, 'confirmed', $createdBy, $createdByType, 'Reserva creada');

        // --- Actualizar tabla de mesa si se asigno ---
        if (!empty($input['table_id'])) {
            $stmt = $pdo->prepare("UPDATE tables SET status = 'reserved' WHERE id = ? AND status = 'available'");
            $stmt->execute([intval($input['table_id'])]);
        }

        // --- Actualizar total de reservas del concierge ---
        if (!empty($input['concierge_id']) || $createdByType === 'concierge') {
            $conciergeId = !empty($input['concierge_id']) ? intval($input['concierge_id']) : $createdBy;
            $stmt = $pdo->prepare("UPDATE concierges SET total_reservations = total_reservations + 1 WHERE id = ?");
            $stmt->execute([$conciergeId]);
        }

        $pdo->commit();

        // Obtener la reserva completa para retornar
        $reservation = getReservationById($pdo, $reservationId);

        $response = [
            'success' => true,
            'message' => 'Reserva creada exitosamente',
            'data'    => $reservation
        ];
        if ($capacityWarning) {
            $response['warning'] = $capacityWarning;
        }
        jsonResponse($response, 201);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// =====================================================
// PUT - Actualizar reserva
// =====================================================
function handlePut() {
    if (!isResLoggedIn() && !isConciergeLoggedIn()) {
        jsonResponse(['error' => 'No autorizado'], 401);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['id'])) {
        jsonResponse(['error' => 'Se requiere el ID de la reserva'], 400);
    }

    $pdo = getResDB();
    $reservationId = intval($input['id']);

    // Obtener reserva actual
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
    $stmt->execute([$reservationId]);
    $current = $stmt->fetch();

    if (!$current) {
        jsonResponse(['error' => 'Reserva no encontrada'], 404);
    }

    // Determinar quien actualiza
    $changedBy     = null;
    $changedByType = 'staff';
    if (isResLoggedIn()) {
        $changedBy     = $_SESSION['res_user_id'];
        $changedByType = 'staff';
    } elseif (isConciergeLoggedIn()) {
        $changedBy     = $_SESSION['concierge_id'];
        $changedByType = 'concierge';
    }

    $pdo->beginTransaction();

    try {
        // Campos permitidos para actualizar
        $allowedFields = [
            'status', 'table_id', 'internal_notes',
            'guest_name', 'guest_phone', 'guest_email',
            'party_size', 'reservation_time',
            'section_preference', 'occasion', 'special_requests', 'allergies'
        ];

        $updates = [];
        $updateParams = [];
        $changes = [];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $input)) {
                $newValue = $input[$field];
                $oldValue = $current[$field];

                // Normalizar para comparacion
                if ($field === 'table_id' || $field === 'party_size') {
                    $newValue = $newValue !== null && $newValue !== '' ? intval($newValue) : null;
                }

                if ($newValue != $oldValue) {
                    $updates[] = "{$field} = ?";
                    $updateParams[] = $newValue;
                    $changes[$field] = ['old' => $oldValue, 'new' => $newValue];
                }
            }
        }

        if (empty($updates)) {
            $pdo->rollBack();
            jsonResponse(['success' => true, 'message' => 'Sin cambios']);
        }

        // --- Manejar cambios de estado especiales ---
        if (isset($changes['status'])) {
            $newStatus = $input['status'];
            $oldStatus = $current['status'];

            // Actualizar timestamps segun nuevo estado
            switch ($newStatus) {
                case 'confirmed':
                    $updates[] = 'confirmed_at = NOW()';
                    break;

                case 'seated':
                    $updates[] = 'seated_at = NOW()';
                    break;

                case 'completed':
                    $updates[] = 'completed_at = NOW()';
                    // Actualizar estadisticas del cliente
                    if ($current['customer_id']) {
                        $stmt = $pdo->prepare("
                            UPDATE customers
                            SET total_visits = total_visits + 1,
                                last_visit_date = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$current['reservation_date'], $current['customer_id']]);
                    }
                    // Liberar mesa
                    if ($current['table_id']) {
                        $stmt = $pdo->prepare("UPDATE tables SET status = 'available' WHERE id = ?");
                        $stmt->execute([$current['table_id']]);
                    }
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
                                $current['concierge_id'], $current['id'],
                                $commType, $commRate, $commAmount, $dueBy
                            ]);

                            // Mantener commission_earned para compatibilidad con dashboard actual
                            if ($commType === 'fixed') {
                                $pdo->prepare("UPDATE concierges SET commission_earned = commission_earned + ? WHERE id = ?")
                                    ->execute([$commRate, $current['concierge_id']]);
                            }
                        }
                    }
                    break;

                case 'no_show':
                    // Actualizar no-shows del cliente
                    if ($current['customer_id']) {
                        $stmt = $pdo->prepare("
                            UPDATE customers
                            SET total_no_shows = total_no_shows + 1
                            WHERE id = ?
                        ");
                        $stmt->execute([$current['customer_id']]);
                    }
                    // Liberar mesa
                    if ($current['table_id']) {
                        $stmt = $pdo->prepare("UPDATE tables SET status = 'available' WHERE id = ?");
                        $stmt->execute([$current['table_id']]);
                    }
                    break;

                case 'cancelled':
                    $updates[] = 'cancelled_at = NOW()';
                    if (!empty($input['cancellation_reason'])) {
                        $updates[] = 'cancellation_reason = ?';
                        $updateParams[] = $input['cancellation_reason'];
                    }
                    // Actualizar cancellations del cliente
                    if ($current['customer_id']) {
                        $stmt = $pdo->prepare("
                            UPDATE customers
                            SET total_cancellations = total_cancellations + 1
                            WHERE id = ?
                        ");
                        $stmt->execute([$current['customer_id']]);
                    }
                    // Liberar mesa
                    if ($current['table_id']) {
                        $stmt = $pdo->prepare("UPDATE tables SET status = 'available' WHERE id = ?");
                        $stmt->execute([$current['table_id']]);
                    }
                    break;
            }

            // Log de cambio de estado
            logReservationHistory(
                $pdo, $reservationId, 'status_changed',
                $oldStatus, $newStatus,
                $changedBy, $changedByType,
                "Estado cambiado de {$oldStatus} a {$newStatus}"
            );
        }

        // --- Manejar cambio de mesa ---
        if (isset($changes['table_id'])) {
            $oldTableId = $current['table_id'];
            $newTableId = $input['table_id'];

            // Liberar mesa anterior
            if ($oldTableId) {
                $stmt = $pdo->prepare("UPDATE tables SET status = 'available' WHERE id = ?");
                $stmt->execute([$oldTableId]);
            }
            // Reservar nueva mesa
            if ($newTableId) {
                $stmt = $pdo->prepare("UPDATE tables SET status = 'reserved' WHERE id = ?");
                $stmt->execute([intval($newTableId)]);
            }

            logReservationHistory(
                $pdo, $reservationId, 'table_assigned',
                $oldTableId, $newTableId,
                $changedBy, $changedByType,
                'Mesa reasignada'
            );
        }

        // --- Recalcular end_time si cambia reservation_time ---
        if (isset($changes['reservation_time'])) {
            $stmt = $pdo->prepare("SELECT default_dining_duration FROM restaurants WHERE id = ?");
            $stmt->execute([$current['restaurant_id']]);
            $rest = $stmt->fetch();
            $duration = $rest['default_dining_duration'] ?? 90;
            $newEndTime = date('H:i:s', strtotime($input['reservation_time']) + ($duration * 60));
            $updates[] = 'end_time = ?';
            $updateParams[] = $newEndTime;
        }

        // --- Log de cambios generales (que no sean status ni table) ---
        $generalChanges = array_diff_key($changes, array_flip(['status', 'table_id']));
        if (!empty($generalChanges)) {
            logReservationHistory(
                $pdo, $reservationId, 'updated',
                json_encode(array_map(function ($c) { return $c['old']; }, $generalChanges)),
                json_encode(array_map(function ($c) { return $c['new']; }, $generalChanges)),
                $changedBy, $changedByType,
                'Datos actualizados: ' . implode(', ', array_keys($generalChanges))
            );
        }

        // --- Ejecutar UPDATE ---
        $updateParams[] = $reservationId;
        $sql = "UPDATE reservations SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateParams);

        $pdo->commit();

        // Retornar reserva actualizada
        $reservation = getReservationById($pdo, $reservationId);

        jsonResponse([
            'success' => true,
            'message' => 'Reserva actualizada exitosamente',
            'data'    => $reservation,
            'changes' => $changes
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// =====================================================
// DELETE - Cancelar reserva (soft delete via status)
// =====================================================
function handleDelete() {
    if (!isResLoggedIn()) {
        jsonResponse(['error' => 'No autorizado'], 401);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $reservationId = intval($input['id'] ?? $_GET['id'] ?? 0);

    if (!$reservationId) {
        jsonResponse(['error' => 'Se requiere el ID de la reserva'], 400);
    }

    $pdo = getResDB();

    // Obtener reserva actual
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
    $stmt->execute([$reservationId]);
    $current = $stmt->fetch();

    if (!$current) {
        jsonResponse(['error' => 'Reserva no encontrada'], 404);
    }

    if ($current['status'] === 'cancelled') {
        jsonResponse(['error' => 'La reserva ya esta cancelada'], 400);
    }

    $pdo->beginTransaction();

    try {
        $changedBy = $_SESSION['res_user_id'];
        $cancellationReason = $input['cancellation_reason'] ?? null;

        // Cancelar reserva
        $stmt = $pdo->prepare("
            UPDATE reservations
            SET status = 'cancelled',
                cancelled_at = NOW(),
                cancellation_reason = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$cancellationReason, $reservationId]);

        // Liberar mesa si estaba asignada
        if ($current['table_id']) {
            $stmt = $pdo->prepare("UPDATE tables SET status = 'available' WHERE id = ?");
            $stmt->execute([$current['table_id']]);
        }

        // Actualizar cancellations del cliente
        if ($current['customer_id']) {
            $stmt = $pdo->prepare("
                UPDATE customers
                SET total_cancellations = total_cancellations + 1
                WHERE id = ?
            ");
            $stmt->execute([$current['customer_id']]);
        }

        // Registrar en historial
        logReservationHistory(
            $pdo, $reservationId, 'cancelled',
            $current['status'], 'cancelled',
            $changedBy, 'staff',
            $cancellationReason ? "Cancelada: {$cancellationReason}" : 'Reserva cancelada'
        );

        $pdo->commit();

        jsonResponse([
            'success' => true,
            'message' => 'Reserva cancelada exitosamente'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// =====================================================
// FUNCIONES AUXILIARES
// =====================================================

/**
 * Buscar cliente existente por telefono/email, o crear uno nuevo
 */
function findOrCreateCustomer(PDO $pdo, string $guestName, string $phone, string $email, array $input): int {
    // Normalizar vacíos a null para búsqueda correcta
    $phone = ($phone !== '') ? $phone : null;
    $email = ($email !== '') ? $email : null;

    // Buscar por telefono primero, luego por email
    $customer = null;

    if ($phone) {
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE phone = ? LIMIT 1");
        $stmt->execute([$phone]);
        $customer = $stmt->fetch();
    }

    if (!$customer && $email) {
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $customer = $stmt->fetch();
    }

    // También buscar por nombre si no encontramos por phone/email
    if (!$customer && $guestName) {
        $nameParts = explode(' ', $guestName, 2);
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE first_name = ? AND last_name = ? LIMIT 1");
        $stmt->execute([$nameParts[0], $nameParts[1] ?? '']);
        $customer = $stmt->fetch();
    }

    if ($customer) {
        // Actualizar datos del cliente existente si vienen nuevos
        $updateFields = [];
        $updateParams = [];

        if ($email) {
            $updateFields[] = 'email = COALESCE(NULLIF(?, ""), email)';
            $updateParams[] = $email;
        }
        if ($phone) {
            $updateFields[] = 'phone = COALESCE(NULLIF(?, ""), phone)';
            $updateParams[] = $phone;
        }

        if (!empty($updateFields)) {
            $updateParams[] = $customer['id'];
            $stmt = $pdo->prepare("UPDATE customers SET " . implode(', ', $updateFields) . " WHERE id = ?");
            $stmt->execute($updateParams);
        }

        return intval($customer['id']);
    }

    // Crear nuevo cliente con protección contra duplicados
    $nameParts = explode(' ', $guestName, 2);
    $firstName = $nameParts[0];
    $lastName  = $nameParts[1] ?? '';

    // Use SAVEPOINT to protect transaction from duplicate key errors
    try {
        $pdo->exec("SAVEPOINT before_customer_insert");
        $stmt = $pdo->prepare("
            INSERT INTO customers (first_name, last_name, email, phone, allergies, source_channel, first_visit_date)
            VALUES (?, ?, ?, ?, ?, ?, CURDATE())
        ");
        $stmt->execute([
            $firstName,
            $lastName,
            $email,
            $phone,
            $input['allergies'] ?? null,
            $input['channel'] ?? 'manual'
        ]);

        return intval($pdo->lastInsertId());
    } catch (\PDOException $e) {
        // Rollback only to savepoint so the outer transaction stays valid
        $pdo->exec("ROLLBACK TO SAVEPOINT before_customer_insert");

        // Si falla por duplicate key, re-buscar el existente
        if (strpos($e->getMessage(), '1062') !== false || $e->getCode() == 23000) {
            $customer = null;
            if ($phone) {
                $stmt = $pdo->prepare("SELECT id FROM customers WHERE phone = ? LIMIT 1");
                $stmt->execute([$phone]);
                $customer = $stmt->fetch();
            }
            if (!$customer && $email) {
                $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $customer = $stmt->fetch();
            }
            if ($customer) {
                return intval($customer['id']);
            }
        }
        throw $e;
    }
}

/**
 * Obtener reserva completa por ID con joins
 */
function getReservationById(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("
        SELECT r.*,
               rest.name AS restaurant_name,
               rest.slug AS restaurant_slug,
               t.table_number,
               t.name AS table_name,
               sec.name AS section_name,
               c.first_name AS customer_first_name,
               c.last_name AS customer_last_name,
               c.vip_level,
               c.total_visits AS customer_visits,
               con.name AS concierge_name,
               h.name AS hotel_name
        FROM reservations r
        LEFT JOIN restaurants rest ON r.restaurant_id = rest.id
        LEFT JOIN tables t ON r.table_id = t.id
        LEFT JOIN restaurant_sections sec ON t.section_id = sec.id
        LEFT JOIN customers c ON r.customer_id = c.id
        LEFT JOIN concierges con ON r.concierge_id = con.id
        LEFT JOIN hotels h ON con.hotel_id = h.id
        WHERE r.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/**
 * Registrar cambio en historial de reserva
 */
function logReservationHistory(PDO $pdo, int $reservationId, string $action, ?string $oldValue, ?string $newValue, ?int $changedBy, string $changedByType, ?string $notes): void {
    $stmt = $pdo->prepare("
        INSERT INTO reservation_history (reservation_id, action, old_value, new_value, changed_by, changed_by_type, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $reservationId,
        $action,
        $oldValue,
        $newValue,
        $changedBy,
        $changedByType,
        $notes
    ]);
}
