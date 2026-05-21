import type { Metadata } from 'next';
import Link from 'next/link';

export const metadata: Metadata = {
  title: 'Contacto',
  description: 'Habla con Plaza Aria. Estamos sobre Av. Huayacán, Cancún.',
};

export default function ContactoPage() {
  return (
    <div className="container-aria py-12 md:py-20">
      <div className="grid gap-12 md:grid-cols-2 md:gap-20">
        <div>
          <p className="eyebrow">Plaza Aria</p>
          <h1 className="mt-2 font-display tracking-display text-4xl md:text-5xl text-aria-ink">
            Hablemos.
          </h1>
          <p className="mt-5 max-w-prose-aria text-aria-slate">
            Plaza Aria está sobre Avenida Huayacán, en plena zona residencial de Cancún.
            Plaza al aire libre, dos pisos, estacionamiento techado.
          </p>
          <p className="mt-4 max-w-prose-aria text-aria-slate">
            ¿Quieres rentar un local? Visita{' '}
            <Link href="/renta" className="text-aria-ink underline underline-offset-4 hover:text-aria-terracotta">
              Renta tu local
            </Link>
            . Para cualquier otra cosa, escríbenos por aquí.
          </p>
        </div>

        <div className="space-y-4">
          <ContactCard
            label="Instagram"
            value="@plaza_aria"
            href="https://www.instagram.com/plaza_aria/"
            external
          />
          <ContactCard
            label="Ubicación"
            value="Av. Huayacán, Cancún, Q. Roo"
            sub="Plaza vecinal al aire libre · 2 pisos · estacionamiento techado"
          />
          <ContactCard
            label="Horario de la plaza"
            value="Lunes a sábado · 10:00 – 22:00"
            sub="Cada negocio define su propio horario."
          />
          <ContactCard
            label="Comercialización"
            value="Renta tu local en Plaza Aria"
            href="/renta"
            sub="Formulario y locales disponibles"
          />
        </div>
      </div>
    </div>
  );
}

function ContactCard({
  label, value, sub, href, external,
}: {
  label: string;
  value: string;
  sub?: string;
  href?: string;
  external?: boolean;
}) {
  const inner = (
    <>
      <p className="eyebrow">{label}</p>
      <p className="mt-1 font-display text-xl text-aria-ink">{value}</p>
      {sub && <p className="mt-1 text-sm text-aria-slate">{sub}</p>}
    </>
  );
  if (!href) {
    return <div className="card p-5">{inner}</div>;
  }
  if (external) {
    return (
      <a
        href={href}
        target="_blank"
        rel="noreferrer noopener"
        className="card block p-5 transition-shadow"
      >
        {inner}
      </a>
    );
  }
  return (
    <Link href={href} className="card block p-5 transition-shadow">
      {inner}
    </Link>
  );
}
