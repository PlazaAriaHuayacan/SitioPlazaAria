import Image from 'next/image';
import Link from 'next/link';
import type { UnidadComercialAgrupada } from '@/lib/plano/agrupar';

export function EspecsOcupado({ unidad }: { unidad: UnidadComercialAgrupada }) {
  const principal = unidad.registroPrincipal;

  return (
    <div className="space-y-6">
      {unidad.logo?.url ? (
        <div className="aspect-square w-32 rounded-card bg-aria-sand flex items-center justify-center p-4 mx-auto">
          <Image
            src={unidad.logo.url}
            alt={`Logo de ${unidad.nombre}`}
            width={120}
            height={120}
            className="max-h-full w-auto object-contain"
          />
        </div>
      ) : null}

      <div>
        <p className="eyebrow">{unidad.giro}</p>
        <p className="mt-2 text-sm text-aria-slate max-w-prose-aria">
          {unidad.descripcion || 'Negocio activo en Plaza Aria.'}
        </p>
      </div>

      <div className="space-y-2 text-sm">
        {principal?.whatsapp && (
          <a
            href={`https://wa.me/${principal.whatsapp.replace(/[^0-9]/g, '')}`}
            target="_blank"
            rel="noreferrer noopener"
            className="block text-aria-ink underline-offset-4 hover:underline"
          >
            WhatsApp · {principal.whatsapp}
          </a>
        )}
        {principal?.telefono && (
          <a href={`tel:${principal.telefono}`} className="block text-aria-ink underline-offset-4 hover:underline">
            Llamar · {principal.telefono}
          </a>
        )}
        {principal?.instagram && (
          <a
            href={`https://www.instagram.com/${principal.instagram}/`}
            target="_blank"
            rel="noreferrer noopener"
            className="block text-aria-ink underline-offset-4 hover:underline"
          >
            Instagram · @{principal.instagram}
          </a>
        )}
      </div>

      <Link href={`/directorio/${unidad.slug}`} className="btn-secondary w-full justify-center">
        Ver ficha completa →
      </Link>
    </div>
  );
}
