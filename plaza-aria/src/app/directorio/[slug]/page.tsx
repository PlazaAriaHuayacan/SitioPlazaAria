import type { Metadata } from 'next';
import Image from 'next/image';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { getLocalBySlug, listLocales } from '@/lib/airtable/client';
import { AbiertoBadge } from '@/components/directorio/AbiertoBadge';
import { HorariosTable } from '@/components/directorio/HorariosTable';
import { InstagramEmbed } from '@/components/directorio/InstagramEmbed';
import { LocalCard } from '@/components/directorio/LocalCard';
import type { Giro, Local } from '@/types/domain';

export const revalidate = 300;

type PageProps = { params: { slug: string } };

// ── Metadata ──────────────────────────────────────────────────────────────────

export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  try {
    const l = await getLocalBySlug(params.slug);
    if (!l) return { title: 'No encontrado' };
    return {
      title: `${l.nombre} · ${l.giro} · Plaza Aria`,
      description: l.descripcion || 'Negocio en Plaza Aria.',
    };
  } catch (err) {
    console.error('[/directorio/[slug]] metadata fetch failed:', err);
    return { title: 'Plaza Aria' };
  }
}

// ── Static params ─────────────────────────────────────────────────────────────

export async function generateStaticParams() {
  try {
    const locales = await listLocales();
    return locales
      .filter((l) => l.slug.length > 0)
      .map((l) => ({ slug: l.slug }));
  } catch {
    return [];
  }
}

// ── Giro accent colour helpers ────────────────────────────────────────────────

const giroBackground: Record<Giro, string> = {
  Restaurante: 'bg-aria-rose/15',
  Belleza: 'bg-aria-rose/15',
  Fitness: 'bg-aria-olive/15',
  'Educación': 'bg-aria-gold/15',
  Hogar: 'bg-aria-sand',
  Servicios: 'bg-aria-sand',
  Otro: 'bg-aria-sand',
};

// ── WhatsApp URL helper ───────────────────────────────────────────────────────

function waLink(phone: string): string {
  return `https://wa.me/${phone.replace(/[^0-9]/g, '')}`;
}

// ── Vecinos section ───────────────────────────────────────────────────────────

function VecinosSection({ current, all, now }: { current: Local; all: Local[]; now: Date }) {
  const vecinos = all
    .filter((l) => l.id !== current.id && l.piso === current.piso)
    .slice(0, 4);

  if (vecinos.length === 0) return null;

  return (
    <section className="mt-20">
      <p className="eyebrow">Vecinos en Aria</p>
      <h2 className="mt-2 font-display text-2xl text-aria-ink">
        También en Piso {current.piso}
      </h2>
      <div className="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        {vecinos.map((v) => (
          <LocalCard key={v.id} local={v} now={now} />
        ))}
      </div>
    </section>
  );
}

// ── Page ──────────────────────────────────────────────────────────────────────

export default async function FichaPage({ params }: PageProps) {
  const [l, allLocales] = await Promise.all([
    getLocalBySlug(params.slug).catch((err) => {
      console.error('[/directorio/[slug]] getLocalBySlug failed:', err);
      return null;
    }),
    listLocales().catch(() => [] as Local[]),
  ]);

  if (!l) notFound();

  const now = new Date();
  const giroBg = giroBackground[l.giro] ?? 'bg-aria-sand';
  const firstLetter = l.nombre.charAt(0).toUpperCase();
  const heroFoto = l.fotos[0] ?? null;
  const extraFotos = l.fotos.slice(1);
  const isDisponible = l.estado === 'Disponible';

  return (
    <div className="container-aria pb-20 pt-8">
      {/* ── Breadcrumb ──────────────────────────────────────────────────── */}
      <nav aria-label="Navegación" className="mb-8">
        <Link
          href="/directorio"
          className="inline-flex items-center gap-1.5 text-sm text-aria-slate transition hover:text-aria-ink"
        >
          <svg
            aria-hidden
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
          >
            <path d="M19 12H5M9 6l-6 6 6 6" />
          </svg>
          Directorio
        </Link>
      </nav>

      {/* ── Hero header ─────────────────────────────────────────────────── */}
      <div className="grid gap-10 md:grid-cols-2 md:gap-16 lg:gap-20">
        {/* Left: gallery */}
        <div className="relative aspect-[4/5] rounded-card overflow-hidden bg-aria-sand">
          {heroFoto ? (
            <Image
              src={heroFoto.url}
              alt={heroFoto.alt ?? l.nombre}
              fill
              priority
              className="object-cover"
              sizes="(max-width: 768px) 100vw, 50vw"
            />
          ) : l.logo?.url ? (
            <div className={`absolute inset-0 ${giroBg} flex items-center justify-center p-10`}>
              <Image
                src={l.logo.url}
                alt={l.logo.alt ?? `Logo de ${l.nombre}`}
                width={l.logo.width ?? 200}
                height={l.logo.height ?? 100}
                className="max-h-32 w-auto object-contain"
                priority
              />
            </div>
          ) : (
            <div
              className={`absolute inset-0 ${giroBg} flex items-center justify-center`}
              aria-hidden
            >
              <span className="font-display text-8xl text-aria-slate/30 select-none">
                {firstLetter}
              </span>
            </div>
          )}
        </div>

        {/* Right: heading block */}
        <div className="flex flex-col justify-center">
          <p className="eyebrow">
            {l.giro} · Piso {l.piso}, Local {l.numeroLocal}
          </p>

          <h1 className="mt-3 font-display text-5xl leading-tight text-aria-ink">
            {l.nombre}
          </h1>

          {!isDisponible && (
            <div className="mt-4">
              <AbiertoBadge horarios={l.horarios} now={now} size="md" />
            </div>
          )}

          {l.descripcion && (
            <p className="mt-5 max-w-prose-aria text-aria-slate leading-relaxed">
              {l.descripcion}
            </p>
          )}

          {/* Action row */}
          <div className="mt-8 flex flex-wrap gap-3">
            {l.whatsapp && (
              <a
                href={waLink(l.whatsapp)}
                target="_blank"
                rel="noreferrer noopener"
                className="btn-primary"
              >
                WhatsApp
              </a>
            )}
            {l.telefono && (
              <a href={`tel:${l.telefono}`} className="btn-secondary">
                Llamar
              </a>
            )}
            {l.menuPDF && (
              <a
                href={l.menuPDF}
                target="_blank"
                rel="noreferrer noopener"
                className="btn-secondary"
              >
                Menú
              </a>
            )}
            {l.linkReservar && (
              <a
                href={l.linkReservar}
                target="_blank"
                rel="noreferrer noopener"
                className="btn-secondary"
              >
                Reservar
              </a>
            )}
          </div>
        </div>
      </div>

      {/* ── Two-column body ──────────────────────────────────────────────── */}
      <div className="mt-16 grid gap-12 md:grid-cols-3">
        {/* Left: horarios + foto grid */}
        <div className="md:col-span-2">
          <p className="eyebrow mb-4">Horarios</p>
          <HorariosTable horarios={l.horarios} now={now} />

          {extraFotos.length > 0 && (
            <div className="mt-10 grid grid-cols-2 gap-4">
              {extraFotos.map((foto, i) => (
                <div
                  key={foto.url}
                  className="relative aspect-[4/3] overflow-hidden rounded-card bg-aria-sand"
                >
                  <Image
                    src={foto.url}
                    alt={foto.alt ?? `${l.nombre} foto ${i + 2}`}
                    fill
                    className="object-cover"
                    sizes="(max-width: 768px) 50vw, 33vw"
                  />
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Right: aside */}
        <aside className="flex flex-col gap-6 md:col-span-1">
          <div>
            <p className="eyebrow mb-2">Ubicación</p>
            <p className="font-medium text-aria-ink">
              Piso {l.piso} · Local {l.numeroLocal}
            </p>
            <p className="mt-0.5 text-sm text-aria-slate">Av. Huayacán, Cancún</p>
          </div>

          {l.instagram && (
            <InstagramEmbed handle={l.instagram} />
          )}

          {isDisponible && (
            <Link
              href={`/renta?local=${l.slug}`}
              className="card block p-5 text-center transition hover:shadow-cardHover"
            >
              <p className="font-display text-xl text-aria-terracotta">
                Renta este local
              </p>
              <p className="mt-1 text-sm text-aria-slate">
                Piso {l.piso} · Local {l.numeroLocal}
                {l.leasing?.m2 ? ` · ${l.leasing.m2} m²` : ''}
              </p>
              <span className="mt-4 inline-flex items-center text-sm font-medium text-aria-terracotta">
                Ver más información →
              </span>
            </Link>
          )}
        </aside>
      </div>

      {/* ── Vecinos ──────────────────────────────────────────────────────── */}
      <VecinosSection current={l} all={allLocales} now={now} />

      {/* ── CTA tail ─────────────────────────────────────────────────────── */}
      <footer className="mt-20 border-t border-aria-line/40 pt-10 text-center">
        <Link
          href="/directorio"
          className="text-sm text-aria-slate transition hover:text-aria-ink"
        >
          Plaza Aria · Av. Huayacán, Cancún
        </Link>
      </footer>
    </div>
  );
}
