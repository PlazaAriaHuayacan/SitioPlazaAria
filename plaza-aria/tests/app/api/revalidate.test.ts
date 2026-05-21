import { describe, it, expect, vi, beforeEach } from 'vitest';

// Hoist the mock so it is available before imports are processed
const { revalidatePathMock } = vi.hoisted(() => ({
  revalidatePathMock: vi.fn(),
}));

vi.mock('next/cache', () => ({ revalidatePath: revalidatePathMock }));

// Re-import after mock is set up
import { POST, GET } from '@/app/api/revalidate/route';
import { NextRequest } from 'next/server';

function mkReq(opts: {
  method?: string;
  authHeader?: string;
  secretQuery?: string;
  body?: unknown;
} = {}) {
  const url = new URL('http://localhost:3000/api/revalidate' + (opts.secretQuery ? `?secret=${opts.secretQuery}` : ''));
  const headers = new Headers();
  if (opts.authHeader) headers.set('authorization', opts.authHeader);
  const init: RequestInit = {
    method: opts.method ?? 'POST',
    headers,
  };
  if (opts.body !== undefined) {
    init.body = JSON.stringify(opts.body);
    headers.set('content-type', 'application/json');
  }
  return new NextRequest(url, init);
}

describe('POST /api/revalidate', () => {
  beforeEach(() => {
    revalidatePathMock.mockReset();
    vi.stubEnv('AIRTABLE_REVALIDATE_SECRET', 'test-secret');
  });

  it('returns 401 when no auth provided', async () => {
    const res = await POST(mkReq());
    expect(res.status).toBe(401);
  });

  it('returns 401 when bearer token is wrong', async () => {
    const res = await POST(mkReq({ authHeader: 'Bearer wrong' }));
    expect(res.status).toBe(401);
  });

  it('accepts a valid bearer token', async () => {
    const res = await POST(mkReq({ authHeader: 'Bearer test-secret' }));
    expect(res.status).toBe(200);
    const json = await res.json();
    expect(json.revalidated).toBe(true);
    // Default paths revalidated
    expect(revalidatePathMock).toHaveBeenCalledWith('/');
    expect(revalidatePathMock).toHaveBeenCalledWith('/directorio');
  });

  it('accepts ?secret= query param as alternative', async () => {
    const res = await POST(mkReq({ secretQuery: 'test-secret' }));
    expect(res.status).toBe(200);
  });

  it('revalidates only the requested paths when body provides them', async () => {
    const res = await POST(mkReq({
      authHeader: 'Bearer test-secret',
      body: { paths: ['/directorio', '/agenda'] },
    }));
    expect(res.status).toBe(200);
    const json = await res.json();
    expect(json.paths).toEqual(['/directorio', '/agenda']);
    expect(revalidatePathMock).toHaveBeenCalledTimes(2);
    expect(revalidatePathMock).toHaveBeenCalledWith('/directorio');
    expect(revalidatePathMock).toHaveBeenCalledWith('/agenda');
  });

  it('normalizes paths without leading slash', async () => {
    const res = await POST(mkReq({
      authHeader: 'Bearer test-secret',
      body: { paths: ['directorio'] },
    }));
    const json = await res.json();
    expect(json.paths).toEqual(['/directorio']);
  });

  it('falls back to defaults when body has empty paths array', async () => {
    const res = await POST(mkReq({
      authHeader: 'Bearer test-secret',
      body: { paths: [] },
    }));
    const json = await res.json();
    expect(json.paths).toEqual(['/', '/directorio', '/agenda', '/renta']);
  });

  it('returns 500 when AIRTABLE_REVALIDATE_SECRET is not set', async () => {
    vi.stubEnv('AIRTABLE_REVALIDATE_SECRET', '');
    const res = await POST(mkReq({ authHeader: 'Bearer anything' }));
    expect(res.status).toBe(500);
  });
});

describe('GET /api/revalidate', () => {
  it('returns 405 method-not-allowed', async () => {
    const res = await GET();
    expect(res.status).toBe(405);
  });
});
