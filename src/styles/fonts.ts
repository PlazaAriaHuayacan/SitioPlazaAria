import { Fraunces, Inter } from 'next/font/google';

// Display: warm serif with optical sizes — used for headlines and brand.
export const fraunces = Fraunces({
  subsets: ['latin'],
  display: 'swap',
  variable: '--font-display',
  axes: ['SOFT', 'opsz'],
});

// Body: clean grotesque for UI and longform reading.
export const inter = Inter({
  subsets: ['latin'],
  display: 'swap',
  variable: '--font-body',
});
