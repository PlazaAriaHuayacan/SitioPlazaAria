/** Real catalog from the Plaza Aria architectural plan. One entry per lot. */
export interface SeedLote {
  numeroLocal: string;
  unidadComercial: string;
  piso: '1' | '2';
  ordenPlano: number;
  m2: number;
}

export const LOTES: SeedLote[] = [
  // ── Piso 1 ────────────────────────────────────────────────────────
  { numeroLocal: 'L1',  unidadComercial: 'L1',     piso: '1', ordenPlano: 1,  m2: 158.21 },
  { numeroLocal: 'L2',  unidadComercial: 'L2',     piso: '1', ordenPlano: 2,  m2: 92.20 },
  { numeroLocal: 'L3',  unidadComercial: 'L3',     piso: '1', ordenPlano: 3,  m2: 77.05 },
  { numeroLocal: 'L4',  unidadComercial: 'L4',     piso: '1', ordenPlano: 4,  m2: 60.35 },
  { numeroLocal: 'L5',  unidadComercial: 'L5',     piso: '1', ordenPlano: 5,  m2: 60.35 },
  { numeroLocal: 'L6',  unidadComercial: 'L6-7',   piso: '1', ordenPlano: 6,  m2: 60.35 },
  { numeroLocal: 'L7',  unidadComercial: 'L6-7',   piso: '1', ordenPlano: 6,  m2: 60.35 },
  { numeroLocal: 'L8',  unidadComercial: 'L8-9',   piso: '1', ordenPlano: 7,  m2: 60.35 },
  { numeroLocal: 'L9',  unidadComercial: 'L8-9',   piso: '1', ordenPlano: 7,  m2: 60.35 },
  { numeroLocal: 'L10', unidadComercial: 'L10',    piso: '1', ordenPlano: 8,  m2: 60.35 },
  { numeroLocal: 'L11', unidadComercial: 'L11',    piso: '1', ordenPlano: 9,  m2: 60.35 },
  { numeroLocal: 'L12', unidadComercial: 'L12',    piso: '1', ordenPlano: 10, m2: 79.35 },
  { numeroLocal: 'L13', unidadComercial: 'L13',    piso: '1', ordenPlano: 11, m2: 90.89 },
  { numeroLocal: 'L14', unidadComercial: 'L14-15', piso: '1', ordenPlano: 12, m2: 114.79 },
  { numeroLocal: 'L15', unidadComercial: 'L14-15', piso: '1', ordenPlano: 12, m2: 114.79 },
  // ── Piso 2 ────────────────────────────────────────────────────────
  { numeroLocal: 'L16', unidadComercial: 'L16-17-18', piso: '2', ordenPlano: 1,  m2: 60.92 },
  { numeroLocal: 'L17', unidadComercial: 'L16-17-18', piso: '2', ordenPlano: 1,  m2: 60.92 },
  { numeroLocal: 'L18', unidadComercial: 'L16-17-18', piso: '2', ordenPlano: 1,  m2: 60.92 },
  { numeroLocal: 'L19', unidadComercial: 'L19',       piso: '2', ordenPlano: 2,  m2: 60.35 },
  { numeroLocal: 'L20', unidadComercial: 'L20',       piso: '2', ordenPlano: 3,  m2: 60.35 },
  { numeroLocal: 'L21', unidadComercial: 'L21',       piso: '2', ordenPlano: 4,  m2: 60.35 },
  { numeroLocal: 'L22', unidadComercial: 'L22',       piso: '2', ordenPlano: 5,  m2: 60.35 },
  { numeroLocal: 'L23', unidadComercial: 'L23',       piso: '2', ordenPlano: 6,  m2: 60.35 },
  { numeroLocal: 'L24', unidadComercial: 'L24',       piso: '2', ordenPlano: 7,  m2: 60.35 },
  { numeroLocal: 'L25', unidadComercial: 'L25',       piso: '2', ordenPlano: 8,  m2: 60.35 },
  { numeroLocal: 'L26', unidadComercial: 'L26',       piso: '2', ordenPlano: 9,  m2: 60.35 },
  { numeroLocal: 'L27', unidadComercial: 'L27',       piso: '2', ordenPlano: 10, m2: 60.35 },
  { numeroLocal: 'L28', unidadComercial: 'L28-29',    piso: '2', ordenPlano: 11, m2: 60.35 },
  { numeroLocal: 'L29', unidadComercial: 'L28-29',    piso: '2', ordenPlano: 11, m2: 60.35 },
  { numeroLocal: 'L30', unidadComercial: 'L30-31',    piso: '2', ordenPlano: 12, m2: 61.14 },
  { numeroLocal: 'L31', unidadComercial: 'L30-31',    piso: '2', ordenPlano: 12, m2: 61.14 },
];
