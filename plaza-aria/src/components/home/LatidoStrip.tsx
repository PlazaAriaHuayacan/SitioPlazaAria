interface LatidoStripProps {
  abiertosCnt: number;
  clasesCnt: number;
  disponiblesCnt: number;
}

function Dot({ color }: { color: string }) {
  return (
    <span
      className="inline-block h-2 w-2 rounded-full flex-shrink-0"
      style={{ backgroundColor: color }}
      aria-hidden="true"
    />
  );
}

export function LatidoStrip({ abiertosCnt, clasesCnt, disponiblesCnt }: LatidoStripProps) {
  const items: Array<{ color: string; label: string }> = [];

  if (abiertosCnt > 0) {
    items.push({
      color: '#4A5D3A', // aria-olive
      label: `${abiertosCnt} ${abiertosCnt === 1 ? 'local abierto' : 'locales abiertos'} ahora`,
    });
  }

  if (clasesCnt > 0) {
    items.push({
      color: '#B85A3C', // aria-terracotta
      label: `${clasesCnt} ${clasesCnt === 1 ? 'clase' : 'clases'} esta semana`,
    });
  }

  if (disponiblesCnt > 0) {
    items.push({
      color: '#C99857', // aria-gold
      label: `${disponiblesCnt} ${disponiblesCnt === 1 ? 'local disponible' : 'locales disponibles'}`,
    });
  }

  if (items.length === 0) return null;

  return (
    <div
      className="border-y border-aria-line"
      style={{ backgroundColor: 'rgba(241,233,220,0.6)' }}
      aria-label="Estado en tiempo real"
    >
      <div className="container-aria py-3">
        <ul className="flex flex-wrap items-center gap-x-6 gap-y-2">
          {items.map((item, i) => (
            <li
              key={i}
              className="flex items-center gap-2 text-xs font-medium uppercase tracking-[0.14em] text-aria-slate"
            >
              <Dot color={item.color} />
              {item.label}
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
}
