# Plaza Aria Fase 2 — Leasing Portal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Plaza Aria leasing portal — interactive isometric plan, "¿Por qué Aria?" dashboard, functional lead form, and personalized PDF generator with automated email delivery.

**Architecture:**
- Sub-fase 2A: Interactive isometric SVG plano replaces `/renta` hero. Uses real architectural data from Airtable (31 lotes grouped into 24 unidades comerciales).
- Sub-fase 2B: Data-driven dashboard with INEGI demographics + plaza-derived metrics.
- Sub-fase 2C: Form-based lead capture with side panel UI + `/api/leads` endpoint.
- Sub-fase 2D: PDF generation with `@react-pdf/renderer` + Resend email delivery to both prospect and comercializador.

**Tech Stack additions over Fase 1:**
- `framer-motion` (already installed) — animations
- `zod` — schema validation for forms and API
- `@react-pdf/renderer` — server-side PDF generation
- `resend` — transactional email delivery
- `qrcode` — generate QR codes inside PDFs
- `dotenv-cli` and `tsx` (already installed) — for seed scripts

**Spec:** `docs/superpowers/specs/2026-05-21-plaza-aria-fase2-leasing-portal-design.md`

---

## Pre-work — Data foundation (3 tasks)

### Task 1: Extend Airtable schema (manual)

**Files:** None (manual Airtable work + documentation)

This task is partly manual (the user adds fields in Airtable UI). The script in Task 3 will assume these fields exist.

- [ ] **Step 1:** Add to `Locales` table in Airtable:
  - `UnidadComercial` (Single line text)
  - `OrdenPlano` (Number, integer)

- [ ] **Step 2:** Add to `Config` table in Airtable (one record only):
  - `IngresoPromedioMXN` (Currency, MXN)
  - `EdadMediana` (Number, integer)
  - `NucleosFamiliares` (Number, integer)
  - `DensidadHabitantesKm2` (Number)
  - `FraccionamientosCercanos` (Long text)
  - `AccesosDescripcion` (Long text)
  - `DemografiaFuente` (Single line text)

- [ ] **Step 3:** Run `npx tsx scripts/inspect-airtable.ts` to verify the new fields show up.

- [ ] **Step 4:** No commit for this task (no code changes), but document in next commit message.

### Task 2: Update domain types + normalizer + schema doc

**Files:**
- Modify: `plaza-aria/src/types/domain.ts`
- Modify: `plaza-aria/src/lib/airtable/normalize.ts`
- Modify: `plaza-aria/docs/airtable-schema.md` (already done in commit `feb7030`)

- [ ] **Step 1:** Add fields to `Local` interface in `src/types/domain.ts`:

```ts
export interface Local {
  // ... existing fields ...
  unidadComercial: string;  // groups lots rented together (default = numeroLocal)
  ordenPlano?: number;      // left-to-right order on the piso
}
```

- [ ] **Step 2:** Add fields to `Config` interface in `src/types/domain.ts`:

```ts
export interface Config {
  // ... existing fields ...
  demografia?: {
    ingresoPromedioMXN?: number;
    edadMediana?: number;
    nucleosFamiliares?: number;
    densidadHabitantesKm2?: number;
    fraccionamientos?: string[];  // parsed from FraccionamientosCercanos
    accesosDescripcion?: string;
    fuente?: string;
  };
}
```

- [ ] **Step 3:** Update `normalizeLocal` in `src/lib/airtable/normalize.ts`:

```ts
unidadComercial: (f.UnidadComercial as string) || (f.NumeroLocal as string) || '',
ordenPlano: typeof f.OrdenPlano === 'number' ? f.OrdenPlano : undefined,
```

- [ ] **Step 4:** Update `normalizeConfig` to populate the `demografia` object. Parse `FraccionamientosCercanos` by splitting on newlines, trimming and filtering empty:

```ts
demografia: {
  ingresoPromedioMXN: f.IngresoPromedioMXN as number | undefined,
  edadMediana: f.EdadMediana as number | undefined,
  nucleosFamiliares: f.NucleosFamiliares as number | undefined,
  densidadHabitantesKm2: f.DensidadHabitantesKm2 as number | undefined,
  fraccionamientos: typeof f.FraccionamientosCercanos === 'string'
    ? f.FraccionamientosCercanos.split('\n').map((s) => s.trim()).filter(Boolean)
    : [],
  accesosDescripcion: f.AccesosDescripcion as string | undefined,
  fuente: f.DemografiaFuente as string | undefined,
}
```

- [ ] **Step 5:** Update `rawLocalOcupado` fixture in `plaza-aria/tests/lib/airtable/fixtures.ts` to include `UnidadComercial: 'L1'` and `OrdenPlano: 1`. Add `rawConfigPobladada` fixture with demographic fields.

- [ ] **Step 6:** Add assertions to existing normalize tests to verify the new fields map correctly. Add a new test in `normalize.test.ts` for the demografia parsing:

```ts
it('parses fraccionamientos cercanos as a list', () => {
  const raw = { id: 'cfg', fields: { FraccionamientosCercanos: 'Real Mayab\nEl Cielo\nResidencial Cumbres' } };
  const c = normalizeConfig(raw);
  expect(c?.demografia?.fraccionamientos).toEqual(['Real Mayab', 'El Cielo', 'Residencial Cumbres']);
});
```

- [ ] **Step 7:** Run `npm test` → all passing.

- [ ] **Step 8:** Run `npm run build` → success.

- [ ] **Step 9:** Commit:

```bash
git commit -m "feat(plaza-aria): add UnidadComercial, OrdenPlano, and demografia fields"
```

### Task 3: Seed script for 31 locales

**Files:**
- Create: `plaza-aria/scripts/seed-locales.ts`
- Create: `plaza-aria/scripts/data/locales-catalog.ts` (the catalog from the spec)

- [ ] **Step 1:** Create the catalog file `plaza-aria/scripts/data/locales-catalog.ts`. One entry per architectural lot (31 entries). Each lot specifies its `NumeroLocal`, `UnidadComercial` (so combined lots share value), `Piso`, `OrdenPlano`, and `M2`:

```ts
/** Real catalog from the architectural plan. One entry per lot. */
export interface SeedLote {
  numeroLocal: string;
  unidadComercial: string;
  piso: '1' | '2';
  ordenPlano: number;
  m2: number;
}

export const LOTES: SeedLote[] = [
  // ── Piso 1 ────────────────────────────────────────────────────────
  { numeroLocal: 'L1',  unidadComercial: 'L1',     piso: '1', ordenPlano: 1,  m2: 158.21 },
  { numeroLocal: 'L2',  unidadComercial: 'L2',     piso: '1', ordenPlano: 2,  m2: 92.20 },
  { numeroLocal: 'L3',  unidadComercial: 'L3',     piso: '1', ordenPlano: 3,  m2: 77.05 },
  { numeroLocal: 'L4',  unidadComercial: 'L4',     piso: '1', ordenPlano: 4,  m2: 60.35 },
  { numeroLocal: 'L5',  unidadComercial: 'L5',     piso: '1', ordenPlano: 5,  m2: 60.35 },
  { numeroLocal: 'L6',  unidadComercial: 'L6-7',   piso: '1', ordenPlano: 6,  m2: 60.35 },
  { numeroLocal: 'L7',  unidadComercial: 'L6-7',   piso: '1', ordenPlano: 6,  m2: 60.35 },
  { numeroLocal: 'L8',  unidadComercial: 'L8-9',   piso: '1', ordenPlano: 7,  m2: 60.35 },
  { numeroLocal: 'L9',  unidadComercial: 'L8-9',   piso: '1', ordenPlano: 7,  m2: 60.35 },
  { numeroLocal: 'L10', unidadComercial: 'L10',    piso: '1', ordenPlano: 8,  m2: 60.35 },
  { numeroLocal: 'L11', unidadComercial: 'L11',    piso: '1', ordenPlano: 9,  m2: 60.35 },
  { numeroLocal: 'L12', unidadComercial: 'L12',    piso: '1', ordenPlano: 10, m2: 79.35 },
  { numeroLocal: 'L13', unidadComercial: 'L13',    piso: '1', ordenPlano: 11, m2: 90.89 },
  { numeroLocal: 'L14', unidadComercial: 'L14-15', piso: '1', ordenPlano: 12, m2: 114.79 },
  { numeroLocal: 'L15', unidadComercial: 'L14-15', piso: '1', ordenPlano: 12, m2: 114.79 },
  // ── Piso 2 ────────────────────────────────────────────────────────
  { numeroLocal: 'L16', unidadComercial: 'L16-17-18', piso: '2', ordenPlano: 1,  m2: 60.92 },
  { numeroLocal: 'L17', unidadComercial: 'L16-17-18', piso: '2', ordenPlano: 1,  m2: 60.92 },
  { numeroLocal: 'L18', unidadComercial: 'L16-17-18', piso: '2', ordenPlano: 1,  m2: 60.92 },
  { numeroLocal: 'L19', unidadComercial: 'L19',       piso: '2', ordenPlano: 2,  m2: 60.35 },
  { numeroLocal: 'L20', unidadComercial: 'L20',       piso: '2', ordenPlano: 3,  m2: 60.35 },
  { numeroLocal: 'L21', unidadComercial: 'L21',       piso: '2', ordenPlano: 4,  m2: 60.35 },
  { numeroLocal: 'L22', unidadComercial: 'L22',       piso: '2', ordenPlano: 5,  m2: 60.35 },
  { numeroLocal: 'L23', unidadComercial: 'L23',       piso: '2', ordenPlano: 6,  m2: 60.35 },
  { numeroLocal: 'L24', unidadComercial: 'L24',       piso: '2', ordenPlano: 7,  m2: 60.35 },
  { numeroLocal: 'L25', unidadComercial: 'L25',       piso: '2', ordenPlano: 8,  m2: 60.35 },
  { numeroLocal: 'L26', unidadComercial: 'L26',       piso: '2', ordenPlano: 9,  m2: 60.35 },
  { numeroLocal: 'L27', unidadComercial: 'L27',       piso: '2', ordenPlano: 10, m2: 60.35 },
  { numeroLocal: 'L28', unidadComercial: 'L28-29',    piso: '2', ordenPlano: 11, m2: 60.35 },
  { numeroLocal: 'L29', unidadComercial: 'L28-29',    piso: '2', ordenPlano: 11, m2: 60.35 },
  { numeroLocal: 'L30', unidadComercial: 'L30-31',    piso: '2', ordenPlano: 12, m2: 61.14 },
  { numeroLocal: 'L31', unidadComercial: 'L30-31',    piso: '2', ordenPlano: 12, m2: 61.14 },
];
```

(`m2` for L14, L15, L16, L17, L18, L30, L31 are computed by dividing the combined area shown in the plan equally across lots — the user may correct these individually in Airtable later.)

- [ ] **Step 2:** Create `plaza-aria/scripts/seed-locales.ts`:

```ts
/**
 * Seed Airtable `Locales` table with the 31 architectural lots.
 * Idempotent: looks up existing records by NumeroLocal, updates if found, creates if not.
 *
 * Usage: cd plaza-aria && npx tsx scripts/seed-locales.ts
 * Add --dry-run to preview without writing.
 */
import { config } from 'dotenv';
import { resolve } from 'path';
config({ path: resolve(__dirname, '../.env.local') });

import { LOTES } from './data/locales-catalog';

const API = 'https://api.airtable.com/v0';
const apiKey = process.env.AIRTABLE_API_KEY!;
const baseId = process.env.AIRTABLE_BASE_ID!;
const TABLE = 'Locales';
const dryRun = process.argv.includes('--dry-run');

async function listExisting(): Promise<Map<string, string>> {
  const map = new Map<string, string>();
  let offset: string | undefined;
  do {
    const url = `${API}/${baseId}/${TABLE}?fields[]=NumeroLocal${offset ? `&offset=${offset}` : ''}`;
    const res = await fetch(url, { headers: { Authorization: `Bearer ${apiKey}` } });
    if (!res.ok) throw new Error(`List failed: ${res.status} ${await res.text()}`);
    const data = await res.json() as { records: Array<{ id: string; fields: Record<string, unknown> }>; offset?: string };
    for (const r of data.records) {
      const num = r.fields.NumeroLocal as string | undefined;
      if (num) map.set(num, r.id);
    }
    offset = data.offset;
  } while (offset);
  return map;
}

async function upsert(numeroLocal: string, fields: Record<string, unknown>, existingId?: string) {
  if (dryRun) {
    console.log(`  ${existingId ? 'UPDATE' : 'CREATE'} ${numeroLocal}:`, fields);
    return;
  }
  const url = existingId
    ? `${API}/${baseId}/${TABLE}/${existingId}`
    : `${API}/${baseId}/${TABLE}`;
  const method = existingId ? 'PATCH' : 'POST';
  const res = await fetch(url, {
    method,
    headers: {
      Authorization: `Bearer ${apiKey}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ fields }),
  });
  if (!res.ok) {
    throw new Error(`${method} ${numeroLocal} failed: ${res.status} ${await res.text()}`);
  }
}

async function main() {
  console.log(`Seed Locales (${dryRun ? 'DRY RUN' : 'WRITE'}): ${LOTES.length} lotes\n`);
  const existing = await listExisting();
  console.log(`Found ${existing.size} existing record(s)\n`);

  let created = 0, updated = 0;
  for (const lote of LOTES) {
    const fields = {
      NumeroLocal: lote.numeroLocal,
      UnidadComercial: lote.unidadComercial,
      Piso: lote.piso,
      OrdenPlano: lote.ordenPlano,
      M2: lote.m2,
      Estado: 'Disponible', // sane default; user adjusts later
      Nombre: `Local ${lote.numeroLocal}`, // placeholder so slug doesn't collide
      Giro: 'Otro',
    };
    const existingId = existing.get(lote.numeroLocal);
    await upsert(lote.numeroLocal, fields, existingId);
    if (existingId) updated++; else created++;
  }
  console.log(`\nDone. Created: ${created}, Updated: ${updated}.`);
}

main().catch((e) => { console.error(e); process.exit(1); });
```

- [ ] **Step 3:** Dry run first:

```bash
cd plaza-aria
npx tsx scripts/seed-locales.ts --dry-run
```

Expected: prints "CREATE L1: {...}" 31 times. No writes.

- [ ] **Step 4:** Real run:

```bash
npx tsx scripts/seed-locales.ts
```

Expected: "Done. Created: 31" (assuming the base started empty for these IDs). If the user already had records with NumeroLocal like "L1", they get updated in place.

- [ ] **Step 5:** Verify via smoke test:

```bash
npx tsx scripts/smoke-airtable.ts
```

Expected: `listLocales()` returns 31 records, each with `unidadComercial` populated.

- [ ] **Step 6:** Commit:

```bash
git add scripts/seed-locales.ts scripts/data/locales-catalog.ts
git commit -m "feat(plaza-aria): add seed script for 31 architectural lotes"
```

---

## Sub-fase 2A — Plano interactivo isométrico (12 tasks)

### Task 4: Group locales by UnidadComercial — pure function + tests

**Files:**
- Create: `plaza-aria/src/lib/plano/agrupar.ts`
- Create: `plaza-aria/tests/lib/plano/agrupar.test.ts`

`agruparPorUnidad(locales)` collapses N records sharing a `unidadComercial` into one logical `UnidadComercialAgrupada`. The combined record carries:
- The merged set of lot IDs (e.g., `["L6","L7"]`)
- The total m² (sum across all lots in the unit)
- The most "important" data: estado, fotos, etc., taken from the first record OR from a designated primary (the lot with the lowest ordenPlano numerically).

- [ ] **Step 1:** Write tests for `agruparPorUnidad` covering:
  - 5 separate locales → 5 unidades
  - 2 locales sharing `UnidadComercial = 'L6-7'` → 1 unidad with `loteIds: ['L6','L7']` and m2 = sum
  - 3 locales sharing `'L16-17-18'` → 1 unidad with 3 lote ids
  - Sort within unidad by ordenPlano numerically ascending
  - Empty input → empty output

- [ ] **Step 2:** Confirm tests fail (function doesn't exist yet).

- [ ] **Step 3:** Implement the function. Define the type and the grouping logic:

```ts
import type { Local, Foto, Giro, EstadoLocal, Piso } from '@/types/domain';

export interface UnidadComercialAgrupada {
  /** Architectural lot IDs that form this unidad (e.g., ["L6","L7"]). */
  loteIds: string[];
  /** Stable identifier for routing/keying. */
  id: string;            // = unidadComercial value
  nombre: string;        // tenant name OR "Local L6-7" if vacant
  slug: string;
  giro: Giro;
  estado: EstadoLocal;
  piso: Piso;
  ordenPlano: number;
  m2Total: number;
  /** All lots' fotos merged. */
  fotos: Foto[];
  logo?: Foto;
  descripcion: string;
  /** Whether this unidad is currently sold/rented combined or individual. */
  esCombinada: boolean;
  /** The first underlying record (for accessing whatsapp, hours, etc.). */
  registroPrincipal: Local;
  /** All underlying records, in ordenPlano order. */
  todosRegistros: Local[];
}

export function agruparPorUnidad(locales: Local[]): UnidadComercialAgrupada[] {
  const byKey = new Map<string, Local[]>();
  for (const l of locales) {
    if (!l.unidadComercial) continue;
    const arr = byKey.get(l.unidadComercial) ?? [];
    arr.push(l);
    byKey.set(l.unidadComercial, arr);
  }
  const unidades: UnidadComercialAgrupada[] = [];
  for (const [key, group] of byKey) {
    const sorted = [...group].sort((a, b) => (a.ordenPlano ?? 0) - (b.ordenPlano ?? 0));
    const principal = sorted[0];
    unidades.push({
      loteIds: sorted.map((l) => l.numeroLocal),
      id: key,
      nombre: principal.nombre,
      slug: principal.slug,
      giro: principal.giro,
      estado: principal.estado,
      piso: principal.piso,
      ordenPlano: principal.ordenPlano ?? 0,
      m2Total: sorted.reduce((sum, l) => sum + (l.leasing?.m2 ?? 0), 0)
        || sorted.length * 60.35, // fallback for occupied units that don't expose m2
      fotos: sorted.flatMap((l) => l.fotos),
      logo: principal.logo,
      descripcion: principal.descripcion,
      esCombinada: sorted.length > 1,
      registroPrincipal: principal,
      todosRegistros: sorted,
    });
  }
  return unidades;
}
```

- [ ] **Step 4:** Tests pass.

- [ ] **Step 5:** Commit:

```bash
git commit -m "feat(plaza-aria): add agruparPorUnidad pure function with tests"
```

### Task 5: Plano geometry module (computes block positions from data)

**Files:**
- Create: `plaza-aria/src/lib/plano/geometry.ts`
- Create: `plaza-aria/tests/lib/plano/geometry.test.ts`

`computeLayout(unidades, piso)` takes a list of unidades for ONE piso (already filtered) and returns an array of `BloqueLayout` objects with `x, y, ancho, alto` for the isometric SVG. Pure math, no rendering.

- [ ] **Step 1:** Define the geometry approach:

```
PLANO_WIDTH = 1200
PLANO_HEIGHT_PER_PISO = 200
CORE_WIDTH = 60       // central core (stairs + elevator) reserves this width
LOCAL_GAP = 4         // gap between blocks
ISOMETRIC_ANGLE = 30  // visual depth angle

For a piso:
  - Filter unidades for this piso, sorted by ordenPlano
  - Compute the natural (front-facing) width of each block from its m²:
      blockWidth(m2) = baseWidth * (m2 / 60.35) ** 0.5
      // larger units are wider but not linearly — sqrt for visual balance
  - Insert CORE in the middle of the orden sequence (between index 6 and 7 typically)
  - Lay out blocks left-to-right with LOCAL_GAP between them
  - Total natural width may exceed PLANO_WIDTH; scale uniformly if needed
```

- [ ] **Step 2:** Write tests:

```ts
describe('computeLayout', () => {
  it('returns one bloque per unidad', () => {
    const r = computeLayout(unidadesPiso1, '1');
    expect(r.length).toBe(unidadesPiso1.length);
  });
  it('inserts the core slot between ordenPlano 6 and 7 (piso 1)', () => {
    const r = computeLayout(unidadesPiso1, '1');
    const core = r.find((b) => b.type === 'core');
    const left = r.filter((b) => b.type === 'bloque' && b.x < core!.x);
    expect(left.every((b) => b.unidad.ordenPlano <= 6)).toBe(true);
  });
  it('larger m² units get wider blocks', () => {
    const r = computeLayout([u60, u120, u230], '1'); // 60, 120, 230 m²
    const widths = r.filter(b=>b.type==='bloque').map(b=>b.ancho);
    expect(widths[0]).toBeLessThan(widths[1]);
    expect(widths[1]).toBeLessThan(widths[2]);
  });
  it('all blocks fit within PLANO_WIDTH', () => {
    const r = computeLayout(unidadesPiso2, '2');
    const lastBloque = r[r.length - 1];
    expect(lastBloque.x + lastBloque.ancho).toBeLessThanOrEqual(1200);
  });
});
```

- [ ] **Step 3:** Implement `computeLayout` and helper types:

```ts
import type { UnidadComercialAgrupada } from './agrupar';
import type { Piso } from '@/types/domain';

export const PLANO_WIDTH = 1200;
export const PLANO_HEIGHT_PER_PISO = 200;
const CORE_WIDTH = 60;
const LOCAL_GAP = 4;
const BASE_BLOCK_WIDTH = 70;

export type BloqueLayout =
  | { type: 'bloque'; unidad: UnidadComercialAgrupada; x: number; y: number; ancho: number; alto: number }
  | { type: 'core'; x: number; y: number; ancho: number; alto: number };

export function computeLayout(unidades: UnidadComercialAgrupada[], piso: Piso): BloqueLayout[] {
  const sorted = [...unidades].sort((a, b) => a.ordenPlano - b.ordenPlano);
  const layouts: BloqueLayout[] = [];
  const splitAt = piso === '1' ? 6 : 6; // piso 1: after orden 6 (L6-7); piso 2: after L23
  const naturalWidths = sorted.map((u) =>
    Math.max(40, BASE_BLOCK_WIDTH * Math.sqrt(u.m2Total / 60.35))
  );
  const totalNatural = naturalWidths.reduce((s, w) => s + w, 0) + (sorted.length - 1) * LOCAL_GAP + CORE_WIDTH + LOCAL_GAP * 2;
  const scale = Math.min(1, PLANO_WIDTH / totalNatural);

  let x = 0;
  const y = 0;
  const alto = PLANO_HEIGHT_PER_PISO * 0.7;

  for (let i = 0; i < sorted.length; i++) {
    if (i === splitAt) {
      layouts.push({ type: 'core', x, y, ancho: CORE_WIDTH * scale, alto });
      x += (CORE_WIDTH + LOCAL_GAP) * scale;
    }
    const ancho = naturalWidths[i] * scale;
    layouts.push({ type: 'bloque', unidad: sorted[i], x, y, ancho, alto });
    x += (ancho + LOCAL_GAP * scale);
  }
  // If splitAt > sorted.length - 1, still insert core at the end (edge case)
  if (splitAt >= sorted.length) {
    layouts.push({ type: 'core', x, y, ancho: CORE_WIDTH * scale, alto });
  }
  return layouts;
}
```

- [ ] **Step 4:** Run tests → pass.

- [ ] **Step 5:** Commit:

```bash
git commit -m "feat(plaza-aria): add plano geometry layout module"
```

### Task 6: Install framer-motion (if not already) + verify

- [ ] **Step 1:**

```bash
cd plaza-aria
npm ls framer-motion || npm install framer-motion@^11
```

- [ ] **Step 2:** Quick build + test pass.

- [ ] **Step 3:** Commit:

```bash
git commit -am "chore(plaza-aria): pin framer-motion for Fase 2 animations"
```

### Task 7: Static SVG components — building base + decorations

**Files:**
- Create: `plaza-aria/src/components/plano/svg/Edificio.tsx`
- Create: `plaza-aria/src/components/plano/svg/Decoraciones.tsx`

Build the static (non-interactive) parts of the isometric plaza:
- `Edificio` — base, walls, terracotta roof, white wordmark band. Two pisos stacked.
- `Decoraciones` — palm trees (front + back), Av. Huayacán camellón (palms in a row at the bottom of the SVG), parking stripes, blue plinth at street level.

- [ ] **Step 1:** Define the SVG viewBox: `0 0 1200 700` (room for two pisos + decorations + street).

- [ ] **Step 2:** Draw isometric building base in `Edificio.tsx`. Use simple polygons for walls/roof with `fill` from theme tokens (`aria-bone`, `aria-sand`, `aria-terracotta`). Use a 30° skew for the isometric effect.

- [ ] **Step 3:** Draw `Decoraciones.tsx` — palm trees, parking, plinth. Keep palette restrained (greens, blues, terracotta accents).

- [ ] **Step 4:** Compose them in a stub `Plano.tsx` that just renders both with no interactivity yet:

```tsx
// src/components/plano/Plano.tsx
export function Plano() {
  return (
    <svg viewBox="0 0 1200 700" className="w-full h-auto">
      <Edificio />
      <Decoraciones />
    </svg>
  );
}
```

- [ ] **Step 5:** Temporarily render it inline somewhere (e.g., on `/renta` page, just for visual check). Run dev server, screenshot via curl + grep to confirm the SVG is in the DOM.

- [ ] **Step 6:** Commit:

```bash
git commit -m "feat(plaza-aria): add static SVG building + decorations"
```

### Task 8: Bloque component (per-unit interactive block)

**Files:**
- Create: `plaza-aria/src/components/plano/Bloque.tsx`
- Create: `plaza-aria/tests/components/plano/Bloque.test.tsx`

Renders one isometric block representing a UnidadComercialAgrupada. Visual variants:
- Ocupado → bloque con logo del negocio centrado (o nombre estilizado si no hay logo)
- Disponible → bloque con borde terracotta, badge "DISPONIBLE", animación de pulsación lenta
- Próximamente → bloque translúcido + badge "Próximamente"

Props: `{ layout: BloqueLayout, onHover, onClick, isHighlighted }`.

- [ ] **Step 1:** Write tests for the 3 visual states + click/hover handler invocation.
- [ ] **Step 2:** Implement using `<motion.g>` from framer-motion for hover lift (`whileHover={{ y: -2 }}`).
- [ ] **Step 3:** For Disponible, add a pulsing border using `animate={{ opacity: [1, 0.7, 1] }}` with infinite repeat.
- [ ] **Step 4:** Render the logo via SVG `<image>` if `unidad.logo?.url` exists; otherwise the nombre in Fraunces stroke text.
- [ ] **Step 5:** Tests pass. Commit:

```bash
git commit -m "feat(plaza-aria): add Bloque component with three estado variants"
```

### Task 9: Floor toggle + plano composition

**Files:**
- Create: `plaza-aria/src/components/plano/PlanoInteractivo.tsx` ('use client')
- Modify: `plaza-aria/src/components/plano/Plano.tsx` (becomes a thin export)

`PlanoInteractivo` is the client component owning:
- Selected piso state (default `1`)
- Hovered unidad state
- Selected unidad state (for opening the side panel)
- Floor toggle UI
- Renders `Edificio` + `Decoraciones` + grid of `<Bloque>` for the visible piso

- [ ] **Step 1:** Implement the state hooks and toggle UI (pill with "Piso 1 / Piso 2" segments, positioned in the top-left of the plano).
- [ ] **Step 2:** Use `useMemo` to compute the layout for the selected piso via `computeLayout`.
- [ ] **Step 3:** Render `<Bloque>` for each layout entry, passing handlers.
- [ ] **Step 4:** When user changes piso, animate the bloque transition with `framer-motion`'s `AnimatePresence` (exit + enter).
- [ ] **Step 5:** Commit:

```bash
git commit -m "feat(plaza-aria): add PlanoInteractivo with floor toggle"
```

### Task 10: Tooltip / preview on hover

**Files:**
- Create: `plaza-aria/src/components/plano/Tooltip.tsx`

When the user hovers a bloque on desktop, show a small floating card with:
- Logo + nombre (occupied) OR "DISPONIBLE" + giro sugerido (disponible)
- m² total
- Si Disponible: línea de renta

Position the tooltip relative to the SVG bounding box. Use absolute positioning over the plano container.

- [ ] **Step 1:** Implement Tooltip with `useState` tracking position.
- [ ] **Step 2:** Wire to PlanoInteractivo's hover handler.
- [ ] **Step 3:** Hide on mobile (no hover events; for touch, click goes straight to the panel).
- [ ] **Step 4:** Commit:

```bash
git commit -m "feat(plaza-aria): add hover tooltip on plano blocks"
```

### Task 11: Side panel — base shell (no form yet; form comes in 2C)

**Files:**
- Create: `plaza-aria/src/components/plano/PanelLateral.tsx` ('use client')

Side panel that slides in from the right when a bloque is clicked. Shows:
- Header with close button + unidad name
- For Ocupado: short summary + link to full ficha at `/directorio/[slug]`
- For Disponible: galería de fotos + specs + (placeholder for form, populated in 2C) + m² + renta
- For Próximamente: muted notice "Este local está en proceso"

Mobile fallback: panel becomes a full-screen modal sliding from the bottom.

- [ ] **Step 1:** Implement the panel shell + close behavior (Escape key, click on backdrop).
- [ ] **Step 2:** Animations via framer-motion (slide-in/out, backdrop fade).
- [ ] **Step 3:** Wire it to PlanoInteractivo's onClick handler.
- [ ] **Step 4:** For mobile (< md breakpoint), use a different transform (translateY) and full-screen sizing.
- [ ] **Step 5:** Commit:

```bash
git commit -m "feat(plaza-aria): add PanelLateral side panel shell"
```

### Task 12: Animation choreography — entry sequence

**Files:**
- Modify: `plaza-aria/src/components/plano/Edificio.tsx`
- Modify: `plaza-aria/src/components/plano/PlanoInteractivo.tsx`

Orchestrate the 3-4s entry animation:
1. (0–1s) Edificio base fades in + slight scale up
2. (1–2s) Piso 1 bloques aparecen left-to-right with staggered delay
3. (2–3s) Piso 2 bloques aparecen left-to-right
4. (3–3.5s) Núcleo central destaca con un pulse
5. (3.5s+) Disponibles inician pulsación

- [ ] **Step 1:** Wrap building polygons in `<motion.g>` with `initial={{ opacity: 0, scale: 0.95 }} animate={{ opacity: 1, scale: 1 }} transition={{ duration: 1 }}`.
- [ ] **Step 2:** Stagger bloques using `transition={{ delay: 1 + index * 0.05 }}`.
- [ ] **Step 3:** Use `useReducedMotion()` from framer-motion to skip animations for users with prefers-reduced-motion.
- [ ] **Step 4:** Visually verify in dev server. Commit:

```bash
git commit -m "feat(plaza-aria): add entry animation choreography for plano"
```

### Task 13: Replace `/renta` hero with the plano

**Files:**
- Modify: `plaza-aria/src/app/renta/page.tsx`
- Possibly add: `plaza-aria/src/components/renta/RentaHeader.tsx` (extract the headline)

- [ ] **Step 1:** In `/renta/page.tsx`:
  - Keep the header text section but slimmer
  - Remove the current grid of LocalCards (the plano replaces it as the primary disponibles visualization)
  - Insert `<PlanoInteractivo unidades={unidadesAgrupadas} />` below the header
  - Move the WhatsApp dark CTA to the bottom
  - Keep the "¿Por qué Aria?" preview section as placeholder (Sub-fase 2B will replace it)

- [ ] **Step 2:** Wire data flow:
  - Server component fetches `listLocales()` and `getConfig()` as before
  - Calls `agruparPorUnidad()` to produce unidades
  - Passes unidades + nowIso to `<PlanoInteractivo>`

- [ ] **Step 3:** Build + curl test of `/renta` page returns valid HTML with plano markup.

- [ ] **Step 4:** Commit:

```bash
git commit -m "feat(plaza-aria): replace /renta hero with interactive plano"
```

### Task 14: Mobile polish

- [ ] **Step 1:** Test the plano on a narrow viewport (< 640px). Add `overflow-x-auto` to the SVG container if needed. Default initial scale to fit width but allow user to scroll if the plano is wider.
- [ ] **Step 2:** Verify floor toggle is reachable with thumb.
- [ ] **Step 3:** Verify the side panel becomes a bottom-slide modal on mobile.
- [ ] **Step 4:** Commit:

```bash
git commit -m "feat(plaza-aria): mobile polish for plano interactivo"
```

### Task 15: Sub-fase 2A checkpoint

- [ ] **Step 1:** Run full test suite — expect all green.
- [ ] **Step 2:** Run `npm run build` — success, no warnings.
- [ ] **Step 3:** Push to GitHub via subtree-split + push to `PlazaAriaHuayacan/SitioPlazaAria`.
- [ ] **Step 4:** Verify production deployment on Vercel.
- [ ] **Step 5:** Manual visual review by the user — should be a "wow" moment.

---

## Sub-fase 2B — Dashboard "¿Por qué Aria?" (6 tasks)

### Task 16: INEGI research + seed Config

**Files:**
- Create: `plaza-aria/scripts/research/inegi-huayacan.md` (research notes)
- Update: Airtable Config record (manual, with values from the research)

- [ ] **Step 1:** Investigate INEGI's open data for Cancún (Q. Roo) — specifically AGEBs around the Huayacán corridor. Look for:
  - Median household income
  - Median age
  - Number of households in 2km radius
  - Population density
- [ ] **Step 2:** Document findings in `inegi-huayacan.md` with sources and dates.
- [ ] **Step 3:** Have the user fill the Config record's new fields with these values (or adjust per their own data).
- [ ] **Step 4:** Commit:

```bash
git commit -m "docs(plaza-aria): INEGI research notes for Huayacan demographics"
```

### Task 17: Zone map component (static)

**Files:**
- Create: `plaza-aria/src/components/dashboard/MapaZona.tsx`

Static SVG map showing:
- Plaza Aria as a terracotta pin in the center
- 2km radius circle (subtle)
- Pins for fraccionamientos (from `config.demografia.fraccionamientos`)
- Av. Huayacán as a line

For Fase 2 we use a hand-drawn approximation. A real interactive map (Mapbox / Leaflet) is overkill for this use case.

- [ ] **Step 1:** Build the SVG with approximate geography (terracotta pin, gray streets, green parks).
- [ ] **Step 2:** Map fraccionamiento names to pin positions hardcoded in the component (5-7 known fraccionamientos around Huayacán).
- [ ] **Step 3:** Commit.

### Task 18: Demographic stats card

**Files:**
- Create: `plaza-aria/src/components/dashboard/DemografiaCard.tsx`

Big number stats in three columns (ingreso, edad, núcleos familiares). Each with a Fraunces display number and a small Inter label. Source footnote at bottom.

- [ ] **Step 1:** Component with three `Stat` sub-components.
- [ ] **Step 2:** Format numbers properly (currency for ingreso, plain integer for edad and nucleos).
- [ ] **Step 3:** Empty state if config.demografia is undefined.
- [ ] **Step 4:** Commit.

### Task 19: Mix actual chart

**Files:**
- Create: `plaza-aria/src/components/dashboard/MixChart.tsx`
- Create: `plaza-aria/src/lib/dashboard/mix.ts` + tests

Pie/donut chart of current giro distribution. Computed from the unidades (not raw locales — to avoid double-counting combined units).

- [ ] **Step 1:** Pure function `computeMix(unidades)` returns `Array<{ giro: Giro; count: number; pct: number }>`.
- [ ] **Step 2:** Tests.
- [ ] **Step 3:** Chart component: simple SVG donut with the giro colors from the LocalCard color map.
- [ ] **Step 4:** Commit.

### Task 20: Gaps + Tráfico cards

**Files:**
- Create: `plaza-aria/src/components/dashboard/GapsCard.tsx`
- Create: `plaza-aria/src/components/dashboard/TraficoCard.tsx`

GapsCard: pill tags for each gap (from `config.gapsGiros`).
TraficoCard: stats for cajones, aforo, accesos.

- [ ] **Step 1:** Implement both. They're small and stateless.
- [ ] **Step 2:** Commit.

### Task 21: Compose dashboard + wire into `/renta`

**Files:**
- Create: `plaza-aria/src/components/dashboard/DashboardPorQueAria.tsx`
- Modify: `plaza-aria/src/app/renta/page.tsx`

Compose the 5 cards into one section. Replace the placeholder "¿Por qué Aria?" in `/renta` with this dashboard.

- [ ] **Step 1:** Layout (grid of 5 cards, responsive).
- [ ] **Step 2:** Wire into renta page.
- [ ] **Step 3:** Build + verify.
- [ ] **Step 4:** Commit.

### Sub-fase 2B checkpoint

- [ ] User visual review.
- [ ] Production deploy + smoke check.

---

## Sub-fase 2C — Ficha disponible + formulario funcional (7 tasks)

### Task 22: Zod schema for lead validation

**Files:**
- Create: `plaza-aria/src/lib/leads/schema.ts`
- Create: `plaza-aria/tests/lib/leads/schema.test.ts`

`leadSchema` — Zod schema for the lead form. Fields: nombre, whatsapp (10 digits, MX format), email, giroPropuesto, mensaje (optional, max 500 chars), localUnidadId (string, the UnidadComercial), honeypot (must be empty).

- [ ] **Step 1:** Install zod: `npm install zod`.
- [ ] **Step 2:** Define schema with refinements (whatsapp regex, honeypot check).
- [ ] **Step 3:** Tests for valid/invalid combos.
- [ ] **Step 4:** Commit.

### Task 23: Lead form component

**Files:**
- Create: `plaza-aria/src/components/leasing/LeadForm.tsx`
- Create: `plaza-aria/tests/components/leasing/LeadForm.test.tsx`

Form inside the side panel for Disponibles. Validates client-side with the Zod schema. On submit, POSTs to `/api/leads`. Shows inline errors and a friendly success state.

- [ ] **Step 1:** Implement with React Hook Form + Zod resolver (or vanilla state — your call; vanilla is fine for ~5 fields).
- [ ] **Step 2:** Tests for happy path and validation errors.
- [ ] **Step 3:** Commit.

### Task 24: `/api/leads` endpoint (without PDF yet)

**Files:**
- Create: `plaza-aria/src/app/api/leads/route.ts`
- Create: `plaza-aria/tests/app/api/leads.test.ts`

POST endpoint that:
1. Validates the body with `leadSchema`
2. Checks honeypot
3. Rate-limits (in-memory Map, 5/hour/IP)
4. Calls `createLeadRenta()` to write to Airtable
5. Returns `{ ok: true, leadId }`

PDF + email integration comes in 2D — for now, just persist to Airtable.

- [ ] **Step 1:** Implement endpoint.
- [ ] **Step 2:** Tests (mock Airtable client).
- [ ] **Step 3:** Commit.

### Task 25: Wire form into PanelLateral

**Files:**
- Modify: `plaza-aria/src/components/plano/PanelLateral.tsx`

When the selected unidad is Disponible, render `<LeadForm>` at the bottom of the panel. Otherwise skip it.

- [ ] **Step 1:** Modify panel to include LeadForm conditionally.
- [ ] **Step 2:** Pass the unidad's id as the `localUnidadId` form field default.
- [ ] **Step 3:** Show success state replacing the form after a successful submit.
- [ ] **Step 4:** Commit.

### Task 26: Confirmation UI + analytics

**Files:**
- Modify: `plaza-aria/src/components/leasing/LeadForm.tsx`

After successful submission, replace the form with a clean confirmation:
- Big "Gracias" headline
- "Recibimos tu interés en el Local X. Te llegará a [email] un kit completo en los próximos minutos."
- WhatsApp CTA so the prospect can also message immediately

Optional: fire a Vercel Analytics event (`track('lead_submitted', { unidadId, giro })`).

- [ ] **Step 1:** Implement confirmation state.
- [ ] **Step 2:** Add analytics hook.
- [ ] **Step 3:** Commit.

### Task 27: Rate limiting refinement

**Files:**
- Modify: `plaza-aria/src/app/api/leads/route.ts`

Improve the rate limiter to:
- Use `x-forwarded-for` for IP (Vercel)
- Reject with 429 + a polite error
- Add tests

- [ ] **Step 1:** Extract `rateLimit()` to its own module.
- [ ] **Step 2:** Tests.
- [ ] **Step 3:** Commit.

### Sub-fase 2C checkpoint

- [ ] Manual test: fill the form on a Disponible local in dev, check Airtable receives the lead correctly.
- [ ] Production deploy + smoke test the form (submit a lead to verify).

---

## Sub-fase 2D — PDF generator + email delivery (7 tasks)

### Task 28: Install PDF + email + QR deps

```bash
npm install @react-pdf/renderer resend qrcode
npm install -D @types/qrcode
```

- [ ] **Step 1:** Install + commit lockfile.
- [ ] **Step 2:** Add to env vars in `.env.local.example` + Vercel:
  - `RESEND_API_KEY`
  - `COMERCIALIZADOR_EMAIL` (default `ferdiaz@punch.com.mx`)
  - `COMERCIALIZADOR_WHATSAPP` (default `5299983214614`)

### Task 29: PDF document structure — portada + ficha del local

**Files:**
- Create: `plaza-aria/src/lib/pdf/LeasingPDF.tsx` (React-PDF document)
- Create: `plaza-aria/src/lib/pdf/styles.ts`
- Create: `plaza-aria/src/lib/pdf/components/Portada.tsx`
- Create: `plaza-aria/src/lib/pdf/components/FichaLocal.tsx`

React-PDF uses its own component API (`<Document><Page>...`). Define the document with 4-6 pages.

- [ ] **Step 1:** Implement Portada with: wordmark Aria, "Propuesta personalizada para [Nombre]", fecha, foto aérea, plaza tagline.
- [ ] **Step 2:** Implement FichaLocal with: foto principal, specs (m², frente, renta, instalaciones), mini-plano (a simplified version of the isometric — drawn as React-PDF primitives).
- [ ] **Step 3:** Register custom fonts (Fraunces + Inter) via `Font.register()`.
- [ ] **Step 4:** Commit.

### Task 30: PDF pages — zona + encaje + contacto

**Files:**
- Create: `plaza-aria/src/lib/pdf/components/Zona.tsx`
- Create: `plaza-aria/src/lib/pdf/components/Encaje.tsx`
- Create: `plaza-aria/src/lib/pdf/components/Contacto.tsx`

- [ ] **Step 1:** Zona page — mini map of Huayacán + demografía resumen + lista de fraccionamientos.
- [ ] **Step 2:** Encaje page — mix de giros (text-based representation, not chart) + gap analysis ("Tu giro propuesto está en gaps detectados ✓" if applicable).
- [ ] **Step 3:** Contacto page — comercializador info + QR code (generated with `qrcode` library, embedded as base64 image in PDF).
- [ ] **Step 4:** Commit.

### Task 31: PDF generation function + test

**Files:**
- Create: `plaza-aria/src/lib/pdf/generate.ts`
- Create: `plaza-aria/tests/lib/pdf/generate.test.ts`

`generateLeasingPDF(lead, unidad, config)` returns a Buffer.

- [ ] **Step 1:** Wire all the components into one Document with conditional rendering for the optional pages.
- [ ] **Step 2:** Use `renderToBuffer` from `@react-pdf/renderer`.
- [ ] **Step 3:** Test: assert the buffer is non-empty and starts with `%PDF-` magic bytes.
- [ ] **Step 4:** Commit.

### Task 32: Resend client + email templates

**Files:**
- Create: `plaza-aria/src/lib/email/client.ts`
- Create: `plaza-aria/src/lib/email/templates/ProspectoEmail.tsx`
- Create: `plaza-aria/src/lib/email/templates/ComercializadorEmail.tsx`

`sendLeadEmails(lead, unidad, pdfBuffer)` sends two emails in parallel.

- [ ] **Step 1:** Initialize Resend SDK with `RESEND_API_KEY`.
- [ ] **Step 2:** Prospecto email: friendly HTML, link to view PDF (stored in Vercel Blob — defer this for now and just attach PDF), ETA, WhatsApp button.
- [ ] **Step 3:** Comercializador email: internal-style with lead details and PDF attached.
- [ ] **Step 4:** Commit.

### Task 33: Integrate PDF + email into `/api/leads`

**Files:**
- Modify: `plaza-aria/src/app/api/leads/route.ts`

After successful Airtable write:
1. Generate the PDF (await `generateLeasingPDF`)
2. Send both emails (await `sendLeadEmails`)
3. Catch failures separately — Airtable lead write is the source of truth; emails are best-effort with error logging

- [ ] **Step 1:** Integrate.
- [ ] **Step 2:** Tests (mock pdf + email modules).
- [ ] **Step 3:** Commit.

### Task 34: End-to-end smoke test in production

- [ ] **Step 1:** Add the Resend API key + comercializador env vars in Vercel.
- [ ] **Step 2:** Deploy.
- [ ] **Step 3:** From the production site, fill out a real lead form against a Disponible local.
- [ ] **Step 4:** Verify:
  - Airtable received the lead
  - Prospecto received email with PDF attached
  - Comercializador received email with PDF attached
  - PDF opens and renders correctly

- [ ] **Step 5:** Document findings in a final note.

### Sub-fase 2D checkpoint

- [ ] User reviews the actual emails and PDFs received.
- [ ] Iterate on PDF design based on feedback.

---

## Final checklist

- [ ] Full test suite passes
- [ ] Lighthouse 90+ móvil (with the plano loaded)
- [ ] Production smoke: home, /directorio, /agenda, /contacto, /renta, /directorio/[slug], /api/leads (POST), /api/revalidate (POST)
- [ ] All 4 sub-fases reviewed by user
- [ ] README updated with Fase 2 additions
- [ ] Final commit + push + production deploy verified
