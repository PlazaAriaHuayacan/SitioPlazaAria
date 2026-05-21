import { describe, it, expect } from 'vitest';
import { normalizeLocal, normalizeEvento, normalizeConfig } from '@/lib/airtable/normalize';
import { rawLocalOcupado, rawLocalDisponible, rawEvento, rawConfig, rawConfigPoblada } from './fixtures';
import type { Local } from '@/types/domain';

describe('normalizeLocal — Ocupado', () => {
  const local = normalizeLocal(rawLocalOcupado);

  it('has estado Ocupado', () => {
    expect(local.estado).toBe('Ocupado');
  });

  it('leasing is undefined for non-Disponible', () => {
    expect(local.leasing).toBeUndefined();
  });

  it('fotos is empty (hidden when not Disponible)', () => {
    expect(local.fotos).toHaveLength(0);
  });

  it('logo is preserved even when Ocupado', () => {
    expect(local.logo?.url).toBe('https://x/logo.png');
  });

  it('slug comes from Airtable Slug field', () => {
    expect(local.slug).toBe('cafe-aria');
  });

  it('all 7 horarios are parsed', () => {
    const dias = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo'] as const;
    for (const dia of dias) {
      expect(local.horarios[dia]).toBeDefined();
    }
  });

  it('Domingo is closed (empty string → null/null)', () => {
    expect(local.horarios.Domingo).toEqual({ open: null, close: null });
  });

  it('Viernes has correct open/close times', () => {
    expect(local.horarios.Viernes).toEqual({ open: '08:00', close: '22:00' });
  });

  it('unidadComercial comes from UnidadComercial field', () => {
    expect(local.unidadComercial).toBe('L1');
  });

  it('ordenPlano is set when present', () => {
    expect(local.ordenPlano).toBe(1);
  });
});

describe('normalizeLocal — Disponible', () => {
  const local = normalizeLocal(rawLocalDisponible);

  it('has estado Disponible', () => {
    expect(local.estado).toBe('Disponible');
  });

  it('leasing.renta is populated', () => {
    expect(local.leasing?.renta).toBe(28000);
  });

  it('fotos is exposed for Disponible', () => {
    expect(local.fotos).toHaveLength(1);
  });

  it('slug is derived from Nombre when Slug field is missing', () => {
    // DISPONIBLE → slugify → 'disponible'
    expect(local.slug).toBe('disponible');
  });

  it('unidadComercial comes from UnidadComercial field when present', () => {
    expect(local.unidadComercial).toBe('L6-7');
  });

  it('ordenPlano is set for Disponible local', () => {
    expect(local.ordenPlano).toBe(6);
  });
});

describe('normalizeLocal — unidadComercial fallback', () => {
  it('falls back to numeroLocal when UnidadComercial field is missing', () => {
    const raw = {
      id: 'recFB001',
      fields: {
        Nombre: 'Test Local',
        Estado: 'Ocupado',
        Piso: '1',
        NumeroLocal: 'L9',
        HorarioLunes: '', HorarioMartes: '', HorarioMiercoles: '',
        HorarioJueves: '', HorarioViernes: '', HorarioSabado: '', HorarioDomingo: '',
      },
    };
    const local = normalizeLocal(raw);
    expect(local.unidadComercial).toBe('L9');
    expect(local.ordenPlano).toBeUndefined();
  });
});

describe('normalizeEvento', () => {
  it('fills localNombre and localSlug from the localsById map', () => {
    const linkedLocal = normalizeLocal(rawLocalOcupado);
    const localsById = new Map<string, Local>([[linkedLocal.id, linkedLocal]]);
    const evento = normalizeEvento(rawEvento, localsById);

    expect(evento.localNombre).toBe('Café Aria');
    expect(evento.localSlug).toBe('cafe-aria');
  });

  it('falls back to empty strings when local is not in map', () => {
    const localsById = new Map<string, Local>();
    const evento = normalizeEvento(rawEvento, localsById);

    expect(evento.localNombre).toBe('');
    expect(evento.localSlug).toBe('');
  });
});

describe('normalizeConfig', () => {
  it('splits GapsGiros into a trimmed array', () => {
    const config = normalizeConfig(rawConfig);
    expect(config.gapsGiros).toEqual(['Panadería', 'Cafetería', 'Farmacia']);
  });

  it('maps numeric fields correctly', () => {
    const config = normalizeConfig(rawConfig);
    expect(config.aforoEstimado).toBe(8000);
    expect(config.cajonesEstacionamiento).toBe(120);
  });

  it('empty GapsGiros yields an empty array', () => {
    const config = normalizeConfig({ id: 'recX', fields: {} });
    expect(config.gapsGiros).toEqual([]);
  });

  it('fotosGenerales is an empty array when field is missing', () => {
    const config = normalizeConfig({ id: 'recX', fields: {} });
    expect(config.fotosGenerales).toEqual([]);
  });

  it('normalizes demografia fields when present', () => {
    const c = normalizeConfig(rawConfigPoblada);
    expect(c?.demografia?.ingresoPromedioMXN).toBe(35000);
    expect(c?.demografia?.edadMediana).toBe(34);
    expect(c?.demografia?.fraccionamientos).toEqual(['Real Mayab', 'El Cielo', 'Residencial Cumbres']);
    expect(c?.demografia?.fuente).toContain('INEGI');
  });

  it('returns empty demografia.fraccionamientos when not provided', () => {
    const c = normalizeConfig(rawConfig);
    expect(c?.demografia?.fraccionamientos).toEqual([]);
    expect(c?.demografia?.ingresoPromedioMXN).toBeUndefined();
  });
});
