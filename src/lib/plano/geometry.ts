import type { UnidadComercialAgrupada } from './agrupar';
import type { Piso } from '@/types/domain';

export const PLANO_WIDTH = 1200;
export const BLOQUE_HEIGHT = 140;
export const CORE_WIDTH = 70;
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
 * The caller positions y vertically based on which piso this is.
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
