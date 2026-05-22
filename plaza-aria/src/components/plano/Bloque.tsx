'use client';

import { motion } from 'framer-motion';
import type { BloqueLayout } from '@/lib/plano/geometry';

// ── Aria design tokens ────────────────────────────────────────────────────────
const C = {
  bone:            '#FAF6F0',
  ink:             '#1F1A14',
  slate:           '#5A544A',
  terra:           '#B85A3C',
  terraLight:      '#CC7055',
  terraDark:       '#8E3F26',
  unitOutline:     '#C4BAB0',  // occupied / próximamente border
  unitBg:          '#FFFFFF',  // occupied fill (white = transparent over zone)
  proximoBg:       '#F7F3EE',  // very light warm tint for próximamente
  highlight:       '#1F1A14',
} as const;

// Thin accent bar at base of each unit (available = terracotta, else none)
const ACCENT_H = 4;

type BloqueProps = {
  layout: Extract<BloqueLayout, { type: 'bloque' }>;
  /** Absolute SVG y of the top of this piso's unit zone. */
  yOffset: number;
  isHighlighted?: boolean;
  onClick?: (unidadId: string) => void;
  onHoverChange?: (unidadId: string | null) => void;
};

export function Bloque({
  layout,
  yOffset,
  isHighlighted = false,
  onClick,
  onHoverChange,
}: BloqueProps) {
  const { unidad, x, ancho, alto } = layout;

  const isDisponible = unidad.estado === 'Disponible';
  const isProximo    = unidad.estado === 'Proximamente';

  const unitId      = unidad.loteIds.join('-');
  const displayName = unidad.nombre || unitId;
  const maxChars    = Math.max(4, Math.floor(ancho / 7));
  const truncated   = displayName.length > maxChars
    ? displayName.slice(0, maxChars - 1) + '…'
    : displayName;

  const cx       = x + ancho / 2;
  const cy       = alto / 2 - 6;
  const fontSize = Math.min(Math.max(ancho * 0.12, 8), 15);

  // ── Visual convention (architectural floor-plan) ──────────────────────────
  // Available   → solid terracotta fill, bone text         (the ONLY colored units)
  // Occupied    → white fill, thin outline, dark text      (recede visually)
  // Próximamente→ barely-there warm tint, dashed outline   (ghosted)
  const bodyFill   = isDisponible ? C.terra     : isProximo ? C.proximoBg : C.unitBg;
  const bodyOpacity= isProximo ? 0.65 : 1;
  const strokeCol  = isHighlighted ? C.highlight
    : isDisponible ? C.terraDark
    : isProximo    ? C.unitOutline
    : C.unitOutline;
  const strokeW    = isHighlighted ? 2.5 : isDisponible ? 1.5 : 1;
  const strokeDash = isProximo ? '4 3' : undefined;
  const labelColor = isDisponible ? C.bone : C.slate;

  const handleEnter = () => onHoverChange?.(unidad.id);
  const handleLeave = () => onHoverChange?.(null);
  const handleClick = () => onClick?.(unidad.id);

  return (
    <motion.g
      data-testid={`bloque-${unidad.id}`}
      role="button"
      tabIndex={0}
      aria-label={`${displayName} · ${unidad.estado}`}
      style={{ cursor: 'pointer' }}
      animate={{ y: yOffset, opacity: bodyOpacity }}
      whileHover={{ y: yOffset - 3 }}
      transition={{ type: 'spring', stiffness: 340, damping: 30 }}
      onMouseEnter={handleEnter}
      onMouseLeave={handleLeave}
      onFocus={handleEnter}
      onBlur={handleLeave}
      onClick={handleClick}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleClick(); }
      }}
    >
      {/* ── Ambient glow for available units (subtle, professional) ──────── */}
      {isDisponible && (
        <motion.rect
          x={x - 3} y={-3}
          width={ancho + 6} height={alto + 6}
          rx={4} fill="none"
          stroke={C.terraLight} strokeWidth={2.5}
          animate={{ opacity: [0, 0.45, 0] }}
          transition={{ duration: 2.4, repeat: Infinity, ease: 'easeInOut', repeatDelay: 1.2 }}
          pointerEvents="none"
        />
      )}

      {/* ── Main body ────────────────────────────────────────────────────── */}
      <rect
        x={x} y={0}
        width={ancho} height={alto}
        fill={bodyFill}
        stroke={strokeCol}
        strokeWidth={strokeW}
        strokeDasharray={strokeDash}
        rx={1}
      />

      {/* ── Terracotta accent bar at bottom (available only) ─────────────── */}
      {isDisponible && (
        <rect
          x={x} y={alto - ACCENT_H}
          width={ancho} height={ACCENT_H}
          fill={C.terraDark} rx={1}
        />
      )}

      {/* ── Logo or text label ───────────────────────────────────────────── */}
      {unidad.logo?.url ? (
        <image
          href={unidad.logo.url}
          x={cx - Math.min(ancho * 0.35, 26)}
          y={cy - 14}
          width={Math.min(ancho * 0.7, 52)}
          height={28}
          preserveAspectRatio="xMidYMid meet"
        />
      ) : (
        <text
          x={cx} y={cy}
          textAnchor="middle" dominantBaseline="middle"
          fontFamily="var(--font-display), serif"
          fontSize={fontSize}
          fill={labelColor}
          fontWeight={isDisponible ? 600 : 400}
          opacity={isDisponible ? 1 : 0.6}
          aria-hidden
        >
          {truncated}
        </text>
      )}

      {/* ── "DISPONIBLE" micro-label (below main label) ──────────────────── */}
      {isDisponible && (
        <text
          x={cx} y={cy + fontSize * 0.9 + 6}
          textAnchor="middle" dominantBaseline="middle"
          fontFamily="var(--font-body), sans-serif"
          fontSize={6.5} fill={C.bone} opacity={0.75}
          letterSpacing={1.6} fontWeight={600}
          aria-hidden
        >
          DISPONIBLE
        </text>
      )}

      {/* ── "PRÓXIMAMENTE" micro-label ────────────────────────────────────── */}
      {isProximo && (
        <text
          x={cx} y={cy + fontSize * 0.9 + 6}
          textAnchor="middle" dominantBaseline="middle"
          fontFamily="var(--font-body), sans-serif"
          fontSize={6} fill={C.slate} opacity={0.55}
          letterSpacing={1.2}
          aria-hidden
        >
          PRÓXIMAMENTE
        </text>
      )}

      {/* ── Selected highlight ring ───────────────────────────────────────── */}
      {isHighlighted && (
        <rect
          x={x + 2} y={2}
          width={ancho - 4} height={alto - 4}
          rx={1} fill="none"
          stroke={C.highlight} strokeWidth={1.5} opacity={0.4}
        />
      )}
    </motion.g>
  );
}
