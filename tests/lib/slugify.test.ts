import { describe, it, expect } from 'vitest';
import { slugify } from '@/lib/slugify';

describe('slugify', () => {
  it('lowercases and hyphenates a simple name', () => {
    expect(slugify('Café Aria')).toBe('cafe-aria');
  });

  it('removes Spanish accents', () => {
    expect(slugify('Educación Niños')).toBe('educacion-ninos');
  });

  it('collapses whitespace into a single hyphen', () => {
    expect(slugify('Plaza   Aria  ')).toBe('plaza-aria');
  });

  it('strips punctuation', () => {
    expect(slugify("Pepe's Tacos & Más!")).toBe('pepes-tacos-mas');
  });

  it('handles ñ correctly', () => {
    expect(slugify('Doña Coco')).toBe('dona-coco');
  });

  it('returns empty string for empty input', () => {
    expect(slugify('')).toBe('');
  });

  it('returns empty string for input with only punctuation', () => {
    expect(slugify('!!! ??? ...')).toBe('');
  });

  it('trims leading and trailing hyphens', () => {
    expect(slugify('--Hola--')).toBe('hola');
  });

  it('preserves numbers', () => {
    expect(slugify('Local 24/7')).toBe('local-247');
  });

  it('handles full uppercase', () => {
    expect(slugify('BARBERÍA EL TÍO')).toBe('barberia-el-tio');
  });
});
