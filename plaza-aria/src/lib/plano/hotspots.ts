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
// CRITICAL INSIGHT (iteration 5): plano.png is an EXPLODED isometric view —
// piso 2 is drawn floating significantly above piso 1 with a large visual gap.
// This is NOT a normal stacked 2-story building view, so P2_YOF must be much
// larger than typical floor-to-floor distance.
//
// Parameters describe a parallelogram-shaped floor hotspot per unit:
//   P1_Y0    = y of front-left corner of piso 1 visible floor (lower band edge)
//   SLOPE    = dy/dx along the building length (right end's vertical drift)
//   DEPTH_DY = y rise from front edge to back edge (parallelogram height)
//   DEPTH_DX = x shift from front to back (lateral isometric shift)
//   P2_YOF   = pixels piso 2 floats above piso 1 (LARGE in exploded view)
//
// Re-derived from direct inspection of plano.png (2528×1684):
//   - Piso 1 visible band sits in lower-middle of image (y ≈ 1090–1400)
//   - Piso 2 visible band sits in upper-middle of image (y ≈ 510–820)
//   - Inter-floor visual gap ≈ 580 px (much larger than a single floor depth)
// ── Iteration 11: per-piso X bounds + steeper slope ─────────────────────────
//
// Visual inspection of plano.png shows piso 1 and piso 2 wings do NOT have
// the same left/right extents — piso 2 is slightly wider. Likewise, slope
// derived from the two piso 2 anchors (531, 719) and (2067, 603) is steeper
// than from one piso 1 anchor alone:
//   SLOPE = (603 − 719) / (2067 − 531) = −0.0755 → round to −0.07
//
// Each piso has its own:
//   - X_LEFT / X_RIGHT   (where the wing begins and ends in image space)
//   - Y0                 (front-bottom y at X_LEFT)
//   - DEPTH_DY           (visible facade height)
//
// SLOPE is shared (both wings parallel along the same building diagonal).
// DEPTH_DX = 0 (axonometric: vertical real walls stay vertical in image).

const SLOPE    = -0.07;
const DEPTH_DX = 0;

// PISO 1 (lower wing, with palm trees and parking in front)
const P1_X_LEFT  = 290;
const P1_X_RIGHT = 2300;
const P1_Y0      = 1174;   // = 1160 + (290 − 483) · (−0.07) → anchored to user click (483, 1160)
const P1_DEPTH_Y = 180;    // visible facade height for piso 1 units (~180 px)

// PISO 2 (upper wing, set back behind piso 1)
const P2_X_LEFT  = 260;
const P2_X_RIGHT = 2360;
const P2_Y0      = 738;    // = 719 + (260 − 531) · (−0.07) → anchored to user click (531, 719)
const P2_DEPTH_Y = 240;    // visible facade height for piso 2 units (~240 px, slightly taller)

function yFront1(x: number): number { return P1_Y0 + (x - P1_X_LEFT) * SLOPE; }
function yFront2(x: number): number { return P2_Y0 + (x - P2_X_LEFT) * SLOPE; }

/** Build a parallelogram for piso 1 — front edge at bottom, back edge above */
function poly1(x0: number, x1: number): [number, number][] {
  return [
    [x0,             yFront1(x0)],                       // front-left
    [x1,             yFront1(x1)],                       // front-right
    [x1 + DEPTH_DX,  yFront1(x1) - P1_DEPTH_Y],         // back-right
    [x0 + DEPTH_DX,  yFront1(x0) - P1_DEPTH_Y],         // back-left
  ];
}
function poly2(x0: number, x1: number): [number, number][] {
  return [
    [x0,             yFront2(x0)],
    [x1,             yFront2(x1)],
    [x1 + DEPTH_DX,  yFront2(x1) - P2_DEPTH_Y],
    [x0 + DEPTH_DX,  yFront2(x0) - P2_DEPTH_Y],
  ];
}

// ─── Unit widths (proportional to FACADE width, not total m²) ────────────────
//
// Back rooms ("bodega") increase total m² but NOT the street-facing facade width.
// We use the primary storefront count as the relative width unit.
// Standard local = 1.0. Combined units = sum of their standard widths.
//
// Piso 1: L1(2x), L2-L5(1x each), L6-7(2x), [stairs], L8-9(2x), L10-L13(1x each), L14-15(3x)
// Piso 2: L16-17-18(3x), L19-L23(1x each), [stairs], L24-L27(1x each), L28-29(2x), L30-31(2x)

// Build a list of [loteKeys, relative_width] segments for a piso,
// scale them to fill the piso's X span, and return HotspotDef[]
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
  const xLeft  = piso === '1' ? P1_X_LEFT  : P2_X_LEFT;
  const xRight = piso === '1' ? P1_X_RIGHT : P2_X_RIGHT;
  const span   = xRight - xLeft;
  const pxPerRel = span / totalRel;

  const hotspots: HotspotDef[] = [];
  let x = xLeft;
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

// ── Per-unit polygon overrides (iter 12: explicit per-unit mapping) ──────────
//
// Each polygon = [front-bottom-left, front-bottom-right, back-top-right, back-top-left]
// in image space (2528 × 1684). Defined explicitly so any single unit can be
// nudged without affecting neighbours. Initial values derived from iter 11
// formula (SLOPE=-0.07, P1_Y0=1174, P1_DEPTH=180; P2_Y0=738, P2_DEPTH=240).
// ── ITER 14: anchored to user-pinpointed corners of L1 (Ugolina) ─────────────
// User provided 4 exact corners of L1 via grid debug:
//   (415, 1300) front-BL, (631, 1238) front-BR, (482, 1060) back-TR, (263, 1115) back-TL
//
// Critical discovery: DEPTH_DX = -150 (BACK shifts LEFT, not right). The
// isometric view is from front-right, depth goes UP-LEFT. All previous
// iterations assumed wrong direction → polygons were 200-300px off.
//
// Derived constants:
//   pxPerRel = (631-415) / 2.0 (L1 relW) = 108
//   DEPTH_DX = -150,  DEPTH_DY = 185
//   SLOPE_main (L2-L15, front facade)   = -0.075 (gentler, along building length)
//   SLOPE_corner (L1 only) = -0.287 (steep, since L1 shows the side facade)
//
// L1 is locked to user's exact data. L2-L14 derived by stepping along the
// main building diagonal from L1's right corner (631, 1238).
const OVERRIDES: Record<string, [number, number][]> = {
  // ── PISO 1 ────────────────────────────────────────────────────────────
  'L1':       [[415, 1300], [631, 1238], [482, 1060], [263, 1115]], // exact user data
  'L2':       [[631, 1238], [739, 1230], [589, 1045], [481, 1053]],
  'L3':       [[739, 1230], [847, 1222], [697, 1037], [589, 1045]],
  'L4':       [[847, 1222], [955, 1214], [805, 1029], [697, 1037]],
  'L5':       [[955, 1214], [1063, 1206],[913, 1021], [805, 1029]],
  'L6-L7':    [[1063, 1206],[1279, 1189],[1129, 1004],[913, 1021]],
  'L8-L9':    [[1441, 1177],[1657, 1161],[1507, 976], [1291, 992]],
  'L10':      [[1657, 1161],[1765, 1153],[1615, 968], [1507, 976]],
  'L11':      [[1765, 1153],[1873, 1145],[1723, 960], [1615, 968]],
  'L12':      [[1873, 1145],[1981, 1137],[1831, 952], [1723, 960]],
  'L13':      [[1981, 1137],[2089, 1129],[1939, 944], [1831, 952]],
  'L14-L15':  [[2089, 1129],[2413, 1104],[2263, 919], [1939, 944]],

  // ── PISO 2 (estimated — same DEPTH_DX/DY, P2_Y0 ≈ 730 at X_LEFT=380) ──
  'L16-L17-L18': [[380, 730], [704, 706], [554, 426], [230, 450]],
  'L19':         [[704, 706], [812, 698], [662, 418], [554, 426]],
  'L20':         [[812, 698], [920, 690], [770, 410], [662, 418]],
  'L21':         [[920, 690], [1028, 681],[878, 401], [770, 410]],
  'L22':         [[1028, 681],[1136, 673],[986, 393], [878, 401]],
  'L23':         [[1136, 673],[1244, 665],[1094, 385],[986, 393]],
  'L24':         [[1406, 653],[1514, 645],[1364, 365],[1256, 373]],
  'L25':         [[1514, 645],[1622, 637],[1472, 357],[1364, 365]],
  'L26':         [[1622, 637],[1730, 629],[1580, 349],[1472, 357]],
  'L27':         [[1730, 629],[1838, 621],[1688, 341],[1580, 349]],
  'L28-L29':     [[1838, 621],[2054, 604],[1904, 324],[1688, 341]],
  'L30-L31':     [[2054, 604],[2378, 580],[2228, 300],[1904, 324]],
};

// ─── Exported hotspot list ────────────────────────────────────────────────────
// Use per-unit overrides if present, otherwise fall back to the formula result.
const computed: HotspotDef[] = [
  ...buildPiso(PISO1_SEGS, 1.5, 5, '1'),
  ...buildPiso(PISO2_SEGS, 1.5, 5, '2'),
];

export const HOTSPOTS: HotspotDef[] = computed.map((h) => {
  const key = h.loteKeys.join('-');
  return OVERRIDES[key] ? { ...h, points: OVERRIDES[key] } : h;
});

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
