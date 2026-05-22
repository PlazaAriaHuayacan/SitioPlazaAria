/**
 * Decoraciones — ambient background for the Plano interactivo.
 *
 * Draws (in order, bottom-to-top in z):
 *   1. Full-canvas bone gradient
 *   2. Parking lot strip below the building
 *   3. Thin green landscape/jardín band
 *   4. Av. Huayacán road with lane markings and street label
 */
export function Decoraciones() {
  return (
    <>
      <defs>
        <linearGradient id="bgGrad" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%"   stopColor="#F0EAE0" />
          <stop offset="45%"  stopColor="#FAF6F0" />
          <stop offset="100%" stopColor="#F5EFE6" />
        </linearGradient>
      </defs>

      {/* 1. Canvas background */}
      <rect x="0" y="0" width="1400" height="560" fill="url(#bgGrad)" />

      {/* 2. Parking area — between building bottom (408) and landscape (460) */}
      <rect x="100" y="408" width="1200" height="52" fill="#DDD8CF" />

      {/* Vertical parking bay dividers */}
      {Array.from({ length: 20 }, (_, i) => {
        const x = 100 + 6 + i * 59;
        return (
          <line
            key={i}
            x1={x} y1={412}
            x2={x} y2={456}
            stroke="#FAF6F0"
            strokeWidth="1"
            opacity={0.55}
          />
        );
      })}

      {/* Parking label */}
      <text
        x="700" y="436"
        textAnchor="middle"
        fontFamily="var(--font-body), sans-serif"
        fontSize="7.5" fill="#5A544A" opacity="0.45" letterSpacing="0.22em"
      >
        ESTACIONAMIENTO TECHADO
      </text>

      {/* 3. Green landscape strip */}
      <rect x="0" y="460" width="1400" height="25" fill="#4A5D3A" opacity="0.75" />
      <line x1="0" y1="460" x2="1400" y2="460" stroke="#324024" strokeWidth="1" opacity="0.6" />
      <line x1="0" y1="485" x2="1400" y2="485" stroke="#324024" strokeWidth="1" opacity="0.4" />

      {/* 4. Road */}
      <rect x="0" y="485" width="1400" height="75" fill="#6B6358" />

      {/* Road edge lines */}
      <line x1="0" y1="488" x2="1400" y2="488" stroke="#FAF6F0" strokeWidth="1" opacity="0.25" />
      <line x1="0" y1="557" x2="1400" y2="557" stroke="#FAF6F0" strokeWidth="1" opacity="0.25" />

      {/* Dashed centre lane */}
      {Array.from({ length: 22 }, (_, i) => (
        <line
          key={i}
          x1={i * 65} y1={522}
          x2={i * 65 + 40} y2={522}
          stroke="#FAF6F0" strokeWidth="1.5" opacity="0.45"
        />
      ))}

      {/* Street label */}
      <text
        x="700" y="545"
        textAnchor="middle"
        fontFamily="var(--font-display), serif"
        fontSize="9" fill="#FAF6F0" letterSpacing="0.28em" opacity="0.65"
      >
        AV. HUAYACÁN
      </text>
    </>
  );
}
