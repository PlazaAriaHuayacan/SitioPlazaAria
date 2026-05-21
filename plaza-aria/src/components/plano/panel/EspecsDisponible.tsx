import Image from 'next/image';
import type { UnidadComercialAgrupada } from '@/lib/plano/agrupar';

const mxn = new Intl.NumberFormat('es-MX', {
  style: 'currency',
  currency: 'MXN',
  maximumFractionDigits: 0,
});

export function EspecsDisponible({ unidad }: { unidad: UnidadComercialAgrupada }) {
  const leasing = unidad.registroPrincipal?.leasing;
  const fotos = unidad.fotos;

  return (
    <div className="space-y-6">
      {/* Gallery */}
      {fotos.length > 0 ? (
        <div className="grid grid-cols-2 gap-2">
          {fotos.slice(0, 4).map((f, i) => (
            <div
              key={f.url}
              className={`relative aspect-square overflow-hidden rounded-card bg-aria-sand ${i === 0 ? 'col-span-2 aspect-[4/3]' : ''}`}
            >
              <Image
                src={f.url}
                alt={f.alt ?? `Local ${unidad.loteIds.join('-')}`}
                fill
                className="object-cover"
                sizes="(max-width:768px) 100vw, 460px"
              />
            </div>
          ))}
        </div>
      ) : (
        <div className="aspect-[4/3] rounded-card bg-aria-sand flex items-center justify-center">
          <span className="text-aria-slate text-sm">Sin fotos aún</span>
        </div>
      )}

      {/* Stats grid */}
      <div className="grid grid-cols-2 gap-3">
        <Stat label="Superficie" value={`${unidad.m2Total.toFixed(2)} m²`} />
        {leasing?.frente !== undefined && <Stat label="Frente" value={`${leasing.frente.toFixed(1)} m`} />}
        {leasing?.renta ? (
          <Stat label="Renta" value={`${mxn.format(leasing.renta)} / mes`} />
        ) : (
          <Stat label="Renta" value="Pregunta por WhatsApp" />
        )}
        {leasing?.mantenimiento ? (
          <Stat label="Mantenimiento" value={`${mxn.format(leasing.mantenimiento)} / mes`} />
        ) : null}
      </div>

      {/* Instalaciones */}
      {leasing?.instalaciones && leasing.instalaciones.length > 0 && (
        <div>
          <p className="eyebrow mb-2">Instalaciones</p>
          <div className="flex flex-wrap gap-2">
            {leasing.instalaciones.map((i) => (
              <span key={i} className="rounded-pill border border-aria-line bg-white px-3 py-1 text-xs text-aria-ink">
                {i}
              </span>
            ))}
          </div>
        </div>
      )}

      {/* Combo notice */}
      {unidad.esCombinada && (
        <p className="text-xs text-aria-slate italic">
          Esta unidad se renta combinada ({unidad.loteIds.join(' + ')}). Si te interesa solo uno de los lotes, pregunta — puede ser posible separar.
        </p>
      )}

      {/* Placeholder for lead form — to be implemented in Sub-fase 2C */}
      <div className="rounded-card border-2 border-dashed border-aria-line p-6 text-center text-aria-slate text-sm">
        Aquí va el formulario de lead (Sub-fase 2C)
      </div>

      {/* Big WA CTA in the meantime */}
      <a
        href={`https://wa.me/5299983214614?text=${encodeURIComponent(`Hola, me interesa el Local ${unidad.loteIds.join('-')} de Plaza Aria.`)}`}
        target="_blank"
        rel="noreferrer noopener"
        className="btn-primary w-full justify-center"
      >
        Solicitar info por WhatsApp
      </a>
    </div>
  );
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-card border border-aria-line/60 bg-white p-4">
      <p className="text-[10px] uppercase tracking-[0.18em] text-aria-slate font-medium">{label}</p>
      <p className="mt-1 font-display text-lg text-aria-ink leading-tight">{value}</p>
    </div>
  );
}
