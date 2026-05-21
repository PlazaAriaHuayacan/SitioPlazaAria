'use client';

import { useMemo, useRef, useState } from 'react';
import { motion, AnimatePresence, useReducedMotion } from 'framer-motion';

import { Edificio } from './svg/Edificio';
import { Decoraciones } from './svg/Decoraciones';
import { Bloque } from './Bloque';
import { PlanoTooltip } from './PlanoTooltip';
import { PanelLateral } from './PanelLateral';
import { computeLayout } from '@/lib/plano/geometry';
import type { UnidadComercialAgrupada } from '@/lib/plano/agrupar';

const TIMING = {
  decoracionesDuration: 0.6,
  edificioDelay: 0.4,
  edificioDuration: 0.8,
  bloquesStartDelay: 1.4,
  bloquesStaggerPerBloque: 0.04,
  bloquesDuration: 0.4,
  piso2ExtraDelay: 0.8,
  corePulseDelay: 3.0,
};

type Props = {
  unidades: UnidadComercialAgrupada[];
  /** Called when the user clicks a unit (parent opens the side panel). */
  onUnidadSelect?: (unidadId: string) => void;
  /** Called as the hovered unit changes. */
  onUnidadHover?: (unidadId: string | null) => void;
  /** Currently selected unit (highlights its bloque). */
  selectedUnidadId?: string | null;
};

const PISO1_Y = 320;
const PISO2_Y = 140;
const BUILDING_X_OFFSET = 100;

export function PlanoInteractivo({
  unidades,
  onUnidadSelect,
  onUnidadHover,
  selectedUnidadId = null,
}: Props) {
  const prefersReducedMotion = useReducedMotion();
  const [piso, setPiso] = useState<'1' | '2'>('1');
  const [hoveredUnidad, setHoveredUnidad] = useState<UnidadComercialAgrupada | null>(null);
  const [mousePos, setMousePos] = useState<{ x: number; y: number } | null>(null);
  const [selectedUnidad, setSelectedUnidad] = useState<UnidadComercialAgrupada | null>(null);
  const [hasInteracted, setHasInteracted] = useState(false);
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

  const unidadesPiso1 = useMemo(
    () => unidades.filter((u) => u.piso === '1'),
    [unidades],
  );
  const unidadesPiso2 = useMemo(
    () => unidades.filter((u) => u.piso === '2'),
    [unidades],
  );

  const layoutPiso1 = useMemo(
    () => computeLayout(unidadesPiso1, '1'),
    [unidadesPiso1],
  );
  const layoutPiso2 = useMemo(
    () => computeLayout(unidadesPiso2, '2'),
    [unidadesPiso2],
  );

  const visibleLayout = piso === '1' ? layoutPiso1 : layoutPiso2;
  const yOffset = piso === '1' ? PISO1_Y : PISO2_Y;

  return (
    <div ref={containerRef} className="relative w-full" onMouseMove={handleMouseMove}>
      {/* Floor toggle */}
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
            onClick={() => { setPiso(p); setHasInteracted(true); }}
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

      {/* Scroll hint — mobile only, fades in after 2.5 s, hides once user interacts */}
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

      <div className="w-full overflow-x-auto -mx-3 px-3 md:mx-0 md:px-0">
        <svg
          viewBox="0 0 1400 800"
          xmlns="http://www.w3.org/2000/svg"
          className="h-auto max-w-page"
          style={{ width: '100%', minWidth: 700 }}
          role="img"
          aria-label={`Plano interactivo de Plaza Aria, Piso ${piso}`}
        >
          <motion.g
            initial={prefersReducedMotion ? false : { opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ duration: TIMING.decoracionesDuration, ease: 'easeOut' }}
          >
            <Decoraciones />
          </motion.g>

          <motion.g
            initial={prefersReducedMotion ? false : { opacity: 0, scale: 0.96 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{
              duration: TIMING.edificioDuration,
              delay: TIMING.edificioDelay,
              ease: [0.22, 1, 0.36, 1],
            }}
            style={{ transformOrigin: '50% 80%' }}
          >
            <Edificio />
          </motion.g>

          {/* Central core pulse — gold glow after building is built */}
          {!prefersReducedMotion && (
            <motion.rect
              x={655}
              y={90}
              width={90}
              height={460}
              rx={6}
              fill="none"
              stroke="#C99857"
              strokeWidth={3}
              initial={{ opacity: 0 }}
              animate={{ opacity: [0, 0.6, 0] }}
              transition={{
                delay: TIMING.corePulseDelay,
                duration: 1.2,
                ease: 'easeInOut',
              }}
              pointerEvents="none"
            />
          )}

          {/* Bloques shifted into the building's interior */}
          <g transform={`translate(${BUILDING_X_OFFSET}, 0)`}>
            <AnimatePresence>
              <motion.g
                key={piso}
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                exit={{ opacity: 0 }}
                transition={{ duration: 0.25, ease: 'easeOut' }}
              >
                {visibleLayout
                  .filter((b) => b.type === 'bloque')
                  .map((b, idx) => {
                    if (b.type !== 'bloque') return null;
                    const pisoOffset = piso === '2' ? TIMING.piso2ExtraDelay : 0;
                    const delay = prefersReducedMotion
                      ? 0
                      : TIMING.bloquesStartDelay + pisoOffset + idx * TIMING.bloquesStaggerPerBloque;
                    return (
                      <motion.g
                        key={b.unidad.id}
                        initial={prefersReducedMotion ? false : { opacity: 0, scale: 0.92 }}
                        animate={{ opacity: 1, scale: 1 }}
                        transition={{ duration: TIMING.bloquesDuration, delay, ease: 'easeOut' }}
                      >
                        <Bloque
                          layout={b}
                          yOffset={yOffset}
                          isHighlighted={selectedUnidadId === b.unidad.id || selectedUnidad?.id === b.unidad.id}
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
