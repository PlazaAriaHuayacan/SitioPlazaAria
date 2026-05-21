'use client';

import { useMemo, useState } from 'react';
import { LocalCard } from './LocalCard';
import { DirectorioFilters } from './DirectorioFilters';
import {
  filtrarYOrdenar,
  type EstadoFiltro,
  type PisoFiltro,
  type OrdenFiltro,
} from '@/lib/directorio/filter';
import type { Giro, Local } from '@/types/domain';

type Props = {
  all: Local[];
  /** ISO string of the moment the server snapshotted "now". Used so SSR/CSR agree. */
  nowIso: string;
};

export function DirectorioWithFilters({ all, nowIso }: Props) {
  const now = useMemo(() => new Date(nowIso), [nowIso]);

  const [query, setQuery] = useState('');
  const [giros, setGiros] = useState<Giro[]>([]);
  const [piso, setPiso] = useState<PisoFiltro>('Todos');
  const [estado, setEstado] = useState<EstadoFiltro>('Todos');
  const [orden, setOrden] = useState<OrdenFiltro>('PisoLocal');

  const availableGiros = useMemo<Giro[]>(() => {
    const set = new Set<Giro>();
    for (const l of all) {
      if (l.nombre && l.slug) set.add(l.giro);
    }
    return Array.from(set).sort((a, b) => a.localeCompare(b, 'es'));
  }, [all]);

  const filtered = useMemo(() => {
    return filtrarYOrdenar(all, { query, giros, piso, estado, orden, now });
  }, [all, query, giros, piso, estado, orden, now]);

  const clearAll = () => {
    setQuery('');
    setGiros([]);
    setPiso('Todos');
    setEstado('Todos');
    setOrden('PisoLocal');
  };

  const toggleGiro = (g: Giro) => {
    setGiros((current) =>
      current.includes(g) ? current.filter((x) => x !== g) : [...current, g],
    );
  };

  return (
    <div className="space-y-8">
      <DirectorioFilters
        query={query}
        giros={giros}
        piso={piso}
        estado={estado}
        orden={orden}
        availableGiros={availableGiros}
        onQuery={setQuery}
        onToggleGiro={toggleGiro}
        onClearGiros={() => setGiros([])}
        onPiso={setPiso}
        onEstado={setEstado}
        onOrden={setOrden}
        resultCount={filtered.length}
      />

      {filtered.length === 0 ? (
        <div className="card flex flex-col items-center gap-3 p-12 text-center">
          <p className="font-display text-2xl text-aria-ink">Sin resultados</p>
          <p className="text-sm text-aria-slate">Ajusta o limpia los filtros para ver todos los locales.</p>
          <button type="button" onClick={clearAll} className="btn-ghost">
            Limpiar filtros
          </button>
        </div>
      ) : (
        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {filtered.map((l) => (
            <LocalCard key={l.id} local={l} now={now} />
          ))}
        </div>
      )}
    </div>
  );
}
