import Link from 'next/link';

interface GapsTagsProps {
  gaps: string[];
}

export function GapsTags({ gaps }: GapsTagsProps) {
  if (gaps.length === 0) return null;

  return (
    <section
      aria-label="Aria está buscando"
      className="border-t border-aria-line"
    >
      <div className="container-aria py-12 md:py-16">
        <p className="eyebrow mb-4">Aria está buscando</p>

        <div className="flex flex-wrap gap-2 mb-6">
          {gaps.map((gap) => (
            <span
              key={gap}
              className="inline-flex items-center rounded-pill border border-aria-terracotta/30 bg-aria-terracotta/8 px-4 py-1.5 text-sm font-medium text-aria-terracotta"
              style={{ backgroundColor: 'rgba(184,90,60,0.08)' }}
            >
              {gap}
            </span>
          ))}
        </div>

        <p className="text-aria-slate text-base">
          Tu marca puede ser la próxima en Aria.{' '}
          <Link
            href="/renta"
            className="text-aria-terracotta font-medium hover:text-aria-terracottaDark transition-colors"
          >
            Renta tu local →
          </Link>
        </p>
      </div>
    </section>
  );
}
