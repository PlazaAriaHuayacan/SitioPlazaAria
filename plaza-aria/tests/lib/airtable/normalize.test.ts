import { describe, it, expect } from 'vitest';
import { normalizeLocal, normalizeEvento, normalizeConfig } from '@/lib/airtable/normalize';
import { rawLocalOcupado, rawLocalDisponible, rawEvento, rawConfig } from './fixtures';
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
});
