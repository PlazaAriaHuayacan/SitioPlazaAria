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
 * Projection constants (calibrated from screenshot analysis):
 *   P1_Y0    = 1161  (y of piso-1 front-left in image)
 *   SLOPE    = -0.12 (dy per dx — image y decreases going right)
 *   DEPTH_DY =  341  (front-to-back y rise)
 *   DEPTH_DX =   50  (back edge is 50px to the right of front — isometric from upper-right)
 *   P2_YOF   =  427  (piso 2 floats this many px above piso 1)
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

// ─── Projection constants ─────────────────────────────────────────────────────
//
// Calibrated from screenshot analysis (image = 2528×1684, screen scale ≈ 0.586):
//
//   P1_Y0    = y of front-left corner of piso 1 floor   (screen band bottom ≈ 680px → 1161)
//   SLOPE    = -Δy per Δx along the building length      (right side higher up in image)
//   DEPTH_DY = y rise from front edge to back edge       (screen band height ≈ 200px → 341)
//   DEPTH_DX = x shift from front to back                (back edge slightly right — isometric view from upper-right)
//   P2_YOF   = how many px piso 2 floats above piso 1    (screen gap ≈ 730px → 427)
//
const P1_Y0    = 1161;
const SLOPE    = -0.12;
const DEPTH_DY = 341;
const DEPTH_DX = 50;    // back of unit appears slightly to the right of front
const P2_YOF   = 427;

function yFront(x: number): number {
  return P1_Y0 + (x - X_LEFT) * SLOPE;
}

/** Build a parallelogram for piso 1 — front edge at bottom, back edge shifted up+right */
function poly1(x0: number, x1: number): [number, number][] {
  return [
    [x0,             yFront(x0)],                      // front-left
    [x1,             yFront(x1)],                      // front-right
    [x1 + DEPTH_DX,  yFront(x1) - DEPTH_DY],          // back-right
    [x0 + DEPTH_DX,  yFront(x0) - DEPTH_DY],          // back-left
  ];
}
/** Same footprint, elevated by P2_YOF */
function poly2(x0: number, x1: number): [number, number][] {
  return poly1(x0, x1).map(([px, py]) => [px, py - P2_YOF]) as [number, number][];
}

// ─── Unit widths (proportional to FACADE width, not total m²) ────────────────
//
// Back rooms ("bodega") increase total m² but NOT the street-facing facade width.
// We use the primary storefront count as the relative width unit.
// Standard local = 1.0. Combined units = sum of their standard widths.
//
// Piso 1: L1(2x), L2-L5(1x each), L6-7(2x), [stairs], L8-9(2x), L10-L13(1x each), L14-15(3x)
// Piso 2: L16-17-18(3x), L19-L23(1x each), [stairs], L24-L27(1x each), L28-29(2x), L30-31(2x)

const X_LEFT  = 120;
const X_RIGHT = 2450;
const SPAN    = X_RIGHT - X_LEFT; // 2330px

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
  { lotes: ['L1'],        relW: 2.0 },  // Large corner unit (2× standard facade)
  { lotes: ['L2'],        relW: 1.0 },  // Standard (back room adds m², not width)
  { lotes: ['L3'],        relW: 1.0 },
  { lotes: ['L4'],        relW: 1.0 },
  { lotes: ['L5'],        relW: 1.0 },
  { lotes: ['L6','L7'],   relW: 2.0 },  // Two standard units combined
  // ← stairs inserted after index 5
  { lotes: ['L8','L9'],   relW: 2.0 },
  { lotes: ['L10'],       relW: 1.0 },
  { lotes: ['L11'],       relW: 1.0 },
  { lotes: ['L12'],       relW: 1.0 },
  { lotes: ['L13'],       relW: 1.0 },
  { lotes: ['L14','L15'], relW: 3.0 },  // Large corner unit (3× standard facade)
];

// ─── Piso 2 segments ─────────────────────────────────────────────────────────
const PISO2_SEGS = [
  { lotes: ['L16','L17','L18'], relW: 3.0 },  // Three standard units combined
  { lotes: ['L19'],             relW: 1.0 },
  { lotes: ['L20'],             relW: 1.0 },
  { lotes: ['L21'],             relW: 1.0 },
  { lotes: ['L22'],             relW: 1.0 },
  { lotes: ['L23'],             relW: 1.0 },
  // ← stairs inserted after index 5
  { lotes: ['L24'],             relW: 1.0 },
  { lotes: ['L25'],             relW: 1.0 },
  { lotes: ['L26'],             relW: 1.0 },
  { lotes: ['L27'],             relW: 1.0 },
  { lotes: ['L28','L29'],       relW: 2.0 },
  { lotes: ['L30','L31'],       relW: 2.0 },
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
