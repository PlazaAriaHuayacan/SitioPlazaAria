// Domain types matching the Airtable schema documented in
// plaza-aria/docs/airtable-schema.md
//
// These types describe the shape of records AFTER the Airtable
// client has normalized them. Raw Airtable responses use slightly
// different shapes (attachments wrapped in arrays, link fields as
// arrays of IDs) — those concerns belong to the client, not here.

export type Giro =
  | 'Restaurante'
  | 'Belleza'
  | 'Fitness'
  | 'Educación'
  | 'Hogar'
  | 'Servicios'
  | 'Otro';

export type EstadoLocal = 'Ocupado' | 'Disponible' | 'Proximamente';

export type Piso = '1' | '2';

export type Instalacion = 'Agua' | 'Luz' | 'A/C' | 'Drenaje' | 'Gas';

export type DiaSemana =
  | 'Lunes'
  | 'Martes'
  | 'Miercoles'
  | 'Jueves'
  | 'Viernes'
  | 'Sabado'
  | 'Domingo';

/**
 * An open-close block for a single day, in plaza-local time (HH:MM 24h).
 * `null` means closed.
 */
export interface HorarioDia {
  open: string | null;
  close: string | null;
}

export type HorariosSemana = Record<DiaSemana, HorarioDia>;

export interface Foto {
  url: string;
  width?: number;
  height?: number;
  alt?: string;
}

export interface Local {
  id: string;
  slug: string;
  nombre: string;
  giro: Giro;
  estado: EstadoLocal;
  piso: Piso;
  numeroLocal: string;
  // Floor plan coords (used in Fase 2; surfaced now so the schema is stable)
  coord?: { x: number; y: number; ancho: number; alto: number };
  // Leasing fields — only populated when estado === 'Disponible'
  leasing?: {
    m2: number;
    frente?: number;
    renta: number;
    mantenimiento?: number;
    instalaciones: Instalacion[];
  };
  fotos: Foto[];
  logo?: Foto;
  descripcion: string;
  horarios: HorariosSemana;
  whatsapp?: string;
  telefono?: string;
  instagram?: string;
  menuPDF?: string;
  linkReservar?: string;
}

export type TipoEvento = 'Fitness' | 'Educación' | 'Lifestyle' | 'Evento';
export type Recurrencia = 'Único' | 'Semanal' | 'Mensual';

export interface Evento {
  id: string;
  titulo: string;
  localId: string;
  localNombre: string;
  localSlug: string;
  tipo: TipoEvento;
  recurrencia: Recurrencia;
  fecha: string; // ISO date in plaza tz
  horaInicio: string; // HH:MM
  horaFin: string; // HH:MM
  cupo?: number;
  descripcion: string;
  foto?: Foto;
  linkReservar?: string;
}

export interface Config {
  heroVideoURL?: string;
  fotosGenerales: Foto[];
  gapsGiros: string[];
  aforoEstimado?: number;
  cajonesEstacionamiento?: number;
  demografia?: string;
}

export interface LeadRenta {
  nombre: string;
  whatsapp: string;
  email: string;
  localInteresadoId?: string;
  giroPropuesto: string;
  mensaje: string;
}

export interface NotifVecino {
  email: string;
  tipoInteres: Array<'Aperturas' | 'Eventos' | 'Clases' | 'Promociones'>;
}
