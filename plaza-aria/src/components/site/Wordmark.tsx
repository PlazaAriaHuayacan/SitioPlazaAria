import Link from 'next/link';

type Props = {
  className?: string;
  size?: 'sm' | 'md' | 'lg';
};

const sizeMap = {
  sm: 'text-xl',
  md: 'text-2xl',
  lg: 'text-4xl md:text-5xl',
};

export function Wordmark({ className = '', size = 'md' }: Props) {
  return (
    <Link
      href="/"
      aria-label="Plaza Aria — Inicio"
      className={`inline-flex items-baseline gap-1 ${sizeMap[size]} ${className}`}
    >
      <span className="font-body italic text-aria-slate tracking-tight">Plaza</span>
      <span aria-hidden className="text-aria-terracotta">·</span>
      <span className="font-display tracking-display text-aria-ink">Aria</span>
    </Link>
  );
}
