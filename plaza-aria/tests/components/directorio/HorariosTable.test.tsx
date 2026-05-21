import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { HorariosTable } from '@/components/directorio/HorariosTable';
import type { HorariosSemana } from '@/types/domain';

const cerrado = { open: null, close: null };
const sample: HorariosSemana = {
  Lunes:     { open: '10:00', close: '21:00' },
  Martes:    { open: '10:00', close: '21:00' },
  Miercoles: { open: '10:00', close: '21:00' },
  Jueves:    { open: '10:00', close: '21:00' },
  Viernes:   { open: '10:00', close: '22:00' },
  Sabado:    { open: '10:00', close: '22:00' },
  Domingo:   cerrado,
};

function plazaTime(y: number, m: number, d: number, h = 14) {
  return new Date(Date.UTC(y, m - 1, d, h + 5, 0));
}

describe('HorariosTable', () => {
  it('renders all 7 days in order', () => {
    render(<HorariosTable horarios={sample} now={plazaTime(2026, 5, 18)} />);
    const dts = screen.getAllByRole('definition'); // each <dd>
    expect(dts.length).toBe(7);
  });

  it('renders Cerrado for closed days', () => {
    render(<HorariosTable horarios={sample} now={plazaTime(2026, 5, 18)} />);
    expect(screen.getByText(/Cerrado/i)).toBeInTheDocument();
  });

  it('highlights today (Lunes on May 18 2026)', () => {
    render(<HorariosTable horarios={sample} now={plazaTime(2026, 5, 18)} />);
    expect(screen.getByText(/Hoy/i)).toBeInTheDocument();
  });

  it('highlights Sabado when now is Saturday in plaza tz', () => {
    // May 23 2026 is a Saturday.
    const { container } = render(<HorariosTable horarios={sample} now={plazaTime(2026, 5, 23)} />);
    const todayRow = container.querySelector('[aria-current="date"]');
    expect(todayRow?.textContent).toMatch(/Sábado/);
  });

  it('renders open hours with en-dash', () => {
    render(<HorariosTable horarios={sample} now={plazaTime(2026, 5, 18)} />);
    const matches = screen.getAllByText('10:00 – 21:00');
    expect(matches.length).toBeGreaterThan(0);
  });
});
