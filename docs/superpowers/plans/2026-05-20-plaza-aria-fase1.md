# Plaza Aria — Fase 1: Foundation + Directorio Vecinos

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a Next.js site with full Airtable-driven directorio of Plaza Aria locales, a functional agenda, and the home/navigation skeleton — deployable to Vercel and presentable as a working demo.

**Architecture:** Next.js 14 App Router with TypeScript + Tailwind. Server components fetch from Airtable REST API. ISR with 5-minute revalidation plus on-demand webhook revalidation. The directorio and agenda are read-only from Airtable; no forms or interactive plano in this phase.

**Tech Stack:** Next.js 14, TypeScript, Tailwind CSS, Vitest, Airtable REST API, Vercel hosting.

**Reference spec:** `docs/superpowers/specs/2026-05-19-plaza-aria-website-design.md`

**Project root for this phase:** `plaza-aria/` (new directory inside the SINERGIA Proyectos workspace).

---

## File Structure

```
plaza-aria/
├── package.json
├── tsconfig.json
├── next.config.mjs
├── tailwind.config.ts
├── postcss.config.mjs
├── vitest.config.ts
├── .env.local.example
├── .gitignore
├── docs/
│   └── airtable-schema.md          # Client-facing schema reference
├── public/
│   └── (placeholder images)
├── src/
│   ├── app/
│   │   ├── layout.tsx              # Root layout
│   │   ├── page.tsx                # Home
│   │   ├── globals.css
│   │   ├── directorio/
│   │   │   ├── page.tsx            # Directorio index
│   │   │   └── [slug]/
│   │   │       └── page.tsx        # Ficha de local
│   │   ├── agenda/
│   │   │   └── page.tsx
│   │   ├── contacto/
│   │   │   └── page.tsx
│   │   └── api/
│   │       └── revalidate/
│   │           └── route.ts        # Airtable webhook revalidation
│   ├── components/
│   │   ├── SiteHeader.tsx
│   │   ├── SiteFooter.tsx
│   │   ├── LocalCard.tsx
│   │   ├── DirectorioFilters.tsx
│   │   ├── HorariosTable.tsx
│   │   ├── AbiertoBadge.tsx
│   │   ├── InstagramEmbed.tsx
│   │   └── AgendaList.tsx
│   ├── lib/
│   │   ├── airtable.ts             # REST client + typed fetchers
│   │   ├── horarios.ts             # "abierto ahora" logic
│   │   └── slug.ts                 # slugify utility
│   └── types/
│       └── airtable.ts             # Domain types
└── tests/
    ├── horarios.test.ts
    ├── slug.test.ts
    └── airtable.test.ts
```

---

## Task 1: Project scaffolding

**Files:**
- Create: `plaza-aria/package.json`, `plaza-aria/tsconfig.json`, `plaza-aria/next.config.mjs`, `plaza-aria/tailwind.config.ts`, `plaza-aria/postcss.config.mjs`, `plaza-aria/.gitignore`, `plaza-aria/.env.local.example`, `plaza-aria/src/app/layout.tsx`, `plaza-aria/src/app/page.tsx`, `plaza-aria/src/app/globals.css`

- [ ] **Step 1: Create Next.js project**

Run from the SINERGIA Proyectos root:

```bash
npx create-next-app@14 plaza-aria \
  --typescript --tailwind --app --src-dir \
  --no-eslint --import-alias "@/*" \
  --use-npm
```

When prompted to use Turbopack, accept defaults (no).

Expected: a `plaza-aria/` directory is created with Next.js 14 scaffolding.

- [ ] **Step 2: Verify dev server starts**

```bash
cd plaza-aria
npm run dev
```

Open http://localhost:3000 — expect the default Next.js welcome page. Stop the server with Ctrl-C.

- [ ] **Step 3: Add `.env.local.example`**

Create `plaza-aria/.env.local.example`:

```
# Airtable
AIRTABLE_API_KEY=
AIRTABLE_BASE_ID=
AIRTABLE_REVALIDATE_SECRET=

# Site
NEXT_PUBLIC_SITE_URL=http://localhost:3000
```

- [ ] **Step 4: Update `.gitignore`**

Append to `plaza-aria/.gitignore` (after the existing content):

```
# Local env
.env.local
.env*.local
```

- [ ] **Step 5: Commit scaffolding**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos"
git add plaza-aria/
git commit -m "feat(plaza-aria): scaffold Next.js 14 project"
```

---

## Task 2: Testing setup with Vitest

**Files:**
- Create: `plaza-aria/vitest.config.ts`, `plaza-aria/tests/smoke.test.ts`
- Modify: `plaza-aria/package.json`

- [ ] **Step 1: Install Vitest**

```bash
cd plaza-aria
npm install -D vitest @vitest/ui @types/node
```

- [ ] **Step 2: Create `vitest.config.ts`**

```ts
import { defineConfig } from 'vitest/config';
import path from 'path';

export default defineConfig({
  test: {
    environment: 'node',
    include: ['tests/**/*.test.ts'],
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
});
```

- [ ] **Step 3: Add test script to `package.json`**

In `plaza-aria/package.json`, add to `"scripts"`:

```json
"test": "vitest run",
"test:watch": "vitest"
```

- [ ] **Step 4: Write smoke test**

Create `plaza-aria/tests/smoke.test.ts`:

```ts
import { describe, it, expect } from 'vitest';

describe('smoke', () => {
  it('runs', () => {
    expect(1 + 1).toBe(2);
  });
});
```

- [ ] **Step 5: Run tests**

```bash
npm test
```

Expected: 1 passing test.

- [ ] **Step 6: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos"
git add plaza-aria/
git commit -m "test(plaza-aria): add vitest setup"
```

---

## Task 3: Airtable schema documentation (client-facing)

This document is what we hand to the client (or fill in ourselves) to set up Airtable. It is the source of truth that the TypeScript types in Task 4 must match.

**Files:**
- Create: `plaza-aria/docs/airtable-schema.md`

- [ ] **Step 1: Write schema doc**

Create `plaza-aria/docs/airtable-schema.md`:

```markdown
# Plaza Aria — Esquema de Airtable

El sitio web lee de esta base de Airtable. Cualquier cambio aquí se refleja en el sitio en máximo 5 minutos (o instantáneamente si está configurado el webhook).

## Base: "Plaza Aria"

### Tabla 1: `Locales`

| Campo | Tipo | Notas |
|---|---|---|
| `Nombre` | Single line text | Obligatorio. Aparece como título. |
| `Slug` | Formula | `LOWER(SUBSTITUTE({Nombre}, " ", "-"))` |
| `Giro` | Single select | Restaurantes, Belleza, Fitness, Educación, Hogar, Servicios, Otros |
| `Estado` | Single select | Ocupado, Disponible, Próximamente |
| `Piso` | Number (integer) | 1 o 2 |
| `Numero_Local` | Single line text | Ej. "L-12" |
| `Plano_X` | Number | Coordenada X en el plano (Fase 2) |
| `Plano_Y` | Number | Coordenada Y |
| `Plano_W` | Number | Ancho |
| `Plano_H` | Number | Alto |
| `M2` | Number | Solo si Disponible |
| `Frente` | Number (metros) | Solo si Disponible |
| `Renta` | Currency | Solo si Disponible |
| `Mantenimiento` | Currency | Solo si Disponible |
| `Fotos` | Attachment | Múltiples |
| `Logo` | Attachment | Una imagen cuadrada |
| `Descripcion` | Long text | 1-2 párrafos |
| `Hora_Apertura_Lun` ... `Hora_Apertura_Dom` | Single line text | Formato `HH:MM` (24h) o vacío si cerrado |
| `Hora_Cierre_Lun` ... `Hora_Cierre_Dom` | Single line text | Formato `HH:MM` |
| `WhatsApp` | Phone number | Con código de país |
| `Telefono` | Phone number | |
| `Instagram` | Single line text | Sin @ ej. `plaza_aria` |
| `Menu_PDF` | URL | |
| `Link_Reservar` | URL | |
| `Instalaciones` | Multiple select | Agua, Luz, A/C, Drenaje, Gas |

### Tabla 2: `Eventos_Clases`

| Campo | Tipo | Notas |
|---|---|---|
| `Titulo` | Single line text | |
| `Local` | Link to Locales | |
| `Tipo` | Single select | Fitness, Educación, Lifestyle, Evento |
| `Dia_Semana` | Multiple select | Lun, Mar, Mié, Jue, Vie, Sáb, Dom (vacío si fecha única) |
| `Fecha_Unica` | Date | Solo si es evento único |
| `Hora_Inicio` | Single line text | `HH:MM` |
| `Hora_Fin` | Single line text | `HH:MM` |
| `Cupo` | Number | |
| `Descripcion` | Long text | |
| `Foto` | Attachment | |
| `Link_Reservar` | URL | WhatsApp o IG |

### Tabla 3: `Leads_Renta` (escritura desde el sitio — Fase 2)

| Campo | Tipo |
|---|---|
| `Fecha` | Created time |
| `Nombre` | Single line text |
| `WhatsApp` | Phone number |
| `Email` | Email |
| `Local_Interesado` | Link to Locales |
| `Giro_Propuesto` | Single line text |
| `Mensaje` | Long text |
| `Estado` | Single select (Nuevo, Contactado, Visita, Cerrado, Perdido) |

### Tabla 4: `Notif_Vecinos` (Fase 2)

| Campo | Tipo |
|---|---|
| `Email` | Email |
| `Tipo_Interes` | Multiple select |
| `Fecha_Alta` | Created time |

### Tabla 5: `Config` (un solo registro)

| Campo | Tipo |
|---|---|
| `Hero_Video_URL` | URL |
| `Fotos_Plaza` | Attachment (múltiples) |
| `Gaps_Giros` | Multiple select | Giros faltantes a mostrar como oportunidad |
| `Aforo_Estimado` | Number |
| `Cajones_Estacionamiento` | Number |
| `Demografia_Ingreso_Promedio` | Currency |
| `Demografia_Edad_Promedio` | Number |
| `Demografia_Hogares_5km` | Number |
```

- [ ] **Step 2: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos"
git add plaza-aria/docs/airtable-schema.md
git commit -m "docs(plaza-aria): document Airtable schema"
```

---

## Task 4: TypeScript types matching Airtable schema

**Files:**
- Create: `plaza-aria/src/types/airtable.ts`

- [ ] **Step 1: Write types**

Create `plaza-aria/src/types/airtable.ts`:

```ts
export type Dia = 'Lun' | 'Mar' | 'Mié' | 'Jue' | 'Vie' | 'Sáb' | 'Dom';

export const DIAS: Dia[] = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

export type Giro =
  | 'Restaurantes'
  | 'Belleza'
  | 'Fitness'
  | 'Educación'
  | 'Hogar'
  | 'Servicios'
  | 'Otros';

export type EstadoLocal = 'Ocupado' | 'Disponible' | 'Próximamente';

export type Instalacion = 'Agua' | 'Luz' | 'A/C' | 'Drenaje' | 'Gas';

export interface AirtableAttachment {
  id: string;
  url: string;
  filename: string;
  width?: number;
  height?: number;
  thumbnails?: {
    small: { url: string; width: number; height: number };
    large: { url: string; width: number; height: number };
  };
}

// Horario por día — null si cerrado
export interface HorarioDia {
  apertura: string | null; // "HH:MM"
  cierre: string | null;
}

export type HorariosSemana = Record<Dia, HorarioDia>;

export interface Local {
  id: string;
  nombre: string;
  slug: string;
  giro: Giro;
  estado: EstadoLocal;
  piso: 1 | 2;
  numeroLocal: string;
  plano: { x: number; y: number; w: number; h: number } | null;
  m2: number | null;
  frente: number | null;
  renta: number | null;
  mantenimiento: number | null;
  fotos: AirtableAttachment[];
  logo: AirtableAttachment | null;
  descripcion: string;
  horarios: HorariosSemana;
  whatsapp: string | null;
  telefono: string | null;
  instagram: string | null;
  menuPdf: string | null;
  linkReservar: string | null;
  instalaciones: Instalacion[];
}

export type TipoEvento = 'Fitness' | 'Educación' | 'Lifestyle' | 'Evento';

export interface EventoClase {
  id: string;
  titulo: string;
  localId: string | null;
  localNombre: string | null;
  tipo: TipoEvento;
  diasSemana: Dia[];
  fechaUnica: string | null; // ISO date
  horaInicio: string; // "HH:MM"
  horaFin: string;
  cupo: number | null;
  descripcion: string;
  foto: AirtableAttachment | null;
  linkReservar: string | null;
}

export interface Config {
  heroVideoUrl: string | null;
  fotosPlaza: AirtableAttachment[];
  gapsGiros: string[];
  aforoEstimado: number | null;
  cajonesEstacionamiento: number | null;
  demografia: {
    ingresoPromedio: number | null;
    edadPromedio: number | null;
    hogares5km: number | null;
  };
}
```

- [ ] **Step 2: Type-check**

```bash
cd plaza-aria
npx tsc --noEmit
```

Expected: no errors.

- [ ] **Step 3: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos"
git add plaza-aria/src/types/
git commit -m "feat(plaza-aria): add domain types"
```

---

## Task 5: Slugify utility (TDD)

**Files:**
- Create: `plaza-aria/src/lib/slug.ts`, `plaza-aria/tests/slug.test.ts`

- [ ] **Step 1: Write failing test**

Create `plaza-aria/tests/slug.test.ts`:

```ts
import { describe, it, expect } from 'vitest';
import { slugify } from '@/lib/slug';

describe('slugify', () => {
  it('lowercases and replaces spaces with hyphens', () => {
    expect(slugify('Café Aria')).toBe('cafe-aria');
  });

  it('removes accents', () => {
    expect(slugify('Peluquería Niño')).toBe('peluqueria-nino');
  });

  it('strips non-alphanumeric chars', () => {
    expect(slugify('Mike & Ana #1')).toBe('mike-ana-1');
  });

  it('collapses consecutive hyphens', () => {
    expect(slugify('A   B---C')).toBe('a-b-c');
  });

  it('trims leading and trailing hyphens', () => {
    expect(slugify('  hola  ')).toBe('hola');
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd plaza-aria
npm test
```

Expected: FAIL — module `@/lib/slug` not found.

- [ ] **Step 3: Implement slugify**

Create `plaza-aria/src/lib/slug.ts`:

```ts
export function slugify(input: string): string {
  return input
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}
```

- [ ] **Step 4: Run test to verify pass**

```bash
npm test
```

Expected: all slug tests PASS.

- [ ] **Step 5: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos"
git add plaza-aria/
git commit -m "feat(plaza-aria): add slugify utility"
```

---

## Task 6: "Abierto ahora" logic (TDD)

**Files:**
- Create: `plaza-aria/src/lib/horarios.ts`, `plaza-aria/tests/horarios.test.ts`

- [ ] **Step 1: Write failing test**

Create `plaza-aria/tests/horarios.test.ts`:

```ts
import { describe, it, expect } from 'vitest';
import { estadoApertura } from '@/lib/horarios';
import type { HorariosSemana } from '@/types/airtable';

const horarios: HorariosSemana = {
  Lun: { apertura: '09:00', cierre: '20:00' },
  Mar: { apertura: '09:00', cierre: '20:00' },
  Mié: { apertura: '09:00', cierre: '20:00' },
  Jue: { apertura: '09:00', cierre: '20:00' },
  Vie: { apertura: '09:00', cierre: '22:00' },
  Sáb: { apertura: '10:00', cierre: '22:00' },
  Dom: { apertura: null, cierre: null },
};

describe('estadoApertura', () => {
  it('returns abierto when within hours', () => {
    // Monday at 12:00
    const lunes12 = new Date('2026-05-18T12:00:00-05:00');
    expect(estadoApertura(horarios, lunes12).estado).toBe('abierto');
  });

  it('returns cierra-pronto within 60 min of close', () => {
    // Monday at 19:30 (closes 20:00)
    const lunes1930 = new Date('2026-05-18T19:30:00-05:00');
    expect(estadoApertura(horarios, lunes1930).estado).toBe('cierra-pronto');
  });

  it('returns cerrado before opening', () => {
    const lunes7 = new Date('2026-05-18T07:00:00-05:00');
    expect(estadoApertura(horarios, lunes7).estado).toBe('cerrado');
  });

  it('returns cerrado after closing', () => {
    const lunes21 = new Date('2026-05-18T21:00:00-05:00');
    expect(estadoApertura(horarios, lunes21).estado).toBe('cerrado');
  });

  it('returns cerrado on a closed day (Sunday)', () => {
    const domingo12 = new Date('2026-05-17T12:00:00-05:00');
    expect(estadoApertura(horarios, domingo12).estado).toBe('cerrado');
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd plaza-aria
npm test horarios
```

Expected: FAIL — module not found.

- [ ] **Step 3: Implement `estadoApertura`**

Create `plaza-aria/src/lib/horarios.ts`:

```ts
import type { HorariosSemana, Dia } from '@/types/airtable';

export type EstadoApertura = 'abierto' | 'cierra-pronto' | 'cerrado';

const JS_DAY_TO_DIA: Record<number, Dia> = {
  0: 'Dom',
  1: 'Lun',
  2: 'Mar',
  3: 'Mié',
  4: 'Jue',
  5: 'Vie',
  6: 'Sáb',
};

interface Result {
  estado: EstadoApertura;
  cierraEn?: number; // minutos hasta el cierre, si abierto
}

function minutosDesdeMedianoche(hhmm: string): number {
  const [h, m] = hhmm.split(':').map(Number);
  return h * 60 + m;
}

export function estadoApertura(horarios: HorariosSemana, ahora: Date = new Date()): Result {
  const dia = JS_DAY_TO_DIA[ahora.getDay()];
  const horario = horarios[dia];

  if (!horario.apertura || !horario.cierre) {
    return { estado: 'cerrado' };
  }

  const ahoraMin = ahora.getHours() * 60 + ahora.getMinutes();
  const aperturaMin = minutosDesdeMedianoche(horario.apertura);
  const cierreMin = minutosDesdeMedianoche(horario.cierre);

  if (ahoraMin < aperturaMin || ahoraMin >= cierreMin) {
    return { estado: 'cerrado' };
  }

  const minutosParaCierre = cierreMin - ahoraMin;
  if (minutosParaCierre <= 60) {
    return { estado: 'cierra-pronto', cierraEn: minutosParaCierre };
  }

  return { estado: 'abierto', cierraEn: minutosParaCierre };
}
```

- [ ] **Step 4: Run tests**

```bash
npm test horarios
```

Expected: all PASS.

- [ ] **Step 5: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos"
git add plaza-aria/
git commit -m "feat(plaza-aria): add horarios abierto-ahora logic"
```

---

## Task 7: Airtable REST client

**Files:**
- Create: `plaza-aria/src/lib/airtable.ts`, `plaza-aria/tests/airtable.test.ts`

- [ ] **Step 1: Write failing test (mapper unit)**

Create `plaza-aria/tests/airtable.test.ts`:

```ts
import { describe, it, expect } from 'vitest';
import { mapLocal } from '@/lib/airtable';

describe('mapLocal', () => {
  it('maps a fully-populated record', () => {
    const raw = {
      id: 'recABC',
      fields: {
        Nombre: 'Café Aria',
        Slug: 'cafe-aria',
        Giro: 'Restaurantes',
        Estado: 'Ocupado',
        Piso: 1,
        Numero_Local: 'L-12',
        Hora_Apertura_Lun: '09:00',
        Hora_Cierre_Lun: '20:00',
        WhatsApp: '+529981234567',
        Instagram: 'cafe_aria',
        Instalaciones: ['Agua', 'Luz'],
        Fotos: [{ id: 'a1', url: 'https://x/1.jpg', filename: '1.jpg' }],
      },
    };
    const local = mapLocal(raw);
    expect(local.id).toBe('recABC');
    expect(local.nombre).toBe('Café Aria');
    expect(local.slug).toBe('cafe-aria');
    expect(local.giro).toBe('Restaurantes');
    expect(local.estado).toBe('Ocupado');
    expect(local.piso).toBe(1);
    expect(local.horarios.Lun.apertura).toBe('09:00');
    expect(local.horarios.Dom.apertura).toBeNull();
    expect(local.instalaciones).toEqual(['Agua', 'Luz']);
    expect(local.fotos).toHaveLength(1);
  });

  it('falls back to slugified name when Slug field is missing', () => {
    const raw = { id: 'r1', fields: { Nombre: 'Estética Niña' } };
    expect(mapLocal(raw).slug).toBe('estetica-nina');
  });

  it('defaults missing fields safely', () => {
    const raw = { id: 'r2', fields: { Nombre: 'X' } };
    const l = mapLocal(raw);
    expect(l.fotos).toEqual([]);
    expect(l.instalaciones).toEqual([]);
    expect(l.whatsapp).toBeNull();
  });
});
```

- [ ] **Step 2: Run test to verify failure**

```bash
cd plaza-aria
npm test airtable
```

Expected: FAIL — module not found.

- [ ] **Step 3: Implement Airtable client**

Create `plaza-aria/src/lib/airtable.ts`:

```ts
import type {
  Local,
  EventoClase,
  Config,
  HorariosSemana,
  Dia,
  Giro,
  EstadoLocal,
  Instalacion,
  TipoEvento,
  AirtableAttachment,
} from '@/types/airtable';
import { slugify } from './slug';

const BASE_URL = 'https://api.airtable.com/v0';
const REVALIDATE_SECONDS = 300; // 5 minutos

function env(name: string): string {
  const v = process.env[name];
  if (!v) throw new Error(`Missing env var ${name}`);
  return v;
}

async function fetchTable<T>(table: string, mapper: (rec: any) => T): Promise<T[]> {
  const apiKey = env('AIRTABLE_API_KEY');
  const baseId = env('AIRTABLE_BASE_ID');
  const url = `${BASE_URL}/${baseId}/${encodeURIComponent(table)}`;
  const res = await fetch(url, {
    headers: { Authorization: `Bearer ${apiKey}` },
    next: { revalidate: REVALIDATE_SECONDS, tags: [table] },
  });
  if (!res.ok) {
    throw new Error(`Airtable ${table} fetch failed: ${res.status}`);
  }
  const data = (await res.json()) as { records: any[] };
  return data.records.map(mapper);
}

const DIA_FIELD_SUFFIX: Record<Dia, string> = {
  Lun: 'Lun',
  Mar: 'Mar',
  Mié: 'Mie',
  Jue: 'Jue',
  Vie: 'Vie',
  Sáb: 'Sab',
  Dom: 'Dom',
};

function buildHorarios(fields: any): HorariosSemana {
  const out = {} as HorariosSemana;
  (Object.keys(DIA_FIELD_SUFFIX) as Dia[]).forEach((dia) => {
    const suffix = DIA_FIELD_SUFFIX[dia];
    const apertura = fields[`Hora_Apertura_${suffix}`] ?? null;
    const cierre = fields[`Hora_Cierre_${suffix}`] ?? null;
    out[dia] = { apertura, cierre };
  });
  return out;
}

function firstAttachment(arr: any): AirtableAttachment | null {
  if (Array.isArray(arr) && arr.length > 0) return arr[0] as AirtableAttachment;
  return null;
}

export function mapLocal(rec: any): Local {
  const f = rec.fields ?? {};
  const nombre = f.Nombre ?? 'Sin nombre';
  return {
    id: rec.id,
    nombre,
    slug: f.Slug ?? slugify(nombre),
    giro: (f.Giro ?? 'Otros') as Giro,
    estado: (f.Estado ?? 'Ocupado') as EstadoLocal,
    piso: (f.Piso === 2 ? 2 : 1) as 1 | 2,
    numeroLocal: f.Numero_Local ?? '',
    plano:
      typeof f.Plano_X === 'number'
        ? { x: f.Plano_X, y: f.Plano_Y, w: f.Plano_W, h: f.Plano_H }
        : null,
    m2: f.M2 ?? null,
    frente: f.Frente ?? null,
    renta: f.Renta ?? null,
    mantenimiento: f.Mantenimiento ?? null,
    fotos: Array.isArray(f.Fotos) ? (f.Fotos as AirtableAttachment[]) : [],
    logo: firstAttachment(f.Logo),
    descripcion: f.Descripcion ?? '',
    horarios: buildHorarios(f),
    whatsapp: f.WhatsApp ?? null,
    telefono: f.Telefono ?? null,
    instagram: f.Instagram ?? null,
    menuPdf: f.Menu_PDF ?? null,
    linkReservar: f.Link_Reservar ?? null,
    instalaciones: (f.Instalaciones ?? []) as Instalacion[],
  };
}

export function mapEvento(rec: any): EventoClase {
  const f = rec.fields ?? {};
  return {
    id: rec.id,
    titulo: f.Titulo ?? '',
    localId: Array.isArray(f.Local) ? f.Local[0] : null,
    localNombre: null, // resolved by caller via Locales lookup
    tipo: (f.Tipo ?? 'Evento') as TipoEvento,
    diasSemana: (f.Dia_Semana ?? []) as Dia[],
    fechaUnica: f.Fecha_Unica ?? null,
    horaInicio: f.Hora_Inicio ?? '',
    horaFin: f.Hora_Fin ?? '',
    cupo: f.Cupo ?? null,
    descripcion: f.Descripcion ?? '',
    foto: firstAttachment(f.Foto),
    linkReservar: f.Link_Reservar ?? null,
  };
}

export function mapConfig(rec: any): Config {
  const f = rec.fields ?? {};
  return {
    heroVideoUrl: f.Hero_Video_URL ?? null,
    fotosPlaza: Array.isArray(f.Fotos_Plaza) ? (f.Fotos_Plaza as AirtableAttachment[]) : [],
    gapsGiros: (f.Gaps_Giros ?? []) as string[],
    aforoEstimado: f.Aforo_Estimado ?? null,
    cajonesEstacionamiento: f.Cajones_Estacionamiento ?? null,
    demografia: {
      ingresoPromedio: f.Demografia_Ingreso_Promedio ?? null,
      edadPromedio: f.Demografia_Edad_Promedio ?? null,
      hogares5km: f.Demografia_Hogares_5km ?? null,
    },
  };
}

export async function getLocales(): Promise<Local[]> {
  return fetchTable('Locales', mapLocal);
}

export async function getLocalBySlug(slug: string): Promise<Local | null> {
  const locales = await getLocales();
  return locales.find((l) => l.slug === slug) ?? null;
}

export async function getEventos(): Promise<EventoClase[]> {
  return fetchTable('Eventos_Clases', mapEvento);
}

export async function getConfig(): Promise<Config | null> {
  const all = await fetchTable('Config', mapConfig);
  return all[0] ?? null;
}
```

- [ ] **Step 4: Run tests**

```bash
npm test
```

Expected: all PASS (slug + horarios + airtable mapper).

- [ ] **Step 5: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos"
git add plaza-aria/
git commit -m "feat(plaza-aria): add Airtable client and mappers"
```

---

## Task 8: Tailwind theme tokens

**Files:**
- Modify: `plaza-aria/tailwind.config.ts`, `plaza-aria/src/app/globals.css`

The brand palette will be refined when we get brand assets, but we set placeholders that look intentional — not the default Tailwind look.

- [ ] **Step 1: Update `tailwind.config.ts`**

Replace `plaza-aria/tailwind.config.ts` with:

```ts
import type { Config } from 'tailwindcss';

const config: Config = {
  content: ['./src/**/*.{ts,tsx}'],
  theme: {
    extend: {
      colors: {
        aria: {
          bg: '#F7F4EE',       // arena cálida
          ink: '#1B1B1B',      // tinta principal
          mute: '#5B5953',     // texto secundario
          line: '#E4DFD5',     // borde sutil
          accent: '#C4A77D',   // dorado quemado (placeholder)
          live: '#3D9A57',     // verde para "abierto"
          warn: '#D98E2B',     // ámbar para "cierra pronto"
        },
      },
      fontFamily: {
        display: ['var(--font-display)', 'serif'],
        body: ['var(--font-body)', 'sans-serif'],
      },
      maxWidth: {
        container: '1200px',
      },
    },
  },
  plugins: [],
};
export default config;
```

- [ ] **Step 2: Update `globals.css`**

Replace `plaza-aria/src/app/globals.css` with:

```css
@tailwind base;
@tailwind components;
@tailwind utilities;

:root {
  --font-display: ui-serif, Georgia, serif;
  --font-body: ui-sans-serif, system-ui, sans-serif;
}

html, body {
  background: theme('colors.aria.bg');
  color: theme('colors.aria.ink');
  font-family: var(--font-body);
}

h1, h2, h3 {
  font-family: var(--font-display);
  letter-spacing: -0.01em;
}
```

- [ ] **Step 3: Verify build**

```bash
cd plaza-aria
npm run build
```

Expected: build succeeds.

- [ ] **Step 4: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos"
git add plaza-aria/
git commit -m "feat(plaza-aria): add brand theme tokens"
```

---

## Task 9: Site layout (header + footer + navigation)

**Files:**
- Create: `plaza-aria/src/components/SiteHeader.tsx`, `plaza-aria/src/components/SiteFooter.tsx`
- Modify: `plaza-aria/src/app/layout.tsx`

- [ ] **Step 1: Create `SiteHeader.tsx`**

Create `plaza-aria/src/components/SiteHeader.tsx`:

```tsx
import Link from 'next/link';

const NAV = [
  { href: '/directorio', label: 'Directorio' },
  { href: '/agenda', label: 'Agenda' },
  { href: '/renta', label: 'Renta tu local' },
  { href: '/contacto', label: 'Contacto' },
];

export function SiteHeader() {
  return (
    <header className="border-b border-aria-line">
      <div className="mx-auto max-w-container px-6 py-5 flex items-center justify-between">
        <Link href="/" className="font-display text-2xl tracking-tight">
          Plaza Aria
        </Link>
        <nav className="hidden md:flex gap-8 text-sm">
          {NAV.map((item) => (
            <Link
              key={item.href}
              href={item.href}
              className="text-aria-mute hover:text-aria-ink transition"
            >
              {item.label}
            </Link>
          ))}
        </nav>
      </div>
    </header>
  );
}
```

- [ ] **Step 2: Create `SiteFooter.tsx`**

Create `plaza-aria/src/components/SiteFooter.tsx`:

```tsx
export function SiteFooter() {
  return (
    <footer className="border-t border-aria-line mt-24">
      <div className="mx-auto max-w-container px-6 py-12 grid md:grid-cols-3 gap-8 text-sm">
        <div>
          <div className="font-display text-xl mb-2">Plaza Aria</div>
          <p className="text-aria-mute">Av. Huayacán, Cancún, Q. Roo.</p>
          <p className="text-aria-mute">Estacionamiento techado · Plaza al aire libre</p>
        </div>
        <div>
          <div className="font-medium mb-2">Horario general</div>
          <p className="text-aria-mute">Lun a Sáb 9:00 - 22:00</p>
          <p className="text-aria-mute">Dom 10:00 - 21:00</p>
          <p className="text-aria-mute mt-1">(Cada local maneja su horario.)</p>
        </div>
        <div>
          <div className="font-medium mb-2">Síguenos</div>
          <a
            href="https://www.instagram.com/plaza_aria/"
            target="_blank"
            rel="noopener noreferrer"
            className="text-aria-mute hover:text-aria-ink"
          >
            @plaza_aria en Instagram
          </a>
        </div>
      </div>
    </footer>
  );
}
```

- [ ] **Step 3: Update `layout.tsx`**

Replace `plaza-aria/src/app/layout.tsx` with:

```tsx
import './globals.css';
import type { Metadata } from 'next';
import { SiteHeader } from '@/components/SiteHeader';
import { SiteFooter } from '@/components/SiteFooter';

export const metadata: Metadata = {
  title: 'Plaza Aria · Av. Huayacán, Cancún',
  description:
    'Plaza al aire libre en Huayacán. Restaurantes, servicios, clases y vida de barrio.',
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="es">
      <body>
        <SiteHeader />
        <main className="min-h-screen">{children}</main>
        <SiteFooter />
      </body>
    </html>
  );
}
```

- [ ] **Step 4: Verify build**

```bash
cd plaza-aria
npm run build
```

Expected: builds without error.

- [ ] **Step 5: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos"
git add plaza-aria/
git commit -m "feat(plaza-aria): add site header and footer"
```

---

## Task 10: Home page (basic hero + CTAs)

This task delivers a presentable home with the two CTAs from the spec. Day-to-Night and Latido de Aria come in Fase 3.

**Files:**
- Modify: `plaza-aria/src/app/page.tsx`

- [ ] **Step 1: Replace home page**

Replace `plaza-aria/src/app/page.tsx` with:

```tsx
import Link from 'next/link';

export default function Home() {
  return (
    <div>
      <section className="mx-auto max-w-container px-6 pt-20 pb-32">
        <p className="text-aria-mute text-sm uppercase tracking-widest mb-6">
          Av. Huayacán · Cancún
        </p>
        <h1 className="font-display text-5xl md:text-7xl leading-[1.05] tracking-tight mb-6">
          Una plaza viva,<br />a la vuelta de tu casa.
        </h1>
        <p className="text-aria-mute text-lg max-w-xl mb-12">
          Restaurantes, servicios, clases y comunidad — al aire libre, con
          estacionamiento techado y todo lo que el barrio necesita cerca.
        </p>
        <div className="flex flex-col sm:flex-row gap-4">
          <Link
            href="/renta"
            className="inline-flex items-center justify-center px-8 py-4 bg-aria-ink text-aria-bg font-medium hover:bg-aria-mute transition"
          >
            Renta tu local →
          </Link>
          <Link
            href="/directorio"
            className="inline-flex items-center justify-center px-8 py-4 border border-aria-ink text-aria-ink hover:bg-aria-ink hover:text-aria-bg transition"
          >
            Explora la plaza
          </Link>
        </div>
      </section>
    </div>
  );
}
```

- [ ] **Step 2: Run dev server and verify visually**

```bash
cd plaza-aria
npm run dev
```

Open http://localhost:3000 — expect header, two large CTAs, footer. Stop the server.

- [ ] **Step 3: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos"
git add plaza-aria/
git commit -m "feat(plaza-aria): add home page hero and CTAs"
```

---

## Task 11: `AbiertoBadge` component

**Files:**
- Create: `plaza-aria/src/components/AbiertoBadge.tsx`

- [ ] **Step 1: Create component**

```tsx
import { estadoApertura } from '@/lib/horarios';
import type { HorariosSemana } from '@/types/airtable';

export function AbiertoBadge({ horarios }: { horarios: HorariosSemana }) {
  const { estado, cierraEn } = estadoApertura(horarios);
  if (estado === 'abierto') {
    return (
      <span className="inline-flex items-center gap-1.5 text-xs font-medium text-aria-live">
        <span className="w-1.5 h-1.5 rounded-full bg-aria-live" /> Abierto ahora
      </span>
    );
  }
  if (estado === 'cierra-pronto') {
    return (
      <span className="inline-flex items-center gap-1.5 text-xs font-medium text-aria-warn">
        <span className="w-1.5 h-1.5 rounded-full bg-aria-warn" />
        Cierra en {cierraEn} min
      </span>
    );
  }
  return (
    <span className="inline-flex items-center gap-1.5 text-xs font-medium text-aria-mute">
      <span className="w-1.5 h-1.5 rounded-full bg-aria-mute" /> Cerrado
    </span>
  );
}
```

- [ ] **Step 2: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos"
git add plaza-aria/
git commit -m "feat(plaza-aria): add AbiertoBadge component"
```

---

## Task 12: `LocalCard` component

**Files:**
- Create: `plaza-aria/src/components/LocalCard.tsx`

- [ ] **Step 1: Create component**

```tsx
import Image from 'next/image';
import Link from 'next/link';
import type { Local } from '@/types/airtable';
import { AbiertoBadge } from './AbiertoBadge';

export function LocalCard({ local }: { local: Local }) {
  const hero = local.fotos[0] ?? local.logo;
  return (
    <Link
      href={`/directorio/${local.slug}`}
      className="group block bg-white border border-aria-line hover:border-aria-ink transition"
    >
      <div className="relative aspect-[4/3] bg-aria-line overflow-hidden">
        {hero && (
          <Image
            src={hero.url}
            alt={local.nombre}
            fill
            sizes="(max-width: 768px) 100vw, 33vw"
            className="object-cover group-hover:scale-105 transition duration-500"
          />
        )}
      </div>
      <div className="p-5">
        <div className="flex items-baseline justify-between mb-1">
          <h3 className="font-display text-xl">{local.nombre}</h3>
          <span className="text-xs text-aria-mute">Piso {local.piso}</span>
        </div>
        <p className="text-sm text-aria-mute mb-3">{local.giro}</p>
        <AbiertoBadge horarios={local.horarios} />
      </div>
    </Link>
  );
}
```

- [ ] **Step 2: Configure remote images**

Replace `plaza-aria/next.config.mjs` with:

```js
/** @type {import('next').NextConfig} */
const nextConfig = {
  images: {
    remotePatterns: [
      { protocol: 'https', hostname: 'v5.airtableusercontent.com' },
      { protocol: 'https', hostname: 'dl.airtable.com' },
    ],
  },
};
export default nextConfig;
```

- [ ] **Step 3: Verify build**

```bash
cd plaza-aria
npm run build
```

Expected: builds without error.

- [ ] **Step 4: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos"
git add plaza-aria/
git commit -m "feat(plaza-aria): add LocalCard and Airtable image hosts"
```

---

## Task 13: Directorio filters (client component)

**Files:**
- Create: `plaza-aria/src/components/DirectorioFilters.tsx`

- [ ] **Step 1: Create filter UI**

```tsx
'use client';

import { useMemo, useState } from 'react';
import type { Local, Giro } from '@/types/airtable';
import { estadoApertura } from '@/lib/horarios';
import { LocalCard } from './LocalCard';

const GIROS: Giro[] = [
  'Restaurantes',
  'Belleza',
  'Fitness',
  'Educación',
  'Hogar',
  'Servicios',
  'Otros',
];

export function DirectorioFilters({ locales }: { locales: Local[] }) {
  const [giro, setGiro] = useState<Giro | 'Todos'>('Todos');
  const [piso, setPiso] = useState<'Todos' | 1 | 2>('Todos');
  const [soloAbiertos, setSoloAbiertos] = useState(false);
  const [q, setQ] = useState('');

  const filtered = useMemo(() => {
    return locales.filter((l) => {
      if (giro !== 'Todos' && l.giro !== giro) return false;
      if (piso !== 'Todos' && l.piso !== piso) return false;
      if (soloAbiertos && estadoApertura(l.horarios).estado === 'cerrado') return false;
      if (q && !l.nombre.toLowerCase().includes(q.toLowerCase())) return false;
      return true;
    });
  }, [locales, giro, piso, soloAbiertos, q]);

  return (
    <div>
      <div className="flex flex-wrap gap-3 items-center mb-8 pb-6 border-b border-aria-line">
        <input
          type="search"
          placeholder="Buscar local…"
          value={q}
          onChange={(e) => setQ(e.target.value)}
          className="px-3 py-2 border border-aria-line bg-white text-sm w-48"
        />
        <select
          value={giro}
          onChange={(e) => setGiro(e.target.value as Giro | 'Todos')}
          className="px-3 py-2 border border-aria-line bg-white text-sm"
        >
          <option value="Todos">Todos los giros</option>
          {GIROS.map((g) => (
            <option key={g}>{g}</option>
          ))}
        </select>
        <select
          value={String(piso)}
          onChange={(e) =>
            setPiso(e.target.value === 'Todos' ? 'Todos' : (Number(e.target.value) as 1 | 2))
          }
          className="px-3 py-2 border border-aria-line bg-white text-sm"
        >
          <option value="Todos">Ambos pisos</option>
          <option value="1">Piso 1</option>
          <option value="2">Piso 2</option>
        </select>
        <label className="inline-flex items-center gap-2 text-sm text-aria-mute">
          <input
            type="checkbox"
            checked={soloAbiertos}
            onChange={(e) => setSoloAbiertos(e.target.checked)}
          />
          Abierto ahora
        </label>
        <span className="ml-auto text-sm text-aria-mute">{filtered.length} locales</span>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {filtered.map((l) => (
          <LocalCard key={l.id} local={l} />
        ))}
      </div>

      {filtered.length === 0 && (
        <p className="text-center text-aria-mute py-16">
          No hay locales con esos filtros.
        </p>
      )}
    </div>
  );
}
```

- [ ] **Step 2: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos"
git add plaza-aria/
git commit -m "feat(plaza-aria): add directorio filters component"
```

---

## Task 14: `/directorio` index page

**Files:**
- Create: `plaza-aria/src/app/directorio/page.tsx`

- [ ] **Step 1: Create page**

```tsx
import { getLocales } from '@/lib/airtable';
import { DirectorioFilters } from '@/components/DirectorioFilters';

export const metadata = {
  title: 'Directorio · Plaza Aria',
  description: 'Conoce todos los locales, restaurantes y servicios de Plaza Aria.',
};

export default async function DirectorioPage() {
  const locales = await getLocales();
  return (
    <div className="mx-auto max-w-container px-6 py-16">
      <h1 className="font-display text-5xl mb-3">Directorio</h1>
      <p className="text-aria-mute text-lg mb-10 max-w-2xl">
        Todo lo que vive en Plaza Aria. Filtra por giro, piso o por lo que está
        abierto ahora mismo.
      </p>
      <DirectorioFilters locales={locales} />
    </div>
  );
}
```

- [ ] **Step 2: Verify (with empty Airtable, page should render with 0 locales)**

```bash
cd plaza-aria
npm run dev
```

If `.env.local` does not yet have real Airtable credentials, the page will error. That's expected — fill in `.env.local` with real credentials before viewing. Document this in commit.

- [ ] **Step 3: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos"
git add plaza-aria/
git commit -m "feat(plaza-aria): add directorio index page"
```

---

## Task 15: `HorariosTable` component

**Files:**
- Create: `plaza-aria/src/components/HorariosTable.tsx`

- [ ] **Step 1: Create component**

```tsx
import type { HorariosSemana, Dia } from '@/types/airtable';
import { DIAS } from '@/types/airtable';

const DIA_LABEL: Record<Dia, string> = {
  Lun: 'Lunes',
  Mar: 'Martes',
  Mié: 'Miércoles',
  Jue: 'Jueves',
  Vie: 'Viernes',
  Sáb: 'Sábado',
  Dom: 'Domingo',
};

export function HorariosTable({ horarios }: { horarios: HorariosSemana }) {
  return (
    <dl className="text-sm">
      {DIAS.map((d) => {
        const h = horarios[d];
        return (
          <div
            key={d}
            className="flex justify-between py-1.5 border-b border-aria-line last:border-0"
          >
            <dt className="text-aria-mute">{DIA_LABEL[d]}</dt>
            <dd>
              {h.apertura && h.cierre ? (
                <>
                  {h.apertura} – {h.cierre}
                </>
              ) : (
                <span className="text-aria-mute">Cerrado</span>
              )}
            </dd>
          </div>
        );
      })}
    </dl>
  );
}
```

- [ ] **Step 2: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos"
git add plaza-aria/
git commit -m "feat(plaza-aria): add HorariosTable component"
```

---

## Task 16: `InstagramEmbed` component

The official Instagram oEmbed requires a Facebook app token. For Fase 1 we use a lightweight approach: link out + iframe-embedded profile preview via `instagram.com/<handle>/embed/`.

**Files:**
- Create: `plaza-aria/src/components/InstagramEmbed.tsx`

- [ ] **Step 1: Create component**

```tsx
export function InstagramEmbed({ handle }: { handle: string }) {
  const clean = handle.replace(/^@/, '');
  return (
    <div className="border border-aria-line bg-white">
      <div className="px-4 py-3 border-b border-aria-line flex items-center justify-between">
        <span className="text-sm font-medium">@{clean}</span>
        <a
          href={`https://instagram.com/${clean}`}
          target="_blank"
          rel="noopener noreferrer"
          className="text-xs text-aria-mute hover:text-aria-ink"
        >
          Ver en Instagram →
        </a>
      </div>
      <iframe
        src={`https://www.instagram.com/${clean}/embed/`}
        className="w-full h-[480px] border-0"
        loading="lazy"
        title={`Instagram de @${clean}`}
      />
    </div>
  );
}
```

- [ ] **Step 2: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos"
git add plaza-aria/
git commit -m "feat(plaza-aria): add InstagramEmbed component"
```

---

## Task 17: `/directorio/[slug]` ficha de local

**Files:**
- Create: `plaza-aria/src/app/directorio/[slug]/page.tsx`

- [ ] **Step 1: Create page**

```tsx
import { notFound } from 'next/navigation';
import Image from 'next/image';
import { getLocalBySlug, getLocales } from '@/lib/airtable';
import { HorariosTable } from '@/components/HorariosTable';
import { AbiertoBadge } from '@/components/AbiertoBadge';
import { InstagramEmbed } from '@/components/InstagramEmbed';
import { LocalCard } from '@/components/LocalCard';

interface Props {
  params: { slug: string };
}

export async function generateMetadata({ params }: Props) {
  const local = await getLocalBySlug(params.slug);
  if (!local) return { title: 'Local · Plaza Aria' };
  return {
    title: `${local.nombre} · Plaza Aria`,
    description: local.descripcion || `${local.nombre} en Plaza Aria, ${local.giro}.`,
  };
}

export default async function LocalPage({ params }: Props) {
  const local = await getLocalBySlug(params.slug);
  if (!local) notFound();

  const todos = await getLocales();
  const vecinos = todos
    .filter((l) => l.piso === local.piso && l.id !== local.id)
    .slice(0, 3);

  const hero = local.fotos[0] ?? local.logo;

  return (
    <div>
      <div className="relative aspect-[16/7] bg-aria-line">
        {hero && (
          <Image src={hero.url} alt={local.nombre} fill className="object-cover" priority />
        )}
      </div>

      <div className="mx-auto max-w-container px-6 py-12 grid md:grid-cols-3 gap-12">
        <div className="md:col-span-2">
          <p className="text-aria-mute text-sm uppercase tracking-widest mb-3">
            {local.giro} · Piso {local.piso} · Local {local.numeroLocal}
          </p>
          <h1 className="font-display text-5xl mb-4">{local.nombre}</h1>
          <div className="mb-8">
            <AbiertoBadge horarios={local.horarios} />
          </div>
          {local.descripcion && (
            <p className="text-lg leading-relaxed text-aria-ink whitespace-pre-line">
              {local.descripcion}
            </p>
          )}

          {local.instagram && (
            <div className="mt-12">
              <h2 className="font-display text-2xl mb-4">En Instagram</h2>
              <InstagramEmbed handle={local.instagram} />
            </div>
          )}
        </div>

        <aside className="space-y-8">
          <div>
            <h2 className="font-display text-xl mb-3">Horario</h2>
            <HorariosTable horarios={local.horarios} />
          </div>

          <div>
            <h2 className="font-display text-xl mb-3">Contacto</h2>
            <ul className="text-sm space-y-2">
              {local.whatsapp && (
                <li>
                  <a
                    href={`https://wa.me/${local.whatsapp.replace(/[^0-9]/g, '')}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="underline"
                  >
                    WhatsApp: {local.whatsapp}
                  </a>
                </li>
              )}
              {local.telefono && <li>Tel: {local.telefono}</li>}
              {local.menuPdf && (
                <li>
                  <a
                    href={local.menuPdf}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="underline"
                  >
                    Ver menú (PDF)
                  </a>
                </li>
              )}
              {local.linkReservar && (
                <li>
                  <a
                    href={local.linkReservar}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="underline"
                  >
                    Reservar →
                  </a>
                </li>
              )}
            </ul>
          </div>
        </aside>
      </div>

      {vecinos.length > 0 && (
        <section className="mx-auto max-w-container px-6 pb-20">
          <h2 className="font-display text-2xl mb-6">Vecinos en piso {local.piso}</h2>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            {vecinos.map((v) => (
              <LocalCard key={v.id} local={v} />
            ))}
          </div>
        </section>
      )}
    </div>
  );
}
```

- [ ] **Step 2: Verify build**

```bash
cd plaza-aria
npm run build
```

Expected: build succeeds (will skip data fetch in build if no env, that's ok).

- [ ] **Step 3: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos"
git add plaza-aria/
git commit -m "feat(plaza-aria): add ficha de local page"
```

---

## Task 18: `AgendaList` component + `/agenda` page

**Files:**
- Create: `plaza-aria/src/components/AgendaList.tsx`, `plaza-aria/src/app/agenda/page.tsx`

- [ ] **Step 1: Create `AgendaList.tsx`**

```tsx
'use client';

import { useMemo, useState } from 'react';
import type { EventoClase, TipoEvento, Dia } from '@/types/airtable';
import { DIAS } from '@/types/airtable';

const TIPOS: TipoEvento[] = ['Fitness', 'Educación', 'Lifestyle', 'Evento'];

const DIA_LABEL: Record<Dia, string> = {
  Lun: 'Lunes',
  Mar: 'Martes',
  Mié: 'Miércoles',
  Jue: 'Jueves',
  Vie: 'Viernes',
  Sáb: 'Sábado',
  Dom: 'Domingo',
};

export function AgendaList({ eventos }: { eventos: EventoClase[] }) {
  const [tipo, setTipo] = useState<TipoEvento | 'Todos'>('Todos');

  const filtered = useMemo(
    () => eventos.filter((e) => tipo === 'Todos' || e.tipo === tipo),
    [eventos, tipo]
  );

  const porDia: Record<Dia, EventoClase[]> = {
    Lun: [], Mar: [], Mié: [], Jue: [], Vie: [], Sáb: [], Dom: [],
  };
  const unicos: EventoClase[] = [];

  filtered.forEach((ev) => {
    if (ev.fechaUnica) {
      unicos.push(ev);
    } else {
      ev.diasSemana.forEach((d) => porDia[d].push(ev));
    }
  });

  return (
    <div>
      <div className="flex gap-2 mb-10 flex-wrap">
        <button
          onClick={() => setTipo('Todos')}
          className={`px-4 py-2 text-sm border ${
            tipo === 'Todos'
              ? 'bg-aria-ink text-aria-bg border-aria-ink'
              : 'border-aria-line text-aria-mute hover:border-aria-ink'
          }`}
        >
          Todos
        </button>
        {TIPOS.map((t) => (
          <button
            key={t}
            onClick={() => setTipo(t)}
            className={`px-4 py-2 text-sm border ${
              tipo === t
                ? 'bg-aria-ink text-aria-bg border-aria-ink'
                : 'border-aria-line text-aria-mute hover:border-aria-ink'
            }`}
          >
            {t}
          </button>
        ))}
      </div>

      <div className="space-y-10">
        {DIAS.map((d) =>
          porDia[d].length === 0 ? null : (
            <section key={d}>
              <h2 className="font-display text-2xl mb-4 border-b border-aria-line pb-2">
                {DIA_LABEL[d]}
              </h2>
              <ul className="divide-y divide-aria-line">
                {porDia[d].map((ev) => (
                  <li key={`${d}-${ev.id}`} className="py-4 flex items-baseline gap-6">
                    <span className="font-mono text-sm text-aria-mute w-24 shrink-0">
                      {ev.horaInicio} – {ev.horaFin}
                    </span>
                    <div className="flex-1">
                      <h3 className="font-medium">{ev.titulo}</h3>
                      <p className="text-sm text-aria-mute">
                        {ev.tipo}
                        {ev.localNombre && <> · {ev.localNombre}</>}
                      </p>
                    </div>
                    {ev.linkReservar && (
                      <a
                        href={ev.linkReservar}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-sm underline shrink-0"
                      >
                        Reservar
                      </a>
                    )}
                  </li>
                ))}
              </ul>
            </section>
          )
        )}

        {unicos.length > 0 && (
          <section>
            <h2 className="font-display text-2xl mb-4 border-b border-aria-line pb-2">
              Próximos eventos
            </h2>
            <ul className="divide-y divide-aria-line">
              {unicos.map((ev) => (
                <li key={ev.id} className="py-4 flex items-baseline gap-6">
                  <span className="font-mono text-sm text-aria-mute w-32 shrink-0">
                    {ev.fechaUnica} · {ev.horaInicio}
                  </span>
                  <div className="flex-1">
                    <h3 className="font-medium">{ev.titulo}</h3>
                    <p className="text-sm text-aria-mute">
                      {ev.tipo}
                      {ev.localNombre && <> · {ev.localNombre}</>}
                    </p>
                  </div>
                </li>
              ))}
            </ul>
          </section>
        )}

        {filtered.length === 0 && (
          <p className="text-center text-aria-mute py-16">No hay eventos para este filtro.</p>
        )}
      </div>
    </div>
  );
}
```

- [ ] **Step 2: Create `/agenda/page.tsx`**

```tsx
import { getEventos, getLocales } from '@/lib/airtable';
import { AgendaList } from '@/components/AgendaList';

export const metadata = {
  title: 'Agenda · Plaza Aria',
  description: 'Clases, eventos y vida semanal en Plaza Aria.',
};

export default async function AgendaPage() {
  const [eventos, locales] = await Promise.all([getEventos(), getLocales()]);
  const localesById = new Map(locales.map((l) => [l.id, l.nombre]));
  const eventosConNombre = eventos.map((e) => ({
    ...e,
    localNombre: e.localId ? localesById.get(e.localId) ?? null : null,
  }));
  return (
    <div className="mx-auto max-w-container px-6 py-16">
      <h1 className="font-display text-5xl mb-3">Agenda</h1>
      <p className="text-aria-mute text-lg mb-10 max-w-2xl">
        Clases de spinning, baile, matemáticas y todo lo que sucede esta semana
        en la plaza.
      </p>
      <AgendaList eventos={eventosConNombre} />
    </div>
  );
}
```

- [ ] **Step 3: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos"
git add plaza-aria/
git commit -m "feat(plaza-aria): add agenda page and list"
```

---

## Task 19: `/contacto` page

**Files:**
- Create: `plaza-aria/src/app/contacto/page.tsx`

- [ ] **Step 1: Create page**

```tsx
export const metadata = {
  title: 'Contacto · Plaza Aria',
};

export default function ContactoPage() {
  return (
    <div className="mx-auto max-w-container px-6 py-16">
      <h1 className="font-display text-5xl mb-3">Contacto</h1>
      <p className="text-aria-mute text-lg mb-10 max-w-2xl">
        ¿Quieres rentar un local, organizar un evento o solo saber dónde estamos?
      </p>

      <div className="grid md:grid-cols-2 gap-10 max-w-3xl">
        <div>
          <h2 className="font-display text-2xl mb-3">Ubicación</h2>
          <p>Av. Huayacán</p>
          <p>Cancún, Quintana Roo</p>
          <p className="text-aria-mute text-sm mt-2">
            Estacionamiento techado. Plaza al aire libre de dos pisos.
          </p>
        </div>
        <div>
          <h2 className="font-display text-2xl mb-3">Renta de locales</h2>
          <p className="text-aria-mute">
            Visita <a href="/renta" className="underline">la sección de renta</a> para
            ver disponibilidad, condiciones y agendar una visita.
          </p>
        </div>
      </div>
    </div>
  );
}
```

- [ ] **Step 2: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos"
git add plaza-aria/
git commit -m "feat(plaza-aria): add contacto page"
```

---

## Task 20: Placeholder `/renta` page

The full Puerta Leasing comes in Fase 2. For now, a placeholder so the CTA in the home doesn't 404.

**Files:**
- Create: `plaza-aria/src/app/renta/page.tsx`

- [ ] **Step 1: Create page**

```tsx
export const metadata = {
  title: 'Renta tu local · Plaza Aria',
};

export default function RentaPage() {
  return (
    <div className="mx-auto max-w-container px-6 py-24 text-center">
      <h1 className="font-display text-5xl mb-4">Renta tu local en Aria</h1>
      <p className="text-aria-mute text-lg max-w-xl mx-auto mb-8">
        Próximamente vas a poder explorar el plano de la plaza, ver locales
        disponibles, conocer la zona y agendar una visita en minutos.
      </p>
      <a
        href="https://wa.me/525500000000"
        target="_blank"
        rel="noopener noreferrer"
        className="inline-flex px-8 py-4 bg-aria-ink text-aria-bg hover:bg-aria-mute transition"
      >
        Quiero rentar — escríbenos por WhatsApp
      </a>
      <p className="text-sm text-aria-mute mt-8">
        (Sustituye el número de WhatsApp en este placeholder cuando el cliente lo confirme.)
      </p>
    </div>
  );
}
```

- [ ] **Step 2: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos"
git add plaza-aria/
git commit -m "feat(plaza-aria): add placeholder renta page"
```

---

## Task 21: Airtable webhook revalidation endpoint

**Files:**
- Create: `plaza-aria/src/app/api/revalidate/route.ts`

- [ ] **Step 1: Create route handler**

```ts
import { NextResponse } from 'next/server';
import { revalidateTag } from 'next/cache';

export async function POST(req: Request) {
  const secret = process.env.AIRTABLE_REVALIDATE_SECRET;
  const provided = req.headers.get('x-revalidate-secret');
  if (!secret || provided !== secret) {
    return NextResponse.json({ ok: false, error: 'unauthorized' }, { status: 401 });
  }

  const body = (await req.json().catch(() => ({}))) as { table?: string };
  const tables = body.table ? [body.table] : ['Locales', 'Eventos_Clases', 'Config'];
  tables.forEach((t) => revalidateTag(t));

  return NextResponse.json({ ok: true, revalidated: tables });
}
```

- [ ] **Step 2: Document the webhook setup**

Append to `plaza-aria/docs/airtable-schema.md`:

```markdown

## Webhook de revalidación (opcional, recomendado)

En Airtable Automations, crea una automatización por cada tabla relevante:

1. **Trigger:** "When record matches conditions" o "When record updated" sobre `Locales` / `Eventos_Clases` / `Config`.
2. **Action:** "Send a webhook" con:
   - URL: `https://<dominio>/api/revalidate`
   - Method: POST
   - Headers: `x-revalidate-secret: <valor de AIRTABLE_REVALIDATE_SECRET>`
   - Body (JSON): `{ "table": "Locales" }` (cambia por tabla)

Sin webhook, el sitio igualmente se refresca cada 5 minutos.
```

- [ ] **Step 3: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos"
git add plaza-aria/
git commit -m "feat(plaza-aria): add revalidate webhook endpoint"
```

---

## Task 22: README and local environment setup

**Files:**
- Create: `plaza-aria/README.md`

- [ ] **Step 1: Create README**

```markdown
# Plaza Aria — Sitio Web

Sitio web de Plaza Aria. Fase 1: foundation + directorio + agenda.

## Stack
- Next.js 14 (App Router) + TypeScript + Tailwind
- Airtable como CMS (REST API + ISR + webhook revalidation)
- Vitest para tests unitarios
- Vercel para hosting (free tier)

## Setup local

1. Instala dependencias:
   ```bash
   npm install
   ```

2. Copia `.env.local.example` a `.env.local` y llena:
   ```
   AIRTABLE_API_KEY=key...
   AIRTABLE_BASE_ID=app...
   AIRTABLE_REVALIDATE_SECRET=<cualquier-string-secreto>
   NEXT_PUBLIC_SITE_URL=http://localhost:3000
   ```

3. Crea la base en Airtable según `docs/airtable-schema.md`.

4. Arranca:
   ```bash
   npm run dev
   ```

## Comandos

- `npm run dev` — servidor de desarrollo
- `npm run build` — build de producción
- `npm test` — corre tests con Vitest
- `npm test:watch` — tests en modo watch

## Estructura

Ver `docs/superpowers/plans/2026-05-20-plaza-aria-fase1.md` en el repo padre.
```

- [ ] **Step 2: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos"
git add plaza-aria/README.md
git commit -m "docs(plaza-aria): add README"
```

---

## Task 23: Deploy to Vercel

This task assumes the developer has a Vercel account and is logged in to `vercel` CLI. The site can run locally without this step — but for an end-to-end demo we want a live URL.

**Files:**
- Create: `plaza-aria/vercel.json` (optional, only if custom config needed)

- [ ] **Step 1: Install Vercel CLI (if not already)**

```bash
npm install -g vercel
```

- [ ] **Step 2: Link project**

```bash
cd plaza-aria
vercel link
```

Choose: create new project, name `plaza-aria`.

- [ ] **Step 3: Add environment variables**

```bash
vercel env add AIRTABLE_API_KEY production
vercel env add AIRTABLE_BASE_ID production
vercel env add AIRTABLE_REVALIDATE_SECRET production
```

Paste each value when prompted. Repeat for `preview` and `development` environments if desired.

- [ ] **Step 4: Deploy**

```bash
vercel --prod
```

Expected: a production URL like `https://plaza-aria.vercel.app`.

- [ ] **Step 5: Smoke test the live URL**

Visit:
- `/` — home with two CTAs
- `/directorio` — directory (will show 0 locales if Airtable empty, that's ok for now)
- `/agenda` — agenda
- `/contacto` — contacto

- [ ] **Step 6: Document the live URL**

Append to `plaza-aria/README.md`:

```markdown

## Producción

- URL preview: <pegar URL de vercel>
- Dashboard: https://vercel.com/<tu-equipo>/plaza-aria
```

- [ ] **Step 7: Commit**

```bash
cd "/Users/fernando.diaz/Downloads/SINERGIA Proyectos"
git add plaza-aria/README.md
git commit -m "docs(plaza-aria): record Vercel deployment URL"
```

---

## Definition of Done (Fase 1)

- [ ] `npm test` — all tests pass
- [ ] `npm run build` — clean production build
- [ ] Site live on Vercel with real Airtable data
- [ ] Home, directorio (con filtros y "abierto ahora"), ficha de local, agenda, contacto y `/renta` placeholder funcionan
- [ ] Webhook de revalidación documentado y endpoint funcional
- [ ] README con instrucciones de setup local

## Próximos pasos (Fase 2)

Cuando esta fase quede aprobada, se inicia el plan de Fase 2:
- Plano interactivo SVG
- Dashboard "¿Por qué Aria?"
- Formulario de lead + tabla `Leads_Renta` + email Resend
- Generador de PDF personalizado
- Tour virtual embed
