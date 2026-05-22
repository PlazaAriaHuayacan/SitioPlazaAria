/**
 * Edificio — Building elevation with cabinet-projection depth.
 *
 * The building is shown as a front elevation.  Select horizontal surfaces
 * (roof fascia, floor slab, foundation plinth) receive a thin parallelogram
 * "top face" to suggest depth without breaking the SVG coordinate system.
 *
 * Y zones (must match geometry.ts constants):
 *   PISO1_UNITS_Y  =  52   → top of Piso 1 interactive band
 *   PISO2_UNITS_Y  = 253   → top of Piso 2 interactive band
 *   BLOQUE_HEIGHT  = 155
 */

// ── Depth parameters (cabinet projection, very subtle) ───────────────────────
const DX = 7;   // horizontal offset of depth faces (px, rightward)
const DY = 4;   // vertical   offset of depth faces (px, upward)

/** Points string for a thin top-face parallelogram above a horizontal line. */
function topFace(x1: number, y: number, x2: number): string {
  return `${x1},${y} ${x2},${y} ${x2 + DX},${y - DY} ${x1 + DX},${y - DY}`;
}
/** Points string for a right-face parallelogram on the right edge of a rect. */
function rightFace(x: number, y1: number, y2: number): string {
  return `${x},${y1} ${x},${y2} ${x + DX},${y2 - DY} ${x + DX},${y1 - DY}`;
}

// ── Layout constants ──────────────────────────────────────────────────────────
// Interior (where interactive Bloques live)
const BX = 100;
const BW = 1200;
const BR = BX + BW;   // 1300

// Exterior (columns + fascia, slightly wider than interior)
const EX  = 74;
const EW  = 1252;
const ER  = EX + EW;  // 1326

// Column width on each side (exterior minus interior)
const COL = BX - EX;  // 26 px

// Vertical (all SVG absolute coordinates)
const ROOF_TOP      =  18;   // top of visible building
const HEADER_BOT    =  52;   // = PISO1_UNITS_Y — where header ends / p1 zone starts
const P1_BOT        = 207;   // piso 1 zone bottom   (= PISO1_UNITS_Y + BLOQUE_HEIGHT)
const SLAB_TOP      = P1_BOT;
const SLAB_BOT      = 253;   // = PISO2_UNITS_Y — slab ends / p2 zone starts
const P2_BOT        = 408;   // piso 2 zone bottom
const PLINTH_TOP    = P2_BOT;
const PLINTH_BOT    = 432;
const BLDG_H        = PLINTH_BOT - ROOF_TOP;  // total visual height

// Accent stripe on header
const STRIPE_H = 5;

// Colors
const FACADE      = '#F0E9DF';  // warm stucco / plaster
const FACADE_DARK = '#DDD4C8';  // column shadow face
const HEADER_BG   = '#1F1A14';  // ink
const SLAB_FRONT  = '#7E7870';  // concrete grey
const SLAB_TOP_C  = '#5A5450';  // slab top-face (darker)
const PLINTH_C    = '#6A6460';
const PLINTH_TOP_C= '#504C48';
const UNIT_BG     = '#F5F0E8';  // slightly warm white for unit zones
const TERRACOTTA  = '#B85A3C';
const BONE        = '#FAF6F0';
const SLATE       = '#5A544A';
const LINE        = '#E7DFD1';
const CORE_C      = '#2E2A26';
const CORE_TOP_C  = '#201E1A';

export function Edificio({ coreSvgX = 665 }: { coreSvgX?: number }) {
  const CORE_W = 70;
  const coreH = PLINTH_BOT - HEADER_BOT;
  const coreTextX = coreSvgX + CORE_W / 2;
  const coreTextY = HEADER_BOT + coreH / 2;

  return (
    <>
      <defs>
        {/* Stucco facade texture (fine diagonal) */}
        <pattern id="stucco" x="0" y="0" width="24" height="24" patternUnits="userSpaceOnUse">
          <rect width="24" height="24" fill={FACADE} />
          <line x1="0" y1="12" x2="12" y2="0" stroke={FACADE_DARK} strokeWidth="0.4" opacity="0.25" />
          <line x1="12" y1="24" x2="24" y2="12" stroke={FACADE_DARK} strokeWidth="0.4" opacity="0.25" />
        </pattern>

        {/* Core concrete grid */}
        <pattern id="coreGrid" x="0" y="0" width="10" height="10" patternUnits="userSpaceOnUse">
          <rect width="10" height="10" fill={CORE_C} />
          <line x1="5"  y1="0"  x2="5"  y2="10" stroke="#4A443C" strokeWidth="0.6" opacity="0.5" />
          <line x1="0"  y1="5"  x2="10" y2="5"  stroke="#4A443C" strokeWidth="0.6" opacity="0.5" />
        </pattern>

        {/* Corridor diagonal hatch */}
        <pattern id="slabHatch" x="0" y="0" width="12" height="12" patternUnits="userSpaceOnUse">
          <rect width="12" height="12" fill="#E2DAD2" />
          <line x1="0" y1="6" x2="6" y2="0" stroke="#CAC2BA" strokeWidth="0.8" opacity="0.6" />
          <line x1="6" y1="12" x2="12" y2="6" stroke="#CAC2BA" strokeWidth="0.8" opacity="0.6" />
        </pattern>

        {/* Drop shadow for whole building */}
        <filter id="bldgShadow" x="-4%" y="-4%" width="112%" height="120%">
          <feDropShadow dx="4" dy="8" stdDeviation="8" floodColor="#1F1A14" floodOpacity="0.14" />
        </filter>
      </defs>

      {/* ═══════════════════════════════════════════════════════════════════
          0.  Shadow layer
      ═══════════════════════════════════════════════════════════════════ */}
      <rect
        x={EX} y={ROOF_TOP}
        width={EW} height={BLDG_H}
        rx={3} fill="#1F1A14" opacity={0.1}
        transform="translate(5,8)"
      />

      {/* ═══════════════════════════════════════════════════════════════════
          1.  Building main face (stucco exterior fill)
      ═══════════════════════════════════════════════════════════════════ */}
      <rect
        x={EX} y={ROOF_TOP}
        width={EW} height={BLDG_H}
        rx={3} fill="url(#stucco)"
      />

      {/* ═══════════════════════════════════════════════════════════════════
          2.  Roof fascia — top face + front face
      ═══════════════════════════════════════════════════════════════════ */}
      {/* Top-face depth parallelogram */}
      <polygon
        points={topFace(EX, ROOF_TOP, ER)}
        fill={FACADE_DARK}
        opacity={0.7}
      />
      {/* Front face of roof fascia */}
      <rect x={EX} y={ROOF_TOP} width={EW} height={HEADER_BOT - ROOF_TOP} fill={HEADER_BG} rx={3} />
      {/* Square off bottom corners of header */}
      <rect x={EX} y={HEADER_BOT - 4} width={EW} height={4} fill={HEADER_BG} />

      {/* Terracotta accent stripe at header bottom */}
      <rect x={EX} y={HEADER_BOT - STRIPE_H} width={EW} height={STRIPE_H} fill={TERRACOTTA} opacity={0.85} />

      {/* Location micro-label (left) */}
      <text
        x={BX + 14} y={ROOF_TOP + 19}
        fontFamily="var(--font-body), sans-serif"
        fontSize="7.5" fill={BONE} opacity={0.45} letterSpacing="0.12em"
      >
        AV. HUAYACÁN · CANCÚN
      </text>

      {/* Wordmark (center) */}
      <text
        x={EX + EW / 2} y={ROOF_TOP + 22}
        textAnchor="middle"
        fontFamily="var(--font-display), serif"
        fontSize="13" fontWeight="600" fill={BONE} letterSpacing="0.3em"
      >
        PLAZA ARIA
      </text>

      {/* Right-face of left roof column edge */}
      <polygon
        points={rightFace(ER, ROOF_TOP, HEADER_BOT)}
        fill={FACADE_DARK}
        opacity={0.5}
      />

      {/* ═══════════════════════════════════════════════════════════════════
          3.  Left exterior column
      ═══════════════════════════════════════════════════════════════════ */}
      {/* Column front face */}
      <rect x={EX} y={HEADER_BOT} width={COL} height={PLINTH_BOT - HEADER_BOT} fill="url(#stucco)" />
      {/* Column right-face depth */}
      <polygon
        points={rightFace(BX, HEADER_BOT, PLINTH_BOT)}
        fill={FACADE_DARK}
        opacity={0.45}
      />
      {/* Thin vertical grooves on column */}
      <line x1={EX + 8}  y1={HEADER_BOT + 6} x2={EX + 8}  y2={PLINTH_BOT - 6} stroke={FACADE_DARK} strokeWidth="1" opacity={0.4} />
      <line x1={EX + 17} y1={HEADER_BOT + 6} x2={EX + 17} y2={PLINTH_BOT - 6} stroke={FACADE_DARK} strokeWidth="1" opacity={0.4} />

      {/* ═══════════════════════════════════════════════════════════════════
          4.  Right exterior column
      ═══════════════════════════════════════════════════════════════════ */}
      <rect x={BR} y={HEADER_BOT} width={COL} height={PLINTH_BOT - HEADER_BOT} fill="url(#stucco)" />
      <line x1={BR + 8}  y1={HEADER_BOT + 6} x2={BR + 8}  y2={PLINTH_BOT - 6} stroke={FACADE_DARK} strokeWidth="1" opacity={0.4} />
      <line x1={BR + 17} y1={HEADER_BOT + 6} x2={BR + 17} y2={PLINTH_BOT - 6} stroke={FACADE_DARK} strokeWidth="1" opacity={0.4} />

      {/* ═══════════════════════════════════════════════════════════════════
          5.  Piso 1 unit zone background
      ═══════════════════════════════════════════════════════════════════ */}
      <rect x={BX} y={HEADER_BOT} width={BW} height={P1_BOT - HEADER_BOT} fill={UNIT_BG} />

      {/* Piso 1 label (subtle, inside zone) */}
      <text
        x={BX + 10} y={HEADER_BOT + 13}
        fontFamily="var(--font-body), sans-serif"
        fontSize="7.5" fontWeight="700" fill={SLATE} opacity={0.45} letterSpacing="0.18em"
      >
        PISO 1
      </text>

      {/* ═══════════════════════════════════════════════════════════════════
          6.  Floor slab (between pisos)
      ═══════════════════════════════════════════════════════════════════ */}
      {/* Top-face depth of slab */}
      <polygon
        points={topFace(BX, SLAB_TOP, BR)}
        fill={SLAB_TOP_C}
        opacity={0.85}
      />
      {/* Slab front face */}
      <rect x={BX} y={SLAB_TOP} width={BW} height={SLAB_BOT - SLAB_TOP} fill="url(#slabHatch)" />

      {/* Slab frame lines */}
      <line x1={BX} y1={SLAB_TOP}  x2={BR} y2={SLAB_TOP}  stroke={SLAB_FRONT} strokeWidth="1.5" opacity="0.6" />
      <line x1={BX} y1={SLAB_BOT}  x2={BR} y2={SLAB_BOT}  stroke={SLAB_FRONT} strokeWidth="1.5" opacity="0.6" />

      {/* Corridor label on the slab */}
      <text
        x={BX + BW / 2} y={SLAB_TOP + (SLAB_BOT - SLAB_TOP) / 2 + 4}
        textAnchor="middle"
        fontFamily="var(--font-body), sans-serif"
        fontSize="7.5" fill={SLATE} opacity={0.55} letterSpacing="0.22em"
      >
        PASILLO · ESCALERAS · PISO 2
      </text>

      {/* ═══════════════════════════════════════════════════════════════════
          7.  Piso 2 unit zone background
      ═══════════════════════════════════════════════════════════════════ */}
      <rect x={BX} y={SLAB_BOT} width={BW} height={P2_BOT - SLAB_BOT} fill={UNIT_BG} />

      <text
        x={BX + 10} y={SLAB_BOT + 13}
        fontFamily="var(--font-body), sans-serif"
        fontSize="7.5" fontWeight="700" fill={SLATE} opacity={0.45} letterSpacing="0.18em"
      >
        PISO 2
      </text>

      {/* ═══════════════════════════════════════════════════════════════════
          8.  Foundation plinth
      ═══════════════════════════════════════════════════════════════════ */}
      {/* Top-face depth */}
      <polygon
        points={topFace(EX, PLINTH_TOP, ER)}
        fill={PLINTH_TOP_C}
        opacity={0.75}
      />
      {/* Front face */}
      <rect x={EX} y={PLINTH_TOP} width={EW} height={PLINTH_BOT - PLINTH_TOP} fill={PLINTH_C} />
      {/* Right-face depth on plinth */}
      <polygon
        points={rightFace(ER, PLINTH_TOP, PLINTH_BOT)}
        fill={PLINTH_TOP_C}
        opacity={0.6}
      />

      {/* ═══════════════════════════════════════════════════════════════════
          9.  Core column (staircase / elevator tower)
      ═══════════════════════════════════════════════════════════════════ */}
      {/* Core top-face protrudes above the header (above y=ROOF_TOP) */}
      <polygon
        points={topFace(coreSvgX, ROOF_TOP - 8, coreSvgX + CORE_W)}
        fill={CORE_TOP_C}
        opacity={0.9}
      />
      {/* Core front face — taller than the building (protrudes above roofline) */}
      <rect x={coreSvgX} y={ROOF_TOP - 8} width={CORE_W} height={coreH + 8} fill="url(#coreGrid)" />
      {/* Core right-face depth (small strip on right edge) */}
      <polygon
        points={rightFace(coreSvgX + CORE_W, ROOF_TOP - 8, PLINTH_BOT)}
        fill={CORE_TOP_C}
        opacity={0.6}
      />
      {/* Core label (rotated) */}
      <text
        x={coreTextX} y={coreTextY}
        textAnchor="middle" dominantBaseline="middle"
        fontFamily="var(--font-body), sans-serif"
        fontSize="6.5" fill={BONE} opacity={0.5} letterSpacing="0.1em"
        transform={`rotate(-90 ${coreTextX} ${coreTextY})`}
      >
        NÚCLEO · ESCALERAS
      </text>
      {/* Core border */}
      <rect
        x={coreSvgX} y={ROOF_TOP - 8} width={CORE_W} height={coreH + 8}
        fill="none" stroke={CORE_TOP_C} strokeWidth="1" opacity={0.4}
      />

      {/* ═══════════════════════════════════════════════════════════════════
         10.  Building outer border
      ═══════════════════════════════════════════════════════════════════ */}
      <rect
        x={EX} y={ROOF_TOP} width={EW} height={BLDG_H}
        rx={3} fill="none" stroke={HEADER_BG} strokeWidth="1.5" opacity={0.6}
      />
    </>
  );
}
