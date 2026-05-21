/**
 * Seed Airtable `Locales` table with the 31 architectural lots.
 *
 * Idempotent: looks up existing records by NumeroLocal, updates if found, creates otherwise.
 * Records created with `Estado = Disponible` and placeholder `Nombre = "Local L{N}"`.
 * The user will manually populate renta, fotos, etc. afterwards.
 *
 * Usage:
 *   cd plaza-aria
 *   npx tsx scripts/seed-locales.ts --dry-run   # preview
 *   npx tsx scripts/seed-locales.ts             # write
 *
 * Requires .env.local with AIRTABLE_API_KEY and AIRTABLE_BASE_ID.
 * Requires that the fields `UnidadComercial` (single text) and `OrdenPlano` (number)
 * have already been added to the Airtable `Locales` table.
 */
import { config } from 'dotenv';
import { resolve } from 'path';
config({ path: resolve(__dirname, '../.env.local') });

import { LOTES, type SeedLote } from './data/locales-catalog';

const API = 'https://api.airtable.com/v0';
const apiKey = process.env.AIRTABLE_API_KEY;
const baseId = process.env.AIRTABLE_BASE_ID;
const TABLE = 'Locales';
const dryRun = process.argv.includes('--dry-run');

if (!apiKey || !baseId) {
  console.error('Missing AIRTABLE_API_KEY or AIRTABLE_BASE_ID in .env.local');
  process.exit(1);
}

function fieldsFor(lote: SeedLote) {
  return {
    NumeroLocal: lote.numeroLocal,
    UnidadComercial: lote.unidadComercial,
    Piso: lote.piso,
    OrdenPlano: lote.ordenPlano,
    M2: lote.m2,
    Estado: 'Disponible',
    Nombre: `Local ${lote.numeroLocal}`,
    Giro: 'Otro',
  };
}

async function listExisting(): Promise<Map<string, string>> {
  const map = new Map<string, string>();
  let offset: string | undefined;
  do {
    const url = `${API}/${baseId}/${TABLE}?fields[]=NumeroLocal${offset ? `&offset=${encodeURIComponent(offset)}` : ''}`;
    const res = await fetch(url, { headers: { Authorization: `Bearer ${apiKey}` } });
    if (!res.ok) {
      const body = await res.text();
      throw new Error(`List failed: ${res.status} ${body}`);
    }
    const data = await res.json() as { records: Array<{ id: string; fields: Record<string, unknown> }>; offset?: string };
    for (const r of data.records) {
      const num = r.fields.NumeroLocal as string | undefined;
      if (num) map.set(num, r.id);
    }
    offset = data.offset;
  } while (offset);
  return map;
}

async function createBatch(loteList: SeedLote[]): Promise<number> {
  let created = 0;
  // Airtable allows up to 10 records per batch create
  for (let i = 0; i < loteList.length; i += 10) {
    const batch = loteList.slice(i, i + 10);
    if (dryRun) {
      for (const lote of batch) console.log(`  CREATE ${lote.numeroLocal}`);
      created += batch.length;
      continue;
    }
    const url = `${API}/${baseId}/${TABLE}`;
    const res = await fetch(url, {
      method: 'POST',
      headers: { Authorization: `Bearer ${apiKey}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ records: batch.map((l) => ({ fields: fieldsFor(l) })) }),
    });
    if (!res.ok) {
      const body = await res.text();
      throw new Error(`Create batch failed: ${res.status} ${body}`);
    }
    created += batch.length;
  }
  return created;
}

async function updateOne(id: string, lote: SeedLote): Promise<void> {
  if (dryRun) {
    console.log(`  UPDATE ${lote.numeroLocal} (id=${id})`);
    return;
  }
  const res = await fetch(`${API}/${baseId}/${TABLE}/${id}`, {
    method: 'PATCH',
    headers: { Authorization: `Bearer ${apiKey}`, 'Content-Type': 'application/json' },
    body: JSON.stringify({ fields: fieldsFor(lote) }),
  });
  if (!res.ok) {
    const body = await res.text();
    throw new Error(`Update ${lote.numeroLocal} failed: ${res.status} ${body}`);
  }
}

async function main() {
  console.log(`\nSeed Locales · ${dryRun ? 'DRY RUN' : 'WRITE MODE'}`);
  console.log(`Catalog has ${LOTES.length} lots\n`);

  const existing = await listExisting();
  console.log(`Found ${existing.size} existing record(s)`);

  const toCreate: SeedLote[] = [];
  const toUpdate: Array<{ id: string; lote: SeedLote }> = [];

  for (const lote of LOTES) {
    const existingId = existing.get(lote.numeroLocal);
    if (existingId) toUpdate.push({ id: existingId, lote });
    else toCreate.push(lote);
  }

  console.log(`Plan: ${toCreate.length} to create, ${toUpdate.length} to update\n`);

  for (const { id, lote } of toUpdate) {
    await updateOne(id, lote);
  }
  const createdCount = await createBatch(toCreate);

  console.log(`\nDone. Created: ${createdCount}, Updated: ${toUpdate.length}.`);
  if (dryRun) console.log('(Dry run — no actual writes.)');
}

main().catch((err) => {
  console.error('\n❌ Seed failed:', err);
  process.exit(1);
});
