import { describe, it, expect, beforeEach, vi } from 'vitest';
import { airtableGet } from '@/lib/airtable/fetch';
import { listLocales, getLocalBySlug, createLeadRenta } from '@/lib/airtable/client';
import { rawLocalOcupado, rawLocalDisponible } from './fixtures';

// ─── Env setup ────────────────────────────────────────────────────────────────

beforeEach(() => {
  vi.unstubAllGlobals();
  vi.stubEnv('AIRTABLE_API_KEY', 'fake-key');
  vi.stubEnv('AIRTABLE_BASE_ID', 'appXYZ');
});

// ─── airtableGet pagination ───────────────────────────────────────────────────

describe('airtableGet', () => {
  it('follows offset cursor across two pages and merges all records', async () => {
    const page1 = {
      records: [{ id: 'rec1', fields: { Nombre: 'A' } }],
      offset: 'tok-page2',
    };
    const page2 = {
      records: [{ id: 'rec2', fields: { Nombre: 'B' } }],
    };

    let callCount = 0;
    vi.stubGlobal('fetch', vi.fn(async (url: string) => {
      callCount++;
      const body = url.includes('offset=tok-page2') ? page2 : page1;
      return {
        ok: true,
        json: async () => body,
      };
    }));

    const result = await airtableGet('Locales');
    expect(result).toHaveLength(2);
    expect(result[0].id).toBe('rec1');
    expect(result[1].id).toBe('rec2');
    expect(callCount).toBe(2);
  });

  it('throws with table name on 401', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => ({
      ok: false,
      status: 401,
      text: async () => 'Unauthorized',
    })));

    await expect(airtableGet('Locales')).rejects.toThrow('Airtable Locales failed: 401 Unauthorized');
  });
});

// ─── listLocales ──────────────────────────────────────────────────────────────

describe('listLocales', () => {
  it('returns locales sorted by piso then numeroLocal', async () => {
    // rawLocalOcupado: Piso '1', NumeroLocal 'A-12'
    // rawLocalDisponible: Piso '2', NumeroLocal 'B-04'
    // Return them in reverse order so sort is actually exercised
    vi.stubGlobal('fetch', vi.fn(async () => ({
      ok: true,
      json: async () => ({ records: [rawLocalDisponible, rawLocalOcupado] }),
    })));

    const result = await listLocales();
    expect(result[0].piso).toBe('1');
    expect(result[1].piso).toBe('2');
  });
});

// ─── getLocalBySlug ───────────────────────────────────────────────────────────

describe('getLocalBySlug', () => {
  beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn(async () => ({
      ok: true,
      json: async () => ({ records: [rawLocalOcupado, rawLocalDisponible] }),
    })));
  });

  it('returns the matching local', async () => {
    const local = await getLocalBySlug('cafe-aria');
    expect(local?.nombre).toBe('Café Aria');
  });

  it('returns null for an unknown slug', async () => {
    const local = await getLocalBySlug('no-existe');
    expect(local).toBeNull();
  });
});

// ─── createLeadRenta ──────────────────────────────────────────────────────────

describe('createLeadRenta', () => {
  it('POSTs to Leads_Renta with correct URL and body shape', async () => {
    let capturedUrl = '';
    let capturedBody: unknown;

    vi.stubGlobal('fetch', vi.fn(async (url: string, init: RequestInit) => {
      capturedUrl = url;
      capturedBody = JSON.parse(init.body as string);
      return {
        ok: true,
        json: async () => ({ id: 'recNEW001' }),
      };
    }));

    const result = await createLeadRenta({
      nombre: 'Ana Torres',
      whatsapp: '+529981234567',
      email: 'ana@test.com',
      giroPropuesto: 'Panadería',
      mensaje: 'Me interesa el local B-04.',
    });

    expect(capturedUrl).toContain('Leads_Renta');
    expect((capturedBody as { fields: Record<string, string> }).fields.Nombre).toBe('Ana Torres');
    expect((capturedBody as { fields: Record<string, string> }).fields.Email).toBe('ana@test.com');
    expect(result.id).toBe('recNEW001');
  });
});
