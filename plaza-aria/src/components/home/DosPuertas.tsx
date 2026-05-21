import Link from 'next/link';

const mxnFmt = new Intl.NumberFormat('es-MX', {
  style: 'currency',
  currency: 'MXN',
  maximumFractionDigits: 0,
});

function roundToThousand(n: number): number {
  return Math.round(n / 1000) * 1000;
}

interface DosPuertasProps {
  totalLocales: number;
  disponiblesCnt: number;
  minRenta?: number; // undefined if no Disponibles or no renta data
  clasesCnt: number;
}

export function DosPuertas({ totalLocales, disponiblesCnt, minRenta, clasesCnt }: DosPuertasProps) {
  const rentaLabel =
    minRenta != null && minRenta > 0
      ? `Desde ${mxnFmt.format(roundToThousand(minRenta))}/mes`
      : 'Pregunta por renta';

  return (
    <section aria-label="Dos puertas" className="container-aria py-16 md:py-24">
      <div className="grid md:grid-cols-2 gap-6 md:gap-10">
        {/* Card 1: Renta — dark */}
        <article className="rounded-card bg-aria-ink text-aria-bone p-8 md:p-10 flex flex-col justify-between gap-8">
          <div>
            <p className="eyebrow mb-4 text-aria-bone/50">Para marcas y emprendedores</p>
            <h2 className="font-display text-3xl md:text-4xl mb-4 leading-tight">
              Espacio disponible
            </h2>
            <p className="text-aria-bone/70 leading-relaxed text-base max-w-prose">
              Locales en Plaza Aria: planta baja y alta, frente a Huayacán,
              estacionamiento techado. Bienvenida tu marca.
            </p>
          </div>

          <div>
            <div className="flex flex-wrap gap-x-6 gap-y-2 mb-6 text-sm">
              {disponiblesCnt > 0 && (
                <span className="flex items-center gap-2 text-aria-gold font-medium">
                  <span className="inline-block h-1.5 w-1.5 rounded-full bg-aria-gold" />
                  {disponiblesCnt} {disponiblesCnt === 1 ? 'local disponible' : 'locales disponibles'}
                </span>
              )}
              <span className="text-aria-bone/60">{rentaLabel}</span>
            </div>

            <Link
              href="/renta"
              className="inline-flex items-center gap-2 text-aria-terracotta font-medium hover:text-aria-bone transition-colors"
            >
              Ver locales disponibles
              <span aria-hidden="true">→</span>
            </Link>
          </div>
        </article>

        {/* Card 2: Explora — light */}
        <article className="rounded-card bg-aria-sand p-8 md:p-10 flex flex-col justify-between gap-8">
          <div>
            <p className="eyebrow mb-4">Para vecinos y visitantes</p>
            <h2 className="font-display text-3xl md:text-4xl mb-4 leading-tight text-aria-ink">
              Vive Aria todos los días
            </h2>
            <p className="text-aria-slate leading-relaxed text-base max-w-prose">
              Restaurantes, tiendas, clases, servicios.
              Lo que necesitas, a un paso de casa.
            </p>
          </div>

          <div>
            <div className="flex flex-wrap gap-x-6 gap-y-2 mb-6 text-sm">
              {totalLocales > 0 && (
                <span className="text-aria-slate font-medium">
                  {totalLocales} {totalLocales === 1 ? 'negocio' : 'negocios'}
                </span>
              )}
              {clasesCnt > 0 && (
                <span className="text-aria-slate font-medium">
                  {clasesCnt} {clasesCnt === 1 ? 'clase y evento' : 'clases y eventos'} esta semana
                </span>
              )}
            </div>

            <Link
              href="/directorio"
              className="inline-flex items-center gap-2 text-aria-terracotta font-medium hover:text-aria-terracottaDark transition-colors"
            >
              Ver directorio
              <span aria-hidden="true">→</span>
            </Link>
          </div>
        </article>
      </div>
    </section>
  );
}
