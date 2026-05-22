/**
 * Decoraciones — minimal site context below the building plan.
 *
 * Philosophy: the floor-plan is the hero — everything here recedes.
 * Layers (back to front):
 *   1. Page background (warm off-white)
 *   2. Soft shadow below building frame
 *   3. Ground apron (parking + sidewalk)
 *   4. Landscaping strip
 *   5. Av. Huayacán road
 */
export function Decoraciones() {
  // Vertical landmarks that must stay below the building frame (FRAME_BOT = 430)
  const APRON_Y   = 432;
  const PARK_Y    = 452;
  const PARK_H    = 46;
  const GREEN_Y   = PARK_Y + PARK_H;        // 498
  const GREEN_H   = 14;
  const ROAD_Y    = GREEN_Y + GREEN_H;       // 512
  const ROAD_H    = 88;

  return (
    <>
      <defs>
        {/* Warm page background */}
        <linearGradient id="pageBg" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%"   stopColor="#F5F0EA" />
          <stop offset="100%" stopColor="#EDE7DF" />
        </linearGradient>

        {/* Soft drop shadow below frame */}
        <linearGradient id="shadowGrad" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%"   stopColor="#1F1A14" stopOpacity="0.08" />
          <stop offset="100%" stopColor="#1F1A14" stopOpacity="0" />
        </linearGradient>
      </defs>

      {/* 1 · Page background */}
      <rect x="0" y="0" width="1400" height="600" fill="url(#pageBg)" />

      {/* 2 · Drop shadow under building frame */}
      <rect x="52" y="430" width="1296" height="18" fill="url(#shadowGrad)" />

      {/* 3 · Ground apron — concrete sidewalk */}
      <rect x="52" y={APRON_Y} width="1296" height={PARK_Y - APRON_Y} fill="#E0D9D0" />

      {/* Parking area */}
      <rect x="52" y={PARK_Y} width="1296" height={PARK_H} fill="#D4CEC6" />

      {/* Parking bay lines */}
      {Array.from({ length: 22 }, (_, i) => {
        const px = 52 + 12 + i * 56;
        return (
          <line
            key={i}
            x1={px} y1={PARK_Y + 3}
            x2={px} y2={PARK_Y + PARK_H - 3}
            stroke="#FAF6F0" strokeWidth="0.8" opacity={0.55}
          />
        );
      })}

      {/* Parking label */}
      <text
        x="700" y={PARK_Y + PARK_H / 2 + 3}
        textAnchor="middle"
        fontFamily="var(--font-body), sans-serif"
        fontSize="7" fill="#5A544A" opacity="0.35" letterSpacing="0.2em"
      >
        ESTACIONAMIENTO TECHADO
      </text>

      {/* 4 · Landscaping strip */}
      <rect x="0" y={GREEN_Y} width="1400" height={GREEN_H} fill="#4A6340" opacity="0.75" />
      <line x1="0" y1={GREEN_Y}          x2="1400" y2={GREEN_Y}          stroke="#2E3E26" strokeWidth="0.8" opacity="0.5" />
      <line x1="0" y1={GREEN_Y + GREEN_H} x2="1400" y2={GREEN_Y + GREEN_H} stroke="#2E3E26" strokeWidth="0.6" opacity="0.3" />

      {/* 5 · Av. Huayacán */}
      <rect x="0" y={ROAD_Y} width="1400" height={ROAD_H} fill="#6E6560" />

      {/* Road edge rules */}
      <line x1="0" y1={ROAD_Y + 3}           x2="1400" y2={ROAD_Y + 3}           stroke="#FAF6F0" strokeWidth="0.8" opacity="0.18" />
      <line x1="0" y1={ROAD_Y + ROAD_H - 3}  x2="1400" y2={ROAD_Y + ROAD_H - 3}  stroke="#FAF6F0" strokeWidth="0.8" opacity="0.18" />

      {/* Centre lane dashes */}
      {Array.from({ length: 24 }, (_, i) => (
        <line
          key={i}
          x1={i * 62}      y1={ROAD_Y + ROAD_H / 2}
          x2={i * 62 + 38} y2={ROAD_Y + ROAD_H / 2}
          stroke="#FAF6F0" strokeWidth="1.2" opacity="0.4"
        />
      ))}

      {/* Street name */}
      <text
        x="700" y={ROAD_Y + ROAD_H / 2 + 18}
        textAnchor="middle"
        fontFamily="var(--font-display), serif"
        fontSize="8" fill="#FAF6F0" letterSpacing="0.28em" opacity="0.55"
      >
        AV. HUAYACÁN
      </text>
    </>
  );
}
