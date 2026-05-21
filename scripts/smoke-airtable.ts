/**
 * Smoke test: connect to the real Airtable base and exercise every
 * client method. Reports what was fetched and flags anything weird.
 *
 * Run: cd plaza-aria && npx tsx scripts/smoke-airtable.ts
 *
 * Requires .env.local with AIRTABLE_API_KEY and AIRTABLE_BASE_ID.
 */
import { config } from 'dotenv';
import { resolve } from 'path';

config({ path: resolve(__dirname, '../.env.local') });

import {
  listLocales,
  getLocalBySlug,
  listEventos,
  getConfig,
} from '../src/lib/airtable/client';

function header(s: string) {
  console.log('\n' + '='.repeat(60) + '\n' + s + '\n' + '='.repeat(60));
}

async function main() {
  header('1. listLocales()');
  const locales = await listLocales();
  console.log(`Fetched ${locales.length} locales.`);
  for (const l of locales) {
    console.log(`  • [${l.estado}] ${l.nombre} (piso ${l.piso}, ${l.numeroLocal}) — slug: ${l.slug}, giro: ${l.giro}`);
    if (l.estado === 'Disponible') {
      console.log(`    leasing: m² ${l.leasing?.m2}, renta ${l.leasing?.renta}, instalaciones ${l.leasing?.instalaciones.join('/')}`);
    }
    const horariosCount = Object.values(l.horarios).filter(h => h.open !== null).length;
    console.log(`    horarios: ${horariosCount}/7 días abiertos`);
  }

  if (locales.length === 0) {
    console.log('⚠️  No locales found. Add at least one Locales record in Airtable.');
  }

  header('2. getLocalBySlug(<first slug>)');
  if (locales.length > 0) {
    const slug = locales[0].slug;
    const found = await getLocalBySlug(slug);
    console.log(`Looking up slug="${slug}": ${found ? 'FOUND ✓' : 'NOT FOUND ✗'}`);
    const missing = await getLocalBySlug('this-slug-does-not-exist-zzz');
    console.log(`Looking up bogus slug: ${missing === null ? 'null ✓' : 'unexpected result ✗'}`);
  }

  header('3. listEventos()');
  const eventos = await listEventos();
  console.log(`Fetched ${eventos.length} eventos.`);
  for (const e of eventos) {
    console.log(`  • [${e.tipo}] ${e.titulo} en ${e.localNombre} — ${e.fecha} ${e.horaInicio}-${e.horaFin}`);
  }

  header('4. getConfig()');
  const cfg = await getConfig();
  if (!cfg) {
    console.log('⚠️  No Config record found. Add one Config record in Airtable.');
  } else {
    console.log('Config fetched:');
    console.log(`  heroVideoURL: ${cfg.heroVideoURL || '(empty)'}`);
    console.log(`  fotosGenerales: ${cfg.fotosGenerales.length} fotos`);
    console.log(`  gapsGiros: [${cfg.gapsGiros.join(', ')}]`);
    console.log(`  aforoEstimado: ${cfg.aforoEstimado ?? '(empty)'}`);
    console.log(`  cajonesEstacionamiento: ${cfg.cajonesEstacionamiento ?? '(empty)'}`);
    console.log(`  demografia.fuente: ${cfg.demografia?.fuente ?? '(empty)'}`);
    console.log(`  demografia.ingresoPromedioMXN: ${cfg.demografia?.ingresoPromedioMXN ?? '(empty)'}`);
  }

  header('SMOKE TEST PASSED');
}

main().catch((err) => {
  console.error('\n❌ SMOKE TEST FAILED:\n', err);
  process.exit(1);
});
