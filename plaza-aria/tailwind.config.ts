import type { Config } from 'tailwindcss';

const config: Config = {
  content: [
    './src/pages/**/*.{js,ts,jsx,tsx,mdx}',
    './src/components/**/*.{js,ts,jsx,tsx,mdx}',
    './src/app/**/*.{js,ts,jsx,tsx,mdx}',
  ],
  theme: {
    extend: {
      fontFamily: {
        display: ['var(--font-display)', 'ui-serif', 'Georgia', 'serif'],
        body: ['var(--font-body)', 'system-ui', 'sans-serif'],
      },
      colors: {
        // Aria palette — warm, calm, with editorial restraint.
        // Tunable later when the client provides real brand colors.
        aria: {
          // Backgrounds
          bone: '#FAF6F0',        // page background, off-white linen
          sand: '#F1E9DC',        // surface contrast
          // Ink
          ink: '#1F1A14',         // primary text, almost-black
          slate: '#5A544A',       // secondary text
          // Brand accents
          terracotta: '#B85A3C',  // primary CTA, warm
          terracottaDark: '#8E3F26',
          olive: '#4A5D3A',       // secondary, calm green
          oliveDark: '#324024',
          // States
          gold: '#C99857',        // highlight (disponible, abierto)
          rose: '#D2786B',        // soft warning
          line: '#E7DFD1',        // hairlines
        },
      },
      borderRadius: {
        'card': '1.25rem',
        'pill': '999px',
      },
      boxShadow: {
        'card': '0 1px 2px rgba(31,26,20,0.04), 0 8px 24px -12px rgba(31,26,20,0.10)',
        'cardHover': '0 1px 2px rgba(31,26,20,0.06), 0 16px 36px -16px rgba(31,26,20,0.18)',
      },
      maxWidth: {
        'prose-aria': '68ch',
        'page': '1200px',
      },
      letterSpacing: {
        'display': '-0.02em',
      },
      transitionTimingFunction: {
        'aria': 'cubic-bezier(0.22, 1, 0.36, 1)',
      },
    },
  },
  plugins: [],
};

export default config;
