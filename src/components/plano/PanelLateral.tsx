'use client';

import { useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { EspecsDisponible } from './panel/EspecsDisponible';
import { EspecsOcupado } from './panel/EspecsOcupado';
import type { UnidadComercialAgrupada } from '@/lib/plano/agrupar';

type Props = {
  unidad: UnidadComercialAgrupada | null;
  onClose: () => void;
};

export function PanelLateral({ unidad, onClose }: Props) {
  // Escape key + body scroll lock while open
  useEffect(() => {
    if (!unidad) return;
    const prevOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', onKey);
    return () => {
      document.body.style.overflow = prevOverflow;
      document.removeEventListener('keydown', onKey);
    };
  }, [unidad, onClose]);

  return (
    <AnimatePresence>
      {unidad && (
        <>
          {/* Backdrop */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.2 }}
            onClick={onClose}
            className="fixed inset-0 z-40 bg-aria-ink/40 backdrop-blur-sm"
            aria-hidden
          />

          {/* Panel — desktop: slides from right; mobile: full-width */}
          <motion.aside
            role="dialog"
            aria-modal="true"
            aria-label={`Detalles de ${unidad.nombre || 'Local ' + unidad.loteIds.join('-')}`}
            initial={{ x: '100%', y: 0 }}
            animate={{ x: 0, y: 0 }}
            exit={{ x: '100%' }}
            transition={{ type: 'spring', damping: 28, stiffness: 240 }}
            className={
              'fixed top-0 right-0 z-50 h-full w-full md:w-[460px] lg:w-[520px] ' +
              'bg-aria-bone shadow-cardHover overflow-y-auto'
            }
          >
            <div className="sticky top-0 z-10 flex items-center justify-between border-b border-aria-line/60 bg-aria-bone/95 backdrop-blur px-6 py-4">
              <div>
                <p className="text-[10px] uppercase tracking-[0.18em] text-aria-slate font-medium">
                  Piso {unidad.piso} · Local {unidad.loteIds.join('-')}
                </p>
                <h2 className="mt-0.5 font-display text-2xl text-aria-ink leading-tight">
                  {unidad.nombre || `Local ${unidad.loteIds.join('-')}`}
                </h2>
              </div>
              <button
                type="button"
                onClick={onClose}
                aria-label="Cerrar"
                className="inline-flex h-9 w-9 items-center justify-center rounded-pill hover:bg-aria-ink/5 text-aria-ink"
              >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
                  <path d="M6 6l12 12M18 6l-12 12" />
                </svg>
              </button>
            </div>

            <div className="px-6 py-6">
              {unidad.estado === 'Disponible' && <EspecsDisponible unidad={unidad} />}
              {unidad.estado === 'Ocupado' && <EspecsOcupado unidad={unidad} />}
              {unidad.estado === 'Proximamente' && (
                <div className="flex flex-col items-center py-12 text-center">
                  <p className="font-display text-2xl text-aria-ink">Próximamente</p>
                  <p className="mt-3 max-w-prose-aria text-sm text-aria-slate">
                    Este local está en proceso. Si te interesa apartarlo,
                    escríbenos por WhatsApp y te avisamos en cuanto esté listo.
                  </p>
                  <a
                    href="https://wa.me/5299983214614"
                    target="_blank"
                    rel="noreferrer noopener"
                    className="btn-primary mt-6"
                  >
                    Pregúntanos
                  </a>
                </div>
              )}
            </div>
          </motion.aside>
        </>
      )}
    </AnimatePresence>
  );
}
