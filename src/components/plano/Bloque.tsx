'use client';

import { motion } from 'framer-motion';
import type { BloqueLayout } from '@/lib/plano/geometry';

const ARIA_COLORS = {
  bone: '#FAF6F0',
  sand: '#F1E9DC',
  ink: '#1F1A14',
  slate: '#5A544A',
  terracotta: '#B85A3C',
  terracottaDark: '#8E3F26',
  line: '#E7DFD1',
} as const;

type BloqueProps = {
  layout: Extract<BloqueLayout, { type: 'bloque' }>;
  /** Vertical offset (the parent decides — piso 1 vs piso 2 positioning). */
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
  const y = yOffset;

  // Visual variant per estado
  const isDisponible = unidad.estado === 'Disponible';
  const isProximo = unidad.estado === 'Proximamente';

  const fill = ARIA_COLORS.bone;
  const stroke = isDisponible
    ? ARIA_COLORS.terracotta
    : isProximo
    ? ARIA_COLORS.slate
    : ARIA_COLORS.line;
  const strokeWidth = isDisponible ? 2.5 : 1.5;
  const opacity = isProximo ? 0.55 : 1;

  const handleEnter = () => onHoverChange?.(unidad.id);
  const handleLeave = () => onHoverChange?.(null);
  const handleClick = () => onClick?.(unidad.id);

  // Center for placement of label/logo
  const cx = x + ancho / 2;
  const cy = alto / 2;

  // Truncate the label for the visible block
  const label = unidad.nombre || `Local ${unidad.loteIds.join('-')}`;
  const labelMaxChars = Math.max(6, Math.floor(ancho / 7));
  const visibleLabel =
    label.length > labelMaxChars ? label.slice(0, labelMaxChars - 1) + '…' : label;

  return (
    <motion.g
      data-testid={`bloque-${unidad.id}`}
      role="button"
      tabIndex={0}
      aria-label={`${unidad.nombre || 'Local ' + unidad.loteIds.join('-')} · ${unidad.estado}`}
      style={{ cursor: 'pointer', opacity }}
      whileHover={{ y: y - 2 }}
      animate={{ y }}
      transition={{ type: 'spring', stiffness: 300, damping: 28 }}
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
      {/* Slow pulse glow for Disponibles */}
      {isDisponible && (
        <motion.rect
          x={x - 2}
          y={0}
          width={ancho + 4}
          height={alto + 4}
          fill="none"
          stroke={ARIA_COLORS.terracotta}
          strokeWidth={3}
          rx={4}
          animate={{ opacity: [0.0, 0.5, 0.0] }}
          transition={{ duration: 1.6, repeat: Infinity, ease: 'easeInOut' }}
        />
      )}

      {/* Main block */}
      <rect
        x={x}
        y={0}
        width={ancho}
        height={alto}
        rx={3}
        fill={fill}
        stroke={isHighlighted ? ARIA_COLORS.ink : stroke}
        strokeWidth={isHighlighted ? 3 : strokeWidth}
      />

      {/* Logo (if available) or stylized initial */}
      {unidad.logo?.url ? (
        <image
          href={unidad.logo.url}
          x={cx - Math.min(ancho * 0.7, 40)}
          y={cy - 24}
          width={Math.min(ancho * 0.7, 80)}
          height={36}
          preserveAspectRatio="xMidYMid meet"
        />
      ) : (
        <text
          x={cx}
          y={cy + 4}
          textAnchor="middle"
          fontFamily="var(--font-display), serif"
          fontSize={Math.min(ancho * 0.18, 22)}
          fill={ARIA_COLORS.ink}
          fontWeight={500}
          aria-hidden
        >
          {visibleLabel}
        </text>
      )}

      {/* Badge for Disponible */}
      {isDisponible && (
        <g>
          <rect
            x={x + ancho - 60}
            y={4}
            width={56}
            height={14}
            rx={7}
            fill={ARIA_COLORS.terracotta}
          />
          <text
            x={x + ancho - 32}
            y={14}
            textAnchor="middle"
            fontFamily="var(--font-body), sans-serif"
            fontSize={8}
            fill={ARIA_COLORS.bone}
            fontWeight={600}
            letterSpacing={1}
          >
            DISPONIBLE
          </text>
        </g>
      )}

      {/* Badge for Proximamente */}
      {isProximo && (
        <g>
          <rect
            x={x + ancho - 70}
            y={4}
            width={66}
            height={14}
            rx={7}
            fill={ARIA_COLORS.slate}
          />
          <text
            x={x + ancho - 37}
            y={14}
            textAnchor="middle"
            fontFamily="var(--font-body), sans-serif"
            fontSize={8}
            fill={ARIA_COLORS.bone}
            fontWeight={600}
            letterSpacing={1}
          >
            PRÓXIMAMENTE
          </text>
        </g>
      )}
    </motion.g>
  );
}
