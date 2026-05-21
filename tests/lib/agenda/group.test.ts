import { describe, it, expect } from 'vitest';
import { groupEventos, plazaDateISO } from '@/lib/agenda/group';
import type { Evento } from '@/types/domain';

function E(over: Partial<Evento>): Evento {
  return {
    id: over.id ?? 'rec',
    titulo: over.titulo ?? 'Evento',
    localId: 'l1', localNombre: 'X', localSlug: 'x',
    tipo: over.tipo ?? 'Evento',
    recurrencia: 'Único',
    fecha: over.fecha ?? '2026-05-25',
    horaInicio: over.horaInicio ?? '10:00',
    horaFin: over.horaFin ?? '11:00',
    descripcion: '',
    ...over,
  };
}

describe('groupEventos', () => {
  const eventos = [
    E({ id: '1', fecha: '2026-05-25', horaInicio: '09:00', titulo: 'A' }),
    E({ id: '2', fecha: '2026-05-25', horaInicio: '07:00', titulo: 'B' }),
    E({ id: '3', fecha: '2026-05-26', horaInicio: '18:00', titulo: 'C' }),
    E({ id: '4', fecha: '2026-05-20', titulo: 'OLD' }), // past
    E({ id: '5', fecha: '2026-05-25', tipo: 'Fitness', titulo: 'F' }),
  ];

  it('drops past events', () => {
    const r = groupEventos({ eventos, todayISO: '2026-05-24', tipos: [] });
    expect(r.flatMap((d) => d.eventos.map((e) => e.id))).not.toContain('4');
  });

  it('groups by date and sorts days ascending', () => {
    const r = groupEventos({ eventos, todayISO: '2026-05-24', tipos: [] });
    expect(r.map((d) => d.dateISO)).toEqual(['2026-05-25', '2026-05-26']);
  });

  it('sorts eventos within a day by horaInicio', () => {
    const r = groupEventos({ eventos, todayISO: '2026-05-24', tipos: [] });
    const may25 = r.find((d) => d.dateISO === '2026-05-25')!;
    expect(may25.eventos.map((e) => e.id)).toEqual(['2', '1', '5']);
  });

  it('filters by tipo', () => {
    const r = groupEventos({ eventos, todayISO: '2026-05-24', tipos: ['Fitness'] });
    expect(r.flatMap((d) => d.eventos.map((e) => e.id))).toEqual(['5']);
  });

  it('returns empty when nothing matches', () => {
    const r = groupEventos({ eventos, todayISO: '2030-01-01', tipos: [] });
    expect(r).toEqual([]);
  });
});

describe('plazaDateISO', () => {
  it('returns plaza-local YYYY-MM-DD', () => {
    // May 18 2026 14:00 plaza = 19:00 UTC
    const d = new Date(Date.UTC(2026, 4, 18, 19, 0));
    expect(plazaDateISO(d)).toBe('2026-05-18');
  });

  it('handles late-night plaza time correctly (does not roll over with UTC)', () => {
    // May 18 2026 23:30 plaza = May 19 04:30 UTC
    const d = new Date(Date.UTC(2026, 4, 19, 4, 30));
    expect(plazaDateISO(d)).toBe('2026-05-18');
  });
});
