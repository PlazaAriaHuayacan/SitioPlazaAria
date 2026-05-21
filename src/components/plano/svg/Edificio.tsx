export function Edificio() {
  return (
    <>
      <defs>
        {/* Terracotta tile texture pattern */}
        <pattern id="roofTiles" x="0" y="0" width="20" height="8" patternUnits="userSpaceOnUse">
          <rect width="20" height="8" fill="#B85A3C" />
          <line x1="0" y1="4" x2="20" y2="4" stroke="#8E3F26" strokeWidth="0.5" opacity="0.6" />
          <line x1="10" y1="0" x2="10" y2="4" stroke="#8E3F26" strokeWidth="0.3" opacity="0.4" />
          <line x1="0" y1="0" x2="0" y2="4" stroke="#8E3F26" strokeWidth="0.3" opacity="0.4" />
        </pattern>

        {/* Concrete formwork texture for tower */}
        <pattern id="concreteFormwork" x="0" y="0" width="70" height="40" patternUnits="userSpaceOnUse">
          <rect width="70" height="40" fill="#5A544A" />
          <line x1="20" y1="0" x2="20" y2="40" stroke="#4A443C" strokeWidth="0.8" opacity="0.5" />
          <line x1="50" y1="0" x2="50" y2="40" stroke="#4A443C" strokeWidth="0.8" opacity="0.5" />
        </pattern>
      </defs>

      {/* 1. Base concrete platform */}
      <rect x="100" y="120" width="1200" height="420" fill="#EDE7DD" />

      {/* 2. Side walls – left and right depth cues */}
      <rect x="86" y="120" width="14" height="420" fill="#2E2820" opacity="0.7" />
      <rect x="1300" y="120" width="14" height="420" fill="#2E2820" opacity="0.7" />

      {/* 3. Piso 2 floor slab */}
      <rect x="100" y="120" width="1200" height="10" fill="#1F1A14" />

      {/* White balcony railing hint (horizontal lines near bottom of piso 2) */}
      <line x1="100" y1="293" x2="1300" y2="293" stroke="#FAF6F0" strokeWidth="2" opacity="0.8" />
      <line x1="100" y1="298" x2="1300" y2="298" stroke="#FAF6F0" strokeWidth="1" opacity="0.5" />

      {/* 4. Piso 1 floor slab */}
      <rect x="100" y="300" width="1200" height="10" fill="#1F1A14" />

      {/* 5. Roof – terracotta tile */}
      <polygon
        points="100,120 1300,120 1280,90 120,90"
        fill="url(#roofTiles)"
      />
      {/* Roof front lip / fascia */}
      <line x1="100" y1="120" x2="1300" y2="120" stroke="#8E3F26" strokeWidth="2" />
      <line x1="120" y1="90" x2="1280" y2="90" stroke="#8E3F26" strokeWidth="1.5" />

      {/* 6. White facade band with wordmark */}
      <rect x="100" y="72" width="1200" height="18" fill="#FAF6F0" />
      <text
        x="145"
        y="84"
        fontFamily="var(--font-display), serif"
        fontSize="11"
        fill="#1F1A14"
        letterSpacing="0.3em"
        fontWeight="600"
      >
        PLAZA ARIA
      </text>

      {/* 7. Central core tower – stairs + elevator */}
      {/* Tower body */}
      <rect x="665" y="66" width="70" height="454" fill="url(#concreteFormwork)" />
      {/* Top cap (slightly lighter) */}
      <rect x="665" y="66" width="70" height="12" fill="#7A746A" />
      {/* Bottom base */}
      <rect x="665" y="506" width="70" height="14" fill="#4A443C" />

      {/* Stairs pictogram in lower half of tower */}
      <line x1="678" y1="400" x2="722" y2="400" stroke="#FAF6F0" strokeWidth="1.5" opacity="0.4" />
      <line x1="678" y1="412" x2="722" y2="412" stroke="#FAF6F0" strokeWidth="1.5" opacity="0.4" />
      <line x1="678" y1="424" x2="722" y2="424" stroke="#FAF6F0" strokeWidth="1.5" opacity="0.4" />
      <line x1="678" y1="436" x2="722" y2="436" stroke="#FAF6F0" strokeWidth="1.5" opacity="0.4" />
      <line x1="678" y1="448" x2="722" y2="448" stroke="#FAF6F0" strokeWidth="1.5" opacity="0.4" />
      {/* Stair vertical divider */}
      <line x1="700" y1="396" x2="700" y2="456" stroke="#FAF6F0" strokeWidth="1" opacity="0.3" />

      {/* Elevator door outline */}
      <rect x="674" y="170" width="22" height="30" fill="none" stroke="#FAF6F0" strokeWidth="1" opacity="0.35" />
      <line x1="685" y1="170" x2="685" y2="200" stroke="#FAF6F0" strokeWidth="0.8" opacity="0.3" />

      {/* Tower separation lines – where it intersects the floor slabs */}
      <line x1="665" y1="130" x2="735" y2="130" stroke="#1F1A14" strokeWidth="2" />
      <line x1="665" y1="300" x2="735" y2="300" stroke="#1F1A14" strokeWidth="2" />
    </>
  );
}
