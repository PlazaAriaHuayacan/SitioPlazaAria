import Link from 'next/link';
import { Wordmark } from './Wordmark';

export function Footer() {
  return (
    <footer className="mt-24 bg-aria-sand">
      <div className="container-aria pt-16 pb-10">
        <div className="grid gap-12 md:grid-cols-3">
          <div>
            <Wordmark size="md" />
            <p className="mt-4 max-w-xs text-sm text-aria-slate">
              Plaza vecinal al aire libre sobre Av. Huayacán. Restaurantes,
              tiendas y servicios para tu día a día.
            </p>
          </div>

          <div>
            <p className="eyebrow mb-4">Explora</p>
            <ul className="space-y-2 text-sm">
              <li><Link href="/directorio" className="text-aria-slate hover:text-aria-ink transition-colors">Directorio</Link></li>
              <li><Link href="/agenda" className="text-aria-slate hover:text-aria-ink transition-colors">Agenda de clases y eventos</Link></li>
              <li><Link href="/renta" className="text-aria-slate hover:text-aria-ink transition-colors">Renta tu local</Link></li>
              <li><Link href="/contacto" className="text-aria-slate hover:text-aria-ink transition-colors">Contacto</Link></li>
            </ul>
          </div>

          <div>
            <p className="eyebrow mb-4">Síguenos</p>
            <ul className="space-y-2 text-sm">
              <li>
                <a
                  href="https://www.instagram.com/plaza_aria/"
                  target="_blank"
                  rel="noreferrer"
                  className="text-aria-slate hover:text-aria-ink transition-colors"
                >
                  Instagram · @plaza_aria
                </a>
              </li>
              <li className="text-aria-slate">
                Av. Huayacán, Cancún, Q. Roo
              </li>
            </ul>
          </div>
        </div>

        <div className="mt-12 border-t border-aria-line/60 pt-6 text-xs text-aria-slate flex flex-col sm:flex-row gap-2 sm:gap-4 justify-between">
          <span>© {new Date().getFullYear()} Plaza Aria</span>
          <span>Hecho con cariño por Punch Marketing</span>
        </div>
      </div>
    </footer>
  );
}
