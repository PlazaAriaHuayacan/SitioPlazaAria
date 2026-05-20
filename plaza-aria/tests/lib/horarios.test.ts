import { describe, it, expect } from 'vitest';
import {
  parseHorarioBlock,
  estadoAhora,
  formatMinutos,
} from '@/lib/horarios';
import type { HorariosSemana } from '@/types/domain';

const cerrado = { open: null, close: null };

const horariosBase: HorariosSemana = {
  Lunes:     { open: '10:00', close: '21:00' },
  Martes:    { open: '10:00', close: '21:00' },
  Miercoles: { open: '10:00', close: '21:00' },
  Jueves:    { open: '10:00', close: '21:00' },
  Viernes:   { open: '10:00', close: '22:00' },
  Sabado:    { open: '10:00', close: '22:00' },
  Domingo:   cerrado,
};

// Helper: build a Date that, when interpreted in America/Cancun (UTC-5, no DST),
// is the given Y/M/D h:m. Plaza tz is UTC-5 year-round.
// So 14:00 plaza time = 19:00 UTC.
function plazaTime(y: number, m: number, d: number, h: number, min = 0): Date {
  return new Date(Date.UTC(y, m - 1, d, h + 5, min));
}

describe('parseHorarioBlock', () => {
  it('parses a valid block', () => {
    expect(parseHorarioBlock('10:00-21:00')).toEqual({ open: '10:00', close: '21:00' });
  });

  it('trims whitespace', () => {
    expect(parseHorarioBlock('  10:00 - 21:00  ')).toEqual({ open: '10:00', close: '21:00' });
  });

  it('returns closed for empty string', () => {
    expect(parseHorarioBlock('')).toEqual(cerrado);
  });

  it('returns closed for whitespace', () => {
    expect(parseHorarioBlock('   ')).toEqual(cerrado);
  });

  it('returns closed for malformed input', () => {
    expect(parseHorarioBlock('not a time')).toEqual(cerrado);
    expect(parseHorarioBlock('10:00')).toEqual(cerrado);
    expect(parseHorarioBlock('25:00-26:00')).toEqual(cerrado);
  });
});

describe('estadoAhora', () => {
  it('returns abierto in the middle of business hours', () => {
    // Monday May 18 2026 at 14:00 plaza time (well within 10-21)
    const r = estadoAhora(horariosBase, plazaTime(2026, 5, 18, 14, 0));
    expect(r.estado).toBe('abierto');
    if (r.estado === 'abierto') {
      expect(r.cierraEn).toBe(7 * 60); // 7 hours until 21:00
    }
  });

  it('returns cierra-pronto when within 30 minutes of close', () => {
    // Monday May 18 2026 at 20:45 (15 min before 21:00 close)
    const r = estadoAhora(horariosBase, plazaTime(2026, 5, 18, 20, 45));
    expect(r.estado).toBe('cierra-pronto');
    if (r.estado === 'cierra-pronto') {
      expect(r.cierraEn).toBe(15);
    }
  });

  it('returns cierra-pronto at exactly 30 minutes before close', () => {
    // Monday May 18 2026 at 20:30
    const r = estadoAhora(horariosBase, plazaTime(2026, 5, 18, 20, 30));
    expect(r.estado).toBe('cierra-pronto');
  });

  it('returns abierto at 31 minutes before close (boundary)', () => {
    // Monday May 18 2026 at 20:29
    const r = estadoAhora(horariosBase, plazaTime(2026, 5, 18, 20, 29));
    expect(r.estado).toBe('abierto');
  });

  it('returns cerrado before opening today, with abreEn set to today opening', () => {
    // Monday May 18 2026 at 08:00 (2 hours before 10:00)
    const r = estadoAhora(horariosBase, plazaTime(2026, 5, 18, 8, 0));
    expect(r.estado).toBe('cerrado');
    if (r.estado === 'cerrado') {
      expect(r.abreEn).toBe(120);
    }
  });

  it('returns cerrado after closing, with abreEn pointing to next day open', () => {
    // Monday May 18 2026 at 22:00 (1 hour after close; next open Tuesday 10:00 = 12 hours)
    const r = estadoAhora(horariosBase, plazaTime(2026, 5, 18, 22, 0));
    expect(r.estado).toBe('cerrado');
    if (r.estado === 'cerrado') {
      expect(r.abreEn).toBe(12 * 60);
    }
  });

  it('skips closed days when computing abreEn', () => {
    // Sunday May 17 2026 at 12:00 (Domingo is closed; Lunes opens at 10:00 next day = 22 hours)
    const r = estadoAhora(horariosBase, plazaTime(2026, 5, 17, 12, 0));
    expect(r.estado).toBe('cerrado');
    if (r.estado === 'cerrado') {
      expect(r.abreEn).toBe(22 * 60);
    }
  });

  it('returns cerrado without abreEn when entire week is closed', () => {
    const allClosed: HorariosSemana = {
      Lunes: cerrado, Martes: cerrado, Miercoles: cerrado,
      Jueves: cerrado, Viernes: cerrado, Sabado: cerrado, Domingo: cerrado,
    };
    const r = estadoAhora(allClosed, plazaTime(2026, 5, 18, 14, 0));
    expect(r.estado).toBe('cerrado');
    if (r.estado === 'cerrado') {
      expect(r.abreEn).toBeUndefined();
    }
  });

  it('returns abierto exactly at opening minute', () => {
    // Monday May 18 2026 at 10:00 (opens at 10:00)
    const r = estadoAhora(horariosBase, plazaTime(2026, 5, 18, 10, 0));
    expect(r.estado).toBe('abierto');
  });

  it('returns cerrado exactly at closing minute', () => {
    // Monday May 18 2026 at 21:00 (closes at 21:00 — no longer open at 21:00 sharp)
    const r = estadoAhora(horariosBase, plazaTime(2026, 5, 18, 21, 0));
    expect(r.estado).toBe('cerrado');
  });
});

describe('formatMinutos', () => {
  it('formats under an hour', () => {
    expect(formatMinutos(5)).toBe('5 min');
    expect(formatMinutos(45)).toBe('45 min');
  });

  it('formats exactly one hour', () => {
    expect(formatMinutos(60)).toBe('1 h');
  });

  it('formats hours and minutes', () => {
    expect(formatMinutos(80)).toBe('1 h 20 min');
    expect(formatMinutos(195)).toBe('3 h 15 min');
  });

  it('formats full hours', () => {
    expect(formatMinutos(120)).toBe('2 h');
    expect(formatMinutos(180)).toBe('3 h');
  });

  it('formats 0 as "0 min"', () => {
    expect(formatMinutos(0)).toBe('0 min');
  });
});
