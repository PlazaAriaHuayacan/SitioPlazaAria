# CenacoloReserve — Subsistema B: Offline-First Design

**Fecha:** 2026-05-23
**Proyecto:** CenacoloReserve (somossinergia.com/CenacoloReserve)
**Stack:** PHP 8+, PDO/MySQL, Tailwind CSS (CDN), vanilla JS con fetch()
**Contexto:** Continuación de Subsistema A (datos bancarios + comisiones). Este subsistema agrega capacidad offline al portal de concierge/hostess.

---

## Objetivo

Permitir que el portal de concierge funcione sin conexión a internet en 2–3 dispositivos simultáneos (tablet de hostess, computadora en entrada). Las operaciones offline se almacenan localmente y se sincronizan automáticamente al reconectar. Si hay conflictos, un humano decide manualmente qué versión conservar.

---

## Enfoque elegido

**Service Worker + IndexedDB** (Opción A)

El Service Worker intercepta todas las llamadas `fetch()` del portal. Las peticiones GET se sirven desde un snapshot en IndexedDB cuando no hay conexión. Las peticiones POST se encolan en un "outbox" en IndexedDB y se procesan en orden FIFO al reconectar. Los endpoints PHP existentes se reutilizan sin cambios de contrato — solo se agrega verificación de versión para detectar conflictos.

---

## Sección 1: Arquitectura general

```
Browser                    Service Worker              Servidor (Hostinger PHP)
──────────────────────────────────────────────────────────────────────────────
Portal pages (PHP-rendered)
     │
     ├─ GET /api/*   ──→  Network-first  ──→  servidor
     │                         │ fallo       ↓
     │                         └──────→  snapshot en IDB
     │
     ├─ POST /api/*  ──→  [Online?]  ──→  fetch normal → servidor
     │                       │
     │                   [Offline?]  ──→  encola en IDB outbox
     │
     └─ Al reconectar ──────────────→  flush outbox → servidor
                                        Si 409 → store conflicts → alerta UI
```

### Archivos nuevos

| Archivo | Responsabilidad |
|---|---|
| `portal/sw.js` | Service Worker — intercept fetch, cache strategy, outbox flush |
| `portal/js/offline.js` | Registro del SW, badge de estado, detección online/offline, postMessage bridge |
| `portal/js/idb.js` | Wrapper delgado sobre IndexedDB — open, get, put, delete, getAll |
| `portal/offline-conflict.php` | Modal de resolución de conflictos (fragmento incluido en layout) |

### Archivos modificados

| Archivo | Cambio |
|---|---|
| `portal/index.php` | Incluir `offline.js`, banner de estado, script de registro SW |
| `portal/reservations.php` | Badge ⏳ en filas offline, escuchar sync complete |
| `portal/floorplan.php` | Mesas con borde punteado si asignación pendiente |
| `portal/new-reservation.php` | Pantalla de confirmación offline, payload `client_version` |
| `api/reservations.php` | Verificación de `client_version` para detección de conflictos |
| `api/floorplan.php` | Ídem |
| `includes/layout-portal.php` | Inyectar banner offline + script registro SW en todas las páginas (si existe layout compartido; si no, se agrega manualmente a cada página) |

---

## Sección 2: IndexedDB — tres stores

**Nombre de la base:** `cenacolo-offline` (versión 1)

### Store: `snapshot`

Copia local de los datos más recientes recibidos del servidor.

```
keyPath: ['type', 'id']

Ejemplos de entradas:
{ type: 'reservation', id: 42, data: {...}, fetchedAt: 1748042400000 }
{ type: 'table',       id: 7,  data: {...}, fetchedAt: 1748042400000 }
{ type: 'restaurant',  id: 1,  data: {...}, fetchedAt: 1748042400000 }
```

- Se sobreescribe en cada GET exitoso.
- Si `fetchedAt` tiene más de 24h, se muestra aviso "Datos de ayer" junto a los datos.

### Store: `outbox`

Cola FIFO de cambios pendientes de sincronizar.

```
keyPath: 'id' (uuid-v4 generado en cliente)
indexes: ['status', 'timestamp']

Estructura:
{
  id: "550e8400-...",
  timestamp: 1748042400000,
  action: "update_reservation_status" | "assign_table" | "create_reservation",
  payload: { reservation_id: 42, new_status: "seated", client_version: 1748040000 },
  status: "pending" | "syncing" | "conflict" | "done"
}
```

### Store: `conflicts`

Entradas del outbox que el servidor rechazó con 409. Permanecen hasta que el humano decide.

```
keyPath: 'id'

Estructura:
{
  ...entrada_del_outbox,
  server_data: { ...datos actuales del servidor al momento del conflicto },
  conflicted_at: 1748042800000
}
```

---

## Sección 3: Estrategia de caché por tipo de request

| Tipo | Estrategia | Detalle |
|---|---|---|
| `GET /api/*` | **Network-first → IDB fallback** | Intenta servidor; si falla, sirve snapshot de IDB |
| `POST /api/*` | **Network-or-queue** | Si online: fetch normal. Si offline: encola en outbox |
| Assets `portal/js/*.js` | **Cache-first** | Se cachean en SW `install`; no tocan red |
| Páginas `portal/*.php` | **Stale-while-revalidate** | Sirve caché, refresca en background |
| Tailwind CDN / Google Fonts | **Cache-first con timeout 3s** | Si CDN tarda >3s, sirve del caché |

### Páginas con soporte offline

| Página | Lectura | Escritura |
|---|---|---|
| `portal/index.php` | ✅ stats del snapshot | ❌ solo lectura |
| `portal/reservations.php` | ✅ lista | ✅ cambio de estado → outbox |
| `portal/floorplan.php` | ✅ plano + mesas | ✅ asignación → outbox |
| `portal/new-reservation.php` | ✅ form estático | ✅ nueva reserva → outbox |
| `portal/commissions.php` | ✅ snapshot | ❌ solo lectura |
| `portal/bank-data.php` | ❌ overlay "requiere conexión" | ❌ |

---

## Sección 4: Detección de conflictos en el servidor

Cada POST que pueda causar conflicto incluye `client_version` (timestamp Unix del snapshot local). El servidor compara con su `updated_at`:

```php
// En api/reservations.php y api/floorplan.php
$clientVersion = (int)($_POST['client_version'] ?? 0);

// Solo verificar si el cliente mandó versión (escrituras offline)
if ($clientVersion > 0) {
    $stmt = $pdo->prepare("SELECT UNIX_TIMESTAMP(updated_at) AS ver FROM reservations WHERE id = ?");
    $stmt->execute([$reservationId]);
    $row = $stmt->fetch();

    if ($row && $row['ver'] > $clientVersion) {
        http_response_code(409);
        echo json_encode(['conflict' => true, 'server_data' => $row]);
        exit;
    }
}
```

Las nuevas reservas (`create_reservation`) nunca generan 409 — se insertan siempre con `INSERT INTO`.

La asignación de mesa puede generar 409 especial si la mesa ya fue asignada a otro:

```php
if ($table['status'] === 'occupied' && $table['reservation_id'] !== $reservationId) {
    http_response_code(409);
    echo json_encode(['conflict' => true, 'reason' => 'table_taken', 'server_data' => $table]);
    exit;
}
```

---

## Sección 5: Flujo de sincronización al reconectar

1. `offline.js` detecta evento `online` del browser
2. Envía `postMessage({ type: 'FLUSH_OUTBOX' })` al SW
3. SW procesa outbox en orden `timestamp ASC`, de a una entrada a la vez
4. Por cada entrada:
   - Marca `status: 'syncing'`
   - Hace fetch al endpoint correspondiente con el payload
   - Si 200: marca `status: 'done'`, borra de outbox
   - Si 409: mueve a store `conflicts`, guarda `server_data`
   - Si error de red: deja `status: 'pending'` y reintenta en próximo flush
5. Al terminar el flush, SW emite `postMessage({ type: 'SYNC_COMPLETE', conflicts: N })`
6. `offline.js` recibe el mensaje:
   - Si `conflicts === 0`: toast verde "✅ Todo sincronizado"
   - Si `conflicts > 0`: activa modal de conflictos

**Background Sync API** (Chrome/Edge nativos): `sw.js` también registra `sync` tag `cenacolo-outbox` para que el browser intente sincronizar aunque el usuario haya cerrado la tab. El fallback `postMessage` cubre Safari/Firefox que no soportan Background Sync.

---

## Sección 6: UX offline — tres capas de visibilidad

### Capa 1: Banner persistente (inocultable)

Barra completa arriba de todo el contenido, visible en todas las páginas del portal mientras no haya conexión:

```
┌─────────────────────────────────────────────────────────────────┐
│  📵  Sin conexión — los cambios se guardan localmente y se      │
│       sincronizarán automáticamente al reconectar.              │
└─────────────────────────────────────────────────────────────────┘
```

- Estilo: `bg-yellow-900 border-b border-yellow-700 text-yellow-200`
- Sin botón de cierre — la hostess siempre sabe en qué modo está
- Al reconectar, el banner se reemplaza por toast verde transitorio "✅ Conexión restaurada. Sincronizando..."

### Capa 2: Confirmación explícita al guardar reserva offline

Al enviar el form de nueva reserva sin conexión, en lugar de un toast, se muestra una pantalla de confirmación de página completa antes de volver a la lista:

```
┌──────────────────────────────────────────────────────┐
│                                                      │
│   ⏳  Reserva guardada sin conexión                  │
│                                                      │
│   Luis García — 19:30 — 4 personas                  │
│                                                      │
│   Esta reserva está guardada en este dispositivo.   │
│   Se confirmará en el sistema cuando recuperes      │
│   internet.                                         │
│                                                      │
│   ⚠️  Avisa al manager si no recuperas conexión     │
│   antes de que llegue el cliente.                   │
│                                                      │
│              [ Entendido ]                           │
└──────────────────────────────────────────────────────┘
```

### Capa 3: Badge en filas no sincronizadas

En la lista de reservas, cada fila con cambios pendientes muestra un badge `⏳ Sin sincronizar` en lugar del estado normal:

```
│ 19:30  Luis García   4 pax  │ ⏳ Sin sincronizar  │  [Sentado]  │
│ 20:00  Ana Reyes     2 pax  │ ✅                   │  [Llegó]    │
```

El badge desaparece row a row conforme el outbox se vacía al reconectar.

### Indicador en navbar (complementario)

Chip discreto en la barra de navegación como referencia secundaria:

- `● En línea` — verde
- `◌ Sin conexión (N pendientes)` — amarillo con conteo del outbox
- `⚠️ N conflictos` — rojo, clickeable abre el modal de resolución

---

## Sección 7: Modal de resolución de conflictos

```
┌─────────────────────────────────────────────────────────┐
│ ⚠️  Conflicto en Reserva #42 — Luis García  (1 de N)   │
│                                                         │
│ Tu versión (guardada offline):                          │
│   Estado → Sentado   (14:28 en este dispositivo)       │
│                                                         │
│ Versión del servidor:                                   │
│   Estado → Cancelado (cambiado a las 14:32)            │
│                                                         │
│  [Conservar la mía]      [Usar la del servidor]        │
└─────────────────────────────────────────────────────────┘
```

- **"Conservar la mía"** → POST con `force: true` al endpoint — el servidor omite verificación de versión
- **"Usar la del servidor"** → descarta entrada de `conflicts`, actualiza snapshot local con `server_data`
- Navega automáticamente al siguiente conflicto si hay más de uno
- Al resolver todos: toast verde "✅ Todos los conflictos resueltos"

---

## Sección 8: Ciclo de vida del Service Worker

### Versionado

```js
// portal/sw.js — línea 1
const SW_VERSION = '1.0.0'; // incrementar en cada deploy

const STATIC_CACHE = `cenacolo-static-${SW_VERSION}`;
const API_CACHE    = `cenacolo-api-${SW_VERSION}`;
```

Cambiar `SW_VERSION` al hacer deploy es suficiente — el browser detecta el cambio y reinstala.

### Fase install

- Precachea páginas del portal y assets JS propios
- Llama `self.skipWaiting()` para activar inmediatamente (tablets que nunca se cierran reciben la actualización en el próximo reload)

### Fase activate

- Limpia cachés cuyo nombre no termine en `SW_VERSION` (elimina versiones viejas)
- Llama `self.clients.claim()` para tomar control de tabs ya abiertas sin recargar

### Compatibilidad con Hostinger

- HTTPS habilitado por defecto en todos los planes de Hostinger → prerequisito del SW cumplido
- No requiere headers especiales en el servidor
- `sw.js` debe estar en `portal/sw.js` (mismo origen que las páginas del portal)
- El scope del SW es `/CenacoloReserve/portal/` — no afecta al panel admin

---

## Sección 9: Lo que NO cambia

- Los endpoints PHP (`/api/*.php`) mantienen su contrato exacto; solo se agrega la verificación de `client_version`
- El panel admin (`/admin/`) no recibe soporte offline — los admins trabajan en oficina con conexión estable
- La autenticación PHP (sesiones) no se replica offline; si la sesión expira mientras está sin conexión, la hostess ve "Reconecta para iniciar sesión" al intentar sincronizar
- No se usa ningún framework JS — todo es vanilla con `fetch()` e IDB nativa

---

## Fuera de alcance (Subsistema B)

- Notificaciones push mientras está offline
- Sincronización en tiempo real entre los 2–3 dispositivos mientras están offline entre sí (cada dispositivo sincroniza con el servidor al reconectar, no entre ellos)
- Soporte offline para el panel admin
- Integración con POS (Subsistema C)
