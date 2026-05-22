import type { UnidadComercialAgrupada } from './agrupar';
import type { Piso } from '@/types/domain';

// ─── Horizontal constants ─────────────────────────────────────────────────────
/** Usable pixel width that the layout fills (x: 0 → PLANO_WIDTH, relative). */
export const PLANO_WIDTH = 1200;
export const CORE_WIDTH = 70;

// ─── Vertical constants ───────────────────────────────────────────────────────
/** Height of each interactive unit block. */
export const BLOQUE_HEIGHT = 155;

// SVG absolute Y for each unit zone (the `g translate(100,0)` wrapper is still used
// for horizontal positioning, so y values are absolute SVG coordinates).
/** Y-top of the Piso 2 interactive unit strip. */
export const PISO2_UNITS_Y = 52;
/** Y-top of the Piso 1 interactive unit strip. */
export const PISO1_UNITS_Y = 253;

const LOCAL_GAP = 4;
const BASE_BLOCK_WIDTH = 70;
const MIN_BLOCK_WIDTH = 40;
const STANDARD_M2 = 60.35;

export type BloqueLayout =
  | { type: 'bloque'; unidad: UnidadComercialAgrupada; x: number; y: number; ancho: number; alto: number }
  | { type: 'core'; x: number; y: number; ancho: number; alto: number };

/** Natural (unscaled) width for a unit, proportional to sqrt of its m² ratio. */
function naturalWidth(m2: number): number {
  return Math.max(MIN_BLOCK_WIDTH, BASE_BLOCK_WIDTH * Math.sqrt(m2 / STANDARD_M2));
}

/**
 * Lay out the bloques + central core for ONE piso along the x-axis.
 * Returns relative x coordinates (0 → PLANO_WIDTH). The caller applies a
 * `translate(BUILDING_X_OFFSET, 0)` to convert to absolute SVG space.
 * The core is inserted at the structural mid-index (Math.floor(n/2)).
 */
export function computeLayout(unidades: UnidadComercialAgrupada[], _piso: Piso): BloqueLayout[] {
  if (unidades.length === 0) return [];

  const sorted = [...unidades].sort((a, b) => a.ordenPlano - b.ordenPlano);
  const naturals = sorted.map((u) => naturalWidth(u.m2Total));

  const totalGaps = (sorted.length - 1) * LOCAL_GAP + 2 * LOCAL_GAP; // gaps between blocks + 2 around core
  const totalNatural = naturals.reduce((s, w) => s + w, 0) + CORE_WIDTH + totalGaps;
  const scale = totalNatural > PLANO_WIDTH ? PLANO_WIDTH / totalNatural : 1;

  const result: BloqueLayout[] = [];
  const insertionIndex = Math.floor(sorted.length / 2);

  let x = 0;
  for (let i = 0; i < sorted.length; i++) {
    if (i === insertionIndex) {
      const coreW = CORE_WIDTH * scale;
      result.push({ type: 'core', x, y: 0, ancho: coreW, alto: BLOQUE_HEIGHT });
      x += coreW + LOCAL_GAP * scale;
    }
    const ancho = naturals[i] * scale;
    result.push({ type: 'bloque', unidad: sorted[i], x, y: 0, ancho, alto: BLOQUE_HEIGHT });
    x += ancho + LOCAL_GAP * scale;
  }

  // Edge case: insertionIndex >= sorted.length — append core at the end
  if (insertionIndex >= sorted.length) {
    const coreW = CORE_WIDTH * scale;
    result.push({ type: 'core', x, y: 0, ancho: coreW, alto: BLOQUE_HEIGHT });
  }

  return result;
}
