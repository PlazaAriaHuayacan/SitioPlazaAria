import type { DiaSemana, HorariosSemana } from '@/types/domain';

type Props = {
  horarios: HorariosSemana;
  /** Plaza-local reference moment (server-provided). */
  now: Date;
  className?: string;
};

const ORDER: { key: DiaSemana; label: string }[] = [
  { key: 'Lunes',     label: 'Lunes' },
  { key: 'Martes',    label: 'Martes' },
  { key: 'Miercoles', label: 'Miércoles' },
  { key: 'Jueves',    label: 'Jueves' },
  { key: 'Viernes',   label: 'Viernes' },
  { key: 'Sabado',    label: 'Sábado' },
  { key: 'Domingo',   label: 'Domingo' },
];

const EN_TO_ES: Record<string, DiaSemana> = {
  Monday: 'Lunes', Tuesday: 'Martes', Wednesday: 'Miercoles',
  Thursday: 'Jueves', Friday: 'Viernes', Saturday: 'Sabado',
  Sunday: 'Domingo',
};

function plazaWeekday(now: Date): DiaSemana {
  const en = new Intl.DateTimeFormat('en-US', {
    timeZone: 'America/Cancun',
    weekday: 'long',
  }).format(now);
  return EN_TO_ES[en] ?? 'Lunes';
}

export function HorariosTable({ horarios, now, className = '' }: Props) {
  const today = plazaWeekday(now);

  return (
    <dl
      className={`divide-y divide-aria-line/60 rounded-card border border-aria-line/60 bg-white ${className}`}
      aria-label="Horarios de la semana"
    >
      {ORDER.map(({ key, label }) => {
        const h = horarios[key];
        const isToday = key === today;
        const closed = !h.open || !h.close;

        return (
          <div
            key={key}
            className={
              'flex items-center justify-between px-4 py-3 text-sm ' +
              (isToday ? 'bg-aria-sand/50' : '')
            }
            aria-current={isToday ? 'date' : undefined}
          >
            <dt
              className={
                (isToday ? 'font-medium text-aria-ink' : 'text-aria-ink/80') +
                ' tracking-tight'
              }
            >
              {label}
              {isToday && (
                <span className="ml-2 align-middle text-[10px] uppercase tracking-[0.15em] text-aria-terracotta">
                  Hoy
                </span>
              )}
            </dt>
            <dd
              className={
                'tabular-nums ' +
                (closed ? 'text-aria-slate italic' : 'text-aria-ink')
              }
            >
              {closed ? 'Cerrado' : `${h.open} – ${h.close}`}
            </dd>
          </div>
        );
      })}
    </dl>
  );
}
