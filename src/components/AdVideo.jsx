import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * Auto-playing ad video that only downloads while it is near the viewport.
 *
 * The carousel repeats every ad several times to fill the strip. Giving every
 * copy a <video src> at once meant the browser downloaded the same MP4 four or
 * five times, which is why the ads crawled. Here each element stays source-less
 * until it is about to be seen, then starts automatically and pauses again when
 * it scrolls away — no play button, no manual tap.
 */
export default function AdVideo({ src, label = '' }) {
  const ref = useRef(null);
  const [active, setActive] = useState(false);

  const play = useCallback(() => {
    const video = ref.current;
    if (!video) return;
    const result = video.play();
    if (result && typeof result.catch === 'function') result.catch(() => {});
  }, []);

  useEffect(() => {
    if (!src) return undefined;
    const video = ref.current;
    if (!video) return undefined;

    const activate = () => setActive(true);

    // Old browsers: no observer, just load and play straight away.
    if (typeof IntersectionObserver === 'undefined') {
      activate();
      return undefined;
    }

    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            activate();
            // On re-entry the src is already attached, so resume here (the
            // setActive above is a no-op in that case and onCanPlay won't fire).
            play();
          } else {
            video.pause();
          }
        }
      },
      { rootMargin: '250px', threshold: 0.01 }
    );
    observer.observe(video);
    return () => observer.disconnect();
  }, [src, play]);

  return (
    <video
      ref={ref}
      src={active ? src : undefined}
      autoPlay
      muted
      loop
      playsInline
      webkit-playsinline=""
      preload="auto"
      aria-label={label}
      onCanPlay={play}
      onLoadedData={play}
    />
  );
}
