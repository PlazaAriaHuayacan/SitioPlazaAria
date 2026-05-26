import type { Metadata } from 'next';
import { listLocales, listEventos } from '@/lib/airtable/client';
import { IsometricoInteractivo } from '@/components/plano/IsometricoInteractivo';
import { LocalesDisponiblesGrid } from '@/components/plano/LocalesDisponiblesGrid';
import { agruparPorUnidad } from '@/lib/plano/agrupar';
import type { Local, Evento } from '@/types/domain';

export const revalidate = 300; // ISR every 5 min

export const metadata: Metadata = {
  title: 'Renta tu local',
  description:
    'Renta un local en Plaza Aria, sobre Av. Huayacán. Plaza vecinal en dos pisos con estacionamiento techado y comunidad activa. Consulta disponibilidad ahora.',
};

// WhatsApp del comercializador de Plaza Aria.
const WA_NUMBER = '5299983214614';
const WA_TEXT = encodeURIComponent('Hola, me interesa rentar un local en Plaza Aria.');
const WA_LINK = `https://wa.me/${WA_NUMBER}?text=${WA_TEXT}`;

const rentaFormatter = new Intl.NumberFormat('es-MX', {
  style: 'currency',
  currency: 'MXN',
  maximumFractionDigits: 0,
});

export default async function RentaPage() {
  let locales: Local[] = [];
  let eventos: Evento[] = [];

  try {
    [locales, eventos] = await Promise.all([listLocales(), listEventos()]);
  } catch (err) {
    console.error('[/renta] Airtable fetch failed:', err);
  }

  const now = new Date();

  const unidades = agruparPorUnidad(locales);

  const disponibles = unidades.filter((u) => u.estado === 'Disponible');
  const totalLocales = unidades.length;

  // Count negocios activos (non-disponible, non-proximamente)
  const negociosActivos = unidades.filter(
    (u) => u.estado !== 'Disponible' && u.estado !== 'Proximamente',
  ).length;

  const sevenDaysLater = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000);
  const eventosEstaSemana = eventos.filter((e) => {
    try {
      const fecha = new Date(e.fecha + 'T00:00:00');
      return fecha >= now && fecha <= sevenDaysLater;
    } catch {
      return false;
    }
  });

  // Minimum renta among disponibles (rounded to nearest 1000)
  const rawMin =
    disponibles.length > 0
      ? Math.min(
          ...disponibles
            .map((u) => u.registroPrincipal.leasing?.renta ?? Infinity)
            .filter(isFinite),
        )
      : undefined;
  const minRenta =
    rawMin !== undefined && isFinite(rawMin)
      ? Math.round(rawMin / 1000) * 1000
      : undefined;

  return (
    <>
      {/* ── 1. Hero ────────────────────────────────────────────────────────── */}
      <section className="pt-16 pb-20 bg-aria-bone">
        <div className="container-aria">
          <div className="flex flex-col gap-12 lg:flex-row lg:items-start lg:gap-16">
            {/* Left: copy + CTAs */}
            <div className="flex-1 max-w-xl">
              <p className="eyebrow">Locales disponibles · Plaza Aria</p>
              <h1 className="mt-3 font-display text-5xl leading-tight text-aria-ink">
                Bienvenida tu marca a Aria.
              </h1>
              <p className="mt-4 text-lg text-aria-slate leading-relaxed">
                Plaza vecinal sobre Av.&nbsp;Huayacán: dos pisos al aire libre,
                estacionamiento techado, comunidad ya viva. Locales listos para
                tu concepto.
              </p>
              <div className="mt-8 flex flex-wrap gap-3">
                <a
                  href={WA_LINK}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="btn-primary"
                >
                  Escribir por WhatsApp
                </a>
                <a href="#disponibles" className="btn-secondary">
                  Ver disponibles abajo
                </a>
              </div>
            </div>

            {/* Right: stats trio */}
            <aside className="card px-6 py-7 flex flex-col gap-6 lg:w-72 shrink-0">
              <StatItem
                value={String(disponibles.length)}
                label="locales disponibles"
                highlight
              />
              <div className="border-t border-aria-sand" />
              <StatItem
                value={
                  minRenta !== undefined
                    ? `${rentaFormatter.format(minRenta)}`
                    : '—'
                }
                label="renta mensual desde"
              />
              <div className="border-t border-aria-sand" />
              <StatItem
                value={String(negociosActivos > 0 ? negociosActivos : totalLocales)}
                label="negocios activos"
              />
            </aside>
          </div>
        </div>
      </section>

      {/* ── 2. ¿Por qué Aria? ─────────────────────────────────────────────── */}
      <section className="py-16 bg-white">
        <div className="container-aria">
          <p className="eyebrow">¿Por qué Aria?</p>
          <h2 className="mt-2 font-display text-3xl text-aria-ink">
            La plaza que ya tiene vecinos
          </h2>

          <div className="mt-8 grid gap-5 sm:grid-cols-3">
            <ReasonCard
              title="Zona residencial"
              body="Av. Huayacán, rodeada de fraccionamientos con alto poder adquisitivo. Tu cliente ya vive a menos de 5 minutos."
            />
            <ReasonCard
              title="Plaza activa"
              body={`${negociosActivos > 0 ? negociosActivos : totalLocales} negocios operando${eventosEstaSemana.length > 0 ? ` · ${eventosEstaSemana.length} ${eventosEstaSemana.length === 1 ? 'evento' : 'eventos'} esta semana` : ''}. Aria ya genera tráfico, tú llegas a una comunidad existente.`}
            />
            <ReasonCard
              title="Tráfico cautivo"
              body="Restaurantes, gimnasio y escuelas generan flujo recurrente. Locales de uso mixto con sinergia entre giros."
            />
          </div>

          <p className="mt-6 text-xs italic text-aria-slate/70">
            Dashboard de oportunidad con demografía detallada disponible
            próximamente — pídelo por{' '}
            <a href={WA_LINK} target="_blank" rel="noopener noreferrer" className="underline">
              WhatsApp
            </a>
            .
          </p>
        </div>
      </section>

      {/* ── 3. Plano (decorativo) ─────────────────────────────────────────── */}
      <section className="py-16 bg-aria-bone">
        <div className="container-aria">
          <p className="eyebrow">El plano</p>
          <h2 className="mt-2 font-display tracking-display text-4xl md:text-5xl text-aria-ink">
            Plaza Aria desde arriba
          </h2>
          <p className="mt-3 max-w-prose-aria text-aria-slate">
            Dos pisos al aire libre con estacionamiento techado y comunidad ya
            viva sobre Av. Huayacán. Abajo, los locales disponibles.
          </p>
          <div className="mt-10 rounded-card border border-aria-line/60 bg-white p-3 md:p-6 shadow-card">
            <IsometricoInteractivo />
          </div>
        </div>
      </section>

      {/* ── 4. Locales disponibles (tarjetas) ─────────────────────────────── */}
      <section id="disponibles" className="py-16 bg-white scroll-mt-20">
        <div className="container-aria">
          <p className="eyebrow">Locales disponibles</p>
          <h2 className="mt-2 font-display tracking-display text-4xl md:text-5xl text-aria-ink">
            {disponibles.length === 0
              ? 'Todos rentados — escríbenos para reservar'
              : `${disponibles.length} ${disponibles.length === 1 ? 'local' : 'locales'} listos para tu concepto`}
          </h2>
          <p className="mt-3 max-w-prose-aria text-aria-slate">
            Renta mensual, m² y servicios incluidos por local. Cuando uno te
            interese, escríbenos por WhatsApp y te mandamos el contrato y los
            siguientes pasos.
          </p>
          <div className="mt-10">
            <LocalesDisponiblesGrid unidades={unidades} />
          </div>
        </div>
      </section>

      {/* ── 4. CTA final ──────────────────────────────────────────────────── */}
      <section className="py-20 bg-aria-ink">
        <div className="container-aria text-center max-w-2xl mx-auto">
          <h2 className="font-display text-4xl text-aria-bone">
            ¿Tu marca encaja en Aria?
          </h2>
          <p className="mt-4 text-aria-bone/80 text-lg">
            Escríbenos por WhatsApp y te mandamos la información detallada del
            local que te interesa.
          </p>
          <a
            href={WA_LINK}
            target="_blank"
            rel="noopener noreferrer"
            className="mt-8 inline-flex items-center justify-center rounded-pill bg-aria-terracotta px-8 py-4 text-base font-medium text-aria-bone shadow-sm transition hover:bg-aria-terracottaDark active:translate-y-px"
          >
            Escribir por WhatsApp
          </a>
        </div>
      </section>

      {/* ── 5. Próximamente strip ─────────────────────────────────────────── */}
      <div className="py-4 bg-aria-sand text-center">
        <p className="text-xs italic text-aria-slate">
          Próximamente — dashboard &ldquo;¿Por qué Aria?&rdquo; con demografía
          detallada y formulario por local con envío automático de propuesta.
        </p>
      </div>
    </>
  );
}

// ─── Sub-components ────────────────────────────────────────────────────────────

function StatItem({
  value,
  label,
  highlight = false,
}: {
  value: string;
  label: string;
  highlight?: boolean;
}) {
  return (
    <div className="flex flex-col gap-0.5">
      <span
        className={`font-display text-3xl leading-none ${highlight ? 'text-aria-terracotta' : 'text-aria-ink'}`}
      >
        {value}
      </span>
      <span className="text-xs text-aria-slate uppercase tracking-wide">
        {label}
      </span>
    </div>
  );
}

function ReasonCard({ title, body }: { title: string; body: string }) {
  return (
    <div className="card px-5 py-6 flex flex-col gap-2">
      <p className="font-display text-lg text-aria-ink">{title}</p>
      <p className="text-sm text-aria-slate leading-relaxed">{body}</p>
    </div>
  );
}
