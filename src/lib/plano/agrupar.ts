import type { Local, Foto, Giro, EstadoLocal, Piso } from '@/types/domain';

/**
 * A commercial unit on the plano. May aggregate multiple architectural lots
 * (e.g., L6 + L7) that are sold/rented as one.
 */
export interface UnidadComercialAgrupada {
  /** Stable identifier for routing/keying. Same as `unidadComercial` value in Airtable. */
  id: string;
  /** Architectural lot IDs grouped here, in ordenPlano ascending. */
  loteIds: string[];
  /** Tenant nombre (or first record's nombre if vacant). */
  nombre: string;
  slug: string;
  giro: Giro;
  estado: EstadoLocal;
  piso: Piso;
  /** Lowest ordenPlano among the underlying records. */
  ordenPlano: number;
  /** Sum of m² across all underlying lots (with a sensible fallback when leasing data is absent). */
  m2Total: number;
  /** Merged fotos from all underlying records. */
  fotos: Foto[];
  logo?: Foto;
  descripcion: string;
  /** True if this unidad aggregates 2+ lots. */
  esCombinada: boolean;
  /** The lot with lowest ordenPlano — used for accessing single-record fields (whatsapp, instagram, etc.). */
  registroPrincipal: Local;
  /** All underlying records, sorted by ordenPlano ascending. */
  todosRegistros: Local[];
}

/** Per-lot m² fallback when leasing is undefined. Matches the typical standard local size. */
const M2_FALLBACK_PER_LOT = 60.35;

/**
 * Collapse a list of Local records into commercial units by their `unidadComercial` field.
 * Records with an empty `unidadComercial` are skipped.
 */
export function agruparPorUnidad(locales: Local[]): UnidadComercialAgrupada[] {
  const byKey = new Map<string, Local[]>();
  for (const l of locales) {
    if (!l.unidadComercial) continue;
    const arr = byKey.get(l.unidadComercial) ?? [];
    arr.push(l);
    byKey.set(l.unidadComercial, arr);
  }

  const result: UnidadComercialAgrupada[] = [];
  for (const [key, group] of Array.from(byKey)) {
    const sorted = [...group].sort(
      (a, b) => (a.ordenPlano ?? Number.MAX_SAFE_INTEGER) - (b.ordenPlano ?? Number.MAX_SAFE_INTEGER),
    );
    const principal = sorted[0];

    // m² total: prefer real leasing data when available; otherwise fall back per lot.
    const m2WithData = sorted.reduce((sum, l) => sum + (l.leasing?.m2 ?? 0), 0);
    const m2Total = m2WithData > 0 ? m2WithData : sorted.length * M2_FALLBACK_PER_LOT;

    result.push({
      id: key,
      loteIds: sorted.map((l) => l.numeroLocal),
      nombre: principal.nombre,
      slug: principal.slug,
      giro: principal.giro,
      estado: principal.estado,
      piso: principal.piso,
      ordenPlano: principal.ordenPlano ?? 0,
      m2Total,
      fotos: sorted.flatMap((l) => l.fotos),
      logo: principal.logo,
      descripcion: principal.descripcion,
      esCombinada: sorted.length > 1,
      registroPrincipal: principal,
      todosRegistros: sorted,
    });
  }

  return result;
}
