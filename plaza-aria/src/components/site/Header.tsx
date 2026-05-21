import Link from 'next/link';
import { Wordmark } from './Wordmark';
import { MobileMenu } from './MobileMenu';

const navLinks = [
  { href: '/directorio', label: 'Directorio' },
  { href: '/agenda', label: 'Agenda' },
  { href: '/contacto', label: 'Contacto' },
];

export function Header() {
  return (
    <header className="sticky top-0 z-40 border-b border-aria-line/60 bg-aria-bone/80 backdrop-blur">
      <div className="container-aria flex h-16 items-center justify-between">
        <Wordmark size="md" />

        {/* Desktop nav */}
        <nav className="hidden md:flex items-center gap-7">
          {navLinks.map((l) => (
            <Link
              key={l.href}
              href={l.href}
              className="text-sm font-medium text-aria-slate transition-colors hover:text-aria-ink"
            >
              {l.label}
            </Link>
          ))}
          <Link href="/renta" className="btn-primary px-4 py-2 text-sm">
            Renta tu local
          </Link>
        </nav>

        {/* Mobile menu */}
        <div className="md:hidden">
          <MobileMenu />
        </div>
      </div>
    </header>
  );
}
