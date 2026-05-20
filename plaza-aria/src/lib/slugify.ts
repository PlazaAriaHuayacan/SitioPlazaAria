/**
 * Convert a string to a URL-safe slug.
 * - Lowercases
 * - Removes diacritics (Spanish accents, ñ → n)
 * - Strips non-alphanumeric except hyphens
 * - Collapses whitespace and runs of hyphens
 * - Trims leading/trailing hyphens
 */
export function slugify(input: string): string {
  return input
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '') // strip combining diacritics
    .toLowerCase()
    .replace(/[^a-z0-9\s-]/g, '') // drop punctuation
    .replace(/\s+/g, '-') // spaces -> hyphens
    .replace(/-+/g, '-') // collapse runs of hyphens
    .replace(/^-+|-+$/g, ''); // trim hyphens
}
