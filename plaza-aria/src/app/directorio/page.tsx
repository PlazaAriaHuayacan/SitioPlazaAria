import type { Metadata } from 'next';
import { listLocales } from '@/lib/airtable/client';
import { DirectorioWithFilters } from '@/components/directorio/DirectorioWithFilters';

export const revalidate = 300; // ISR every 5 min

export const metadata: Metadata = {
  title: 'Directorio',
  description:
    'Descubre todos los negocios de Plaza Aria: restaurantes, tiendas, clases y servicios sobre Av. Huayacán.',
};

export default async function DirectorioPage() {
  let locales: Awaited<ReturnType<typeof listLocales>> = [];
  try {
    locales = await listLocales();
  } catch (err) {
    console.error('[/directorio] failed to load locales:', err);
  }

  const nowIso = new Date().toISOString();

  return (
    <div className="container-aria py-12 md:py-16">
      <header className="mb-10 md:mb-14">
        <p className="eyebrow">Plaza Aria</p>
        <h1 className="mt-2 font-display tracking-display text-4xl md:text-5xl text-aria-ink">
          Directorio
        </h1>
        <p className="mt-3 max-w-prose-aria text-aria-slate">
          Todo lo que hay en Plaza Aria, a un paso de tu casa. Filtra por giro,
          piso o lo que esté abierto ahora.
        </p>
      </header>

      <DirectorioWithFilters all={locales} nowIso={nowIso} />
    </div>
  );
}
