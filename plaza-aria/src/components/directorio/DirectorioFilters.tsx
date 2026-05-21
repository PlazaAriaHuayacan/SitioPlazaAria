'use client';

import type { Giro, Piso } from '@/types/domain';
import type {
  EstadoFiltro, PisoFiltro, OrdenFiltro,
} from '@/lib/directorio/filter';

type Props = {
  query: string;
  giros: Giro[];
  piso: PisoFiltro;
  estado: EstadoFiltro;
  orden: OrdenFiltro;
  availableGiros: Giro[];
  onQuery: (v: string) => void;
  onToggleGiro: (g: Giro) => void;
  onClearGiros: () => void;
  onPiso: (v: PisoFiltro) => void;
  onEstado: (v: EstadoFiltro) => void;
  onOrden: (v: OrdenFiltro) => void;
  resultCount: number;
};

const pisoOptions: { value: PisoFiltro; label: string }[] = [
  { value: 'Todos', label: 'Todos los pisos' },
  { value: '1', label: 'Piso 1' },
  { value: '2', label: 'Piso 2' },
];

const estadoOptions: { value: EstadoFiltro; label: string }[] = [
  { value: 'Todos', label: 'Todos' },
  { value: 'AbiertosAhora', label: 'Abiertos ahora' },
  { value: 'Disponibles', label: 'Disponibles' },
];

const ordenOptions: { value: OrdenFiltro; label: string }[] = [
  { value: 'PisoLocal', label: 'Por piso / local' },
  { value: 'Alfabetico', label: 'Alfabético' },
  { value: 'Novedades', label: 'Novedades' },
];

export function DirectorioFilters(props: Props) {
  return (
    <div className="border-b border-aria-line/60 pb-6">
      {/* Search */}
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <label className="flex w-full max-w-md items-center gap-2 rounded-pill border border-aria-line bg-white px-4 py-2.5 focus-within:border-aria-ink/30">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="text-aria-slate">
            <circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" />
          </svg>
          <input
            type="search"
            value={props.query}
            onChange={(e) => props.onQuery(e.target.value)}
            placeholder="Buscar por nombre, giro…"
            className="w-full bg-transparent text-sm placeholder:text-aria-slate focus:outline-none"
          />
        </label>
        <span className="text-xs text-aria-slate">
          {props.resultCount} {props.resultCount === 1 ? 'resultado' : 'resultados'}
        </span>
      </div>

      {/* Chip rows */}
      <div className="mt-5 flex flex-col gap-3">
        <ChipRow label="Giro">
          <Chip active={props.giros.length === 0} onClick={props.onClearGiros}>Todos</Chip>
          {props.availableGiros.map((g) => (
            <Chip
              key={g}
              active={props.giros.includes(g)}
              onClick={() => props.onToggleGiro(g)}
            >
              {g}
            </Chip>
          ))}
        </ChipRow>

        <ChipRow label="Piso">
          {pisoOptions.map((o) => (
            <Chip
              key={o.value}
              active={props.piso === o.value}
              onClick={() => props.onPiso(o.value)}
            >
              {o.label}
            </Chip>
          ))}
        </ChipRow>

        <ChipRow label="Estado">
          {estadoOptions.map((o) => (
            <Chip
              key={o.value}
              active={props.estado === o.value}
              onClick={() => props.onEstado(o.value)}
            >
              {o.label}
            </Chip>
          ))}
        </ChipRow>

        <ChipRow label="Ordenar">
          {ordenOptions.map((o) => (
            <Chip
              key={o.value}
              active={props.orden === o.value}
              onClick={() => props.onOrden(o.value)}
            >
              {o.label}
            </Chip>
          ))}
        </ChipRow>
      </div>
    </div>
  );
}

function ChipRow({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex flex-wrap items-center gap-2">
      <span className="eyebrow mr-2">{label}</span>
      {children}
    </div>
  );
}

function Chip({
  active, onClick, children,
}: {
  active: boolean;
  onClick: () => void;
  children: React.ReactNode;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={
        'rounded-pill border px-3 py-1 text-xs font-medium transition ' +
        (active
          ? 'border-aria-ink bg-aria-ink text-aria-bone'
          : 'border-aria-line bg-white text-aria-ink hover:border-aria-ink/40')
      }
    >
      {children}
    </button>
  );
}
