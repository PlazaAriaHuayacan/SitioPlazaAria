/**
 * Isometric hotspot definitions for the Plaza Aria floor plan.
 *
 * Coordinate system: pixel space of the isometric illustration (2528 × 1684).
 * Each hotspot is a parallelogram polygon tracing the visible floor area
 * of the unit in the isometric view.
 *
 * ┌── CALIBRATION NOTE ──────────────────────────────────────────────────────┐
 * │ Coordinates are derived from the architectural floor plan proportions    │
 * │ and a ~30° isometric projection estimate. Add ?debug=1 to the URL to    │
 * │ see colored overlays and fine-tune visually.                             │
 * └──────────────────────────────────────────────────────────────────────────┘
 *
 * Projection constants used:
 *   slope  = -0.167  (dy per dx — image y decreases going right)
 *   depth  =  220    (front-to-back pixel height of each unit floor)
 *   p2_yoff =  620   (piso 2 floats this many px above piso 1)
 */

import type { UnidadComercialAgrupada } from './agrupar';

// ─── Image dimensions ─────────────────────────────────────────────────────────
export const IMG_W = 2528;
export const IMG_H = 1684;

// ─── Types ────────────────────────────────────────────────────────────────────
export interface HotspotDef {
  /** Lot numbers that belong to this hotspot, e.g. ["L6","L7"] */
  loteKeys: string[];
  piso: '1' | '2';
  /** Polygon corner points [x, y] in the 2528×1684 image coordinate space */
  points: [number, number][];
}

// ─── Coordinate helpers ───────────────────────────────────────────────────────
const SLOPE  = -0.167;   // dy / dx
const DEPTH  = 220;      // front-to-back height (px)
const P1_Y0  = 1480;     // y of front-left corner of piso 1
const P2_YOF = 620;      // piso 2 rises this many px above piso 1

function yFront(x: number): number {
  return P1_Y0 + (x - 80) * SLOPE;
}
function yBack(x: number): number {
  return yFront(x) - DEPTH;
}
/** Build a parallelogram polygon for a piso-1 unit spanning [x0, x1] */
function poly1(x0: number, x1: number): [number, number][] {
  return [
    [x0, yFront(x0)],
    [x1, yFront(x1)],
    [x1, yBack(x1)],
    [x0, yBack(x0)],
  ];
}
/** Same for piso 2 (identical x, shifted up by P2_YOF) */
function poly2(x0: number, x1: number): [number, number][] {
  return poly1(x0, x1).map(([px, py]) => [px, py - P2_YOF]) as [number, number][];
}

// ─── Unit widths (proportional to m², scaled to fill building span) ───────────
//
// Piso 1 — units left→right: L1, L2, L3, L4, L5, L6-7, [stairs], L8-9, L10, L11, L12, L13, L14-15
// m² source: architectural floor plan
//
// Piso 2 — units left→right: L16-17-18, L19-23, [stairs], L24-27, L28-29, L30-31

const X_LEFT  = 80;
const X_RIGHT = 2460;
const SPAN    = X_RIGHT - X_LEFT; // 2380px

// Build a list of [loteKeys, relative_width] segments for a piso,
// then scale them to fill SPAN and return HotspotDef[]
function buildPiso(
  segments: Array<{ lotes: string[]; relW: number }>,
  stairs_relW: number,
  stairsAfterIdx: number, // insert stairs AFTER this segment index (0-based)
  piso: '1' | '2',
): HotspotDef[] {
  const allSegs = [
    ...segments.slice(0, stairsAfterIdx + 1),
    { lotes: [] as string[], relW: stairs_relW }, // stairs placeholder
    ...segments.slice(stairsAfterIdx + 1),
  ];
  const totalRel = allSegs.reduce((s, seg) => s + seg.relW, 0);
  const pxPerRel = SPAN / totalRel;

  const hotspots: HotspotDef[] = [];
  let x = X_LEFT;
  for (const seg of allSegs) {
    const w = seg.relW * pxPerRel;
    if (seg.lotes.length > 0) {
      hotspots.push({
        loteKeys: seg.lotes,
        piso,
        points: piso === '1' ? poly1(x, x + w) : poly2(x, x + w),
      });
    }
    x += w;
  }
  return hotspots;
}

// ─── Piso 1 segments ─────────────────────────────────────────────────────────
const PISO1_SEGS = [
  { lotes: ['L1'],       relW: 2.62 },   // Local 1:      158.21 m²
  { lotes: ['L2'],       relW: 1.53 },   // Local 2:       92.20 m²
  { lotes: ['L3'],       relW: 1.28 },   // Local 3:       77.05 m²
  { lotes: ['L4'],       relW: 1.00 },   // Local 4:       60.35 m²
  { lotes: ['L5'],       relW: 1.00 },   // Local 5:       60.35 m²
  { lotes: ['L6','L7'],  relW: 2.00 },   // Locales 6-7:  120.70 m²
  // ← stairs after index 5
  { lotes: ['L8','L9'],  relW: 2.00 },   // Locales 8-9:  120.70 m²
  { lotes: ['L10'],      relW: 1.00 },
  { lotes: ['L11'],      relW: 1.00 },
  { lotes: ['L12'],      relW: 1.32 },   // Local 12:      79.35 m²
  { lotes: ['L13'],      relW: 1.51 },   // Local 13:      90.89 m²
  { lotes: ['L14','L15'],relW: 3.80 },   // Locales 14-15: 229.58 m²
];

// ─── Piso 2 segments ─────────────────────────────────────────────────────────
const PISO2_SEGS = [
  { lotes: ['L16','L17','L18'], relW: 3.03 }, // 182.76 m²
  { lotes: ['L19'],             relW: 1.00 },
  { lotes: ['L20'],             relW: 1.00 },
  { lotes: ['L21'],             relW: 1.00 },
  { lotes: ['L22'],             relW: 1.00 },
  { lotes: ['L23'],             relW: 1.00 },
  // ← stairs after index 5
  { lotes: ['L24'],             relW: 1.00 },
  { lotes: ['L25'],             relW: 1.00 },
  { lotes: ['L26'],             relW: 1.00 },
  { lotes: ['L27'],             relW: 1.00 },
  { lotes: ['L28','L29'],       relW: 2.00 }, // 120.70 m²
  { lotes: ['L30','L31'],       relW: 2.03 }, // 122.28 m²
];

// ─── Exported hotspot list ────────────────────────────────────────────────────
export const HOTSPOTS: HotspotDef[] = [
  ...buildPiso(PISO1_SEGS, 1.5, 5, '1'),
  ...buildPiso(PISO2_SEGS, 1.5, 5, '2'),
];

// ─── Lookup helper ────────────────────────────────────────────────────────────
/**
 * Find the UnidadComercialAgrupada that corresponds to a hotspot.
 * Matches on loteIds intersection (any key in hotspot.loteKeys present in unit.loteIds).
 */
export function findUnit(
  hotspot: HotspotDef,
  unidades: UnidadComercialAgrupada[],
): UnidadComercialAgrupada | null {
  return (
    unidades.find((u) =>
      hotspot.loteKeys.some((k) => u.loteIds.includes(k)),
    ) ?? null
  );
}
