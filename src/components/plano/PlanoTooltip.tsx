'use client';

import { motion, AnimatePresence } from 'framer-motion';
import type { UnidadComercialAgrupada } from '@/lib/plano/agrupar';

type Props = {
  unidad: UnidadComercialAgrupada | null;
  /** Pixel coords relative to the SVG container (top-left = 0,0). */
  position: { x: number; y: number } | null;
};

const formatMXN = new Intl.NumberFormat('es-MX', {
  style: 'currency',
  currency: 'MXN',
  maximumFractionDigits: 0,
});

export function PlanoTooltip({ unidad, position }: Props) {
  return (
    <AnimatePresence>
      {unidad && position && (
        <motion.div
          initial={{ opacity: 0, y: 4 }}
          animate={{ opacity: 1, y: 0 }}
          exit={{ opacity: 0, y: 4 }}
          transition={{ duration: 0.15, ease: 'easeOut' }}
          style={{
            position: 'absolute',
            left: position.x + 12,
            top: position.y + 12,
            pointerEvents: 'none',
          }}
          className="z-20 hidden md:block"
        >
          <div className="rounded-card border border-aria-line bg-white shadow-card p-3 min-w-[180px] max-w-[240px]">
            {unidad.estado === 'Disponible' ? (
              <>
                <p className="text-[10px] uppercase tracking-[0.18em] text-aria-terracotta font-medium">
                  Disponible
                </p>
                <p className="mt-1 font-display text-lg leading-tight text-aria-ink">
                  {unidad.loteIds.length > 1
                    ? `Local ${unidad.loteIds.join(' + ')}`
                    : `Local ${unidad.loteIds[0]}`}
                </p>
                <p className="mt-1 text-xs text-aria-slate">
                  {unidad.m2Total.toFixed(1)} m² · Piso {unidad.piso}
                </p>
                {unidad.registroPrincipal?.leasing?.renta ? (
                  <p className="mt-2 text-xs font-medium text-aria-ink">
                    Desde {formatMXN.format(unidad.registroPrincipal.leasing.renta)}{' '}
                    <span className="text-aria-slate font-normal">/ mes</span>
                  </p>
                ) : (
                  <p className="mt-2 text-xs text-aria-slate italic">
                    Pregunta por renta
                  </p>
                )}
              </>
            ) : unidad.estado === 'Proximamente' ? (
              <>
                <p className="text-[10px] uppercase tracking-[0.18em] text-aria-slate font-medium">
                  Próximamente
                </p>
                <p className="mt-1 font-display text-lg leading-tight text-aria-ink">
                  {unidad.nombre || `Local ${unidad.loteIds.join('-')}`}
                </p>
              </>
            ) : (
              <>
                <p className="text-[10px] uppercase tracking-[0.18em] text-aria-slate font-medium">
                  {unidad.giro}
                </p>
                <p className="mt-1 font-display text-lg leading-tight text-aria-ink">
                  {unidad.nombre || `Local ${unidad.loteIds.join('-')}`}
                </p>
                <p className="mt-1 text-xs text-aria-slate">
                  Piso {unidad.piso} · Local {unidad.loteIds.join('-')}
                </p>
              </>
            )}
          </div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}
