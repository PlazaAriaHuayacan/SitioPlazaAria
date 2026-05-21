import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { AbiertoBadge } from '@/components/directorio/AbiertoBadge';
import type { HorariosSemana } from '@/types/domain';

const cerrado = { open: null, close: null };

const horariosAbiertos: HorariosSemana = {
  Lunes:     { open: '10:00', close: '21:00' },
  Martes:    { open: '10:00', close: '21:00' },
  Miercoles: { open: '10:00', close: '21:00' },
  Jueves:    { open: '10:00', close: '21:00' },
  Viernes:   { open: '10:00', close: '22:00' },
  Sabado:    { open: '10:00', close: '22:00' },
  Domingo:   cerrado,
};

const horariosTodoCerrado: HorariosSemana = {
  Lunes: cerrado, Martes: cerrado, Miercoles: cerrado,
  Jueves: cerrado, Viernes: cerrado, Sabado: cerrado, Domingo: cerrado,
};

// Plaza is America/Cancun (UTC-5, no DST). 14:00 plaza = 19:00 UTC.
function plazaTime(y: number, m: number, d: number, h: number, min = 0) {
  return new Date(Date.UTC(y, m - 1, d, h + 5, min));
}

describe('AbiertoBadge', () => {
  it('renders "Abierto" with cierra-en when in business hours', () => {
    render(<AbiertoBadge horarios={horariosAbiertos} now={plazaTime(2026, 5, 18, 14, 0)} />);
    const badge = screen.getByRole('status');
    expect(badge).toHaveTextContent(/Abierto/i);
    expect(badge).toHaveTextContent(/cierra en/i);
    expect(badge).toHaveTextContent(/7 h/);
  });

  it('renders "Cierra en" when within 30 minutes of close', () => {
    render(<AbiertoBadge horarios={horariosAbiertos} now={plazaTime(2026, 5, 18, 20, 45)} />);
    const badge = screen.getByRole('status');
    expect(badge).toHaveTextContent(/Cierra en 15 min/);
    // Should NOT include the "Abierto · " prefix in the cierra-pronto variant
    expect(badge).not.toHaveTextContent(/Abierto/);
  });

  it('renders "Cerrado · abre en" before opening', () => {
    render(<AbiertoBadge horarios={horariosAbiertos} now={plazaTime(2026, 5, 18, 8, 0)} />);
    expect(screen.getByRole('status')).toHaveTextContent(/Cerrado · abre en 2 h/);
  });

  it('renders plain "Cerrado" when the whole week is closed', () => {
    render(<AbiertoBadge horarios={horariosTodoCerrado} now={plazaTime(2026, 5, 18, 14, 0)} />);
    expect(screen.getByRole('status')).toHaveTextContent(/^Cerrado$/);
  });

  it('uses the small size class when size="sm"', () => {
    render(<AbiertoBadge horarios={horariosAbiertos} now={plazaTime(2026, 5, 18, 14, 0)} size="sm" />);
    expect(screen.getByRole('status').className).toMatch(/text-\[11px\]/);
  });

  it('forwards extra className', () => {
    render(<AbiertoBadge horarios={horariosAbiertos} now={plazaTime(2026, 5, 18, 14, 0)} className="ml-2" />);
    expect(screen.getByRole('status').className).toMatch(/ml-2/);
  });

  it('exposes the full status as aria-label', () => {
    render(<AbiertoBadge horarios={horariosAbiertos} now={plazaTime(2026, 5, 18, 14, 0)} />);
    const badge = screen.getByRole('status');
    expect(badge.getAttribute('aria-label')).toMatch(/Abierto · cierra en/);
  });
});
