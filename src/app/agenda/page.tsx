import type { Metadata } from 'next';
import { listEventos } from '@/lib/airtable/client';
import { plazaDateISO } from '@/lib/agenda/group';
import { AgendaWithFilters } from '@/components/agenda/AgendaWithFilters';

export const revalidate = 300;

export const metadata: Metadata = {
  title: 'Agenda',
  description:
    'Clases, eventos y novedades en Plaza Aria. Spinning, baile, mate, y mucho más sobre Av. Huayacán.',
};

export default async function AgendaPage() {
  let eventos: Awaited<ReturnType<typeof listEventos>> = [];
  try {
    eventos = await listEventos();
  } catch (err) {
    console.error('[/agenda] failed to load eventos:', err);
  }

  const todayISO = plazaDateISO(new Date());

  return (
    <div className="container-aria py-12 md:py-16">
      <header className="mb-10 md:mb-14">
        <p className="eyebrow">Plaza Aria</p>
        <h1 className="mt-2 font-display tracking-display text-4xl md:text-5xl text-aria-ink">
          Agenda
        </h1>
        <p className="mt-3 max-w-prose-aria text-aria-slate">
          Clases, eventos y novedades en Plaza Aria. De spinning a clases de matemáticas,
          esto es lo que está pasando esta semana.
        </p>
      </header>

      <AgendaWithFilters eventos={eventos} todayISO={todayISO} />
    </div>
  );
}
