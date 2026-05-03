# Punch Social Dashboard — Design Spec
**Fecha:** 2026-05-03
**Estado:** Aprobado

---

## Resumen

Dashboard interno de métricas de redes sociales para la agencia Punch. Permite al equipo de Punch visualizar el rendimiento de los 16 clientes en META (Instagram + Facebook) y LinkedIn. Los datos se cargan vía importación de Excel o, en una segunda fase, mediante sincronización automática por API. El objetivo principal es generar vistas que se puedan compartir como screenshot al cliente.

---

## Contexto

- **PunchReports** (proyecto anterior) fue descartado — nunca logró datos reales confiables.
- **PunchDashboard** existe como base React/Vite pero sin backend funcional.
- El problema histórico: las APIs de redes sociales devuelven datos que no coinciden con lo que se ve en las apps nativas. La solución es guardar snapshots en DB en vez de consultar live.

---

## Usuarios

- **Equipo interno de Punch** — único usuario. No hay acceso para clientes.
- Los reportes se comparten vía screenshot o PDF del dashboard.

---

## Plataformas en alcance

| Fase | Plataformas |
|------|-------------|
| MVP  | META (Instagram + Facebook), LinkedIn |
| Fase 2 | TikTok, YouTube (opcionales) |

---

## Arquitectura

### Stack

| Capa | Tecnología |
|------|-----------|
| Frontend | React + Vite (extiende PunchDashboard existente) |
| Backend | PHP 8.2 API REST |
| Base de datos | MySQL |
| Gráficas | Recharts |
| Componentes UI | shadcn/ui |
| Excel parsing | SheetJS (xlsx) — en navegador |
| OAuth | league/oauth2-client |
| Hosting | Easypanel (VPS existente) |

### Estructura de carpetas

```
punch-social-dashboard/
├── frontend/
│   └── src/
│       ├── pages/
│       │   ├── Dashboard.tsx        ← vista principal
│       │   ├── Content.tsx          ← biblioteca de contenido
│       │   ├── Clients.tsx          ← CRUD de clientes
│       │   └── Import.tsx           ← subida de Excel
│       ├── components/
│       │   ├── MetricCard.tsx
│       │   ├── TimelineChart.tsx
│       │   ├── ContentThumb.tsx
│       │   ├── ClientSelector.tsx
│       │   └── PlatformTabs.tsx
│       └── lib/
│           ├── api.ts               ← fetch helpers
│           └── excel-parser.ts      ← parseo de columnas META/LinkedIn
└── backend/
    ├── api/
    │   ├── auth.php
    │   ├── clients.php
    │   ├── metrics.php
    │   ├── content.php
    │   ├── import.php
    │   └── sync.php                 ← fase 2
    ├── integrations/
    │   ├── meta.php                 ← META Graph API
    │   └── linkedin.php             ← LinkedIn API
    ├── cron/
    │   └── daily-sync.php           ← fase 2
    └── includes/
        ├── config.php
        ├── db.php
        └── auth.php
```

---

## Base de datos

### `clients`
| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT PK | |
| name | VARCHAR(100) | Nombre del cliente |
| slug | VARCHAR(50) | Identificador URL |
| active | TINYINT | 1 = activo |
| meta_page_id | VARCHAR(50) | ID de página Facebook |
| meta_ig_id | VARCHAR(50) | ID de cuenta Instagram |
| linkedin_org_id | VARCHAR(50) | ID organización LinkedIn |
| meta_token | TEXT | Token encriptado |
| meta_token_expires_at | DATETIME | Expiración token META |
| linkedin_token | TEXT | Token encriptado |
| linkedin_token_expires_at | DATETIME | Expiración token LinkedIn |
| created_at | DATETIME | |

### `metrics_snapshots`
| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT PK | |
| client_id | INT FK | |
| platform | ENUM('instagram','facebook','linkedin') | |
| snapshot_date | DATE | Fecha del dato |
| views | BIGINT | |
| reach | BIGINT | |
| impressions | BIGINT | |
| engagements | INT | |
| followers | INT | |
| content_count | INT | Posts publicados en el periodo |
| source | ENUM('api','excel') | Origen del dato |
| created_at | DATETIME | |

### `content_posts`
| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT PK | |
| client_id | INT FK | |
| platform | ENUM('instagram','facebook','linkedin') | |
| post_id | VARCHAR(100) | ID nativo de la plataforma |
| post_type | ENUM('reel','image','carousel','article','video') | |
| title | TEXT | Descripción/caption truncado |
| thumbnail_url | VARCHAR(500) | |
| published_at | DATETIME | |
| views | BIGINT | |
| likes | INT | |
| comments | INT | |
| shares | INT | |
| source | ENUM('api','excel') | |
| created_at | DATETIME | |

### `excel_imports`
| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT PK | |
| client_id | INT FK | |
| platform | VARCHAR(20) | |
| filename | VARCHAR(200) | |
| rows_imported | INT | |
| date_range_start | DATE | |
| date_range_end | DATE | |
| imported_by | VARCHAR(100) | |
| created_at | DATETIME | |

---

## Flujo de datos

### Flujo A — Importación Excel (MVP)
1. Usuario descarga reporte nativo de Instagram Insights / LinkedIn Analytics
2. Sube el archivo en `/import` del dashboard
3. `excel-parser.ts` lee las columnas en el navegador y normaliza los datos
4. Frontend envía JSON al endpoint `POST /api/import`
5. PHP guarda en `metrics_snapshots` y `content_posts` con `source = 'excel'`
6. Dashboard lee de la DB — nunca consulta las APIs en tiempo real

> **Nota sobre columnas de Excel:** Instagram Insights y LinkedIn Analytics exportan archivos con nombres de columnas distintos (y que pueden cambiar entre versiones). El `excel-parser.ts` incluirá un mapa de aliases configurable (`"Impresiones" → views`, `"Alcance" → reach`, etc.) por plataforma. Si el sistema no reconoce una columna, muestra una pantalla de mapeo manual antes de confirmar la importación.

### Flujo B — Sync automático por API (Fase 2)
1. Cron job en Easypanel ejecuta `daily-sync.php` a las 3am
2. Para cada cliente con token válido, llama a META Graph API y LinkedIn API
3. Guarda snapshot en DB con `source = 'api'`
4. Si el token expiró, guarda alerta en `clients.token_expires_at` y salta al siguiente cliente

---

## API Endpoints (backend PHP)

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/api/auth/login` | Login con email + password |
| POST | `/api/auth/logout` | Cerrar sesión |
| GET | `/api/clients` | Listar clientes |
| POST | `/api/clients` | Crear cliente |
| PUT | `/api/clients/{id}` | Editar cliente |
| GET | `/api/metrics?client_id=&platform=&from=&to=` | Métricas para dashboard |
| GET | `/api/content?client_id=&platform=&sort=` | Posts para biblioteca |
| POST | `/api/import` | Importar Excel (JSON normalizado) |
| GET | `/api/import/history?client_id=` | Historial de importaciones |

---

## UI — Componentes principales

### Dashboard (vista principal)
- **Selector de cliente** — dropdown en sidebar, persiste en localStorage
- **Platform tabs** — Todas / Instagram / Facebook / LinkedIn
- **Date range** — 7d, 1m, 3m, 1y, Custom
- **4 metric cards** — Views/Alcance, Engagements, Contenido Publicado, Seguidores (con mini sparkline y % de cambio)
- **2 gráficas** — Views over time (barras), Follower Growth (línea)
- **Content Library** — grid de thumbnails con platform badge, título, views y likes. Ordenable por Recientes / Top Views / Top Engagements

### Import (subida de Excel)
- Drag & drop de archivo .xlsx
- Preview de columnas detectadas antes de confirmar
- Selección de cliente y plataforma
- Confirmación con count de filas a importar

### Clientes (CRUD)
- Lista de 16 clientes con estado de conexión de tokens
- Formulario de edición con campos de IDs de plataforma
- Indicador visual de token próximo a expirar (< 7 días)

---

## Alcance MVP vs Fase 2

### MVP (construir primero)
- [x] Auth básico (email + password, sesión PHP)
- [x] CRUD de clientes
- [x] Importación de Excel para META y LinkedIn
- [x] Dashboard con las 4 metric cards y 2 gráficas
- [x] Biblioteca de contenido (desde datos importados)
- [x] Selector de cliente y filtros de fecha
- [x] Dark theme screenshot-friendly

### Fase 2 (después de validar con Excel)
- [ ] OAuth connect para META (Facebook + Instagram)
- [ ] OAuth connect para LinkedIn
- [ ] Sync diario automático vía cron
- [ ] Alertas de token expirado
- [ ] TikTok / YouTube (opcional)

---

## Despliegue en Easypanel

```yaml
servicios:
  punch-social:
    tipo: static (nginx)
    build: npm run build en /frontend
    dominio: social.punch.com.mx (sugerido)

  punch-social-api:
    tipo: PHP-FPM + nginx
    root: /backend
    dominio: social-api.punch.com.mx (sugerido)

  mysql:
    tipo: MySQL 8
    base_de_datos: punch_social
    (misma instancia existente o nueva)
```

---

## Decisiones de diseño

| Decisión | Razón |
|----------|-------|
| Snapshots en DB en vez de consultas live | Evita el problema histórico de datos inconsistentes entre API y dashboard |
| Excel como Flujo A (MVP) | Valida que los datos se ven correctos antes de automatizar |
| Sync automático en Fase 2 | Reduce riesgo técnico del MVP |
| Sin acceso de clientes | Simplifica auth y evita gestión de cuentas externas |
| Dark theme | Mejor para screenshots que se envían a clientes |
| Tokens encriptados en DB | Los tokens OAuth de las cuentas de cliente son sensibles |
