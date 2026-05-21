import { listLocales, listEventos, getConfig } from '@/lib/airtable/client';
import { estadoAhora } from '@/lib/horarios';
import { Hero } from '@/components/home/Hero';
import { LatidoStrip } from '@/components/home/LatidoStrip';
import { DosPuertas } from '@/components/home/DosPuertas';
import { SemanaTeaser } from '@/components/home/SemanaTeaser';
import { GapsTags } from '@/components/home/GapsTags';
import type { Local, Evento, Config } from '@/types/domain';

export const revalidate = 300;

export default async function HomePage() {
  // Fetch all data concurrently; silence errors so the page never crashes.
  let locales: Local[] = [];
  let eventos: Evento[] = [];
  let config: Config | null = null;

  try {
    [locales, eventos, config] = await Promise.all([
      listLocales(),
      listEventos(),
      getConfig(),
    ]);
  } catch (err) {
    console.error('[HomePage] Airtable fetch failed:', err);
  }

  // ── Derived counts ──────────────────────────────────────────────────────────
  const now = new Date();

  const abiertosCnt = locales.filter((l) => {
    const est = estadoAhora(l.horarios, now);
    return est.estado === 'abierto' || est.estado === 'cierra-pronto';
  }).length;

  const sevenDaysLater = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000);
  const eventosEstaSemana = eventos.filter((e) => {
    try {
      const fecha = new Date(e.fecha + 'T00:00:00');
      return fecha >= now && fecha <= sevenDaysLater;
    } catch {
      return false;
    }
  });
  const clasesCnt = eventosEstaSemana.length;

  const disponibles = locales.filter((l) => l.estado === 'Disponible');
  const disponiblesCnt = disponibles.length;

  const minRenta =
    disponibles.length > 0
      ? Math.min(...disponibles.map((l) => l.leasing?.renta ?? Infinity).filter(isFinite))
      : undefined;

  // ── Render ──────────────────────────────────────────────────────────────────
  const heroFoto = config?.fotosGenerales?.[0];

  return (
    <>
      <Hero heroFoto={heroFoto} />

      <LatidoStrip
        abiertosCnt={abiertosCnt}
        clasesCnt={clasesCnt}
        disponiblesCnt={disponiblesCnt}
      />

      <DosPuertas
        totalLocales={locales.length}
        disponiblesCnt={disponiblesCnt}
        minRenta={minRenta !== Infinity ? minRenta : undefined}
        clasesCnt={clasesCnt}
      />

      <SemanaTeaser eventos={eventosEstaSemana} />

      <GapsTags gaps={config?.gapsGiros ?? []} />
    </>
  );
}
