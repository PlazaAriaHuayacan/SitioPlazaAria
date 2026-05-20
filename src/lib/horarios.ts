import type { HorarioDia, HorariosSemana, DiaSemana } from '@/types/domain';

// ─── Types ───────────────────────────────────────────────────────────────────

export type EstadoAhora =
  | { estado: 'abierto'; cierraEn: number }
  | { estado: 'cierra-pronto'; cierraEn: number }
  | { estado: 'cerrado'; abreEn?: number };

// ─── parseHorarioBlock ────────────────────────────────────────────────────────

const TIME_RE = /^(\d{2}):(\d{2})$/;

function isValidTime(t: string): boolean {
  const m = TIME_RE.exec(t);
  if (!m) return false;
  const h = parseInt(m[1], 10);
  const min = parseInt(m[2], 10);
  return h >= 0 && h <= 23 && min >= 0 && min <= 59;
}

export function parseHorarioBlock(raw: string): HorarioDia {
  const trimmed = raw.trim();
  if (!trimmed) return { open: null, close: null };

  const parts = trimmed.split('-').map((s) => s.trim());
  if (parts.length !== 2) return { open: null, close: null };

  const [open, close] = parts;
  if (!isValidTime(open) || !isValidTime(close)) return { open: null, close: null };

  return { open, close };
}

// ─── Timezone helpers ─────────────────────────────────────────────────────────

const CANCUN_TZ = 'America/Cancun';

interface PlazaClock {
  weekday: string; // 'Monday' .. 'Sunday'
  hour: number;    // 0-23
  minute: number;  // 0-59
}

function getPlazaClock(now: Date): PlazaClock {
  const fmt = new Intl.DateTimeFormat('en-US', {
    timeZone: CANCUN_TZ,
    weekday: 'long',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  });
  const parts = fmt.formatToParts(now);
  const get = (type: string) => parts.find((p) => p.type === type)?.value ?? '';

  const weekday = get('weekday');
  const hour = parseInt(get('hour'), 10);
  const minute = parseInt(get('minute'), 10);

  return { weekday, hour, minute };
}

// Map English weekday → DiaSemana
const WEEKDAY_MAP: Record<string, DiaSemana> = {
  Monday: 'Lunes',
  Tuesday: 'Martes',
  Wednesday: 'Miercoles',
  Thursday: 'Jueves',
  Friday: 'Viernes',
  Saturday: 'Sabado',
  Sunday: 'Domingo',
};

// Ordered days of the week for iteration
const DIAS: DiaSemana[] = [
  'Domingo',
  'Lunes',
  'Martes',
  'Miercoles',
  'Jueves',
  'Viernes',
  'Sabado',
];

// Convert HH:MM to total minutes from midnight
function toMinutes(time: string): number {
  const [h, m] = time.split(':').map(Number);
  return h * 60 + m;
}

// ─── estadoAhora ─────────────────────────────────────────────────────────────

export function estadoAhora(horarios: HorariosSemana, now: Date): EstadoAhora {
  const clock = getPlazaClock(now);
  const diaSemana = WEEKDAY_MAP[clock.weekday];
  const nowMin = clock.hour * 60 + clock.minute;

  // Check today's schedule
  const hoy = horarios[diaSemana];

  if (hoy.open !== null && hoy.close !== null) {
    const openMin = toMinutes(hoy.open);
    const closeMin = toMinutes(hoy.close);

    if (nowMin >= openMin && nowMin < closeMin) {
      const cierraEn = closeMin - nowMin;
      if (cierraEn <= 30) {
        return { estado: 'cierra-pronto', cierraEn };
      }
      return { estado: 'abierto', cierraEn };
    }
  }

  // Closed — find next opening within 7 days
  // DIAS array uses JS getDay() order: index 0 = Sunday
  // We need to find which index today is
  const todayIndex = DIAS.indexOf(diaSemana);

  for (let delta = 0; delta < 7; delta++) {
    const checkIndex = (todayIndex + delta) % 7;
    const checkDia = DIAS[checkIndex];
    const checkHorario = horarios[checkDia];

    if (checkHorario.open === null) continue;

    const openMin = toMinutes(checkHorario.open);

    if (delta === 0) {
      // Same day: only valid if opening is still in the future
      if (openMin > nowMin) {
        return { estado: 'cerrado', abreEn: openMin - nowMin };
      }
      // Already passed opening (and we're closed = past close or never opened today)
      continue;
    }

    // Future day: minutes until that opening
    const minutesUntilMidnight = 24 * 60 - nowMin;
    const minutesFromMidnight = delta * 24 * 60 - 24 * 60; // extra full days after tomorrow's midnight
    const abreEn = minutesUntilMidnight + minutesFromMidnight + openMin;
    return { estado: 'cerrado', abreEn };
  }

  // Entire week is closed
  return { estado: 'cerrado' };
}

// ─── formatMinutos ────────────────────────────────────────────────────────────

export function formatMinutos(min: number): string {
  if (min < 60) return `${min} min`;
  const h = Math.floor(min / 60);
  const m = min % 60;
  if (m === 0) return `${h} h`;
  return `${h} h ${m} min`;
}
