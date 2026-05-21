import type { Metadata } from 'next';
import { fraunces, inter } from '@/styles/fonts';
import './globals.css';

export const metadata: Metadata = {
  title: {
    default: 'Plaza Aria',
    template: '%s · Plaza Aria',
  },
  description: 'Plaza vecinal sobre Av. Huayacán. Restaurantes, tiendas, clases y servicios para tu día a día.',
  metadataBase: new URL(process.env.NEXT_PUBLIC_SITE_URL ?? 'http://localhost:3000'),
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="es-MX" className={`${fraunces.variable} ${inter.variable}`}>
      <body>{children}</body>
    </html>
  );
}
