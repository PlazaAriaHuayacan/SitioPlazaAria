/**
 * Pure normalizer functions: raw Airtable record → domain type.
 * These have no side effects and are easy to unit-test with fixture JSON.
 */
import type {
  Local,
  Evento,
  Config,
  Foto,
  Giro,
  EstadoLocal,
  Piso,
  Instalacion,
  TipoEvento,
  Recurrencia,
  HorariosSemana,
  DiaSemana,
} from '@/types/domain';
import { parseHorarioBlock } from '@/lib/horarios';
import { slugify } from '@/lib/slugify';

// ─── Attachment helper ────────────────────────────────────────────────────────

interface RawAttachment {
  url: string;
  width?: number;
  height?: number;
  filename?: string;
}

function toFoto(att: RawAttachment): Foto {
  return {
    url: att.url,
    ...(att.width !== undefined ? { width: att.width } : {}),
    ...(att.height !== undefined ? { height: att.height } : {}),
  };
}

function firstFoto(atts: RawAttachment[] | undefined): Foto | undefined {
  if (!atts || atts.length === 0) return undefined;
  return toFoto(atts[0]);
}

function allFotos(atts: RawAttachment[] | undefined): Foto[] {
  if (!atts) return [];
  return atts.map(toFoto);
}

// ─── Horarios ────────────────────────────────────────────────────────────────

const HORARIO_FIELDS: Array<[string, DiaSemana]> = [
  ['HorarioLunes', 'Lunes'],
  ['HorarioMartes', 'Martes'],
  ['HorarioMiercoles', 'Miercoles'],
  ['HorarioJueves', 'Jueves'],
  ['HorarioViernes', 'Viernes'],
  ['HorarioSabado', 'Sabado'],
  ['HorarioDomingo', 'Domingo'],
];

function buildHorarios(fields: Record<string, unknown>): HorariosSemana {
  const result = {} as HorariosSemana;
  for (const [fieldName, dia] of HORARIO_FIELDS) {
    const raw = typeof fields[fieldName] === 'string' ? (fields[fieldName] as string) : '';
    result[dia] = parseHorarioBlock(raw);
  }
  return result;
}

// ─── normalizeLocal ───────────────────────────────────────────────────────────

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type RawFields = Record<string, any>;

export function normalizeLocal(raw: { id: string; fields: RawFields }): Local {
  const f = raw.fields;
  const estado = (f.Estado ?? 'Ocupado') as EstadoLocal;
  const nombre = (f.Nombre as string) ?? '';

  // Slug: use the Airtable formula field if present, otherwise derive from Nombre
  const slug = f.Slug ? slugify(f.Slug as string) : slugify(nombre);

  // Leasing fields only for Disponible
  const leasing =
    estado === 'Disponible'
      ? {
          m2: (f.M2 as number) ?? 0,
          frente: f.Frente as number | undefined,
          renta: (f.Renta as number) ?? 0,
          mantenimiento: f.Mantenimiento as number | undefined,
          instalaciones: ((f.Instalaciones as string[]) ?? []) as Instalacion[],
        }
      : undefined;

  // Fotos: only shown for Disponible (don't leak empty-space photos for other states)
  // Logo stays even for Ocupado — it's the brand mark of the occupant
  const fotos = estado === 'Disponible' ? allFotos(f.Fotos as RawAttachment[]) : [];
  const logo = firstFoto(f.Logo as RawAttachment[]);

  // Floor-plan coords — all four must be present
  const coord =
    f.CoordX !== undefined && f.CoordY !== undefined && f.Ancho !== undefined && f.Alto !== undefined
      ? {
          x: f.CoordX as number,
          y: f.CoordY as number,
          ancho: f.Ancho as number,
          alto: f.Alto as number,
        }
      : undefined;

  return {
    id: raw.id,
    slug,
    nombre,
    giro: (f.Giro ?? 'Otro') as Giro,
    estado,
    piso: (f.Piso ?? '1') as Piso,
    numeroLocal: (f.NumeroLocal as string) ?? '',
    coord,
    leasing,
    fotos,
    logo,
    descripcion: (f.Descripcion as string) ?? '',
    horarios: buildHorarios(f),
    // NOTE: The Airtable field is named "Whastapp" (typo in the live base).
    // We honour the misspelling as-is rather than renaming the field in Airtable.
    whatsapp: f.Whastapp as string | undefined,
    telefono: f.Telefono as string | undefined,
    instagram: f.Instagram as string | undefined,
    menuPDF: f.MenuPDF as string | undefined,
    linkReservar: f.LinkReservar as string | undefined,
  };
}

// ─── normalizeEvento ──────────────────────────────────────────────────────────

export function normalizeEvento(
  raw: { id: string; fields: RawFields },
  localsById: Map<string, Local>,
): Evento {
  const f = raw.fields;
  // Local is a Linked Record field — array of IDs
  const localId = Array.isArray(f.Local) ? (f.Local[0] as string) : '';
  const linked = localsById.get(localId);

  return {
    id: raw.id,
    titulo: (f.Titulo as string) ?? '',
    localId,
    localNombre: linked?.nombre ?? '',
    localSlug: linked?.slug ?? '',
    tipo: (f.Tipo ?? 'Evento') as TipoEvento,
    recurrencia: (f.Recurrencia ?? 'Único') as Recurrencia,
    fecha: (f.Fecha as string) ?? '',
    horaInicio: (f.HoraInicio as string) ?? '',
    horaFin: (f.HoraFin as string) ?? '',
    cupo: f.Cupo as number | undefined,
    descripcion: (f['Descripción'] as string) ?? '',
    foto: firstFoto(f.Foto as RawAttachment[]),
    linkReservar: f.LinkReservar as string | undefined,
  };
}

// ─── normalizeConfig ──────────────────────────────────────────────────────────

export function normalizeConfig(raw: { id: string; fields: RawFields }): Config {
  const f = raw.fields;

  const gapsGiros: string[] =
    typeof f.GapsGiros === 'string' && f.GapsGiros.trim()
      ? f.GapsGiros
          .split(',')
          .map((s: string) => s.trim())
          .filter(Boolean)
      : [];

  return {
    heroVideoURL: f.HeroVideoURL as string | undefined,
    fotosGenerales: allFotos(f.FotosGenerales as RawAttachment[]),
    gapsGiros,
    aforoEstimado: f.AforoEstimado as number | undefined,
    cajonesEstacionamiento: f.CajonesEstacionamiento as number | undefined,
    demografia: f.Demografia as string | undefined,
  };
}
