/** Draws a stylized coconut palm at (cx, baseY) with the given scale factor */
function Palm({ cx, baseY, scale = 1 }: { cx: number; baseY: number; scale?: number }) {
  const trunkH = 60 * scale;
  const trunkW = 6 * scale;
  const tx = cx - trunkW / 2;
  const ty = baseY - trunkH;

  // Six fronds radiating out from trunk top
  const fronds = [
    // [dx, dy, angle description]
    [-28 * scale, -18 * scale, -14 * scale, -32 * scale],
    [-18 * scale, -28 * scale, 2 * scale, -38 * scale],
    [6 * scale, -32 * scale, 22 * scale, -22 * scale],
    [24 * scale, -20 * scale, 30 * scale, -6 * scale],
    [18 * scale, -6 * scale, 14 * scale, 10 * scale],
    [-8 * scale, 8 * scale, -20 * scale, 4 * scale],
  ];

  return (
    <>
      {/* Trunk */}
      <rect x={tx} y={ty} width={trunkW} height={trunkH} fill="#8E3F26" rx={2} />
      {/* Fronds */}
      {fronds.map(([x1, y1, x2, y2], i) => (
        <ellipse
          key={i}
          cx={cx + (x1 + x2) / 2}
          cy={ty + (y1 + y2) / 2}
          rx={Math.abs(x2 - x1) / 2 + 6 * scale}
          ry={5 * scale}
          fill={i % 2 === 0 ? '#4A5D3A' : '#324024'}
          opacity={0.9}
          transform={`rotate(${Math.atan2(
            Number(y2) - Number(y1),
            Number(x2) - Number(x1),
          ) * (180 / Math.PI)}, ${cx + (Number(x1) + Number(x2)) / 2}, ${ty + (Number(y1) + Number(y2)) / 2})`}
        />
      ))}
      {/* Crown dot */}
      <circle cx={cx} cy={ty - 2} r={4 * scale} fill="#4A5D3A" />
    </>
  );
}

export function Decoraciones() {
  // Positions for front palms (skip 700 range because of tower, slight offsets)
  const frontPalms = [195, 420, 680, 920, 1150];
  // Positions for camellón palms
  const camepalms = Array.from({ length: 14 }, (_, i) => 60 + i * 95);

  return (
    <>
      <defs>
        {/* Sky gradient */}
        <linearGradient id="skyGrad" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#E8F4F8" />
          <stop offset="100%" stopColor="#FAF6F0" />
        </linearGradient>
      </defs>

      {/* 1. Sky / background */}
      <rect x="0" y="0" width="1400" height="90" fill="url(#skyGrad)" />

      {/* 2. Sidewalk between parking and camellón */}
      <rect x="0" y="540" width="1400" height="40" fill="#E7DFD1" />

      {/* 3. Parking lot ground */}
      <rect x="100" y="480" width="1200" height="60" fill="#D8D0C4" />

      {/* Diagonal parking stripes (~16 spots) */}
      {Array.from({ length: 17 }, (_, i) => {
        const x = 110 + i * 72;
        return (
          <line
            key={i}
            x1={x}
            y1={480}
            x2={x - 30}
            y2={540}
            stroke="#FAF6F0"
            strokeWidth="1.5"
            opacity={0.55}
          />
        );
      })}

      {/* Blue plinth wall */}
      <rect x="100" y="470" width="1200" height="10" fill="#3D5A80" />

      {/* 4. Front palm trees */}
      {frontPalms.map((cx) => (
        <Palm key={cx} cx={cx} baseY={480} scale={1} />
      ))}

      {/* 5. Av. Huayacán camellón (green median) */}
      <rect x="0" y="580" width="1400" height="40" fill="#4A5D3A" />

      {/* Camellón edge lines */}
      <line x1="0" y1="580" x2="1400" y2="580" stroke="#324024" strokeWidth="2" />
      <line x1="0" y1="620" x2="1400" y2="620" stroke="#324024" strokeWidth="2" />

      {/* Small palms along camellón */}
      {camepalms.map((cx) => (
        <Palm key={cx} cx={cx} baseY={585} scale={0.5} />
      ))}

      {/* 6. Road asphalt */}
      <rect x="0" y="620" width="1400" height="60" fill="#5A544A" />

      {/* Lane divider dashed lines */}
      {Array.from({ length: 28 }, (_, i) => (
        <line
          key={i}
          x1={i * 50}
          y1={650}
          x2={i * 50 + 30}
          y2={650}
          stroke="#FAF6F0"
          strokeWidth="2"
          opacity={0.6}
        />
      ))}

      {/* Road edge lines */}
      <line x1="0" y1="625" x2="1400" y2="625" stroke="#FAF6F0" strokeWidth="1" opacity="0.3" />
      <line x1="0" y1="678" x2="1400" y2="678" stroke="#FAF6F0" strokeWidth="1" opacity="0.3" />

      {/* Street label */}
      <text
        x="700"
        y="670"
        textAnchor="middle"
        fontFamily="var(--font-display), serif"
        fontSize="10"
        fill="#FAF6F0"
        letterSpacing="0.25em"
        opacity={0.7}
      >
        AV. HUAYACÁN
      </text>
    </>
  );
}
