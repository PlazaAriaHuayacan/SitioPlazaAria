'use client';

import { useMemo, useState } from 'react';
import Link from 'next/link';
import { groupEventos } from '@/lib/agenda/group';
import { EventoCard } from './EventoCard';
import type { Evento, TipoEvento } from '@/types/domain';

const TIPOS: TipoEvento[] = ['Fitness', 'Educación', 'Lifestyle', 'Evento'];

const DAY_FORMAT = new Intl.DateTimeFormat('es-MX', {
  timeZone: 'America/Cancun',
  weekday: 'long',
  day: 'numeric',
  month: 'long',
});

function formatDayLabel(dateISO: string) {
  // dateISO is YYYY-MM-DD in plaza time. Build a Date at noon plaza to avoid TZ edge cases.
  const [y, m, d] = dateISO.split('-').map(Number);
  // Noon plaza = 17:00 UTC
  const dt = new Date(Date.UTC(y, m - 1, d, 17, 0));
  const raw = DAY_FORMAT.format(dt);
  // Capitalize first letter (Intl returns lowercase weekday in es-MX)
  return raw.charAt(0).toUpperCase() + raw.slice(1);
}

type Props = {
  eventos: Evento[];
  todayISO: string;
};

export function AgendaWithFilters({ eventos, todayISO }: Props) {
  const [tipos, setTipos] = useState<TipoEvento[]>([]);

  const grouped = useMemo(
    () => groupEventos({ eventos, todayISO, tipos }),
    [eventos, todayISO, tipos],
  );

  const toggle = (t: TipoEvento) =>
    setTipos((cur) => (cur.includes(t) ? cur.filter((x) => x !== t) : [...cur, t]));

  if (eventos.length === 0) {
    return (
      <div className="card flex flex-col items-center gap-3 p-12 text-center">
        <p className="font-display text-2xl text-aria-ink">
          Aún no hay clases ni eventos publicados
        </p>
        <p className="max-w-prose-aria text-sm text-aria-slate">
          Plaza Aria publica clases (spinning, baile, mate) y eventos especiales
          aquí. Mientras tanto, explora los negocios que ya están abiertos.
        </p>
        <Link href="/directorio" className="btn-primary mt-3">
          Ver directorio
        </Link>
      </div>
    );
  }

  return (
    <div className="space-y-10">
      <div className="flex flex-wrap items-center gap-2">
        <span className="eyebrow mr-2">Tipo</span>
        <Chip active={tipos.length === 0} onClick={() => setTipos([])}>Todos</Chip>
        {TIPOS.map((t) => (
          <Chip key={t} active={tipos.includes(t)} onClick={() => toggle(t)}>{t}</Chip>
        ))}
      </div>

      {grouped.length === 0 ? (
        <div className="card p-10 text-center">
          <p className="text-aria-slate">Sin eventos para esos filtros.</p>
        </div>
      ) : (
        grouped.map((day) => (
          <section key={day.dateISO} className="space-y-4">
            <h2 className="font-display text-2xl text-aria-ink tracking-display">
              {formatDayLabel(day.dateISO)}
            </h2>
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
              {day.eventos.map((e) => (
                <EventoCard key={e.id} evento={e} />
              ))}
            </div>
          </section>
        ))
      )}
    </div>
  );
}

function Chip({
  active, onClick, children,
}: { active: boolean; onClick: () => void; children: React.ReactNode }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={
        'rounded-pill border px-3 py-1 text-xs font-medium transition ' +
        (active
          ? 'border-aria-ink bg-aria-ink text-aria-bone'
          : 'border-aria-line bg-white text-aria-ink hover:border-aria-ink/40')
      }
    >
      {children}
    </button>
  );
}
