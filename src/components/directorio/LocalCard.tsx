import Link from 'next/link';
import Image from 'next/image';
import type { Local, Giro } from '@/types/domain';
import { AbiertoBadge } from './AbiertoBadge';

type Props = {
  local: Local;
  now: Date;
};

const giroBackground: Record<Giro, string> = {
  Restaurante: 'bg-aria-rose/15',
  Belleza: 'bg-aria-rose/15',
  Fitness: 'bg-aria-olive/15',
  'Educación': 'bg-aria-gold/15',
  Hogar: 'bg-aria-sand',
  Servicios: 'bg-aria-sand',
  Otro: 'bg-aria-sand',
};

const rentaFormatter = new Intl.NumberFormat('es-MX', {
  style: 'currency',
  currency: 'MXN',
  maximumFractionDigits: 0,
});

export function LocalCard({ local, now }: Props) {
  const {
    slug,
    nombre,
    giro,
    estado,
    piso,
    numeroLocal,
    fotos,
    logo,
    horarios,
    leasing,
  } = local;

  const isDisponible = estado === 'Disponible';
  const isProximamente = estado === 'Proximamente';

  const giroBg = giroBackground[giro] ?? 'bg-aria-sand';
  const firstLetter = nombre.charAt(0).toUpperCase();

  const wrapperMuted = isProximamente ? 'opacity-70 saturate-[0.75]' : '';

  return (
    <Link
      href={`/directorio/${slug}`}
      aria-label={`${nombre} — ${giro}, Piso ${piso} Local ${numeroLocal}`}
      className={`card block group ${wrapperMuted} group-hover:translate-y-[-2px] transition-transform overflow-hidden`}
    >
      {/* ── Media zone ── */}
      <div className="relative aspect-[4/3] overflow-hidden">
        {logo?.url ? (
          /* Logo on tinted swatch */
          <div className={`absolute inset-0 ${giroBg} flex items-center justify-center p-6`}>
            <Image
              src={logo.url}
              alt={logo.alt ?? `Logo de ${nombre}`}
              width={logo.width ?? 160}
              height={logo.height ?? 80}
              className="max-h-20 w-auto object-contain"
            />
          </div>
        ) : fotos[0] ? (
          /* Cover photo */
          <Image
            src={fotos[0].url}
            alt={fotos[0].alt ?? nombre}
            fill
            className="object-cover transition-transform duration-500 group-hover:scale-105"
            sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
          />
        ) : (
          /* Letter placeholder */
          <div
            className={`absolute inset-0 ${giroBg} flex items-center justify-center`}
            aria-hidden
          >
            <span className="font-display text-5xl text-aria-slate/40 select-none">
              {firstLetter}
            </span>
          </div>
        )}

        {/* Disponible pill — top-right of media zone */}
        {isDisponible && (
          <span className="absolute top-3 right-3 inline-flex items-center rounded-pill bg-aria-terracotta px-2.5 py-1 text-[11px] font-medium text-white tracking-wide">
            DISPONIBLE
          </span>
        )}

        {/* Proximamente pill — top-right of media zone */}
        {isProximamente && (
          <span className="absolute top-3 right-3 inline-flex items-center rounded-pill bg-aria-slate px-2.5 py-1 text-[11px] font-medium text-white tracking-wide">
            Próximamente
          </span>
        )}
      </div>

      {/* ── Info zone ── */}
      <div className="p-4 flex flex-col gap-1.5">
        {/* Name */}
        <p className="font-display text-lg leading-snug text-aria-ink line-clamp-1">
          {nombre}
        </p>

        {/* Giro + location */}
        <p className="text-xs text-aria-slate">
          {giro} · Piso {piso} · {numeroLocal}
        </p>

        {/* Open/close badge or renta line */}
        {isDisponible ? (
          <p className="text-xs font-medium text-aria-terracotta mt-0.5">
            Disponible
            {leasing?.renta != null
              ? ` · Desde ${rentaFormatter.format(leasing.renta)} MXN/mes`
              : ''}
          </p>
        ) : (
          <AbiertoBadge horarios={horarios} now={now} size="sm" className="mt-0.5 self-start" />
        )}
      </div>
    </Link>
  );
}
