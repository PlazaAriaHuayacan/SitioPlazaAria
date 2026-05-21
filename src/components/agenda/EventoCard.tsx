import Link from 'next/link';
import type { Evento, TipoEvento } from '@/types/domain';

const tipoColor: Record<TipoEvento, string> = {
  Fitness: 'bg-aria-olive',
  Educación: 'bg-aria-gold',
  Lifestyle: 'bg-aria-terracotta',
  Evento: 'bg-aria-ink',
};

type Props = { evento: Evento };

export function EventoCard({ evento: e }: Props) {
  return (
    <article className="card flex flex-col gap-3 p-5">
      <div className="flex items-center gap-3 text-xs">
        <span
          aria-hidden
          className={`h-2 w-2 rounded-full ${tipoColor[e.tipo]}`}
        />
        <span className="uppercase tracking-[0.15em] text-aria-slate">
          {e.tipo}
        </span>
        {e.recurrencia !== 'Único' && (
          <span className="rounded-pill border border-aria-line px-2 py-0.5 text-[10px] uppercase tracking-wider text-aria-slate">
            {e.recurrencia === 'Semanal' ? 'Cada semana' : 'Cada mes'}
          </span>
        )}
      </div>

      <h3 className="font-display text-xl text-aria-ink leading-tight">
        {e.titulo}
      </h3>

      <p className="text-sm text-aria-slate">
        <span className="tabular-nums">{e.horaInicio} – {e.horaFin}</span>
        <span className="mx-2 text-aria-line">·</span>
        en{' '}
        <Link
          href={`/directorio/${e.localSlug}`}
          className="text-aria-ink underline-offset-4 hover:underline"
        >
          {e.localNombre}
        </Link>
      </p>

      {e.descripcion && (
        <p className="text-sm text-aria-slate max-w-prose-aria">
          {e.descripcion}
        </p>
      )}

      <div className="mt-1 flex items-center gap-4">
        {e.cupo !== undefined && (
          <span className="text-xs text-aria-slate">Cupo: {e.cupo}</span>
        )}
        {e.linkReservar && (
          <a
            href={e.linkReservar}
            target="_blank"
            rel="noreferrer noopener"
            className="btn-ghost"
          >
            Reservar →
          </a>
        )}
      </div>
    </article>
  );
}
