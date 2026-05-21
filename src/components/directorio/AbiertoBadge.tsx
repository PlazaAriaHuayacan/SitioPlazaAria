import { estadoAhora, formatMinutos } from '@/lib/horarios';
import type { HorariosSemana } from '@/types/domain';

type Props = {
  horarios: HorariosSemana;
  /** Pass `new Date()` from a server component or freeze for tests. */
  now: Date;
  /** Visual size variant. */
  size?: 'sm' | 'md';
  className?: string;
};

/**
 * Pill summarizing whether a local is open right now.
 * Three visual states:
 *   - Abierto       → aria-olive  (calm green)
 *   - Cierra pronto → aria-gold   (warm warning)
 *   - Cerrado       → aria-slate  (neutral, muted)
 */
export function AbiertoBadge({ horarios, now, size = 'md', className = '' }: Props) {
  const r = estadoAhora(horarios, now);

  const base =
    'inline-flex items-center gap-1.5 rounded-pill font-medium whitespace-nowrap';
  const sizeClass = size === 'sm' ? 'text-[11px] px-2 py-0.5' : 'text-xs px-2.5 py-1';

  let label: string;
  let palette: string;
  let dot: string;

  if (r.estado === 'abierto') {
    label = `Abierto · cierra en ${formatMinutos(r.cierraEn)}`;
    palette = 'bg-aria-olive/12 text-aria-oliveDark';
    dot = 'bg-aria-olive';
  } else if (r.estado === 'cierra-pronto') {
    label = `Cierra en ${formatMinutos(r.cierraEn)}`;
    palette = 'bg-aria-gold/15 text-[#7A5827]';
    dot = 'bg-aria-gold';
  } else {
    if (typeof r.abreEn === 'number') {
      label = `Cerrado · abre en ${formatMinutos(r.abreEn)}`;
    } else {
      label = 'Cerrado';
    }
    palette = 'bg-aria-ink/8 text-aria-slate';
    dot = 'bg-aria-slate';
  }

  return (
    <span
      role="status"
      aria-label={label}
      className={`${base} ${sizeClass} ${palette} ${className}`}
    >
      <span aria-hidden className={`h-1.5 w-1.5 rounded-full ${dot}`} />
      {label}
    </span>
  );
}
