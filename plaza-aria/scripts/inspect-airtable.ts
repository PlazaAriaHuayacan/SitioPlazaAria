/**
 * Diagnostic: print the raw field names of one record from each table
 * in the user's real Airtable base.
 *
 * Run: cd plaza-aria && npx tsx scripts/inspect-airtable.ts
 */
import { config } from 'dotenv';
import { resolve } from 'path';

config({ path: resolve(__dirname, '../.env.local') });

const API = 'https://api.airtable.com/v0';
const apiKey = process.env.AIRTABLE_API_KEY!;
const baseId = process.env.AIRTABLE_BASE_ID!;

async function fetchOne(table: string) {
  const url = `${API}/${baseId}/${encodeURIComponent(table)}?maxRecords=3`;
  const res = await fetch(url, {
    headers: { Authorization: `Bearer ${apiKey}` },
  });
  if (!res.ok) {
    const body = await res.text();
    return { table, error: `${res.status} ${res.statusText}: ${body}` };
  }
  const data = await res.json();
  return { table, records: data.records };
}

async function fetchSchema() {
  // Airtable meta API - returns full schema if token has schema.bases:read
  const url = `${API}/meta/bases/${baseId}/tables`;
  const res = await fetch(url, {
    headers: { Authorization: `Bearer ${apiKey}` },
  });
  if (!res.ok) {
    const body = await res.text();
    return { error: `${res.status}: ${body}` };
  }
  return res.json();
}

async function main() {
  console.log('\n=== META SCHEMA (all tables and fields) ===\n');
  const schema = await fetchSchema();
  if ((schema as any).error) {
    console.log('Schema API failed:', (schema as any).error);
    console.log('(Token may lack schema.bases:read scope. Continuing with table-by-table probing.)');
  } else {
    for (const t of (schema as any).tables || []) {
      console.log(`\nTable: "${t.name}"  (id: ${t.id})`);
      for (const f of t.fields) {
        const opts = f.options?.choices?.map((c: any) => c.name).join(' | ');
        console.log(`  - "${f.name}"  [${f.type}]${opts ? '  options: ' + opts : ''}`);
      }
    }
  }

  // Probe each expected table by name and a few common variants
  const candidates = [
    'Locales', 'Eventos_Clases', 'Eventos', 'Leads_Renta', 'Leads', 'Notif_Vecinos', 'Notif', 'Config',
  ];

  console.log('\n=== TABLE PROBE (try fetching by name) ===\n');
  for (const name of candidates) {
    const r = await fetchOne(name);
    if ('error' in r) {
      console.log(`✗ "${name}": ${r.error?.substring(0, 120) ?? ''}`);
    } else {
      console.log(`✓ "${name}": ${r.records.length} records`);
      if (r.records[0]) {
        console.log(`   Field keys present: ${Object.keys(r.records[0].fields).map(k => `"${k}"`).join(', ')}`);
      }
    }
  }
}

main().catch(e => { console.error(e); process.exit(1); });
