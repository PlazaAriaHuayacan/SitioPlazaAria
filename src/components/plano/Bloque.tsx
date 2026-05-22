'use client';

import { motion } from 'framer-motion';
import type { BloqueLayout } from '@/lib/plano/geometry';

const C = {
  bone:            '#FAF6F0',
  sand:            '#F1E9DC',
  ink:             '#1F1A14',
  slate:           '#5A544A',
  terracotta:      '#B85A3C',
  terracottaDark:  '#8E3F26',
  terracottaLight: '#C97458',
  line:            '#E7DFD1',
  cream:           '#EDE7DD',
} as const;

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

  // ── Visual fills ────────────────────────────────────────────────────────────
  const bgFill = isDisponible
    ? C.terracotta
    : isProximo
    ? C.sand
    : C.cream;

  const borderColor = isHighlighted
    ? C.ink
    : isDisponible
    ? C.terracottaDark
    : isProximo
    ? C.slate
    : C.line;

  const borderWidth = isHighlighted ? 2.5 : isDisponible ? 2 : 1;
  const textColor   = isDisponible ? C.bone : C.ink;
  const opacity     = isProximo ? 0.6 : 1;

  // ── Label text ──────────────────────────────────────────────────────────────
  const unitId    = unidad.loteIds.join('-');
  // Always prefer the nombre; fall back to unit ID (e.g. "L6-7").
  // For disponibles, nombre is typically the lot ID anyway.
  const mainLabel = unidad.nombre || unitId;
  const maxChars  = Math.max(4, Math.floor(ancho / 7));
  const truncated = mainLabel.length > maxChars
    ? mainLabel.slice(0, maxChars - 1) + '…'
    : mainLabel;

  const cx = x + ancho / 2;
  const midY = alto / 2;
  const fontSize = Math.min(Math.max(ancho * 0.14, 9), 18);

  const handleEnter  = () => onHoverChange?.(unidad.id);
  const handleLeave  = () => onHoverChange?.(null);
  const handleClick  = () => onClick?.(unidad.id);

  return (
    <motion.g
      data-testid={`bloque-${unidad.id}`}
      role="button"
      tabIndex={0}
      aria-label={`${unidad.nombre || unitId} · ${unidad.estado}`}
      style={{ cursor: 'pointer', opacity }}
      animate={{ y: yOffset }}
      whileHover={{ y: yOffset - 3 }}
      transition={{ type: 'spring', stiffness: 320, damping: 28 }}
      onMouseEnter={handleEnter}
      onMouseLeave={handleLeave}
      onFocus={handleEnter}
      onBlur={handleLeave}
      onClick={handleClick}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          handleClick();
        }
      }}
    >
      {/* Pulse glow for disponibles */}
      {isDisponible && (
        <motion.rect
          x={x - 3} y={-3}
          width={ancho + 6} height={alto + 6}
          rx={5} fill="none"
          stroke={C.terracottaLight}
          strokeWidth={3}
          animate={{ opacity: [0, 0.55, 0] }}
          transition={{ duration: 2.0, repeat: Infinity, ease: 'easeInOut', repeatDelay: 0.5 }}
        />
      )}

      {/* Main block */}
      <rect
        x={x} y={0}
        width={ancho} height={alto}
        rx={3}
        fill={bgFill}
        stroke={borderColor}
        strokeWidth={borderWidth}
      />

      {/* Logo (if available) */}
      {unidad.logo?.url ? (
        <image
          href={unidad.logo.url}
          x={cx - Math.min(ancho * 0.35, 28)}
          y={midY - 18}
          width={Math.min(ancho * 0.7, 56)}
          height={36}
          preserveAspectRatio="xMidYMid meet"
        />
      ) : (
        <text
          x={cx} y={midY + 5}
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

      {/* Disponible sub-label: "LIBRE" */}
      {isDisponible && (
        <text
          x={cx} y={alto - 10}
          textAnchor="middle"
          fontFamily="var(--font-body), sans-serif"
          fontSize={7.5}
          fill={C.bone}
          opacity={0.8}
          letterSpacing={1.5}
          fontWeight={600}
          aria-hidden
        >
          DISPONIBLE
        </text>
      )}

      {/* Proximamente sub-label */}
      {isProximo && (
        <text
          x={cx} y={alto - 10}
          textAnchor="middle"
          fontFamily="var(--font-body), sans-serif"
          fontSize={7}
          fill={C.slate}
          opacity={0.75}
          letterSpacing={1}
          fontWeight={600}
          aria-hidden
        >
          PRÓXIMAMENTE
        </text>
      )}

      {/* Selected outline */}
      {isHighlighted && (
        <rect
          x={x + 1} y={1}
          width={ancho - 2} height={alto - 2}
          rx={2} fill="none"
          stroke={C.ink}
          strokeWidth={1.5}
          opacity={0.4}
        />
      )}
    </motion.g>
  );
}
