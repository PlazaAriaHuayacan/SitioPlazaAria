import type { Evento, TipoEvento } from '@/types/domain';

/**
 * Given a list of eventos and a "today" anchored in plaza-local time,
 * filter out past events and group remaining by ISO date (YYYY-MM-DD).
 * Returns an ordered array of [dateISO, eventos[]] tuples.
 */
export interface GroupedDay {
  dateISO: string; // YYYY-MM-DD in plaza time
  eventos: Evento[];
}

export interface GroupParams {
  eventos: Evento[];
  todayISO: string; // YYYY-MM-DD (plaza tz)
  tipos: TipoEvento[]; // empty = all
}

export function groupEventos({ eventos, todayISO, tipos }: GroupParams): GroupedDay[] {
  const byDate = new Map<string, Evento[]>();

  for (const e of eventos) {
    if (!e.fecha) continue;
    if (e.fecha < todayISO) continue;
    if (tipos.length > 0 && !tipos.includes(e.tipo)) continue;

    if (!byDate.has(e.fecha)) byDate.set(e.fecha, []);
    byDate.get(e.fecha)!.push(e);
  }

  const days: GroupedDay[] = Array.from(byDate.entries())
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([dateISO, list]) => ({
      dateISO,
      eventos: list.sort((a, b) => a.horaInicio.localeCompare(b.horaInicio)),
    }));

  return days;
}

/** Plaza-local YYYY-MM-DD for a given Date. */
export function plazaDateISO(now: Date): string {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'America/Cancun',
    year: 'numeric', month: '2-digit', day: '2-digit',
  }).format(now);
  return parts; // en-CA formats as YYYY-MM-DD
}
