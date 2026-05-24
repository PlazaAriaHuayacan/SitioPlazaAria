'use client';

import { useRef, useState, useEffect, useMemo, Suspense } from 'react';
import { useSearchParams } from 'next/navigation';
import { motion } from 'framer-motion';

import { PlanoTooltip } from './PlanoTooltip';
import { PanelLateral } from './PanelLateral';
import { HOTSPOTS, findUnit, IMG_W, IMG_H } from '@/lib/plano/hotspots';
import type { UnidadComercialAgrupada } from '@/lib/plano/agrupar';

// ── Colors ────────────────────────────────────────────────────────────────────
const TERRA        = '#B85A3C';
const TERRA_LIGHT  = '#CC7055';
const TERRA_DARK   = '#8E3F26';
const INK          = '#1F1A14';

type Props = {
  unidades: UnidadComercialAgrupada[];
  onUnidadSelect?: (id: string) => void;
  onUnidadHover?: (id: string | null) => void;
  selectedUnidadId?: string | null;
};

function IsometricoInner({
  unidades,
  onUnidadSelect,
  onUnidadHover,
  selectedUnidadId = null,
}: Props) {
  const containerRef = useRef<HTMLDivElement>(null);
  const videoRef     = useRef<HTMLVideoElement>(null);
  const params       = useSearchParams();
  const debug        = params?.get('debug') === '1';

  const [hoveredUnit,   setHoveredUnit]   = useState<UnidadComercialAgrupada | null>(null);
  const [selectedUnit,  setSelectedUnit]  = useState<UnidadComercialAgrupada | null>(null);
  const [mousePos,      setMousePos]      = useState<{ x: number; y: number } | null>(null);
  const [videoEnded,    setVideoEnded]    = useState(false);
  const [pisoFilter,    setPisoFilter]    = useState<'all' | '1' | '2'>('all');

  // Precompute unit map: loteKey → unit
  const unitByLote = useMemo(() => {
    const map = new Map<string, UnidadComercialAgrupada>();
    for (const u of unidades) {
      for (const id of u.loteIds) map.set(id, u);
    }
    return map;
  }, [unidades]);

  // After video plays once, crossfade to poster
  useEffect(() => {
    const v = videoRef.current;
    if (!v) return;
    const onEnd = () => setVideoEnded(true);
    v.addEventListener('ended', onEnd);
    return () => v.removeEventListener('ended', onEnd);
  }, []);

  const handleMouseMove = (e: React.MouseEvent) => {
    const rect = containerRef.current?.getBoundingClientRect();
    if (!rect) return;
    setMousePos({ x: e.clientX - rect.left, y: e.clientY - rect.top });
  };

  const handleHotspotEnter = (unit: UnidadComercialAgrupada | null) => {
    setHoveredUnit(unit);
    onUnidadHover?.(unit?.id ?? null);
  };

  const handleHotspotClick = (unit: UnidadComercialAgrupada | null) => {
    if (!unit) return;
    setSelectedUnit(unit);
    onUnidadSelect?.(unit.id);
  };

  // Resolve hotspots to units once
  const resolvedHotspots = useMemo(
    () => HOTSPOTS.map((h) => ({ ...h, unit: findUnit(h, unidades) })),
    [unidades],
  );

  return (
    <div
      ref={containerRef}
      className="relative w-full select-none"
      onMouseMove={handleMouseMove}
      onMouseLeave={() => setMousePos(null)}
    >
      {/* ── Piso filter ────────────────────────────────────────────────────── */}
      <div
        className="absolute top-3 left-3 z-10 inline-flex rounded-pill border border-aria-line bg-white shadow-card overflow-hidden"
        role="tablist"
        aria-label="Filtrar por piso"
      >
        {(['all', '1', '2'] as const).map((p) => (
          <button
            key={p}
            type="button"
            role="tab"
            aria-selected={pisoFilter === p}
            onClick={() => setPisoFilter(p)}
            className={
              'px-4 py-2 text-xs font-medium transition ' +
              (pisoFilter === p
                ? 'bg-aria-ink text-aria-bone'
                : 'bg-transparent text-aria-ink hover:bg-aria-ink/5')
            }
          >
            {p === 'all' ? 'Todos' : `Piso ${p}`}
          </button>
        ))}
      </div>

      {/* ── Illustration container ─────────────────────────────────────────── */}
      <div className="relative w-full overflow-hidden rounded-2xl bg-[#F0EDE8]"
           style={{ aspectRatio: `${IMG_W} / ${IMG_H}` }}>

        {/* Static poster (always below video) */}
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img
          src="/plano.png"
          alt="Plano isométrico de Plaza Aria"
          className="absolute inset-0 w-full h-full object-contain"
          draggable={false}
        />

        {/* Video — plays once, then fades out revealing the poster */}
        <motion.video
          ref={videoRef}
          src="/plano.mp4"
          autoPlay
          muted
          playsInline
          preload="auto"
          className="absolute inset-0 w-full h-full object-contain"
          initial={{ opacity: 1 }}
          animate={{ opacity: videoEnded ? 0 : 1 }}
          transition={{ duration: 0.8, ease: 'easeOut' }}
          aria-hidden
        />

        {/* ── SVG hotspot overlay ──────────────────────────────────────────── */}
        <svg
          viewBox={`0 0 ${IMG_W} ${IMG_H}`}
          className="absolute inset-0 w-full h-full"
          preserveAspectRatio="xMidYMid meet"
          aria-hidden={!debug}
          role={debug ? 'img' : undefined}
        >
          {resolvedHotspots.map((h, i) => {
            const unit    = h.unit;
            const isDisp  = unit?.estado === 'Disponible';
            const isProx  = unit?.estado === 'Proximamente';
            const isHov   = hoveredUnit?.id === unit?.id;
            const isSel   = selectedUnit?.id === unit?.id || selectedUnidadId === unit?.id;
            const dimmed  = pisoFilter !== 'all' && h.piso !== pisoFilter;

            // Points string for SVG polygon
            const pts = h.points.map(([x, y]) => `${x},${y}`).join(' ');

            // Fill logic:
            // Available → terracotta
            // Occupied  → transparent (just outline on hover/select)
            // Próximo   → very subtle
            const baseFill = isDisp
              ? TERRA
              : isProx
              ? 'rgba(180,160,140,0.15)'
              : 'transparent';

            const fillOpacity = dimmed
              ? 0
              : isSel
              ? 0.85
              : isHov
              ? 0.7
              : isDisp
              ? 0.55
              : 0;

            const strokeColor = isSel
              ? INK
              : isHov
              ? TERRA_DARK
              : debug
              ? 'rgba(100,100,200,0.6)'
              : 'transparent';

            const strokeW = isSel ? 4 : isHov ? 2 : debug ? 1 : 0;

            return (
              <g key={i} style={{ cursor: unit ? 'pointer' : 'default' }}>
                {/* Pulse glow for available units */}
                {isDisp && !dimmed && (
                  <motion.polygon
                    points={pts}
                    fill={TERRA_LIGHT}
                    stroke="none"
                    animate={{ opacity: [0, 0.25, 0] }}
                    transition={{
                      duration: 2.6,
                      repeat: Infinity,
                      ease: 'easeInOut',
                      repeatDelay: 1.0 + i * 0.12,
                    }}
                    pointerEvents="none"
                  />
                )}

                {/* Main interactive polygon */}
                <polygon
                  data-testid={unit ? `hotspot-${unit.id}` : undefined}
                  points={pts}
                  fill={unit ? baseFill : 'transparent'}
                  fillOpacity={fillOpacity}
                  stroke={strokeColor}
                  strokeWidth={strokeW}
                  strokeLinejoin="round"
                  style={{ opacity: dimmed ? 0.15 : 1 }}
                  onMouseEnter={() => handleHotspotEnter(unit)}
                  onMouseLeave={() => handleHotspotEnter(null)}
                  onClick={() => handleHotspotClick(unit)}
                />

                {/* Debug label */}
                {debug && unit && (
                  <text
                    x={h.points.reduce((s, [px]) => s + px, 0) / h.points.length}
                    y={h.points.reduce((s, [, py]) => s + py, 0) / h.points.length}
                    textAnchor="middle"
                    dominantBaseline="middle"
                    fontSize={24}
                    fill={isDisp ? '#fff' : '#333'}
                    fontFamily="sans-serif"
                    pointerEvents="none"
                  >
                    {h.loteKeys.join('+')}
                  </text>
                )}
              </g>
            );
          })}
        </svg>

        {/* Debug legend */}
        {debug && (
          <div className="absolute bottom-3 left-3 bg-white/90 text-xs p-2 rounded font-mono text-slate-700 shadow z-20">
            🔵 = hotspot outline &nbsp;|&nbsp; ?debug=1 active
          </div>
        )}
      </div>

      <PlanoTooltip unidad={hoveredUnit} position={mousePos} />
      <PanelLateral unidad={selectedUnit} onClose={() => setSelectedUnit(null)} />
    </div>
  );
}

// Wrap with Suspense (required by useSearchParams in Next.js 14 App Router)
export function IsometricoInteractivo(props: Props) {
  return (
    <Suspense fallback={
      <div className="relative w-full overflow-hidden rounded-2xl bg-[#F0EDE8]"
           style={{ aspectRatio: '2528 / 1684' }}>
        {/* eslint-disable-next-line @next/next/no-img-element */}
        <img src="/plano.png" alt="Plano Plaza Aria" className="w-full h-full object-contain" />
      </div>
    }>
      <IsometricoInner {...props} />
    </Suspense>
  );
}
