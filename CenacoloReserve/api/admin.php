<?php
/**
 * Cenacolo Reservas - API Admin
 * Gestión de configuración, usuarios, restaurantes, integraciones
 */
require_once __DIR__ . '/../includes/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if (!isResLoggedIn() || !isResAdmin()) {
    jsonResponse(['error' => 'No autorizado'], 401);
}

try {
    $pdo = getResDB();
    $method = $_SERVER['REQUEST_METHOD'];
    $data = json_decode(file_get_contents('php://input'), true);

    // ========================
    // PUT - Actualizar
    // ========================
    if ($method === 'PUT') {
        $action = $data['action'] ?? '';

        // Actualizar notificaciones
        if ($action === 'update_notifications') {
            foreach ($data['settings'] as $setting) {
                $stmt = $pdo->prepare("
                    UPDATE notification_settings SET
                        confirmation_email = ?, reminder_email = ?, cancellation_email = ?,
                        daily_summary_email = ?, reminder_hours = ?, daily_summary_recipients = ?
                    WHERE restaurant_id = ?
                ");
                $stmt->execute([
                    $setting['confirmation_email'] ?? 1,
                    $setting['reminder_email'] ?? 1,
                    $setting['cancellation_email'] ?? 1,
                    $setting['daily_summary_email'] ?? 0,
                    $setting['reminder_hours'] ?? 24,
                    $setting['daily_summary_recipients'] ?? '',
                    $setting['restaurant_id']
                ]);
            }
            jsonResponse(['success' => true, 'message' => 'Notificaciones actualizadas']);
        }

        // Actualizar restaurante
        if ($action === 'update_restaurant') {
            $stmt = $pdo->prepare("
                UPDATE restaurants SET
                    name = ?, address = ?, city = ?, state = ?, phone = ?, email = ?,
                    timezone = ?, opening_time = ?, closing_time = ?,
                    slot_duration = ?, max_party_size = ?, default_dining_duration = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['name'], $data['address'] ?? '', $data['city'] ?? '', $data['state'] ?? '',
                $data['phone'] ?? '', $data['email'] ?? '', $data['timezone'] ?? 'America/Cancun',
                $data['opening_time'] ?? '12:00', $data['closing_time'] ?? '23:00',
                $data['slot_duration'] ?? 30, $data['max_party_size'] ?? 20,
                $data['default_dining_duration'] ?? 90, $data['id']
            ]);
            jsonResponse(['success' => true, 'message' => 'Restaurante actualizado']);
        }

        // Actualizar integración
        if ($action === 'update_integration') {
            $stmt = $pdo->prepare("
                INSERT INTO restaurant_integrations (restaurant_id, platform, api_key, api_secret, client_id, restaurant_ref_id, access_token, refresh_token, sync_enabled)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    api_key = VALUES(api_key), api_secret = VALUES(api_secret),
                    client_id = VALUES(client_id), restaurant_ref_id = VALUES(restaurant_ref_id),
                    access_token = VALUES(access_token), refresh_token = VALUES(refresh_token),
                    sync_enabled = VALUES(sync_enabled)
            ");
            $stmt->execute([
                $data['restaurant_id'], $data['platform'] ?? 'opentable',
                $data['api_key'] ?? '', $data['api_secret'] ?? '',
                $data['client_id'] ?? '', $data['restaurant_ref_id'] ?? '',
                $data['access_token'] ?? '', $data['refresh_token'] ?? '',
                $data['sync_enabled'] ?? 1
            ]);
            jsonResponse(['success' => true, 'message' => 'Integración actualizada']);
        }

        // Actualizar usuario
        if ($action === 'update_user') {
            $id = $data['id'] ?? null;
            if (!$id) jsonResponse(['error' => 'ID requerido'], 400);

            // Proteger superadmin
            $stmtChk = $pdo->prepare("SELECT role FROM res_users WHERE id = ?");
            $stmtChk->execute([$id]);
            $target = $stmtChk->fetch();
            if ($target && $target['role'] === 'superadmin') {
                jsonResponse(['error' => 'No se puede modificar este usuario'], 403);
            }

            // No permitir asignar rol superadmin
            if (($data['role'] ?? '') === 'superadmin') {
                $data['role'] = 'admin';
            }

            $sets = ['name = ?', 'username = ?', 'email = ?', 'role = ?', 'restaurant_id = ?', 'phone = ?'];
            $params = [
                $data['name'], $data['username'], $data['email'] ?? null,
                $data['role'] ?? 'hostess', $data['restaurant_id'] ?: null, $data['phone'] ?? null
            ];
            if (!empty($data['password'])) {
                $sets[] = 'password_hash = ?';
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            $params[] = $id;
            $stmt = $pdo->prepare("UPDATE res_users SET " . implode(', ', $sets) . " WHERE id = ?");
            $stmt->execute($params);
            jsonResponse(['success' => true, 'message' => 'Usuario actualizado']);
        }

        // Actualizar concierge/afiliado
        if ($action === 'update_concierge') {
            $id = $data['id'] ?? null;
            if (!$id) jsonResponse(['error' => 'ID requerido'], 400);

            $sets = ['name = ?', 'email = ?', 'phone = ?', 'hotel_id = ?', 'type = ?',
                     'commission_type = ?', 'commission_value = ?', 'company_name = ?'];
            $params = [
                $data['name'], $data['email'], $data['phone'] ?? null,
                $data['hotel_id'] ?: null, $data['type'] ?? 'concierge',
                $data['commission_type'] ?? 'percentage',
                floatval($data['commission_value'] ?? 0),
                $data['company_name'] ?? null
            ];
            if (!empty($data['password'])) {
                $sets[] = 'password_hash = ?';
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            $params[] = $id;
            $stmt = $pdo->prepare("UPDATE concierges SET " . implode(', ', $sets) . " WHERE id = ?");
            $stmt->execute($params);
            jsonResponse(['success' => true, 'message' => 'Actualizado correctamente']);
        }

        // Actualizar capacidad de turno (defaults)
        if ($action === 'update_capacity') {
            $id = $data['id'] ?? null;
            if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
            $stmt = $pdo->prepare("
                UPDATE restaurant_capacity SET
                    shift_start = ?, shift_end = ?, max_covers = ?
                WHERE id = ? AND override_date IS NULL
            ");
            $stmt->execute([
                $data['shift_start'], $data['shift_end'],
                intval($data['max_covers']), $id
            ]);
            jsonResponse(['success' => true, 'message' => 'Capacidad actualizada']);
        }

        // Actualizar hotel
        if ($action === 'update_hotel') {
            $id = $data['id'] ?? null;
            if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
            $stmt = $pdo->prepare("
                UPDATE hotels SET name = ?, address = ?, city = ?, phone = ?,
                    contact_name = ?, contact_email = ?, commission_percentage = ?, notes = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['name'], $data['address'] ?? '', $data['city'] ?? '',
                $data['phone'] ?? '', $data['contact_name'] ?? '', $data['contact_email'] ?? '',
                $data['commission_percentage'] ?? 0, $data['notes'] ?? '', $id
            ]);
            jsonResponse(['success' => true, 'message' => 'Hotel actualizado']);
        }

        jsonResponse(['error' => 'Acción no válida'], 400);
    }

    // ========================
    // POST - Crear
    // ========================
    if ($method === 'POST') {
        $action = $data['action'] ?? '';

        // Crear usuario
        if ($action === 'create_user') {
            // Verificar límite de 10 usuarios (excluyendo superadmin)
            $stmtCount = $pdo->query("SELECT COUNT(*) FROM res_users WHERE role != 'superadmin' AND active = 1");
            $currentCount = $stmtCount->fetchColumn();
            if ($currentCount >= 10) {
                jsonResponse(['error' => 'Límite de 10 usuarios alcanzado'], 400);
            }

            // No permitir crear superadmin
            if (($data['role'] ?? '') === 'superadmin') {
                $data['role'] = 'admin';
            }

            $hash = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO res_users (name, username, email, password_hash, role, restaurant_id, phone)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['name'], $data['username'], $data['email'] ?? null, $hash,
                $data['role'] ?? 'hostess', $data['restaurant_id'] ?: null, $data['phone'] ?? null
            ]);
            jsonResponse(['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Usuario creado'], 201);
        }

        // Crear concierge o afiliado
        if ($action === 'create_concierge') {
            $hash = password_hash($data['password'], PASSWORD_DEFAULT);
            $type = $data['type'] ?? 'concierge';
            $prefix = $type === 'affiliate' ? 'AF' : 'CN';
            $referralCode = generateReferralCode($prefix);

            $stmt = $pdo->prepare("
                INSERT INTO concierges (hotel_id, type, referral_code, commission_type, commission_value, company_name, name, email, phone, password_hash)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['hotel_id'] ?: null,
                $type,
                $referralCode,
                $data['commission_type'] ?? 'percentage',
                floatval($data['commission_value'] ?? 0),
                $data['company_name'] ?? null,
                $data['name'],
                $data['email'],
                $data['phone'] ?? null,
                $hash
            ]);
            $label = $type === 'affiliate' ? 'Afiliado' : 'Concierge';
            jsonResponse([
                'success' => true,
                'id' => $pdo->lastInsertId(),
                'referral_code' => $referralCode,
                'referral_url' => getReferralUrl($referralCode),
                'message' => "{$label} creado"
            ], 201);
        }

        // Crear hotel
        if ($action === 'create_hotel') {
            $stmt = $pdo->prepare("
                INSERT INTO hotels (name, address, city, phone, contact_name, contact_email, commission_percentage, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['name'], $data['address'] ?? '', $data['city'] ?? '',
                $data['phone'] ?? '', $data['contact_name'] ?? '', $data['contact_email'] ?? '',
                $data['commission_percentage'] ?? 0, $data['notes'] ?? ''
            ]);
            jsonResponse(['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Hotel creado'], 201);
        }

        // Trigger sync OpenTable
        if ($action === 'sync_opentable') {
            require_once __DIR__ . '/../includes/opentable.php';
            $sync = new OpenTableSync($pdo, $data['restaurant_id']);
            $days = isset($data['days']) ? intval($data['days']) : 30;
            if ($days < 1) $days = 1;
            if ($days > 90) $days = 90;
            $result = $sync->syncReservations(null, $days, true); // incluir guests en sync manual

            // Save sync range info to integration config
            $endDate = $result['end_date'] ?? date('Y-m-d', strtotime("+".($days-1)." days"));
            $stmtCfg = $pdo->prepare("SELECT config FROM restaurant_integrations WHERE restaurant_id = ? AND platform = 'opentable'");
            $stmtCfg->execute([$data['restaurant_id']]);
            $row = $stmtCfg->fetch();
            if ($row) {
                $cfg = json_decode($row['config'] ?: '{}', true);
                $cfg['last_sync_end_date'] = $endDate;
                $cfg['last_sync_days'] = $days;
                $pdo->prepare("UPDATE restaurant_integrations SET config = ? WHERE restaurant_id = ? AND platform = 'opentable'")
                    ->execute([json_encode($cfg), $data['restaurant_id']]);
            }

            jsonResponse(['success' => true, 'result' => $result]);
        }

        // Test conexión OpenTable
        if ($action === 'test_opentable') {
            require_once __DIR__ . '/../includes/opentable.php';
            $sync = new OpenTableSync($pdo, $data['restaurant_id']);
            $result = $sync->testConnection();
            jsonResponse(['success' => true, 'result' => $result]);
        }

        // Preview reservas OpenTable (sin guardar)
        if ($action === 'preview_opentable') {
            require_once __DIR__ . '/../includes/opentable.php';
            $sync = new OpenTableSync($pdo, $data['restaurant_id']);
            $result = $sync->previewReservations($data['date'] ?? null);
            jsonResponse(['success' => true, 'result' => $result]);
        }

        // Sincronizar guests de OpenTable (perfiles de clientes)
        // Ahora usa parámetros de fecha (requerido por API, verificado con N8N)
        if ($action === 'sync_guests') {
            require_once __DIR__ . '/../includes/opentable.php';
            $sync = new OpenTableSync($pdo, $data['restaurant_id']);
            $dateFrom = $data['date_from'] ?? null;
            $dateTo   = $data['date_to'] ?? null;
            $result = $sync->syncGuests($dateFrom, $dateTo);
            jsonResponse(['success' => true, 'result' => $result]);
        }

        // Preview guests de OpenTable (sin guardar)
        if ($action === 'preview_guests') {
            require_once __DIR__ . '/../includes/opentable.php';
            $sync = new OpenTableSync($pdo, $data['restaurant_id']);
            $result = $sync->previewGuests($data['limit'] ?? 20, $data['date'] ?? null);
            jsonResponse(['success' => true, 'result' => $result]);
        }

        // Enriquecer clientes existentes con datos de Guests API
        if ($action === 'enrich_customers') {
            require_once __DIR__ . '/../includes/opentable.php';
            $sync = new OpenTableSync($pdo, $data['restaurant_id']);
            $result = $sync->enrichExistingCustomers();
            jsonResponse(['success' => true, 'result' => $result]);
        }

        // DEBUG: Ver respuesta RAW del API de OpenTable (reservations + guests)
        if ($action === 'debug_opentable') {
            require_once __DIR__ . '/../includes/opentable.php';
            $sync = new OpenTableSync($pdo, $data['restaurant_id']);
            $result = $sync->debugApiResponse($data['date'] ?? null);
            jsonResponse(['success' => true, 'result' => $result]);
        }

        // Crear perk para concierge/afiliado
        if ($action === 'create_perk') {
            $conciergeId = $data['concierge_id'] ?? null;
            $perkType = $data['perk_type'] ?? null;
            $name = trim($data['name'] ?? '');
            if (!$conciergeId || !$perkType || !$name) {
                jsonResponse(['error' => 'concierge_id, perk_type y name son requeridos'], 400);
            }
            $stmt = $pdo->prepare("
                INSERT INTO concierge_perks (concierge_id, perk_type, name, description, value)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $conciergeId, $perkType, $name,
                $data['description'] ?? null, $data['value'] ?? null
            ]);
            jsonResponse(['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Perk creado'], 201);
        }

        // Crear override de capacidad para un día específico
        if ($action === 'create_capacity_override') {
            $restaurantId = $data['restaurant_id'] ?? null;
            $shiftName = $data['shift_name'] ?? null;
            $overrideDate = $data['override_date'] ?? null;
            $maxCovers = $data['max_covers'] ?? null;

            if (!$restaurantId || !$shiftName || !$overrideDate || $maxCovers === null) {
                jsonResponse(['error' => 'restaurant_id, shift_name, override_date y max_covers son requeridos'], 400);
            }

            // Get default shift times for this shift_name
            $stmtDef = $pdo->prepare("SELECT shift_start, shift_end FROM restaurant_capacity WHERE restaurant_id = ? AND shift_name = ? AND override_date IS NULL AND active = 1 LIMIT 1");
            $stmtDef->execute([$restaurantId, $shiftName]);
            $default = $stmtDef->fetch();
            if (!$default) {
                jsonResponse(['error' => "No existe turno '{$shiftName}' configurado para este restaurante"], 400);
            }

            // Delete existing override for same day/shift if exists
            $pdo->prepare("DELETE FROM restaurant_capacity WHERE restaurant_id = ? AND shift_name = ? AND override_date = ?")
                ->execute([$restaurantId, $shiftName, $overrideDate]);

            $stmt = $pdo->prepare("
                INSERT INTO restaurant_capacity (restaurant_id, shift_name, shift_start, shift_end, max_covers, override_date)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $restaurantId, $shiftName,
                $data['shift_start'] ?? $default['shift_start'],
                $data['shift_end'] ?? $default['shift_end'],
                intval($maxCovers), $overrideDate
            ]);
            jsonResponse(['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Override de capacidad creado'], 201);
        }

        // Crear bloqueo de disponibilidad
        if ($action === 'create_block') {
            $restaurantId = $data['restaurant_id'] ?? null;
            $blockDate = $data['block_date'] ?? null;
            if (!$restaurantId || !$blockDate) {
                jsonResponse(['error' => 'restaurant_id y block_date son requeridos'], 400);
            }
            $stmt = $pdo->prepare("
                INSERT INTO restaurant_blocked_periods (restaurant_id, block_date, start_time, end_time, reason, blocked_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $restaurantId,
                $blockDate,
                $data['start_time'] ?? null,
                $data['end_time'] ?? null,
                $data['reason'] ?? null,
                $_SESSION['res_user_id'] ?? null
            ]);
            jsonResponse(['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Bloqueo creado'], 201);
        }

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

        jsonResponse(['error' => 'Acción no válida'], 400);
    }

    // ========================
    // GET - Listar
    // ========================
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'users';

        if ($action === 'users') {
            $stmt = $pdo->query("SELECT u.*, r.name as restaurant_name FROM res_users u LEFT JOIN restaurants r ON u.restaurant_id = r.id ORDER BY u.role, u.name");
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
        }

        if ($action === 'concierges') {
            $stmt = $pdo->query("SELECT c.*, h.name as hotel_name FROM concierges c LEFT JOIN hotels h ON c.hotel_id = h.id ORDER BY c.name");
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
        }

        if ($action === 'hotels') {
            $stmt = $pdo->query("SELECT * FROM hotels WHERE active = 1 ORDER BY name");
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
        }

        if ($action === 'restaurant') {
            $stmt = $pdo->prepare("SELECT * FROM restaurants WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            jsonResponse(['success' => true, 'data' => $stmt->fetch()]);
        }

        if ($action === 'perks') {
            $conciergeId = $_GET['concierge_id'] ?? null;
            if (!$conciergeId) jsonResponse(['error' => 'concierge_id requerido'], 400);
            $stmt = $pdo->prepare("SELECT * FROM concierge_perks WHERE concierge_id = ? AND active = 1 ORDER BY perk_type, name");
            $stmt->execute([$conciergeId]);
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
        }

        // Obtener capacidad por turno
        if ($action === 'capacity') {
            $restaurantId = $_GET['restaurant_id'] ?? null;
            if (!$restaurantId) jsonResponse(['error' => 'restaurant_id requerido'], 400);

            // Defaults (override_date IS NULL)
            $stmt = $pdo->prepare("SELECT * FROM restaurant_capacity WHERE restaurant_id = ? AND override_date IS NULL AND active = 1 ORDER BY shift_start");
            $stmt->execute([$restaurantId]);
            $defaults = $stmt->fetchAll();

            // Overrides (future dates)
            $stmt2 = $pdo->prepare("SELECT * FROM restaurant_capacity WHERE restaurant_id = ? AND override_date IS NOT NULL AND override_date >= CURDATE() AND active = 1 ORDER BY override_date, shift_start");
            $stmt2->execute([$restaurantId]);
            $overrides = $stmt2->fetchAll();

            // Occupancy for next 7 days
            $occupancy = [];
            for ($d = 0; $d < 7; $d++) {
                $date = date('Y-m-d', strtotime("+{$d} days"));
                $dayData = ['date' => $date, 'shifts' => []];
                foreach ($defaults as $def) {
                    $cap = checkCapacity($pdo, $restaurantId, $date, $def['shift_start'], 0);
                    $dayData['shifts'][] = [
                        'shift_name' => $def['shift_name'],
                        'max_covers' => $cap['max_covers'],
                        'current_covers' => $cap['current_covers'],
                        'remaining' => $cap['remaining'],
                        'is_override' => $cap['is_override']
                    ];
                }
                $occupancy[] = $dayData;
            }

            jsonResponse(['success' => true, 'data' => [
                'defaults' => $defaults,
                'overrides' => $overrides,
                'occupancy' => $occupancy
            ]]);
        }

        if ($action === 'blocks') {
            $restaurantId = $_GET['restaurant_id'] ?? null;
            if (!$restaurantId) jsonResponse(['error' => 'restaurant_id requerido'], 400);
            $stmt = $pdo->prepare("SELECT * FROM restaurant_blocked_periods WHERE restaurant_id = ? AND block_date >= CURDATE() ORDER BY block_date, start_time");
            $stmt->execute([$restaurantId]);
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
        }

        jsonResponse(['error' => 'Acción no válida'], 400);
    }

    // ========================
    // DELETE
    // ========================
    if ($method === 'DELETE') {
        $type = $data['type'] ?? '';
        $id = $data['id'] ?? null;

        if (!$id) jsonResponse(['error' => 'ID requerido'], 400);

        // Soft delete for perks
        if ($type === 'perk') {
            $stmt = $pdo->prepare("UPDATE concierge_perks SET active = 0 WHERE id = ?");
            $stmt->execute([$id]);
            jsonResponse(['success' => true, 'message' => 'Perk eliminado']);
        }

        // Hard delete for capacity overrides
        if ($type === 'capacity_override') {
            $stmt = $pdo->prepare("DELETE FROM restaurant_capacity WHERE id = ? AND override_date IS NOT NULL");
            $stmt->execute([$id]);
            jsonResponse(['success' => true, 'message' => 'Override de capacidad eliminado']);
        }

        // Hard delete for blocks
        if ($type === 'block') {
            $stmt = $pdo->prepare("DELETE FROM restaurant_blocked_periods WHERE id = ?");
            $stmt->execute([$id]);
            jsonResponse(['success' => true, 'message' => 'Bloqueo eliminado']);
        }

        // Soft delete for other types
        $tables = ['user' => 'res_users', 'concierge' => 'concierges', 'hotel' => 'hotels'];
        if (!isset($tables[$type])) jsonResponse(['error' => 'Tipo no válido'], 400);

        // Proteger superadmin de eliminación
        if ($type === 'user') {
            $stmtChk = $pdo->prepare("SELECT role FROM res_users WHERE id = ?");
            $stmtChk->execute([$id]);
            $target = $stmtChk->fetch();
            if ($target && $target['role'] === 'superadmin') {
                jsonResponse(['error' => 'No se puede eliminar este usuario'], 403);
            }
        }

        $stmt = $pdo->prepare("UPDATE {$tables[$type]} SET active = 0 WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true, 'message' => ucfirst($type) . ' desactivado']);
    }

} catch (PDOException $e) {
    jsonResponse(['error' => 'Error de base de datos', 'detail' => $e->getMessage()], 500);
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
