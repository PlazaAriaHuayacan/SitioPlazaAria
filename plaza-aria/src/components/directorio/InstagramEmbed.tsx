type Props = {
  /** Instagram handle without the leading @ (e.g. "cafe_aria"). */
  handle?: string | null;
  className?: string;
};

export function InstagramEmbed({ handle, className = '' }: Props) {
  const clean = (handle ?? '').trim().replace(/^@/, '');
  if (!clean) return null;

  const url = `https://www.instagram.com/${encodeURIComponent(clean)}/`;

  return (
    <a
      href={url}
      target="_blank"
      rel="noreferrer noopener"
      aria-label={`Ver el Instagram de @${clean}`}
      className={
        'card group flex items-center gap-4 p-4 transition-shadow ' +
        className
      }
    >
      <span
        aria-hidden
        className="grid h-12 w-12 shrink-0 place-items-center rounded-card text-white"
        style={{
          background:
            'linear-gradient(135deg, #FEDA77 0%, #F58529 25%, #DD2A7B 50%, #8134AF 75%, #515BD4 100%)',
        }}
      >
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <rect x="3" y="3" width="18" height="18" rx="5" />
          <circle cx="12" cy="12" r="4" />
          <circle cx="17.5" cy="6.5" r="0.5" fill="currentColor" />
        </svg>
      </span>

      <span className="flex flex-1 flex-col">
        <span className="text-xs uppercase tracking-[0.18em] text-aria-slate">
          Instagram
        </span>
        <span className="font-display text-lg text-aria-ink">@{clean}</span>
        <span className="mt-0.5 text-xs text-aria-slate">
          Última actividad en su Instagram
        </span>
      </span>

      <span aria-hidden className="text-aria-slate transition-colors group-hover:text-aria-ink">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
          <path d="M7 17L17 7M9 7h8v8" />
        </svg>
      </span>
    </a>
  );
}
