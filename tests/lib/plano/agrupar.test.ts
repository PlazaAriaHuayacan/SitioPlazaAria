import { describe, it, expect } from 'vitest';
import { agruparPorUnidad } from '@/lib/plano/agrupar';
import type { Local } from '@/types/domain';

const cerrado = { open: null, close: null };
const horarios = {
  Lunes: cerrado, Martes: cerrado, Miercoles: cerrado,
  Jueves: cerrado, Viernes: cerrado, Sabado: cerrado, Domingo: cerrado,
};

function L(over: Partial<Local>): Local {
  return {
    id: over.id ?? 'rec',
    slug: over.slug ?? 'x',
    nombre: over.nombre ?? 'Local',
    giro: over.giro ?? 'Otro',
    estado: over.estado ?? 'Disponible',
    piso: over.piso ?? '1',
    numeroLocal: over.numeroLocal ?? 'L1',
    unidadComercial: over.unidadComercial ?? over.numeroLocal ?? 'L1',
    ordenPlano: over.ordenPlano,
    fotos: over.fotos ?? [],
    descripcion: over.descripcion ?? '',
    horarios,
    leasing: over.leasing,
    ...over,
  };
}

describe('agruparPorUnidad', () => {
  it('returns one unidad per individual local with unique unidadComercial', () => {
    const r = agruparPorUnidad([
      L({ id: '1', numeroLocal: 'L1', unidadComercial: 'L1', ordenPlano: 1 }),
      L({ id: '2', numeroLocal: 'L2', unidadComercial: 'L2', ordenPlano: 2 }),
      L({ id: '3', numeroLocal: 'L3', unidadComercial: 'L3', ordenPlano: 3 }),
    ]);
    expect(r.length).toBe(3);
    expect(r.map((u) => u.id).sort()).toEqual(['L1', 'L2', 'L3']);
  });

  it('groups two locales sharing UnidadComercial = "L6-7" into one unidad', () => {
    const r = agruparPorUnidad([
      L({ id: '1', numeroLocal: 'L6', unidadComercial: 'L6-7', ordenPlano: 6,
          leasing: { m2: 60.35, renta: 30000, instalaciones: [] } }),
      L({ id: '2', numeroLocal: 'L7', unidadComercial: 'L6-7', ordenPlano: 6,
          leasing: { m2: 60.35, renta: 30000, instalaciones: [] } }),
    ]);
    expect(r.length).toBe(1);
    expect(r[0].id).toBe('L6-7');
    expect(r[0].loteIds.sort()).toEqual(['L6', 'L7']);
    expect(r[0].esCombinada).toBe(true);
    expect(r[0].m2Total).toBeCloseTo(120.7, 2);
  });

  it('groups three locales sharing UnidadComercial = "L16-17-18"', () => {
    const r = agruparPorUnidad([
      L({ id: '1', numeroLocal: 'L16', unidadComercial: 'L16-17-18', ordenPlano: 1,
          leasing: { m2: 60.92, renta: 25000, instalaciones: [] } }),
      L({ id: '2', numeroLocal: 'L17', unidadComercial: 'L16-17-18', ordenPlano: 1,
          leasing: { m2: 60.92, renta: 25000, instalaciones: [] } }),
      L({ id: '3', numeroLocal: 'L18', unidadComercial: 'L16-17-18', ordenPlano: 1,
          leasing: { m2: 60.92, renta: 25000, instalaciones: [] } }),
    ]);
    expect(r.length).toBe(1);
    expect(r[0].loteIds.length).toBe(3);
    expect(r[0].m2Total).toBeCloseTo(182.76, 1);
  });

  it('selects the lot with the lowest ordenPlano as the principal', () => {
    const r = agruparPorUnidad([
      L({ id: '2', numeroLocal: 'L7', unidadComercial: 'L6-7', ordenPlano: 7, nombre: 'Bar B' }),
      L({ id: '1', numeroLocal: 'L6', unidadComercial: 'L6-7', ordenPlano: 6, nombre: 'Bar A' }),
    ]);
    expect(r.length).toBe(1);
    expect(r[0].registroPrincipal.numeroLocal).toBe('L6');
    expect(r[0].nombre).toBe('Bar A');
  });

  it('marks esCombinada=false for solo unidades', () => {
    const r = agruparPorUnidad([
      L({ id: '1', numeroLocal: 'L1', unidadComercial: 'L1', ordenPlano: 1 }),
    ]);
    expect(r[0].esCombinada).toBe(false);
    expect(r[0].loteIds).toEqual(['L1']);
  });

  it('falls back to a sensible m2Total when leasing data is missing', () => {
    const r = agruparPorUnidad([
      L({ id: '1', numeroLocal: 'L4', unidadComercial: 'L4', ordenPlano: 4, estado: 'Ocupado' }),
    ]);
    // No leasing field; expect a positive number > 0 (fallback to 60.35 per lot)
    expect(r[0].m2Total).toBeGreaterThan(0);
  });

  it('skips records with empty unidadComercial', () => {
    const r = agruparPorUnidad([
      L({ id: '1', numeroLocal: 'L1', unidadComercial: 'L1', ordenPlano: 1 }),
      L({ id: 'orphan', numeroLocal: '', unidadComercial: '', ordenPlano: 0 }),
    ]);
    expect(r.length).toBe(1);
  });

  it('merges fotos from all underlying records', () => {
    const r = agruparPorUnidad([
      L({ id: '1', numeroLocal: 'L6', unidadComercial: 'L6-7', ordenPlano: 6,
          fotos: [{ url: 'a.jpg' }] }),
      L({ id: '2', numeroLocal: 'L7', unidadComercial: 'L6-7', ordenPlano: 6,
          fotos: [{ url: 'b.jpg' }, { url: 'c.jpg' }] }),
    ]);
    expect(r[0].fotos.length).toBe(3);
  });

  it('returns empty array for empty input', () => {
    expect(agruparPorUnidad([])).toEqual([]);
  });
});
