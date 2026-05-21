import Image from 'next/image';
import Link from 'next/link';
import type { Foto } from '@/types/domain';

interface HeroProps {
  heroFoto?: Foto;
}

export function Hero({ heroFoto }: HeroProps) {
  return (
    <section
      aria-label="Hero"
      className="relative overflow-hidden pt-24 pb-32 md:pt-32 md:pb-40"
    >
      {/* Background: photo or gradient */}
      {heroFoto ? (
        <>
          <Image
            src={heroFoto.url}
            alt={heroFoto.alt ?? 'Plaza Aria'}
            fill
            priority
            className="object-cover"
            sizes="100vw"
          />
          {/* Warm overlay for legibility */}
          <div className="absolute inset-0 bg-gradient-to-br from-aria-ink/60 via-aria-ink/40 to-transparent" />
        </>
      ) : (
        <>
          {/* Intentional warm gradient — not a fallback, a design statement */}
          <div
            className="absolute inset-0"
            style={{
              background:
                'linear-gradient(135deg, #FAF6F0 0%, #F1E9DC 45%, #E9D5BB 75%, #D4B896 100%)',
            }}
          />
          {/* Soft radial light — top right */}
          <div
            className="absolute -top-24 -right-24 h-[500px] w-[500px] rounded-full blur-3xl"
            style={{ background: 'rgba(201,152,87,0.18)' }}
          />
          {/* Soft radial light — bottom left */}
          <div
            className="absolute -bottom-16 -left-16 h-[400px] w-[400px] rounded-full blur-3xl"
            style={{ background: 'rgba(184,90,60,0.12)' }}
          />
          {/* Subtle centre warmth */}
          <div
            className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-[600px] w-[700px] rounded-full blur-3xl"
            style={{ background: 'rgba(241,233,220,0.7)' }}
          />
        </>
      )}

      {/* Content */}
      <div className="container-aria relative z-10">
        <div className="max-w-2xl">
          <p className="eyebrow mb-6" style={{ color: heroFoto ? '#FAF6F0' : undefined }}>
            Av. Huayacán · Cancún
          </p>

          <h1
            className="font-display text-5xl leading-tight sm:text-6xl md:text-7xl mb-6"
            style={{ color: heroFoto ? '#FAF6F0' : '#1F1A14' }}
          >
            Tu plaza
            <br />
            <em
              className="not-italic"
              style={{ color: '#B85A3C' }}
            >
              de todos los días.
            </em>
          </h1>

          <p
            className="text-lg md:text-xl leading-relaxed mb-10 max-w-prose-aria"
            style={{ color: heroFoto ? 'rgba(250,246,240,0.85)' : '#5A544A' }}
          >
            Comer, mover el cuerpo, hacer pendientes, encontrarte con vecinos.
            Plaza Aria, al aire libre sobre Huayacán.
          </p>

          <div className="flex flex-wrap gap-4">
            <Link
              href="/renta"
              className="btn-primary px-8 py-4 text-base md:text-lg"
            >
              Renta tu local
            </Link>
            <Link
              href="/directorio"
              className={
                heroFoto
                  ? 'btn-secondary border-aria-bone/40 text-aria-bone hover:bg-aria-bone/10 hover:border-aria-bone/70 px-8 py-4 text-base md:text-lg'
                  : 'btn-secondary px-8 py-4 text-base md:text-lg'
              }
            >
              Explora la plaza
            </Link>
          </div>
        </div>
      </div>
    </section>
  );
}
