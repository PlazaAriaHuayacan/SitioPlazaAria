/**
 * Low-level Airtable REST API helpers.
 * All requests go through airtableGet (GET) or airtablePost (POST).
 */
import { getAirtableEnv } from './env';

const BASE_URL = 'https://api.airtable.com/v0';

export interface AirtableRecord<T = unknown> {
  id: string;
  fields: T;
}

function authHeaders(apiKey: string): HeadersInit {
  return {
    Authorization: `Bearer ${apiKey}`,
    Accept: 'application/json',
  };
}

/**
 * Paginated GET — follows offset cursor until all pages are fetched.
 * Returns the full merged array of records.
 */
export async function airtableGet<T = unknown>(
  table: string,
  params?: Record<string, string>,
): Promise<Array<AirtableRecord<T>>> {
  const { apiKey, baseId } = getAirtableEnv();
  const records: Array<AirtableRecord<T>> = [];
  let offset: string | undefined;

  do {
    const url = new URL(`${BASE_URL}/${baseId}/${encodeURIComponent(table)}`);
    if (params) {
      for (const [k, v] of Object.entries(params)) url.searchParams.set(k, v);
    }
    if (offset) url.searchParams.set('offset', offset);

    const res = await fetch(url.toString(), { headers: authHeaders(apiKey) });

    if (!res.ok) {
      const body = await res.text().catch(() => '');
      throw new Error(`Airtable ${table} failed: ${res.status} ${body}`);
    }

    const json = (await res.json()) as {
      records: Array<AirtableRecord<T>>;
      offset?: string;
    };

    records.push(...json.records);
    offset = json.offset;
  } while (offset);

  return records;
}

/**
 * Single POST — creates one record in the given table.
 * Returns the created record's id.
 */
export async function airtablePost<TFields = unknown>(
  table: string,
  fields: TFields,
): Promise<{ id: string }> {
  const { apiKey, baseId } = getAirtableEnv();
  const url = `${BASE_URL}/${baseId}/${encodeURIComponent(table)}`;

  const res = await fetch(url, {
    method: 'POST',
    headers: {
      ...authHeaders(apiKey),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ fields }),
  });

  if (!res.ok) {
    const body = await res.text().catch(() => '');
    throw new Error(`Airtable ${table} failed: ${res.status} ${body}`);
  }

  const json = (await res.json()) as { id: string };
  return { id: json.id };
}
