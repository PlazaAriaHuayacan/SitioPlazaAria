# Plaza Aria — Airtable Schema

This document defines the structure of the Airtable base that powers the Plaza Aria website. Field names and option values listed here are the source of truth — the website code reads these names verbatim. Don't rename a field in Airtable without updating the code.

## Base setup

- Workspace: Punch Marketing
- Base name: `Plaza Aria`
- Locale / timezone of the records: America/Cancún (UTC-5, no DST)

## Tables

### 1. `Locales`

| Field | Type | Notes |
|---|---|---|
| `Nombre` | Single line text | Public-facing name of the business or "DISPONIBLE" if vacant. |
| `Slug` | Formula | `LOWER(SUBSTITUTE({Nombre}, " ", "-"))` — used for URL paths. Override manually for special cases. |
| `Giro` | Single select | Options: `Restaurante`, `Belleza`, `Fitness`, `Educación`, `Hogar`, `Servicios`, `Otro`. |
| `Estado` | Single select | Options: `Ocupado`, `Disponible`, `Próximamente`. |
| `Piso` | Single select | Options: `1`, `2`. |
| `NumeroLocal` | Single line text | E.g. `A-12`, `B-04`. |
| `CoordX` | Number | X position on the floor plan SVG (px). Used in Fase 2. |
| `CoordY` | Number | Y position on the floor plan SVG (px). Used in Fase 2. |
| `Ancho` | Number | Width on the floor plan SVG (px). Used in Fase 2. |
| `Alto` | Number | Height on the floor plan SVG (px). Used in Fase 2. |
| `M2` | Number | Total square meters. Visible publicly only if `Estado = Disponible`. |
| `Frente` | Number | Meters of facade. Disponible only. |
| `Renta` | Currency | MXN, monthly. Disponible only. |
| `Mantenimiento` | Currency | MXN, monthly. Disponible only. |
| `Fotos` | Attachment | Multiple images of the local. |
| `Logo` | Attachment | Single logo image (transparent PNG preferred). |
| `Descripcion` | Long text | 1-2 sentence description, public. |
| `HorarioLunes` | Single line text | Format: `HH:MM-HH:MM` (24h) or empty if closed. Example: `10:00-21:00`. |
| `HorarioMartes` | Single line text | Same format. |
| `HorarioMiercoles` | Single line text | Same format. |
| `HorarioJueves` | Single line text | Same format. |
| `HorarioViernes` | Single line text | Same format. |
| `HorarioSabado` | Single line text | Same format. |
| `HorarioDomingo` | Single line text | Same format. |
| `Whatsapp` | Phone | E.164 format preferred (`+52998…`). |
| `Telefono` | Phone | Optional secondary number. |
| `Instagram` | Single line text | Handle without `@` (e.g., `plaza_aria`). |
| `MenuPDF` | URL | Direct link to a public menu PDF. Optional. |
| `LinkReservar` | URL | External link (Calendly, IG DM, etc.) for reservations. Optional. |
| `Instalaciones` | Multiple select | Options: `Agua`, `Luz`, `A/C`, `Drenaje`, `Gas`. |

### 2. `Eventos_Clases`

| Field | Type | Notes |
|---|---|---|
| `Titulo` | Single line text | Public name of the class or event. |
| `Local` | Link to `Locales` | The host business. Required. |
| `Tipo` | Single select | Options: `Fitness`, `Educación`, `Lifestyle`, `Evento`. |
| `Recurrencia` | Single select | Options: `Único`, `Semanal`, `Mensual`. |
| `Fecha` | Date | First / only occurrence. |
| `HoraInicio` | Single line text | `HH:MM`. |
| `HoraFin` | Single line text | `HH:MM`. |
| `Cupo` | Number | Optional. |
| `Descripcion` | Long text | Public. |
| `Foto` | Attachment | Single image. |
| `LinkReservar` | URL | WhatsApp / IG / external. |

### 3. `Leads_Renta` (write-only from the website)

| Field | Type | Notes |
|---|---|---|
| `Fecha` | Created time | Auto. |
| `Nombre` | Single line text | From form. |
| `Whatsapp` | Phone | From form. |
| `Email` | Email | From form. |
| `LocalInteresado` | Link to `Locales` | Optional, single record. |
| `GiroPropuesto` | Single line text | From form. |
| `Mensaje` | Long text | From form. |
| `EstadoSeguimiento` | Single select | Options: `Nuevo`, `Contactado`, `Visita agendada`, `Cerrado ganado`, `Cerrado perdido`. Default `Nuevo`. |

### 4. `Notif_Vecinos`

| Field | Type | Notes |
|---|---|---|
| `Email` | Email | Required. |
| `TipoInteres` | Multiple select | Options: `Aperturas`, `Eventos`, `Clases`, `Promociones`. |
| `Fecha` | Created time | Auto. |

### 5. `Config` (single record)

| Field | Type | Notes |
|---|---|---|
| `HeroVideoURL` | URL | Main drone video. |
| `FotosGenerales` | Attachment | Plaza ambience photos. |
| `GapsGiros` | Long text | Comma-separated list of categories the plaza is looking for (e.g. `Panadería, Cafetería, Farmacia`). |
| `AforoEstimado` | Number | Visitors per week. |
| `CajonesEstacionamiento` | Number | |
| `Demografia` | Long text | Free-text bullets about the zone demographics. |

## Notes for implementers

- The website reads via the Airtable REST API with `view=Grid view` (the default). If you create custom views, don't make them the API default.
- `Estado = Disponible` is the trigger for showing leasing fields publicly. The API client must hide `Renta`, `Mantenimiento`, `M2`, `Frente`, `Fotos` (of empty space) when `Estado = Ocupado`.
- Horario fields use 24h `HH:MM-HH:MM` for the open hour and close hour of a single contiguous block. Split shifts (e.g. siesta) are out of scope for Fase 1.
- All timestamps stored in Airtable are interpreted in `America/Cancún` (UTC-5, no DST). The site uses the visitor's local clock only for greetings, not for "abierto ahora" which must be evaluated in plaza time.
