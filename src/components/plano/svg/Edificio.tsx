/**
 * Edificio — architectural floor-plan style.
 *
 * Design principle: restraint over decoration.
 * - Solid terracotta for available units makes them pop.
 * - No fake 3-D, no textures, no gradients.
 * - Thin-line structural elements (concrete hatch for core + slab).
 * - Title block with legend in the header band.
 *
 * Y zones (must stay in sync with geometry.ts):
 *   PISO1_UNITS_Y  =  52   (BLOQUE_HEIGHT = 155 → bottom = 207)
 *   PISO2_UNITS_Y  = 253   (bottom = 408)
 */

// ── Layout (SVG absolute coordinates) ────────────────────────────────────────
const BX  = 100;           // unit-zone left edge (matches translate offset)
const BW  = 1200;          // unit-zone width
const BR  = BX + BW;       // 1300

// Outer frame is slightly wider than the unit zone
const FX  = 52;
const FW  = 1296;
const FR  = FX + FW;       // 1348

// Vertical landmarks
const HDR_TOP    =  0;
const HDR_H      = 48;     // header band height
const P1_TOP     = HDR_H;  // 48  (just below header — 4px above PISO1_UNITS_Y)
const P1_ZONE_H  = 159;    // P1_TOP → 207
const SEP_TOP    = 207;
const SEP_H      = 46;     // 207 → 253
const P2_TOP     = 253;
const P2_ZONE_H  = 155;    // 253 → 408
const FOOT_TOP   = 408;
const FOOT_H     = 22;     // base plinth
const FRAME_BOT  = FOOT_TOP + FOOT_H;    // 430
const FRAME_H    = FRAME_BOT - HDR_TOP;  // 430

// Legend dot size
const DOT = 10;

// ── Colors ───────────────────────────────────────────────────────────────────
const INK      = '#1F1A14';
const BONE     = '#FAF6F0';
const TERRA    = '#B85A3C';
const SLATE    = '#5A544A';
const SAND     = '#F1E9DC';
const UNIT_BG  = '#FFFFFF';
const COL_BG   = '#EAE4DC';  // exterior columns / margins
const SEP_BG   = '#E8E1D7';  // floor-slab separator
const FOOT_BG  = '#C8C0B6';
const CORE_BG  = '#2A2620';  // structural core

export function Edificio({ coreSvgX = 665 }: { coreSvgX?: number }) {
  const CORE_W  = 70;
  // Core spans from just below header to the base plinth
  const coreTop = P1_TOP;
  const coreH   = FOOT_TOP - coreTop;   // 360
  const coreCX  = coreSvgX + CORE_W / 2;
  const coreCY  = coreTop  + coreH  / 2;

  return (
    <>
      <defs>
        {/* Diagonal hatch for structural/concrete elements */}
        <pattern id="concreteHatch" x="0" y="0" width="8" height="8" patternUnits="userSpaceOnUse">
          <rect width="8" height="8" fill={SEP_BG} />
          <line x1="0" y1="4" x2="4" y2="0" stroke={SLATE} strokeWidth="0.6" opacity="0.25" />
          <line x1="4" y1="8" x2="8" y2="4" stroke={SLATE} strokeWidth="0.6" opacity="0.25" />
        </pattern>
        <pattern id="coreHatch" x="0" y="0" width="6" height="6" patternUnits="userSpaceOnUse">
          <rect width="6" height="6" fill={CORE_BG} />
          <line x1="0" y1="3" x2="3" y2="0" stroke="#4A443C" strokeWidth="0.8" opacity="0.5" />
          <line x1="3" y1="6" x2="6" y2="3" stroke="#4A443C" strokeWidth="0.8" opacity="0.5" />
        </pattern>
      </defs>

      {/* ── 1 · Outer frame fill (columns + margins) ─────────────────────── */}
      <rect x={FX} y={HDR_TOP} width={FW} height={FRAME_H} fill={COL_BG} />

      {/* ── 2 · Unit-zone whites ─────────────────────────────────────────── */}
      <rect x={BX} y={P1_TOP}  width={BW} height={P1_ZONE_H} fill={UNIT_BG} />
      <rect x={BX} y={P2_TOP}  width={BW} height={P2_ZONE_H} fill={UNIT_BG} />

      {/* ── 3 · Floor-slab separator ─────────────────────────────────────── */}
      <rect x={FX} y={SEP_TOP} width={FW} height={SEP_H} fill="url(#concreteHatch)" />
      <line x1={FX} y1={SEP_TOP}           x2={FR} y2={SEP_TOP}           stroke={INK} strokeWidth="1" opacity="0.18" />
      <line x1={FX} y1={SEP_TOP + SEP_H}   x2={FR} y2={SEP_TOP + SEP_H}  stroke={INK} strokeWidth="1" opacity="0.18" />
      <text
        x={coreSvgX - 30} y={SEP_TOP + SEP_H / 2 + 4}
        textAnchor="end"
        fontFamily="var(--font-body), sans-serif"
        fontSize="7.5" fill={SLATE} opacity="0.55" letterSpacing="0.2em"
      >
        PASILLO · ESCALERAS
      </text>

      {/* ── 4 · Piso labels on left margin ───────────────────────────────── */}
      {(
        [
          { label: 'PISO 1', cy: P1_TOP + P1_ZONE_H / 2 },
          { label: 'PISO 2', cy: P2_TOP + P2_ZONE_H / 2 },
        ] as const
      ).map(({ label, cy }) => (
        <text
          key={label}
          x={FX + (BX - FX) / 2}
          y={cy}
          textAnchor="middle" dominantBaseline="middle"
          fontFamily="var(--font-body), sans-serif"
          fontSize="8" fontWeight="700" fill={SLATE} opacity="0.5"
          letterSpacing="0.18em"
          transform={`rotate(-90 ${FX + (BX - FX) / 2} ${cy})`}
        >
          {label}
        </text>
      ))}

      {/* ── 5 · Structural core ──────────────────────────────────────────── */}
      <rect x={coreSvgX} y={coreTop} width={CORE_W} height={coreH} fill="url(#coreHatch)" />
      {/* Core label */}
      <text
        x={coreCX} y={coreCY}
        textAnchor="middle" dominantBaseline="middle"
        fontFamily="var(--font-body), sans-serif"
        fontSize="6" fill={BONE} opacity="0.55" letterSpacing="0.12em"
        transform={`rotate(-90 ${coreCX} ${coreCY})`}
      >
        NÚCLEO
      </text>
      <rect x={coreSvgX} y={coreTop} width={CORE_W} height={coreH}
        fill="none" stroke={INK} strokeWidth="0.8" opacity="0.3" />

      {/* ── 6 · Base plinth ──────────────────────────────────────────────── */}
      <rect x={FX} y={FOOT_TOP} width={FW} height={FOOT_H} fill={FOOT_BG} />
      <line x1={FX} y1={FOOT_TOP} x2={FR} y2={FOOT_TOP} stroke={INK} strokeWidth="1" opacity="0.2" />

      {/* ── 7 · Header band ──────────────────────────────────────────────── */}
      <rect x={FX} y={HDR_TOP} width={FW} height={HDR_H} fill={INK} />

      {/* Thin terracotta accent bar at bottom of header */}
      <rect x={FX} y={HDR_H - 3} width={FW} height={3} fill={TERRA} opacity="0.8" />

      {/* Wordmark */}
      <text
        x={FX + 20} y={29}
        fontFamily="var(--font-display), serif"
        fontSize="14" fontWeight="600" fill={BONE} letterSpacing="0.3em"
      >
        PLAZA ARIA
      </text>

      {/* Sub-label */}
      <text
        x={FX + 20} y={41}
        fontFamily="var(--font-body), sans-serif"
        fontSize="7.5" fill={BONE} opacity="0.4" letterSpacing="0.15em"
      >
        PLANO DE LOCALES · AV. HUAYACÁN, CANCÚN
      </text>

      {/* Legend (right side of header) */}
      {/* Disponible */}
      <rect x={FR - 190} y={HDR_H / 2 - DOT / 2} width={DOT} height={DOT} fill={TERRA} />
      <text
        x={FR - 190 + DOT + 5} y={HDR_H / 2 + 4}
        fontFamily="var(--font-body), sans-serif"
        fontSize="8" fill={BONE} opacity="0.75" letterSpacing="0.1em"
      >
        Disponible
      </text>
      {/* Ocupado */}
      <rect
        x={FR - 100} y={HDR_H / 2 - DOT / 2} width={DOT} height={DOT}
        fill="none" stroke={BONE} strokeWidth="1" opacity="0.45"
      />
      <text
        x={FR - 100 + DOT + 5} y={HDR_H / 2 + 4}
        fontFamily="var(--font-body), sans-serif"
        fontSize="8" fill={BONE} opacity="0.75" letterSpacing="0.1em"
      >
        Ocupado
      </text>

      {/* ── 8 · Outer border on top ───────────────────────────────────────── */}
      <rect x={FX} y={HDR_TOP} width={FW} height={FRAME_H}
        fill="none" stroke={INK} strokeWidth="1.5" opacity="0.5" />

      {/* Inner border around unit zones */}
      <rect x={BX} y={P1_TOP}  width={BW} height={P1_ZONE_H}
        fill="none" stroke={INK} strokeWidth="0.8" opacity="0.12" />
      <rect x={BX} y={P2_TOP}  width={BW} height={P2_ZONE_H}
        fill="none" stroke={INK} strokeWidth="0.8" opacity="0.12" />
    </>
  );
}
