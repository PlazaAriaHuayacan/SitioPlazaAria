import Link from 'next/link';
import type { Evento, TipoEvento } from '@/types/domain';

const TIPO_COLORS: Record<TipoEvento, string> = {
  Fitness: '#4A5D3A',
  Educación: '#B85A3C',
  Lifestyle: '#C99857',
  Evento: '#5A544A',
};

function formatFechaHora(fecha: string, horaInicio: string): string {
  try {
    const [year, month, day] = fecha.split('-').map(Number);
    const date = new Date(year, month - 1, day);
    const diaSemana = date.toLocaleDateString('es-MX', { weekday: 'short' });
    const diaMes = date.toLocaleDateString('es-MX', { day: 'numeric', month: 'short' });
    return `${diaSemana} ${diaMes} · ${horaInicio}`;
  } catch {
    return `${fecha} · ${horaInicio}`;
  }
}

interface SemanaTeaserProps {
  eventos: Evento[];
}

export function SemanaTeaser({ eventos }: SemanaTeaserProps) {
  if (eventos.length === 0) return null;

  const shown = eventos.slice(0, 3);

  return (
    <section aria-label="Esta semana en Aria" className="container-aria pb-16 md:pb-24">
      <div className="mb-8">
        <p className="eyebrow mb-2">Esta semana en Aria</p>
        <h2 className="font-display text-2xl md:text-3xl text-aria-ink">
          Clases y eventos
        </h2>
      </div>

      <div className="grid md:grid-cols-3 gap-4">
        {shown.map((evento) => (
          <article
            key={evento.id}
            className="card p-5 flex flex-col gap-3"
          >
            <div className="flex items-center gap-2">
              <span
                className="inline-block h-2 w-2 rounded-full flex-shrink-0"
                style={{ backgroundColor: TIPO_COLORS[evento.tipo] ?? '#5A544A' }}
                aria-hidden="true"
              />
              <span className="text-xs font-medium uppercase tracking-[0.12em] text-aria-slate">
                {evento.tipo}
              </span>
            </div>

            <p className="text-xs text-aria-slate/70">
              {formatFechaHora(evento.fecha, evento.horaInicio)}
            </p>

            <h3 className="font-display text-lg leading-snug text-aria-ink">
              {evento.titulo}
            </h3>

            <p className="text-sm text-aria-slate">
              en {evento.localNombre}
            </p>
          </article>
        ))}
      </div>

      <div className="mt-6">
        <Link
          href="/agenda"
          className="inline-flex items-center gap-2 text-aria-terracotta font-medium hover:text-aria-terracottaDark transition-colors"
        >
          Ver agenda completa
          <span aria-hidden="true">→</span>
        </Link>
      </div>
    </section>
  );
}
