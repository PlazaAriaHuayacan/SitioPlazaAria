import { describe, it, expect } from 'vitest';
import { computeLayout, PLANO_WIDTH, BLOQUE_HEIGHT, CORE_WIDTH } from '@/lib/plano/geometry';
import type { UnidadComercialAgrupada } from '@/lib/plano/agrupar';

function U(over: Partial<UnidadComercialAgrupada>): UnidadComercialAgrupada {
  return {
    id: over.id ?? 'U',
    loteIds: over.loteIds ?? ['L1'],
    nombre: over.nombre ?? 'Local',
    slug: over.slug ?? 'local',
    giro: over.giro ?? 'Otro',
    estado: over.estado ?? 'Disponible',
    piso: over.piso ?? '1',
    ordenPlano: over.ordenPlano ?? 1,
    m2Total: over.m2Total ?? 60.35,
    fotos: [],
    descripcion: '',
    esCombinada: false,
    registroPrincipal: {} as any,
    todosRegistros: [],
    ...over,
  };
}

const piso1FullSet = [
  U({ id: 'L1',     ordenPlano: 1,  m2Total: 158.21 }),
  U({ id: 'L2',     ordenPlano: 2,  m2Total: 92.20 }),
  U({ id: 'L3',     ordenPlano: 3,  m2Total: 77.05 }),
  U({ id: 'L4',     ordenPlano: 4,  m2Total: 60.35 }),
  U({ id: 'L5',     ordenPlano: 5,  m2Total: 60.35 }),
  U({ id: 'L6-7',   ordenPlano: 6,  m2Total: 120.7, esCombinada: true }),
  U({ id: 'L8-9',   ordenPlano: 7,  m2Total: 120.7, esCombinada: true }),
  U({ id: 'L10',    ordenPlano: 8,  m2Total: 60.35 }),
  U({ id: 'L11',    ordenPlano: 9,  m2Total: 60.35 }),
  U({ id: 'L12',    ordenPlano: 10, m2Total: 79.35 }),
  U({ id: 'L13',    ordenPlano: 11, m2Total: 90.89 }),
  U({ id: 'L14-15', ordenPlano: 12, m2Total: 229.58, esCombinada: true }),
];

describe('computeLayout', () => {
  it('returns one bloque entry per unidad plus one core entry', () => {
    const r = computeLayout(piso1FullSet, '1');
    expect(r.filter((b) => b.type === 'bloque').length).toBe(12);
    expect(r.filter((b) => b.type === 'core').length).toBe(1);
  });

  it('orders bloques left-to-right by ordenPlano', () => {
    const r = computeLayout(piso1FullSet, '1');
    const bloques = r.filter((b) => b.type === 'bloque') as Extract<typeof r[number], { type: 'bloque' }>[];
    for (let i = 0; i < bloques.length - 1; i++) {
      expect(bloques[i].x).toBeLessThan(bloques[i + 1].x);
    }
    expect(bloques[0].unidad.ordenPlano).toBe(1);
    expect(bloques[bloques.length - 1].unidad.ordenPlano).toBe(12);
  });

  it('places the core in the middle (between 6th and 7th unidad)', () => {
    const r = computeLayout(piso1FullSet, '1');
    const core = r.find((b) => b.type === 'core')!;
    const bloques = r.filter((b) => b.type === 'bloque') as Extract<typeof r[number], { type: 'bloque' }>[];
    const leftOfCore = bloques.filter((b) => b.x < core.x);
    const rightOfCore = bloques.filter((b) => b.x > core.x);
    expect(leftOfCore.length).toBe(6);
    expect(rightOfCore.length).toBe(6);
  });

  it('larger m² produces wider blocks (when nothing is scaled down)', () => {
    const r = computeLayout(
      [
        U({ id: 'A', ordenPlano: 1, m2Total: 60 }),
        U({ id: 'B', ordenPlano: 2, m2Total: 120 }),
        U({ id: 'C', ordenPlano: 3, m2Total: 240 }),
      ],
      '1',
    );
    const blocks = r.filter((b) => b.type === 'bloque') as Extract<typeof r[number], { type: 'bloque' }>[];
    expect(blocks[0].ancho).toBeLessThan(blocks[1].ancho);
    expect(blocks[1].ancho).toBeLessThan(blocks[2].ancho);
  });

  it('all entries fit within PLANO_WIDTH after scaling', () => {
    const r = computeLayout(piso1FullSet, '1');
    const last = r[r.length - 1];
    expect(last.x + last.ancho).toBeLessThanOrEqual(PLANO_WIDTH + 0.01); // tiny float fuzz
  });

  it('all blocks have alto = BLOQUE_HEIGHT and y = 0', () => {
    const r = computeLayout(piso1FullSet, '1');
    for (const b of r) {
      expect(b.alto).toBe(BLOQUE_HEIGHT);
      expect(b.y).toBe(0);
    }
  });

  it('returns empty array (no core either) when no unidades given', () => {
    expect(computeLayout([], '1')).toEqual([]);
  });

  it('still inserts a core even with only 2 unidades', () => {
    const r = computeLayout(
      [
        U({ id: 'A', ordenPlano: 1, m2Total: 60 }),
        U({ id: 'B', ordenPlano: 2, m2Total: 60 }),
      ],
      '1',
    );
    expect(r.filter((b) => b.type === 'core').length).toBe(1);
  });

  it('respects a minimum block width of 40px even for tiny m²', () => {
    const r = computeLayout([U({ id: 'tiny', ordenPlano: 1, m2Total: 1 })], '1');
    const bloque = r.find((b) => b.type === 'bloque') as Extract<typeof r[number], { type: 'bloque' }>;
    // After scaling to fit, it might be smaller; without scaling the minimum is 40.
    // With one tiny block + core, totalNatural is well under PLANO_WIDTH, so scale=1.
    expect(bloque.ancho).toBeGreaterThanOrEqual(40);
  });
});
