import type { Local, Giro, Piso } from '@/types/domain';
import { slugify } from '@/lib/slugify';
import { estadoAhora } from '@/lib/horarios';

export type EstadoFiltro = 'Todos' | 'AbiertosAhora' | 'Disponibles';
export type PisoFiltro = 'Todos' | Piso;
export type OrdenFiltro = 'Alfabetico' | 'PisoLocal' | 'Novedades';

export interface FiltroParams {
  query: string;
  giros: Giro[];          // empty = all giros
  piso: PisoFiltro;
  estado: EstadoFiltro;
  orden: OrdenFiltro;
  now: Date;
}

/**
 * Filter + sort the list of locales according to user-selected criteria.
 * Pure function: easy to unit-test and reuse outside React.
 */
export function filtrarYOrdenar(locales: Local[], p: FiltroParams): Local[] {
  const q = p.query.trim() ? slugify(p.query) : '';

  let out = locales.filter((l) => {
    // Skip data-empty rows (placeholders) — these shouldn't appear in the public directory
    if (!l.nombre || !l.slug) return false;

    if (p.giros.length > 0 && !p.giros.includes(l.giro)) return false;
    if (p.piso !== 'Todos' && l.piso !== p.piso) return false;

    if (p.estado === 'Disponibles' && l.estado !== 'Disponible') return false;
    if (p.estado === 'AbiertosAhora') {
      const r = estadoAhora(l.horarios, p.now);
      if (r.estado === 'cerrado') return false;
    }

    if (q) {
      const hay = slugify(`${l.nombre} ${l.giro} ${l.descripcion}`);
      if (!hay.includes(q)) return false;
    }
    return true;
  });

  if (p.orden === 'Alfabetico') {
    out = [...out].sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'));
  } else if (p.orden === 'PisoLocal') {
    out = [...out].sort((a, b) => {
      if (a.piso !== b.piso) return a.piso.localeCompare(b.piso);
      return a.numeroLocal.localeCompare(b.numeroLocal);
    });
  } else if (p.orden === 'Novedades') {
    out = [...out].sort((a, b) => b.id.localeCompare(a.id));
  }

  return out;
}
