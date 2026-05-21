import { vi } from 'vitest';

vi.mock('next/image', () => ({
  default: ({ src, alt, fill, ...props }: any) => (
    // eslint-disable-next-line @next/next/no-img-element
    <img src={src} alt={alt} data-fill={fill ? 'true' : undefined} {...props} />
  ),
}));

vi.mock('next/link', () => ({
  default: ({ children, href, ...props }: any) => (
    <a href={href} {...props}>
      {children}
    </a>
  ),
}));

import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { LocalCard } from '@/components/directorio/LocalCard';
import type { Local } from '@/types/domain';

// Plaza is America/Cancun (UTC-5, no DST).
function plazaTime(y: number, m: number, d: number, h: number, min = 0) {
  return new Date(Date.UTC(y, m - 1, d, h + 5, min));
}

// Frozen during business hours on a Monday
const nowOpen = plazaTime(2026, 5, 18, 14, 0); // 14:00 plaza on Mon

function makeLocal(overrides: Partial<Local> = {}): Local {
  return {
    id: 'rec1',
    slug: 'cafe-aria',
    nombre: 'Café Aria',
    giro: 'Restaurante',
    estado: 'Ocupado',
    piso: '1',
    numeroLocal: 'A-12',
    fotos: [],
    descripcion: 'Café especialidad.',
    horarios: {
      Lunes:     { open: '08:00', close: '20:00' },
      Martes:    { open: '08:00', close: '20:00' },
      Miercoles: { open: '08:00', close: '20:00' },
      Jueves:    { open: '08:00', close: '20:00' },
      Viernes:   { open: '08:00', close: '22:00' },
      Sabado:    { open: '08:00', close: '22:00' },
      Domingo:   { open: null,   close: null   },
    },
    ...overrides,
  };
}

describe('LocalCard', () => {
  it('renders a link to /directorio/{slug} with nombre in aria-label', () => {
    const local = makeLocal();
    render(<LocalCard local={local} now={nowOpen} />);
    const link = screen.getByRole('link');
    expect(link).toHaveAttribute('href', '/directorio/cafe-aria');
    expect(link.getAttribute('aria-label')).toContain('Café Aria');
  });

  it('shows AbiertoBadge for an Ocupado local during business hours', () => {
    const local = makeLocal();
    render(<LocalCard local={local} now={nowOpen} />);
    const badge = screen.getByRole('status');
    // At 14:00 on Monday, opens at 08:00, closes at 20:00 → Abierto
    expect(badge).toHaveTextContent(/Abierto/i);
  });

  it('shows AbiertoBadge "Cerrado" text when local is closed', () => {
    const local = makeLocal();
    // 23:00 on a Sunday (closed all day)
    const nowClosed = plazaTime(2026, 5, 17, 23, 0);
    render(<LocalCard local={local} now={nowClosed} />);
    const badge = screen.getByRole('status');
    expect(badge).toHaveTextContent(/Cerrado/i);
  });

  it('Disponible variant: renders "DISPONIBLE" pill and formatted renta', () => {
    const local = makeLocal({
      estado: 'Disponible',
      leasing: {
        m2: 50,
        renta: 18000,
        instalaciones: ['Agua', 'Luz'],
      },
    });
    render(<LocalCard local={local} now={nowOpen} />);
    // DISPONIBLE pill in media zone
    expect(screen.getByText('DISPONIBLE')).toBeTruthy();
    // Renta line — MXN formatted
    const rentaEl = screen.getByText(/Disponible/i, { selector: 'p' });
    expect(rentaEl.textContent).toMatch(/\$18,000/);
    // No AbiertoBadge
    expect(screen.queryByRole('status')).toBeNull();
  });

  it('Disponible variant without leasing shows "Disponible" without renta', () => {
    const local = makeLocal({ estado: 'Disponible' });
    render(<LocalCard local={local} now={nowOpen} />);
    expect(screen.getByText('DISPONIBLE')).toBeTruthy();
    const rentaEl = screen.getByText(/^Disponible$/i, { selector: 'p' });
    expect(rentaEl).toBeTruthy();
    expect(screen.queryByRole('status')).toBeNull();
  });

  it('Proximamente variant: renders "Próximamente" pill and opacity class', () => {
    const local = makeLocal({ estado: 'Proximamente' });
    const { container } = render(<LocalCard local={local} now={nowOpen} />);
    expect(screen.getByText('Próximamente')).toBeTruthy();
    const link = container.querySelector('a');
    expect(link?.className).toMatch(/opacity-/);
  });

  it('with a logo renders logo img with logo URL', () => {
    const local = makeLocal({
      logo: { url: 'https://example.com/logo.png', alt: 'Logo test' },
    });
    render(<LocalCard local={local} now={nowOpen} />);
    const imgs = screen.getAllByRole('img');
    const logoImg = imgs.find((img) => img.getAttribute('src') === 'https://example.com/logo.png');
    expect(logoImg).toBeTruthy();
  });

  it('without logo and without fotos renders the first letter of nombre', () => {
    const local = makeLocal({ fotos: [] });
    render(<LocalCard local={local} now={nowOpen} />);
    // First letter of 'Café Aria' is 'C'
    expect(screen.getByText('C')).toBeTruthy();
  });

  it('with a foto and no logo renders a cover image with the foto URL', () => {
    const local = makeLocal({
      fotos: [{ url: 'https://example.com/foto.jpg', alt: 'Cover' }],
    });
    render(<LocalCard local={local} now={nowOpen} />);
    const imgs = screen.getAllByRole('img');
    const coverImg = imgs.find((img) => img.getAttribute('src') === 'https://example.com/foto.jpg');
    expect(coverImg).toBeTruthy();
  });
});
