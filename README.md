# Plaza Aria — Sitio web (Fase 1)

Sitio web de Plaza Aria, plaza vecinal sobre Av. Huayacán, Cancún.
Construido por **Punch Marketing**. Diseñado para vender locales disponibles
y dar utilidad cotidiana a vecinos.

## Stack

- **Next.js 14** (App Router) + TypeScript
- **Tailwind CSS** con design system propio (paleta `aria-*`)
- **Airtable** como CMS (REST API; sin SDK)
- **Vitest** + Testing Library para tests
- **Vercel** para hosting · **Hostinger** para correo / dominio

## Estructura del proyecto

```
plaza-aria/
├── docs/
│   └── airtable-schema.md      # Source of truth del schema Airtable
├── scripts/
│   ├── smoke-airtable.ts       # End-to-end smoke contra la base real
│   └── inspect-airtable.ts     # Diagnóstico de campos vs schema
├── src/
│   ├── app/
│   │   ├── page.tsx            # Home
│   │   ├── directorio/
│   │   ├── agenda/
│   │   ├── renta/
│   │   ├── contacto/
│   │   └── api/revalidate/     # Webhook ISR on-demand
│   ├── components/
│   │   ├── site/               # Header, Footer, Wordmark, MobileMenu
│   │   ├── home/               # Hero, LatidoStrip, DosPuertas, etc.
│   │   ├── directorio/         # LocalCard, AbiertoBadge, HorariosTable, InstagramEmbed, Filters
│   │   └── agenda/             # EventoCard, AgendaWithFilters
│   ├── lib/
│   │   ├── airtable/           # client.ts, fetch.ts, normalize.ts, env.ts
│   │   ├── slugify.ts          # URL-safe slugs (acentos, ñ)
│   │   ├── horarios.ts         # parseHorarioBlock, estadoAhora, formatMinutos
│   │   ├── directorio/filter.ts # filtrarYOrdenar (pure)
│   │   └── agenda/group.ts     # groupEventos, plazaDateISO
│   ├── styles/
│   │   └── fonts.ts            # next/font (Fraunces + Inter)
│   └── types/
│       └── domain.ts           # Local, Evento, Config, LeadRenta, NotifVecino
└── tests/                      # Vitest + RTL
```

## Setup

### Requisitos

- Node.js 20+
- Una base de Airtable que siga `docs/airtable-schema.md`
- Un Personal Access Token de Airtable con scopes:
  - `data.records:read`
  - `data.records:write`
  - `schema.bases:read`

### Variables de entorno

Copia el archivo example y rellena los valores:

```bash
cp .env.local.example .env.local
```

Variables requeridas:

| Variable | Para qué sirve | Dónde se obtiene |
|---|---|---|
| `AIRTABLE_API_KEY` | Token para leer/escribir Airtable | https://airtable.com/create/tokens |
| `AIRTABLE_BASE_ID` | ID de la base `Plaza Aria` (empieza con `app...`) | URL de la base en Airtable |
| `AIRTABLE_REVALIDATE_SECRET` | Secreto para el webhook de revalidación on-demand | Cualquier string largo aleatorio (mínimo 32 chars) |
| `NEXT_PUBLIC_SITE_URL` | URL absoluta usada en metadata, OG tags, sitemaps | `http://localhost:3000` (local) o el dominio en producción |

### Instalación

```bash
npm install
```

### Desarrollo local

```bash
npm run dev
```

Abre http://localhost:3000

### Smoke test contra Airtable real

Útil cuando cambia el schema o cuando empiezas con una base nueva:

```bash
npx tsx scripts/smoke-airtable.ts
```

Conecta a tu base real (usa `.env.local`) y reporta lo que devuelve el cliente para cada tabla.

Para diagnosticar mismatches entre nombres de campo en el código vs la base:

```bash
npx tsx scripts/inspect-airtable.ts
```

## Scripts

| Comando | Qué hace |
|---|---|
| `npm run dev` | Servidor local con hot reload |
| `npm run build` | Build de producción |
| `npm start` | Sirve el build de producción |
| `npm test` | Corre todos los tests una vez |
| `npm run test:watch` | Tests en modo watch |

## Airtable Schema

Ver `docs/airtable-schema.md`. Es el contrato entre la base y el código.

⚠️ El nombre del campo `Whatsapp` en la base está guardado como `Whastapp` (typo).
El normalizador lo respeta. Si renombras el campo en Airtable, también
actualiza `src/lib/airtable/normalize.ts`.

## Webhook de revalidación

Para refrescar el sitio al instante cuando cambia algo en Airtable
(sin esperar los 5 min de ISR):

**Endpoint:** `POST /api/revalidate`

**Autenticación:** uno de estos dos:
- Header: `Authorization: Bearer <AIRTABLE_REVALIDATE_SECRET>`
- Query string: `?secret=<AIRTABLE_REVALIDATE_SECRET>`

**Body (opcional):**
```json
{ "paths": ["/", "/directorio", "/agenda", "/renta"] }
```

Sin body, refresca los 4 paths por default.

### Configurar Airtable Automations

1. En la base, ve a `Automations` → New automation.
2. Trigger: "When a record matches conditions" en la tabla `Locales`,
   o "When a record is updated".
3. Action: "Send HTTP request" con:
   - Method: `POST`
   - URL: `https://<tu-dominio>/api/revalidate?secret=<secret>`
   - No body necesario para refrescar todo el sitio.

## Deploy

Resumen rápido del despliegue (T23 del plan):

1. Push del branch `feat/plaza-aria-fase1` a GitHub
2. Import del repo en Vercel
3. Configurar las 4 env vars en Vercel (Settings → Environment Variables)
4. Apuntar el dominio de Hostinger a Vercel vía DNS

Hostinger se conserva para correo + email marketing. Vercel sirve el sitio.

## Fases siguientes

Lo que **no** está en Fase 1 (queda para Fase 2 / Fase 3):

- Plano interactivo SVG (centro de la propuesta de leasing)
- Dashboard "¿Por qué Aria?" con demografía detallada
- Formulario por local + generador de PDF de propuesta
- Tour virtual / video drone embebido en hero
- "Day-to-Night" hero animado
- "Latido de Aria" en tiempo real con UI rica
- Sábado en Aria
- Easter egg para pitch
- Notificaciones opt-in para vecinos

Ver `docs/superpowers/specs/2026-05-19-plaza-aria-website-design.md` para el detalle.

---

Hecho con cariño por **Punch Marketing**.
