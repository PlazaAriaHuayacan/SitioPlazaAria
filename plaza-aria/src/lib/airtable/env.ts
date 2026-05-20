/**
 * Reads Airtable credentials from process.env.
 * Validation is lazy (called at runtime, not module load) so tests can stub
 * process.env per-test before calling any client function.
 */
export interface AirtableEnv {
  apiKey: string;
  baseId: string;
}

export function getAirtableEnv(): AirtableEnv {
  const apiKey = process.env.AIRTABLE_API_KEY;
  if (!apiKey) throw new Error('Missing AIRTABLE_API_KEY');

  const baseId = process.env.AIRTABLE_BASE_ID;
  if (!baseId) throw new Error('Missing AIRTABLE_BASE_ID');

  return { apiKey, baseId };
}
