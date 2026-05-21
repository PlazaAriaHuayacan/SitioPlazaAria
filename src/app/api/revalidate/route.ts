import { NextRequest, NextResponse } from 'next/server';
import { revalidatePath } from 'next/cache';

const DEFAULT_PATHS = ['/', '/directorio', '/agenda', '/renta'];

function getSecret(req: NextRequest): string | null {
  const header = req.headers.get('authorization');
  if (header?.startsWith('Bearer ')) return header.slice(7).trim();
  const fromQuery = req.nextUrl.searchParams.get('secret');
  return fromQuery ?? null;
}

export async function POST(req: NextRequest) {
  const expected = process.env.AIRTABLE_REVALIDATE_SECRET;
  if (!expected) {
    return NextResponse.json(
      { error: 'server-missing-secret' },
      { status: 500 },
    );
  }

  const provided = getSecret(req);
  if (provided !== expected) {
    return NextResponse.json({ error: 'unauthorized' }, { status: 401 });
  }

  let paths: string[] = DEFAULT_PATHS;
  try {
    const body = await req.json().catch(() => null);
    if (body && Array.isArray(body.paths)) {
      const cleaned = body.paths
        .filter((p: unknown): p is string => typeof p === 'string')
        .map((p: string) => (p.startsWith('/') ? p : `/${p}`));
      if (cleaned.length > 0) paths = cleaned;
    }
  } catch {
    // Ignore body parse errors — use defaults.
  }

  for (const p of paths) {
    revalidatePath(p);
  }

  console.log(`[/api/revalidate] revalidated ${paths.length} path(s):`, paths);
  return NextResponse.json({ revalidated: true, paths });
}

export async function GET() {
  return NextResponse.json({ error: 'method-not-allowed' }, { status: 405 });
}
