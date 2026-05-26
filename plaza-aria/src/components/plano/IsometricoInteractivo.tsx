'use client';

import { useRef, useState, useEffect } from 'react';
import { motion } from 'framer-motion';

// ── Image dimensions (must match the actual plano.png aspect ratio) ──────────
const IMG_W = 2528;
const IMG_H = 1684;

/**
 * Decorative isometric illustration of Plaza Aria.
 *
 * Plays an intro video once, then crossfades to a static poster image.
 * No interactive hotspots — available unit selection happens in the
 * card grid below.
 */
export function IsometricoInteractivo() {
  const videoRef = useRef<HTMLVideoElement>(null);
  const [videoEnded, setVideoEnded] = useState(false);

  // After the video plays once, fade it out to reveal the static poster
  useEffect(() => {
    const v = videoRef.current;
    if (!v) return;
    const onEnd = () => setVideoEnded(true);
    v.addEventListener('ended', onEnd);
    return () => v.removeEventListener('ended', onEnd);
  }, []);

  return (
    <div
      className="relative w-full overflow-hidden rounded-2xl bg-[#F0EDE8]"
      style={{ aspectRatio: `${IMG_W} / ${IMG_H}` }}
    >
      {/* Static poster (always below video) */}
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img
        src="/plano.png"
        alt="Plano isométrico de Plaza Aria"
        className="absolute inset-0 w-full h-full object-contain"
        draggable={false}
      />

      {/* Intro video — plays once then fades out */}
      <motion.video
        ref={videoRef}
        src="/plano.mp4"
        autoPlay
        muted
        playsInline
        preload="auto"
        className="absolute inset-0 w-full h-full object-contain"
        initial={{ opacity: 1 }}
        animate={{ opacity: videoEnded ? 0 : 1 }}
        transition={{ duration: 0.8, ease: 'easeOut' }}
        aria-hidden
      />
    </div>
  );
}
