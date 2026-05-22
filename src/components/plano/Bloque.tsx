'use client';

import { motion } from 'framer-motion';
import type { BloqueLayout } from '@/lib/plano/geometry';

// ── Aria design tokens ────────────────────────────────────────────────────────
const C = {
  bone:            '#FAF6F0',
  ink:             '#1F1A14',
  slate:           '#5A544A',
  terracotta:      '#B85A3C',
  terracottaDark:  '#8E3F26',
  terracottaLight: '#CC7055',
  lineColor:       '#E7DFD1',
  cream:           '#EDE7DD',
  creamDark:       '#C8BFB3',
  sand:            '#F1E9DC',
  sandDark:        '#D8CFC3',
} as const;

// Height reserved for the "glazing / vitrina" strip at the bottom of each unit
const GLAZING_H = 28;
// Height of the base threshold strip (depth effect)
const BASE_H = 5;

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
  const maxChars    = Math.max(4, Math.floor(ancho / 6.5));
  const truncated   = displayName.length > maxChars
    ? displayName.slice(0, maxChars - 1) + '…'
    : displayName;

  const cx       = x + ancho / 2;
  const labelY   = (alto - GLAZING_H) / 2 + 5;
  const fontSize = Math.min(Math.max(ancho * 0.13, 9), 17);
  const opacity  = isProximo ? 0.58 : 1;

  // Colors per estado
  const mainFill   = isDisponible ? C.terracotta   : isProximo ? C.sand    : C.cream;
  const topFill    = isDisponible ? C.terracottaLight : isProximo ? '#F7F1E8' : '#F5EEE6';
  const baseFill   = isDisponible ? C.terracottaDark  : isProximo ? C.sandDark : C.creamDark;
  const borderCol  = isHighlighted ? C.ink
    : isDisponible ? C.terracottaDark
    : isProximo    ? C.slate
    : C.lineColor;
  const borderW    = isHighlighted ? 2.5 : isDisponible ? 1.5 : 1;
  const textColor  = isDisponible ? C.bone : C.ink;
  const glazingCol = isDisponible ? 'rgba(250,246,240,0.18)' : 'rgba(200,191,179,0.35)';

  const handleEnter  = () => onHoverChange?.(unidad.id);
  const handleLeave  = () => onHoverChange?.(null);
  const handleClick  = () => onClick?.(unidad.id);

  return (
    <motion.g
      data-testid={`bloque-${unidad.id}`}
      role="button"
      tabIndex={0}
      aria-label={`${displayName} · ${unidad.estado}`}
      style={{ cursor: 'pointer', opacity }}
      animate={{ y: yOffset }}
      whileHover={{ y: yOffset - 4 }}
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
      {/* ── Pulse glow for disponibles ──────────────────────────────────── */}
      {isDisponible && (
        <motion.rect
          x={x - 4} y={-4}
          width={ancho + 8} height={alto + 8}
          rx={6} fill="none"
          stroke={C.terracottaLight} strokeWidth={3}
          animate={{ opacity: [0, 0.5, 0] }}
          transition={{ duration: 2.2, repeat: Infinity, ease: 'easeInOut', repeatDelay: 0.6 }}
        />
      )}

      {/* ── Top highlight strip (light-from-above illusion) ──────────────── */}
      <rect x={x} y={0} width={ancho} height={6} rx={2} fill={topFill} />

      {/* ── Main storefront body ─────────────────────────────────────────── */}
      <rect
        x={x} y={6}
        width={ancho} height={alto - GLAZING_H - BASE_H - 6}
        fill={mainFill}
      />

      {/* ── Glazing / vitrina strip at bottom ────────────────────────────── */}
      <rect
        x={x} y={alto - GLAZING_H - BASE_H}
        width={ancho} height={GLAZING_H}
        fill={glazingCol}
      />
      {/* Thin horizontal line separating body from glazing */}
      <line
        x1={x} y1={alto - GLAZING_H - BASE_H}
        x2={x + ancho} y2={alto - GLAZING_H - BASE_H}
        stroke={isDisponible ? 'rgba(250,246,240,0.3)' : C.lineColor}
        strokeWidth={0.8}
      />

      {/* ── Base threshold (depth cue at very bottom) ─────────────────────── */}
      <rect
        x={x} y={alto - BASE_H}
        width={ancho} height={BASE_H}
        fill={baseFill} opacity={0.75}
      />

      {/* ── Outer border ──────────────────────────────────────────────────── */}
      <rect
        x={x} y={0}
        width={ancho} height={alto}
        rx={2}
        fill="none"
        stroke={borderCol}
        strokeWidth={borderW}
      />

      {/* ── Logo or text label ───────────────────────────────────────────── */}
      {unidad.logo?.url ? (
        <image
          href={unidad.logo.url}
          x={cx - Math.min(ancho * 0.35, 28)}
          y={labelY - 18}
          width={Math.min(ancho * 0.7, 56)}
          height={32}
          preserveAspectRatio="xMidYMid meet"
        />
      ) : (
        <text
          x={cx} y={labelY}
          textAnchor="middle"
          fontFamily="var(--font-display), serif"
          fontSize={fontSize}
          fill={textColor}
          fontWeight={isDisponible ? 600 : 500}
          aria-hidden
        >
          {truncated}
        </text>
      )}

      {/* ── "DISPONIBLE" sub-label in glazing strip ──────────────────────── */}
      {isDisponible && (
        <text
          x={cx} y={alto - BASE_H - GLAZING_H / 2 + 4}
          textAnchor="middle"
          fontFamily="var(--font-body), sans-serif"
          fontSize={7} fill={C.bone} opacity={0.85}
          letterSpacing={1.8} fontWeight={600}
          aria-hidden
        >
          DISPONIBLE
        </text>
      )}

      {/* ── "PRÓXIMAMENTE" in glazing strip ──────────────────────────────── */}
      {isProximo && (
        <text
          x={cx} y={alto - BASE_H - GLAZING_H / 2 + 4}
          textAnchor="middle"
          fontFamily="var(--font-body), sans-serif"
          fontSize={6.5} fill={C.slate} opacity={0.8}
          letterSpacing={1.2} fontWeight={600}
          aria-hidden
        >
          PRÓXIMAMENTE
        </text>
      )}

      {/* ── Selected highlight overlay ────────────────────────────────────── */}
      {isHighlighted && (
        <rect
          x={x + 1} y={1}
          width={ancho - 2} height={alto - 2}
          rx={1} fill="none"
          stroke={C.ink} strokeWidth={1.5} opacity={0.35}
        />
      )}
    </motion.g>
  );
}
