/**
 * Edificio — clean 2D floor-plan building shell.
 *
 * Draws the Plaza Aria building as a flat, architectural top-down view:
 *   • Dark header band with wordmark
 *   • "PISO 2" zone strip  (y=36–207)
 *   • Corridor / escaleras strip (y=207–237)
 *   • "PISO 1" zone strip  (y=237–408)
 *   • Core column running through both unit zones
 *
 * All x coordinates are in absolute SVG space (building occupies x=100–1300).
 * The interactive Bloque components are rendered on top by PlanoInteractivo.
 *
 * @param coreSvgX – absolute SVG x of the left edge of the core column,
 *   derived from computeLayout so the visual core matches the interactive one.
 */

// ── Layout constants (must match PlanoInteractivo + geometry) ─────────────────
const BX = 100;          // building left edge (SVG absolute)
const BW = 1200;         // building width
const BR = BX + BW;      // building right edge = 1300

const HEADER_Y = 0;
const HEADER_H = 36;

const P2_LABEL_Y = HEADER_Y + HEADER_H;   // 36
const P2_LABEL_H = 16;

const P2_UNITS_Y = P2_LABEL_Y + P2_LABEL_H;   // 52  ← must match PISO2_UNITS_Y in geometry
const P2_UNITS_H = 155;                          // must match BLOQUE_HEIGHT

const CORRIDOR_Y = P2_UNITS_Y + P2_UNITS_H;    // 207
const CORRIDOR_H = 30;

const P1_LABEL_Y = CORRIDOR_Y + CORRIDOR_H;     // 237
const P1_LABEL_H = 16;

const P1_UNITS_Y = P1_LABEL_Y + P1_LABEL_H;    // 253  ← must match PISO1_UNITS_Y in geometry
const P1_UNITS_H = 155;                          // must match BLOQUE_HEIGHT

const BUILDING_BOTTOM = P1_UNITS_Y + P1_UNITS_H; // 408
const BUILDING_H = BUILDING_BOTTOM - HEADER_Y;    // 408

const CORE_W = 70;

// ── Color palette (Aria design tokens) ───────────────────────────────────────
const INK   = '#1F1A14';
const BONE  = '#FAF6F0';
const SAND  = '#F1E9DC';
const SLATE = '#5A544A';
const LINE  = '#E7DFD1';

export function Edificio({ coreSvgX = 665 }: { coreSvgX?: number }) {
  const coreH = BUILDING_BOTTOM - P2_UNITS_Y;   // 356
  const coreTextX = coreSvgX + CORE_W / 2;
  const coreTextY = P2_UNITS_Y + coreH / 2;

  return (
    <>
      <defs>
        {/* Diagonal hatch for corridor */}
        <pattern id="corridorHatch" x="0" y="0" width="14" height="14" patternUnits="userSpaceOnUse">
          <rect width="14" height="14" fill="#E9E2D8" />
          <line x1="0" y1="7" x2="7" y2="0" stroke={LINE} strokeWidth="1" opacity="0.8" />
          <line x1="7" y1="14" x2="14" y2="7" stroke={LINE} strokeWidth="1" opacity="0.8" />
        </pattern>

        {/* Fine grid for core column */}
        <pattern id="coreGrid" x="0" y="0" width="10" height="10" patternUnits="userSpaceOnUse">
          <rect width="10" height="10" fill="#35302A" />
          <line x1="5" y1="0" x2="5" y2="10" stroke="#4A443C" strokeWidth="0.6" opacity="0.6" />
          <line x1="0" y1="5" x2="10" y2="5" stroke="#4A443C" strokeWidth="0.6" opacity="0.6" />
        </pattern>
      </defs>

      {/* ── Drop shadow ──────────────────────────────────────────────────── */}
      <rect
        x={BX + 5} y={6}
        width={BW} height={BUILDING_H}
        rx={5} fill={INK} opacity={0.08}
      />

      {/* ── Building base fill ───────────────────────────────────────────── */}
      <rect
        x={BX} y={HEADER_Y}
        width={BW} height={BUILDING_H}
        rx={4} fill={BONE}
      />

      {/* ── Header / fascia bar ──────────────────────────────────────────── */}
      {/* Dark band */}
      <rect x={BX} y={HEADER_Y} width={BW} height={HEADER_H} rx={4} fill={INK} />
      {/* Square-off the bottom corners */}
      <rect x={BX} y={HEADER_Y + HEADER_H - 4} width={BW} height={4} fill={INK} />

      {/* Location sub-label (left) */}
      <text
        x={BX + 14} y={HEADER_Y + 21}
        fontFamily="var(--font-body), sans-serif"
        fontSize="8" fill={BONE} opacity={0.5} letterSpacing="0.12em"
      >
        AV. HUAYACÁN · CANCÚN · PLANTA BAJA / PISO 2
      </text>

      {/* Wordmark (center) */}
      <text
        x={BX + BW / 2} y={HEADER_Y + 22}
        textAnchor="middle"
        fontFamily="var(--font-display), serif"
        fontSize="12" fontWeight="600" fill={BONE} letterSpacing="0.28em"
      >
        PLAZA ARIA
      </text>

      {/* ── Piso 2 label strip ───────────────────────────────────────────── */}
      <rect x={BX} y={P2_LABEL_Y} width={BW} height={P2_LABEL_H} fill={SAND} />
      <line x1={BX} y1={P2_LABEL_Y + P2_LABEL_H} x2={BR} y2={P2_LABEL_Y + P2_LABEL_H}
        stroke={LINE} strokeWidth="1" />
      <text
        x={BX + 14} y={P2_LABEL_Y + 11}
        fontFamily="var(--font-body), sans-serif"
        fontSize="8.5" fontWeight="700" fill={SLATE} letterSpacing="0.18em"
      >
        PISO 2
      </text>

      {/* ── Piso 2 unit zone background ──────────────────────────────────── */}
      <rect x={BX} y={P2_UNITS_Y} width={BW} height={P2_UNITS_H} fill="#F5EFE6" />

      {/* ── Corridor ─────────────────────────────────────────────────────── */}
      <rect x={BX} y={CORRIDOR_Y} width={BW} height={CORRIDOR_H} fill="url(#corridorHatch)" />
      <line x1={BX} y1={CORRIDOR_Y} x2={BR} y2={CORRIDOR_Y} stroke="#C8BFB3" strokeWidth="1" />
      <line x1={BX} y1={CORRIDOR_Y + CORRIDOR_H} x2={BR} y2={CORRIDOR_Y + CORRIDOR_H}
        stroke="#C8BFB3" strokeWidth="1" />
      <text
        x={BX + BW / 2} y={CORRIDOR_Y + 19}
        textAnchor="middle"
        fontFamily="var(--font-body), sans-serif"
        fontSize="7.5" fill={SLATE} opacity="0.6" letterSpacing="0.22em"
      >
        PASILLO · ESCALERAS
      </text>

      {/* ── Piso 1 label strip ───────────────────────────────────────────── */}
      <rect x={BX} y={P1_LABEL_Y} width={BW} height={P1_LABEL_H} fill={SAND} />
      <line x1={BX} y1={P1_LABEL_Y + P1_LABEL_H} x2={BR} y2={P1_LABEL_Y + P1_LABEL_H}
        stroke={LINE} strokeWidth="1" />
      <text
        x={BX + 14} y={P1_LABEL_Y + 11}
        fontFamily="var(--font-body), sans-serif"
        fontSize="8.5" fontWeight="700" fill={SLATE} letterSpacing="0.18em"
      >
        PISO 1
      </text>

      {/* ── Piso 1 unit zone background ──────────────────────────────────── */}
      <rect x={BX} y={P1_UNITS_Y} width={BW} height={P1_UNITS_H} fill="#F5EFE6" />

      {/* ── Core column (spans both unit zones) ──────────────────────────── */}
      <rect x={coreSvgX} y={P2_UNITS_Y} width={CORE_W} height={coreH} fill="url(#coreGrid)" />

      {/* Core label rotated */}
      <text
        x={coreTextX} y={coreTextY}
        textAnchor="middle" dominantBaseline="middle"
        fontFamily="var(--font-body), sans-serif"
        fontSize="7" fill={BONE} opacity="0.6" letterSpacing="0.12em"
        transform={`rotate(-90 ${coreTextX} ${coreTextY})`}
      >
        NÚCLEO · ESCALERAS
      </text>

      {/* Core border */}
      <rect x={coreSvgX} y={P2_UNITS_Y} width={CORE_W} height={coreH}
        fill="none" stroke={INK} strokeWidth="1" opacity="0.25" />

      {/* Core crossing the corridor — cover hatch with solid */}
      <rect x={coreSvgX} y={CORRIDOR_Y} width={CORE_W} height={CORRIDOR_H}
        fill="#35302A" opacity="0.85" />

      {/* ── Bottom sill ──────────────────────────────────────────────────── */}
      <rect x={BX} y={BUILDING_BOTTOM} width={BW} height={6} fill={INK} opacity="0.12" />

      {/* ── Building outer border ────────────────────────────────────────── */}
      <rect x={BX} y={HEADER_Y} width={BW} height={BUILDING_H}
        rx={4} fill="none" stroke={INK} strokeWidth="1.5" />
    </>
  );
}
