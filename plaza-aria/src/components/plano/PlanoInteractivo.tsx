'use client';

import { useMemo, useRef, useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Edificio } from './svg/Edificio';
import { Decoraciones } from './svg/Decoraciones';
import { Bloque } from './Bloque';
import { PlanoTooltip } from './PlanoTooltip';
import { computeLayout } from '@/lib/plano/geometry';
import type { UnidadComercialAgrupada } from '@/lib/plano/agrupar';

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
  const [piso, setPiso] = useState<'1' | '2'>('1');
  const [hoveredUnidad, setHoveredUnidad] = useState<UnidadComercialAgrupada | null>(null);
  const [mousePos, setMousePos] = useState<{ x: number; y: number } | null>(null);
  const containerRef = useRef<HTMLDivElement>(null);

  const byId = useMemo(() => new Map(unidades.map((u) => [u.id, u])), [unidades]);

  const handleHover = (id: string | null) => {
    setHoveredUnidad(id ? (byId.get(id) ?? null) : null);
    onUnidadHover?.(id);
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
            onClick={() => setPiso(p)}
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

      <div className="w-full overflow-x-auto">
        <svg
          viewBox="0 0 1400 800"
          xmlns="http://www.w3.org/2000/svg"
          className="w-full h-auto max-w-page"
          role="img"
          aria-label={`Plano interactivo de Plaza Aria, Piso ${piso}`}
        >
          <Decoraciones />
          <Edificio />

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
                  .map((b) => {
                    if (b.type !== 'bloque') return null;
                    return (
                      <Bloque
                        key={b.unidad.id}
                        layout={b}
                        yOffset={yOffset}
                        isHighlighted={selectedUnidadId === b.unidad.id}
                        onClick={onUnidadSelect}
                        onHoverChange={handleHover}
                      />
                    );
                  })}
              </motion.g>
            </AnimatePresence>
          </g>
        </svg>
      </div>

      <PlanoTooltip unidad={hoveredUnidad} position={mousePos} />
    </div>
  );
}
