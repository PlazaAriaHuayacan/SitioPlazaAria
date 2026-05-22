/**
 * Decoraciones — ambient environment for the building elevation.
 *
 * Layers (back to front):
 *   1. Sky gradient (top)
 *   2. Ground plane / sidewalk (warm grey)
 *   3. Parking strip with bay markings
 *   4. Thin green landscape buffer
 *   5. Av. Huayacán road with lane dividers
 */
export function Decoraciones() {
  return (
    <>
      <defs>
        {/* Sky gradient — warm blue-white → warm bone at horizon */}
        <linearGradient id="skyGrad" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%"   stopColor="#DCE8F0" />
          <stop offset="55%"  stopColor="#EEF3F6" />
          <stop offset="100%" stopColor="#F5EFE6" />
        </linearGradient>

        {/* Ground gradient — slightly lighter at top */}
        <linearGradient id="groundGrad" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%"   stopColor="#E2DBD2" />
          <stop offset="100%" stopColor="#D5CEC5" />
        </linearGradient>
      </defs>

      {/* 1 · Sky */}
      <rect x="0" y="0" width="1400" height="560" fill="url(#skyGrad)" />

      {/* Subtle cloud-haze band near horizon */}
      <rect x="0" y="400" width="1400" height="40" fill="#F5EFE6" opacity="0.35" />

      {/* 2 · Sidewalk / ground apron directly in front of building */}
      <rect x="74" y="432" width="1252" height="28" fill="url(#groundGrad)" />
      {/* Kerb line */}
      <line x1="74" y1="460" x2="1326" y2="460" stroke="#B8B0A6" strokeWidth="1.5" opacity="0.7" />

      {/* 3 · Parking area */}
      <rect x="74" y="460" width="1252" height="50" fill="#D8D1C8" />

      {/* Parking bay dividers */}
      {Array.from({ length: 21 }, (_, i) => {
        const px = 74 + 8 + i * 57;
        return (
          <line
            key={i}
            x1={px} y1={463}
            x2={px} y2={507}
            stroke="#FAF6F0" strokeWidth="1" opacity={0.5}
          />
        );
      })}

      {/* Parking label */}
      <text
        x="700" y="487"
        textAnchor="middle"
        fontFamily="var(--font-body), sans-serif"
        fontSize="7.5" fill="#5A544A" opacity="0.4" letterSpacing="0.22em"
      >
        ESTACIONAMIENTO TECHADO
      </text>

      {/* 4 · Green landscape buffer */}
      <rect x="0" y="510" width="1400" height="18" fill="#3D5030" opacity="0.8" />
      <line x1="0" y1="510" x2="1400" y2="510" stroke="#2A3820" strokeWidth="1" opacity="0.5" />
      <line x1="0" y1="528" x2="1400" y2="528" stroke="#2A3820" strokeWidth="0.8" opacity="0.35" />

      {/* 5 · Av. Huayacán road */}
      <rect x="0" y="528" width="1400" height="72" fill="#706860" />

      {/* Road edge lines */}
      <line x1="0" y1="531" x2="1400" y2="531" stroke="#FAF6F0" strokeWidth="1" opacity="0.2" />
      <line x1="0" y1="596" x2="1400" y2="596" stroke="#FAF6F0" strokeWidth="1" opacity="0.2" />

      {/* Centre lane dashes */}
      {Array.from({ length: 22 }, (_, i) => (
        <line
          key={i}
          x1={i * 65}     y1={562}
          x2={i * 65 + 40} y2={562}
          stroke="#FAF6F0" strokeWidth="1.5" opacity="0.45"
        />
      ))}

      {/* Street name */}
      <text
        x="700" y="580"
        textAnchor="middle"
        fontFamily="var(--font-display), serif"
        fontSize="8.5" fill="#FAF6F0" letterSpacing="0.3em" opacity="0.6"
      >
        AV. HUAYACÁN
      </text>
    </>
  );
}
