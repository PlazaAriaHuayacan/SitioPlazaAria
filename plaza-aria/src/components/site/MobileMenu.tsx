'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { Wordmark } from './Wordmark';

const links = [
  { href: '/directorio', label: 'Directorio' },
  { href: '/agenda', label: 'Agenda' },
  { href: '/contacto', label: 'Contacto' },
];

export function MobileMenu() {
  const [open, setOpen] = useState(false);

  // Close on escape; lock scroll while open
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false);
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', onKey);
    return () => {
      document.body.style.overflow = '';
      document.removeEventListener('keydown', onKey);
    };
  }, [open]);

  return (
    <>
      <button
        type="button"
        aria-label="Abrir menú"
        aria-expanded={open}
        onClick={() => setOpen(true)}
        className="inline-flex h-10 w-10 items-center justify-center rounded-pill text-aria-ink hover:bg-aria-ink/5"
      >
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
          <path d="M4 7h16M4 12h16M4 17h16" />
        </svg>
      </button>

      <div
        aria-hidden={!open}
        className={`fixed inset-0 z-50 transition-opacity duration-200 ease-aria ${open ? 'opacity-100' : 'pointer-events-none opacity-0'}`}
      >
        <div className="absolute inset-0 bg-aria-bone" />
        <div className="relative flex h-full flex-col">
          <div className="container-aria flex h-16 items-center justify-between">
            <Wordmark size="sm" />
            <button
              type="button"
              aria-label="Cerrar menú"
              onClick={() => setOpen(false)}
              className="inline-flex h-10 w-10 items-center justify-center rounded-pill text-aria-ink hover:bg-aria-ink/5"
            >
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
                <path d="M6 6l12 12M18 6l-12 12" />
              </svg>
            </button>
          </div>
          <nav className="container-aria flex flex-1 flex-col justify-center gap-6">
            {links.map((l) => (
              <Link
                key={l.href}
                href={l.href}
                onClick={() => setOpen(false)}
                className="font-display text-4xl text-aria-ink tracking-display hover:text-aria-terracotta transition-colors"
              >
                {l.label}
              </Link>
            ))}
            <Link
              href="/renta"
              onClick={() => setOpen(false)}
              className="btn-primary mt-4 w-fit"
            >
              Renta tu local
            </Link>
          </nav>
        </div>
      </div>
    </>
  );
}
