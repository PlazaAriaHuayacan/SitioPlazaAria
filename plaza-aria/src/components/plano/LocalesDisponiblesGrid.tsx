'use client';

import { useState, useMemo } from 'react';
import type { UnidadComercialAgrupada } from '@/lib/plano/agrupar';

// ─── Formatters ───────────────────────────────────────────────────────────────
const rentaFormatter = new Intl.NumberFormat('es-MX', {
  style: 'currency',
  currency: 'MXN',
  maximumFractionDigits: 0,
});

// WhatsApp config
const WA_NUMBER = '5299983214614';
const buildWhatsAppLink = (unidad: UnidadComercialAgrupada): string => {
  const locales = unidad.loteIds.join(', ');
  const txt = encodeURIComponent(
    `Hola, me interesa el local ${locales} (Piso ${unidad.piso}, ${Math.round(unidad.m2Total)} m²) en Plaza Aria.`,
  );
  return `https://wa.me/${WA_NUMBER}?text=${txt}`;
};

// ─── Types ────────────────────────────────────────────────────────────────────
type FilterPiso = 'all' | '1' | '2';
type SortBy = 'renta-asc' | 'renta-desc' | 'm2-desc' | 'piso';

type Props = {
  unidades: UnidadComercialAgrupada[];
};

// ─── Main component ───────────────────────────────────────────────────────────
export function LocalesDisponiblesGrid({ unidades }: Props) {
  const [pisoFilter, setPisoFilter] = useState<FilterPiso>('all');
  const [sortBy, setSortBy] = useState<SortBy>('piso');

  // Filter only available units, then apply piso filter
  const disponibles = useMemo(() => {
    const filtered = unidades.filter((u) => u.estado === 'Disponible');
    return pisoFilter === 'all'
      ? filtered
      : filtered.filter((u) => u.piso === pisoFilter);
  }, [unidades, pisoFilter]);

  // Apply sorting
  const sorted = useMemo(() => {
    const arr = [...disponibles];
    switch (sortBy) {
      case 'renta-asc':
        return arr.sort(
          (a, b) =>
            (a.registroPrincipal.leasing?.renta ?? Infinity) -
            (b.registroPrincipal.leasing?.renta ?? Infinity),
        );
      case 'renta-desc':
        return arr.sort(
          (a, b) =>
            (b.registroPrincipal.leasing?.renta ?? -Infinity) -
            (a.registroPrincipal.leasing?.renta ?? -Infinity),
        );
      case 'm2-desc':
        return arr.sort((a, b) => b.m2Total - a.m2Total);
      case 'piso':
      default:
        return arr.sort((a, b) => {
          if (a.piso !== b.piso) return a.piso.localeCompare(b.piso);
          return a.ordenPlano - b.ordenPlano;
        });
    }
  }, [disponibles, sortBy]);

  const piso1Count = unidades.filter(
    (u) => u.estado === 'Disponible' && u.piso === '1',
  ).length;
  const piso2Count = unidades.filter(
    (u) => u.estado === 'Disponible' && u.piso === '2',
  ).length;

  if (disponibles.length === 0 && pisoFilter !== 'all') {
    // No disponibles in this piso — show message + offer to switch
    return (
      <div className="text-center py-12">
        <p className="text-aria-slate">
          No hay locales disponibles en Piso {pisoFilter} en este momento.
        </p>
        <button
          type="button"
          onClick={() => setPisoFilter('all')}
          className="mt-4 text-aria-terracotta underline text-sm"
        >
          Ver todos los locales disponibles
        </button>
      </div>
    );
  }

  if (disponibles.length === 0) {
    return (
      <div className="text-center py-12">
        <p className="text-aria-slate">
          Por ahora no hay locales disponibles. Escríbenos por WhatsApp y te
          avisamos cuando se libere alguno.
        </p>
      </div>
    );
  }

  return (
    <div>
      {/* ── Filter + sort bar ───────────────────────────────────────────── */}
      <div className="flex flex-wrap items-center justify-between gap-4 mb-6">
        {/* Piso filter */}
        <div
          className="inline-flex rounded-pill border border-aria-line bg-white overflow-hidden"
          role="tablist"
          aria-label="Filtrar por piso"
        >
          <FilterButton
            active={pisoFilter === 'all'}
            onClick={() => setPisoFilter('all')}
            label="Todos"
            count={piso1Count + piso2Count}
          />
          <FilterButton
            active={pisoFilter === '1'}
            onClick={() => setPisoFilter('1')}
            label="Piso 1"
            count={piso1Count}
          />
          <FilterButton
            active={pisoFilter === '2'}
            onClick={() => setPisoFilter('2')}
            label="Piso 2"
            count={piso2Count}
          />
        </div>

        {/* Sort selector */}
        <label className="inline-flex items-center gap-2 text-sm text-aria-slate">
          <span>Ordenar:</span>
          <select
            value={sortBy}
            onChange={(e) => setSortBy(e.target.value as SortBy)}
            className="rounded-md border border-aria-line bg-white px-3 py-1.5 text-aria-ink focus:outline-none focus:ring-2 focus:ring-aria-terracotta/30"
          >
            <option value="piso">Piso (predeterminado)</option>
            <option value="renta-asc">Renta: menor a mayor</option>
            <option value="renta-desc">Renta: mayor a menor</option>
            <option value="m2-desc">m²: mayor a menor</option>
          </select>
        </label>
      </div>

      {/* ── Card grid ───────────────────────────────────────────────────── */}
      <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        {sorted.map((u) => (
          <LocalCard key={u.id} unidad={u} />
        ))}
      </div>
    </div>
  );
}

// ─── Sub-components ───────────────────────────────────────────────────────────

function FilterButton({
  active,
  onClick,
  label,
  count,
}: {
  active: boolean;
  onClick: () => void;
  label: string;
  count: number;
}) {
  return (
    <button
      type="button"
      role="tab"
      aria-selected={active}
      onClick={onClick}
      className={
        'px-4 py-2 text-sm font-medium transition ' +
        (active
          ? 'bg-aria-ink text-aria-bone'
          : 'bg-transparent text-aria-ink hover:bg-aria-ink/5')
      }
    >
      {label}
      <span
        className={
          'ml-2 inline-flex items-center justify-center min-w-[1.4rem] h-5 rounded-full text-xs ' +
          (active ? 'bg-aria-bone/20 text-aria-bone' : 'bg-aria-sand text-aria-slate')
        }
      >
        {count}
      </span>
    </button>
  );
}

function LocalCard({ unidad }: { unidad: UnidadComercialAgrupada }) {
  const localesLabel = unidad.loteIds.join(' + ');
  const renta = unidad.registroPrincipal.leasing?.renta;
  const mantenimiento = unidad.registroPrincipal.leasing?.mantenimiento;
  const frente = unidad.registroPrincipal.leasing?.frente;
  const instalaciones = unidad.registroPrincipal.leasing?.instalaciones ?? [];
  const photo = unidad.fotos[0];

  return (
    <article className="group flex flex-col bg-white rounded-card border border-aria-line/60 shadow-card overflow-hidden transition hover:shadow-lg hover:border-aria-terracotta/40">
      {/* Photo or placeholder */}
      <div className="relative aspect-[4/3] bg-aria-sand overflow-hidden">
        {photo ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={photo.url}
            alt={photo.alt ?? `Local ${localesLabel}`}
            className="w-full h-full object-cover transition group-hover:scale-[1.02]"
            loading="lazy"
          />
        ) : (
          <div className="w-full h-full flex items-center justify-center">
            <span className="text-aria-slate/40 text-xs uppercase tracking-wider">
              Sin foto
            </span>
          </div>
        )}

        {/* Disponible badge */}
        <span className="absolute top-3 left-3 bg-aria-terracotta text-aria-bone text-xs font-semibold uppercase tracking-wide px-2.5 py-1 rounded-full">
          Disponible
        </span>

        {/* Piso badge */}
        <span className="absolute top-3 right-3 bg-white/95 text-aria-ink text-xs font-medium px-2.5 py-1 rounded-full">
          Piso {unidad.piso}
        </span>
      </div>

      {/* Body */}
      <div className="flex flex-col flex-1 p-5">
        <p className="font-display text-lg text-aria-ink">
          Local {localesLabel}
          {unidad.esCombinada && (
            <span className="ml-2 text-xs text-aria-slate font-sans">
              (locales combinados)
            </span>
          )}
        </p>

        {/* Stats row */}
        <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-aria-slate">
          <span>
            <strong className="text-aria-ink">{Math.round(unidad.m2Total)}</strong> m²
          </span>
          {frente !== undefined && (
            <span>
              Frente <strong className="text-aria-ink">{frente} m</strong>
            </span>
          )}
        </div>

        {/* Instalaciones */}
        {instalaciones.length > 0 && (
          <div className="mt-3 flex flex-wrap gap-1.5">
            {instalaciones.map((i) => (
              <span
                key={i}
                className="inline-block text-[10px] uppercase tracking-wide bg-aria-bone text-aria-slate px-2 py-0.5 rounded-full border border-aria-line"
              >
                {i}
              </span>
            ))}
          </div>
        )}

        {/* Price */}
        <div className="mt-4 pt-4 border-t border-aria-line">
          {renta ? (
            <>
              <p className="text-2xl font-display text-aria-terracotta leading-none">
                {rentaFormatter.format(renta)}
                <span className="text-sm text-aria-slate font-sans ml-1">/mes</span>
              </p>
              {mantenimiento !== undefined && mantenimiento > 0 && (
                <p className="text-xs text-aria-slate mt-1">
                  + {rentaFormatter.format(mantenimiento)} mantenimiento
                </p>
              )}
            </>
          ) : (
            <p className="text-base font-display text-aria-ink/70 italic">
              Pregunta por renta
            </p>
          )}
        </div>

        {/* CTA */}
        <a
          href={buildWhatsAppLink(unidad)}
          target="_blank"
          rel="noopener noreferrer"
          className="mt-4 inline-flex items-center justify-center gap-2 rounded-pill bg-aria-terracotta text-aria-bone font-medium px-5 py-2.5 text-sm transition hover:bg-aria-terracottaDark active:translate-y-px"
        >
          Consultar por WhatsApp
        </a>
      </div>
    </article>
  );
}
