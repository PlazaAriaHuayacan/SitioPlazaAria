import { describe, it, expect } from 'vitest';
import { filtrarYOrdenar } from '@/lib/directorio/filter';
import type { Local } from '@/types/domain';

const cerrado = { open: null, close: null };

const horariosAbiertos = {
  Lunes: { open: '10:00', close: '21:00' },
  Martes: { open: '10:00', close: '21:00' },
  Miercoles: { open: '10:00', close: '21:00' },
  Jueves: { open: '10:00', close: '21:00' },
  Viernes: { open: '10:00', close: '21:00' },
  Sabado: { open: '10:00', close: '21:00' },
  Domingo: cerrado,
};

const closedAll = {
  Lunes: cerrado, Martes: cerrado, Miercoles: cerrado,
  Jueves: cerrado, Viernes: cerrado, Sabado: cerrado, Domingo: cerrado,
};

function L(over: Partial<Local>): Local {
  return {
    id: over.id ?? 'rec1',
    slug: over.slug ?? 'sample',
    nombre: over.nombre ?? 'Sample',
    giro: over.giro ?? 'Otro',
    estado: over.estado ?? 'Ocupado',
    piso: over.piso ?? '1',
    numeroLocal: over.numeroLocal ?? 'A-01',
    fotos: [],
    descripcion: over.descripcion ?? '',
    horarios: over.horarios ?? horariosAbiertos,
    ...over,
  };
}

function plazaTime(y: number, m: number, d: number, h: number) {
  return new Date(Date.UTC(y, m - 1, d, h + 5, 0));
}

const monday14 = plazaTime(2026, 5, 18, 14);

describe('filtrarYOrdenar', () => {
  const base: Local[] = [
    L({ id: 'rec3', slug: 'cafe', nombre: 'Café Aria', giro: 'Restaurante', piso: '1', numeroLocal: 'A-02' }),
    L({ id: 'rec1', slug: 'kumon', nombre: 'Kumon', giro: 'Educación', piso: '2', numeroLocal: 'A-11' }),
    L({ id: 'rec2', slug: 'bici', nombre: 'BiciSpin', giro: 'Fitness', piso: '2', numeroLocal: 'B-04' }),
    L({ id: 'rec4', slug: 'avail', nombre: 'DISPONIBLE', giro: 'Otro', piso: '1', numeroLocal: 'A-05', estado: 'Disponible', horarios: closedAll }),
    L({ id: 'rec5', slug: '', nombre: '', giro: 'Otro', piso: '1', numeroLocal: '' }), // placeholder
  ];

  const baseParams = {
    query: '', giros: [], piso: 'Todos' as const, estado: 'Todos' as const,
    orden: 'Alfabetico' as const, now: monday14,
  };

  it('skips placeholder rows with empty nombre/slug', () => {
    const r = filtrarYOrdenar(base, baseParams);
    expect(r.find((l) => l.id === 'rec5')).toBeUndefined();
    expect(r.length).toBe(4);
  });

  it('filters by giro', () => {
    const r = filtrarYOrdenar(base, { ...baseParams, giros: ['Educación'] });
    expect(r.map((l) => l.slug)).toEqual(['kumon']);
  });

  it('filters by piso', () => {
    const r = filtrarYOrdenar(base, { ...baseParams, piso: '2' });
    expect(r.map((l) => l.slug).sort()).toEqual(['bici', 'kumon']);
  });

  it('filters by Disponibles', () => {
    const r = filtrarYOrdenar(base, { ...baseParams, estado: 'Disponibles' });
    expect(r.map((l) => l.slug)).toEqual(['avail']);
  });

  it('filters by AbiertosAhora skipping Disponible with closed horarios', () => {
    const r = filtrarYOrdenar(base, { ...baseParams, estado: 'AbiertosAhora' });
    expect(r.find((l) => l.slug === 'avail')).toBeUndefined();
    expect(r.length).toBe(3);
  });

  it('text search is accent-insensitive', () => {
    const r = filtrarYOrdenar(base, { ...baseParams, query: 'cafe' });
    expect(r.map((l) => l.slug)).toEqual(['cafe']);
    const r2 = filtrarYOrdenar(base, { ...baseParams, query: 'EDUCACION' });
    expect(r2.map((l) => l.slug)).toEqual(['kumon']);
  });

  it('orders by piso/local', () => {
    const r = filtrarYOrdenar(base, { ...baseParams, orden: 'PisoLocal' });
    expect(r.map((l) => l.numeroLocal)).toEqual(['A-02', 'A-05', 'A-11', 'B-04']);
  });

  it('orders alfabetico (es) — handles accents', () => {
    const localsWithAccent = [
      L({ id: 'r1', slug: 'a', nombre: 'Ávila' }),
      L({ id: 'r2', slug: 'b', nombre: 'Bali' }),
      L({ id: 'r3', slug: 'c', nombre: 'Caña' }),
    ];
    const r = filtrarYOrdenar(localsWithAccent, baseParams);
    expect(r.map((l) => l.nombre)).toEqual(['Ávila', 'Bali', 'Caña']);
  });

  it('orders novedades by id desc', () => {
    const r = filtrarYOrdenar(base, { ...baseParams, orden: 'Novedades' });
    expect(r.map((l) => l.id)).toEqual(['rec4', 'rec3', 'rec2', 'rec1']);
  });
});
