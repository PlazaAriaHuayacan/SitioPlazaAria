/**
 * High-level Airtable client.
 * Composes fetch.ts (I/O) + normalize.ts (data shaping) to return clean domain types.
 */
import type { Local, Evento, Config, LeadRenta, NotifVecino } from '@/types/domain';
import { airtableGet, airtablePost } from './fetch';
import { normalizeLocal, normalizeEvento, normalizeConfig } from './normalize';

// ─── Read helpers ─────────────────────────────────────────────────────────────

/**
 * Returns all locales sorted by piso (ascending) then numeroLocal (lexicographic).
 */
export async function listLocales(): Promise<Local[]> {
  const records = await airtableGet('Locales');
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const locales = records.map((r) => normalizeLocal(r as { id: string; fields: Record<string, any> }));
  return locales.sort((a, b) => {
    if (a.piso !== b.piso) return a.piso.localeCompare(b.piso);
    return a.numeroLocal.localeCompare(b.numeroLocal);
  });
}

/**
 * Finds a local by slug.
 * Uses listLocales() rather than an Airtable filter formula because:
 *  – the dataset is small (< 50 records) and listLocales is already cached by Next.js
 *  – avoids encoding a formula string in the URL, keeping the client simpler
 */
export async function getLocalBySlug(slug: string): Promise<Local | null> {
  const locales = await listLocales();
  return locales.find((l) => l.slug === slug) ?? null;
}

/**
 * Returns all eventos, resolving the linked Local record for display.
 */
export async function listEventos(): Promise<Evento[]> {
  const [eventoRecords, locales] = await Promise.all([
    airtableGet('Eventos_Clases'),
    listLocales(),
  ]);

  const localsById = new Map<string, Local>(locales.map((l) => [l.id, l]));

  return eventoRecords.map((r) =>
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    normalizeEvento(r as { id: string; fields: Record<string, any> }, localsById),
  );
}

/**
 * Returns the single Config record, or null if the table is empty.
 */
export async function getConfig(): Promise<Config | null> {
  const records = await airtableGet('Config');
  if (records.length === 0) return null;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  return normalizeConfig(records[0] as { id: string; fields: Record<string, any> });
}

// ─── Write helpers ────────────────────────────────────────────────────────────

/**
 * Creates a new lead in Leads_Renta.
 */
export async function createLeadRenta(lead: LeadRenta): Promise<{ id: string }> {
  const fields: Record<string, unknown> = {
    Nombre: lead.nombre,
    Whatsapp: lead.whatsapp,
    Email: lead.email,
    GiroPropuesto: lead.giroPropuesto,
    Mensaje: lead.mensaje,
  };
  if (lead.localInteresadoId) {
    fields.LocalInteresado = [lead.localInteresadoId];
  }
  return airtablePost('Leads_Renta', fields);
}

/**
 * Subscribes a neighbour notification request in Notif_Vecinos.
 */
export async function createNotifVecino(notif: NotifVecino): Promise<{ id: string }> {
  const fields: Record<string, unknown> = {
    Email: notif.email,
    TipoInteres: notif.tipoInteres,
  };
  return airtablePost('Notif_Vecinos', fields);
}
