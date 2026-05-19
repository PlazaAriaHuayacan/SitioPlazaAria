# CenacoloReserve — Datos Bancarios y Pagos a Concierges

**Fecha:** 2026-05-19
**Proyecto:** CenacoloReserve
**Developer:** Somos SinergIA
**Status:** Aprobado para implementación

---

## 1. Contexto

CenacoloReserve ya tiene un sistema de concierges y afiliados con comisiones configurables (fija o porcentaje del consumo). El campo `commission_earned` existe en la tabla `concierges` como acumulado global, pero no hay tracking por reserva ni mecanismo para registrar pagos.

El compromiso operativo de Cenacolo es pagar comisiones dentro de las **24 horas** de que ocurra la visita. La administración de Cenacolo hace los depósitos vía SPEI. Solo se paga a los concierges/afiliados que hayan registrado sus datos bancarios.

**Dependencia importante:** Los montos de comisión por porcentaje requieren el consumo real del cliente, que vendrá del POS (Subsistema C). Este subsistema se construye ahora pero **no se lanzará en producción hasta que el POS esté integrado**, ya que sin el monto del cheque, las comisiones por porcentaje quedan en NULL.

---

## 2. Alcance

**Incluye:**
- Registro y edición de datos bancarios por el concierge (portal)
- Tracking de comisiones a nivel de reserva (tabla `commissions`)
- Panel de pagos pendientes para administración de Cenacolo con indicador de urgencia
- Historial de comisiones para administración y para el concierge
- Acción de "marcar pagado" por comisión individual

**No incluye:**
- Notificaciones al concierge al recibir pago
- Pagos automáticos o integración con banco
- Cálculo de comisiones por porcentaje (requiere POS — campo queda NULL hasta entonces)
- Agrupación de pagos por quincena o periodo

---

## 3. Modelo de Datos

### 3.1 Columnas nuevas en `concierges`

```sql
ALTER TABLE concierges
  ADD COLUMN bank_name    VARCHAR(100)  NULL AFTER company_name,
  ADD COLUMN bank_clabe   CHAR(18)      NULL AFTER bank_name,
  ADD COLUMN bank_account VARCHAR(30)   NULL AFTER bank_clabe,
  ADD COLUMN bank_holder  VARCHAR(255)  NULL AFTER bank_account;
```

Se agregan directamente a `concierges` — un concierge tiene un solo set de datos bancarios activos. No se necesita historial de cambios bancarios.

### 3.2 Tabla nueva `commissions`

```sql
CREATE TABLE commissions (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  concierge_id       INT UNSIGNED NOT NULL,
  reservation_id     INT UNSIGNED NOT NULL,
  commission_type    ENUM('fixed','percentage') NOT NULL,
  commission_rate    DECIMAL(10,2) NOT NULL DEFAULT 0,
  consumption_total  DECIMAL(10,2) NULL COMMENT 'Monto del cheque — viene del POS',
  commission_amount  DECIMAL(10,2) NULL COMMENT 'NULL hasta tener consumption_total si es porcentaje',
  status             ENUM('pending','paid') NOT NULL DEFAULT 'pending',
  due_by             DATETIME NOT NULL COMMENT 'reservation_datetime + 24 horas',
  paid_at            DATETIME NULL,
  paid_by_user_id    INT UNSIGNED NULL,
  notes              TEXT NULL,
  created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY uq_reservation (reservation_id),
  KEY idx_concierge_status (concierge_id, status),
  KEY idx_due_by (due_by),
  CONSTRAINT fk_comm_concierge FOREIGN KEY (concierge_id) REFERENCES concierges(id),
  CONSTRAINT fk_comm_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Campos snapshot:** `commission_type` y `commission_rate` guardan los valores vigentes al momento de completar la reserva. Si el concierge cambia su comisión en el futuro, los registros históricos no se alteran.

**Comisión fija:** `commission_amount = commission_rate` al momento de crear el registro.
**Comisión por porcentaje:** `commission_amount = NULL` hasta recibir `consumption_total` del POS.

---

## 4. Archivos Nuevos

### 4.1 `portal/bank-data.php`

**Propósito:** El concierge registra o actualiza sus datos bancarios.

**Comportamiento:**
- Requiere sesión de concierge (`requireConciergeLogin()`)
- Si ya tiene datos guardados, pre-llena el formulario
- Si no tiene datos, muestra banner de advertencia: *"Sin datos bancarios no podremos procesar tu comisión"*
- Formulario con 4 campos: Banco (text), CLABE (18 dígitos, validación client-side y server-side), Número de cuenta (text), Nombre del titular (text)
- Al guardar: POST a `api/bank-data.php`, respuesta JSON, feedback inline sin reload de página
- Link de regreso al dashboard del portal
- Accesible desde el nav del portal y desde la tarjeta de comisiones en `portal/index.php`

**Validaciones:**
- CLABE: exactamente 18 dígitos numéricos (no letras, no espacios, no guiones)
- Banco y titular: requeridos, máximo 100 y 255 caracteres respectivamente
- Cuenta: opcional (algunos bancos no la requieren para SPEI)

### 4.2 `portal/commissions.php`

**Propósito:** El concierge ve su historial de comisiones.

**Comportamiento:**
- Requiere sesión de concierge
- Tabla con columnas: Fecha reserva, Restaurante, Huésped, Tipo comisión, Monto, Estado, Fecha de pago
- Filtro por estado: Todas / Pendientes / Pagadas
- Resumen superior: total pendiente de cobro + total histórico pagado
- Solo lectura — sin acciones
- Comisiones con `commission_amount = NULL` muestran *"Por liquidar"* en la columna de monto (esperando POS)

### 4.3 `admin/commissions.php`

**Propósito:** Panel central de la administración de Cenacolo para gestionar pagos.

**Requiere:** rol admin o superadmin.

**Tab "Pendientes":**
- Ordenado por `due_by` ASC (más urgente arriba)
- Indicador de urgencia por fila (basado en tiempo restante hasta `due_by`):
  - Verde: quedan más de 8 horas
  - Amarillo: quedan entre 2 y 8 horas
  - Rojo: quedan menos de 2 horas o ya vencido
- Columnas: Urgencia, Concierge, Reserva (fecha + hora + huésped), Monto, Banco, CLABE, Acción
- CLABE y banco visibles directamente en la fila — administración puede hacer el SPEI sin salir de la pantalla
- Si el concierge **no tiene datos bancarios**: fila en gris con badge *"Sin datos bancarios"* — botón de marcar pagado deshabilitado
- Botón **"Marcar pagado"** por fila → modal de confirmación simple → llama `api/admin.php` action `mark_commission_paid` → fila desaparece de pendientes

**Tab "Historial":**
- Todas las comisiones con status `paid`
- Filtros: por concierge (select), por rango de fechas
- Columnas: Fecha reserva, Concierge, Huésped, Monto, Pagado el, Pagado por

---

## 5. API

### 5.1 `api/bank-data.php` (nuevo)

**Método:** POST
**Auth:** sesión de concierge activa
**Body:** `{ bank_name, bank_clabe, bank_account, bank_holder }`
**Validaciones server-side:** CLABE = 18 dígitos numéricos; bank_name y bank_holder requeridos
**Acción:** UPDATE en `concierges` SET bank_name, bank_clabe, bank_account, bank_holder WHERE id = concierge_id de sesión
**Respuesta:** `{ success: true }` o `{ error: "mensaje" }`

### 5.2 `api/admin.php` — acción nueva `mark_commission_paid`

**Método:** POST
**Auth:** sesión admin/superadmin
**Body:** `{ action: "mark_commission_paid", commission_id: N }`
**Validaciones:**
- La comisión existe y está en status `pending`
- El concierge asociado tiene CLABE registrada
**Acción:** UPDATE commissions SET status='paid', paid_at=NOW(), paid_by_user_id=? WHERE id=?
**Respuesta:** `{ success: true }` o `{ error: "mensaje" }`

### 5.3 `api/reservations.php` — trigger de comisión (modificación)

Cuando una reserva cambia a `completed` y tiene `concierge_id`:

```php
// Obtener datos de comisión del concierge
$concierge = getConciergeById($concierge_id);
$type = $concierge['commission_type'];
$rate = floatval($concierge['commission_value']);
$amount = ($type === 'fixed') ? $rate : null; // porcentaje queda NULL hasta POS

// due_by = fecha+hora de la reserva + 24 horas
$reservationDatetime = $reservation['reservation_date'] . ' ' . $reservation['reservation_time'];
$dueBy = date('Y-m-d H:i:s', strtotime($reservationDatetime . ' +24 hours'));

$pdo->prepare("
    INSERT IGNORE INTO commissions
    (concierge_id, reservation_id, commission_type, commission_rate, commission_amount, due_by)
    VALUES (?, ?, ?, ?, ?, ?)
")->execute([$concierge_id, $reservation_id, $type, $rate, $amount, $dueBy]);
```

`INSERT IGNORE` previene duplicados si la reserva se marca completed más de una vez por error.

Solo se crea comisión si `commission_value > 0`. Concierges con comisión en cero se omiten.

---

## 6. Migración DB

Archivo: `migrate_commissions.php` (corre una vez vía browser, luego se elimina)

1. ALTER TABLE concierges — agregar 4 columnas bancarias
2. CREATE TABLE commissions
3. Backfill: para reservas existentes con status `completed` y `concierge_id`, verificar si ya existe comisión y crearla si no (con `consumption_total = NULL`, `commission_amount` según tipo). Esto asegura que el historial pasado esté representado.

---

## 7. Cambios en páginas existentes

**`portal/index.php`:**
- Agregar link *"Mis Comisiones"* en nav del portal
- Si el concierge no tiene datos bancarios: mostrar banner amarillo en el dashboard con link a `portal/bank-data.php`
- La tarjeta de comisiones existente puede actualizarse para mostrar total pendiente de cobro en lugar del `commission_earned` actual (que es un acumulado impreciso)

**`admin/concierges.php`:**
- Agregar columna "Datos bancarios" en la tabla (checkmark verde / X roja)
- Link a `admin/commissions.php` en el nav del admin

---

## 8. Archivos modificados

| Archivo | Tipo de cambio |
|---------|---------------|
| `api/reservations.php` | Agregar trigger de comisión al completar reserva |
| `api/admin.php` | Agregar acción `mark_commission_paid` |
| `portal/index.php` | Banner sin datos bancarios + link a comisiones |
| `admin/concierges.php` | Columna datos bancarios + link al panel de pagos |

| Archivo nuevo | Propósito |
|--------------|-----------|
| `portal/bank-data.php` | Formulario de datos bancarios (concierge) |
| `portal/commissions.php` | Historial de comisiones (concierge) |
| `admin/commissions.php` | Panel de pagos pendientes (Cenacolo admin) |
| `api/bank-data.php` | Endpoint guardar datos bancarios |
| `migrate_commissions.php` | Migración DB — correr una vez |

---

## 9. Lo que NO cambia

- Lógica de OpenTable sync
- Webhook de afiliados/referidos
- `book.php`, `floorplan.php`, `reservations.php`
- Estructura de la tabla `concierges` más allá de las 4 columnas nuevas
- Sistema de perks y acuerdos

---

## 10. Criterios de aceptación

- [ ] Concierge puede guardar y editar sus datos bancarios desde el portal
- [ ] CLABE se valida client-side y server-side (18 dígitos)
- [ ] Al completar una reserva con concierge asignado, se crea automáticamente un registro en `commissions`
- [ ] Admin ve lista de pendientes ordenada por urgencia con indicador de color
- [ ] CLABE y banco visibles en la fila sin necesidad de abrir detalle
- [ ] Concierges sin datos bancarios aparecen en gris y no se pueden marcar como pagados
- [ ] "Marcar pagado" registra `paid_at` y `paid_by_user_id`
- [ ] Concierge ve su historial completo en `portal/commissions.php`
- [ ] No se crean comisiones duplicadas si la reserva se marca completed dos veces
