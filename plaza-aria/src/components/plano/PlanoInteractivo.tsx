'use client';

import { useMemo, useRef, useState } from 'react';
import { motion, AnimatePresence, useReducedMotion } from 'framer-motion';

import { Edificio } from './svg/Edificio';
import { Decoraciones } from './svg/Decoraciones';
import { Bloque } from './Bloque';
import { PlanoTooltip } from './PlanoTooltip';
import { PanelLateral } from './PanelLateral';
import {
  computeLayout,
  PISO1_UNITS_Y,
  PISO2_UNITS_Y,
} from '@/lib/plano/geometry';
import type { UnidadComercialAgrupada } from '@/lib/plano/agrupar';

// ── Animation timing ──────────────────────────────────────────────────────────
const TIMING = {
  decoracionesDuration:    0.5,
  edificioDelay:           0.3,
  edificioDuration:        0.7,
  bloquesStartDelay:       1.1,
  bloquesStaggerPerBloque: 0.04,
  bloquesDuration:         0.35,
  piso2ExtraDelay:         0.6,
  corePulseDelay:          2.5,
};

// Building x offset — bloques use relative coords (0→1200) and are shifted here
const BUILDING_X_OFFSET = 100;

type Props = {
  unidades: UnidadComercialAgrupada[];
  onUnidadSelect?: (unidadId: string) => void;
  onUnidadHover?: (unidadId: string | null) => void;
  selectedUnidadId?: string | null;
};

export function PlanoInteractivo({
  unidades,
  onUnidadSelect,
  onUnidadHover,
  selectedUnidadId = null,
}: Props) {
  const prefersReducedMotion = useReducedMotion();
  const [piso, setPiso]                 = useState<'1' | '2'>('1');
  const [hoveredUnidad, setHoveredUnidad] = useState<UnidadComercialAgrupada | null>(null);
  const [mousePos, setMousePos]         = useState<{ x: number; y: number } | null>(null);
  const [selectedUnidad, setSelectedUnidad] = useState<UnidadComercialAgrupada | null>(null);
  const [hasInteracted, setHasInteracted]   = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);

  const byId = useMemo(() => new Map(unidades.map((u) => [u.id, u])), [unidades]);

  const handleHover = (id: string | null) => {
    setHoveredUnidad(id ? (byId.get(id) ?? null) : null);
    onUnidadHover?.(id);
  };

  const handleSelect = (id: string) => {
    setHasInteracted(true);
    const u = byId.get(id);
    setSelectedUnidad(u ?? null);
    onUnidadSelect?.(id);
  };

  const handleMouseMove = (e: React.MouseEvent) => {
    const rect = containerRef.current?.getBoundingClientRect();
    if (!rect) return;
    setMousePos({ x: e.clientX - rect.left, y: e.clientY - rect.top });
  };

  const unidadesPiso1 = useMemo(() => unidades.filter((u) => u.piso === '1'), [unidades]);
  const unidadesPiso2 = useMemo(() => unidades.filter((u) => u.piso === '2'), [unidades]);

  const layoutPiso1 = useMemo(() => computeLayout(unidadesPiso1, '1'), [unidadesPiso1]);
  const layoutPiso2 = useMemo(() => computeLayout(unidadesPiso2, '2'), [unidadesPiso2]);

  const visibleLayout = piso === '1' ? layoutPiso1 : layoutPiso2;
  const yOffset       = piso === '1' ? PISO1_UNITS_Y : PISO2_UNITS_Y;

  // Derive the core's absolute SVG x from whichever piso is active so Edificio
  // can draw its column at the exact same position as the interactive layout.
  const coreEntry = (piso === '1' ? layoutPiso1 : layoutPiso2).find((b) => b.type === 'core');
  const coreSvgX  = coreEntry ? coreEntry.x + BUILDING_X_OFFSET : 665;

  return (
    <div ref={containerRef} className="relative w-full" onMouseMove={handleMouseMove}>
      {/* ── Floor toggle ──────────────────────────────────────────────────── */}
      <div
        className="absolute top-3 left-3 z-10 inline-flex rounded-pill border border-aria-line bg-white shadow-card overflow-hidden"
        role="tablist"
        aria-label="Cambiar piso"
      >
        {(['1', '2'] as const).map((p) => (
          <button
            key={p}
            type="button"
            role="tab"
            aria-selected={piso === p}
            onClick={() => {
              setPiso(p);
              setHasInteracted(true);
            }}
            className={
              'px-4 py-2 text-xs font-medium transition ' +
              (piso === p
                ? 'bg-aria-ink text-aria-bone'
                : 'bg-transparent text-aria-ink hover:bg-aria-ink/5')
            }
          >
            Piso {p}
          </button>
        ))}
      </div>

      {/* ── Mobile scroll hint ────────────────────────────────────────────── */}
      {!hasInteracted && (
        <motion.p
          className="absolute top-16 left-3 z-10 md:hidden text-xs text-aria-slate flex items-center gap-1 pointer-events-none"
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 2.5, duration: 0.5 }}
        >
          <span aria-hidden>←</span>
          arrastra para explorar
          <span aria-hidden>→</span>
        </motion.p>
      )}

      {/* ── SVG canvas ────────────────────────────────────────────────────── */}
      <div className="w-full overflow-x-auto -mx-3 px-3 md:mx-0 md:px-0">
        <svg
          viewBox="0 0 1400 560"
          xmlns="http://www.w3.org/2000/svg"
          className="h-auto max-w-page"
          style={{ width: '100%', minWidth: 680 }}
          role="img"
          aria-label={`Plano interactivo de Plaza Aria, Piso ${piso}`}
        >
          {/* Background decorations */}
          <motion.g
            initial={prefersReducedMotion ? false : { opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ duration: TIMING.decoracionesDuration, ease: 'easeOut' }}
          >
            <Decoraciones />
          </motion.g>

          {/* Building shell — receives coreSvgX so it aligns with the layout */}
          <motion.g
            initial={prefersReducedMotion ? false : { opacity: 0, scale: 0.97 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{
              duration: TIMING.edificioDuration,
              delay: TIMING.edificioDelay,
              ease: [0.22, 1, 0.36, 1],
            }}
            style={{ transformOrigin: '50% 60%' }}
          >
            <Edificio coreSvgX={coreSvgX} />
          </motion.g>

          {/* Core pulse glow — a gold outline around the structural column */}
          {!prefersReducedMotion && (
            <motion.rect
              x={coreSvgX - 4}
              y={PISO2_UNITS_Y - 4}
              width={70 + 8}
              height={362}
              rx={5}
              fill="none"
              stroke="#C99857"
              strokeWidth={3}
              initial={{ opacity: 0 }}
              animate={{ opacity: [0, 0.55, 0] }}
              transition={{
                delay: TIMING.corePulseDelay,
                duration: 1.4,
                ease: 'easeInOut',
              }}
              pointerEvents="none"
            />
          )}

          {/* Interactive bloques — shifted by the same BUILDING_X_OFFSET as before */}
          <g transform={`translate(${BUILDING_X_OFFSET}, 0)`}>
            <AnimatePresence>
              <motion.g
                key={piso}
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                exit={{ opacity: 0 }}
                transition={{ duration: 0.22, ease: 'easeOut' }}
              >
                {visibleLayout
                  .filter((b) => b.type === 'bloque')
                  .map((b, idx) => {
                    if (b.type !== 'bloque') return null;
                    const pisoDelay = piso === '2' ? TIMING.piso2ExtraDelay : 0;
                    const delay = prefersReducedMotion
                      ? 0
                      : TIMING.bloquesStartDelay + pisoDelay + idx * TIMING.bloquesStaggerPerBloque;
                    return (
                      <motion.g
                        key={b.unidad.id}
                        initial={prefersReducedMotion ? false : { opacity: 0, scale: 0.94 }}
                        animate={{ opacity: 1, scale: 1 }}
                        transition={{ duration: TIMING.bloquesDuration, delay, ease: 'easeOut' }}
                      >
                        <Bloque
                          layout={b}
                          yOffset={yOffset}
                          isHighlighted={
                            selectedUnidadId === b.unidad.id ||
                            selectedUnidad?.id === b.unidad.id
                          }
                          onClick={handleSelect}
                          onHoverChange={handleHover}
                        />
                      </motion.g>
                    );
                  })}
              </motion.g>
            </AnimatePresence>
          </g>
        </svg>
      </div>

      <PlanoTooltip unidad={hoveredUnidad} position={mousePos} />
      <PanelLateral unidad={selectedUnidad} onClose={() => setSelectedUnidad(null)} />
    </div>
  );
}
