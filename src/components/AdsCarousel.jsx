import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { useUI } from '../context/UIContext.jsx';
import { localizeAdTitle } from '../i18n/catalog.js';

/**
 * Hero Ads — an endless card train.
 *
 * One "set" is the full ad list. The track repeats that set enough times to
 * always cover the viewport, then slides by exactly one set width and restarts.
 * Since every copy is identical the restart is invisible: the card that leaves
 * on one side re-enters on the other with no gap and no jump.
 *
 * The number of copies is measured at runtime because the card width is
 * viewport-relative (`min(50vw, 200px)`); the CSS keyframes read the resulting
 * shift from the `--ads-shift` custom property so LTR/RTL stay in sync.
 */
export default function AdsCarousel({ ads = [] }) {
  const { t, lang } = useUI();
  const viewportRef = useRef(null);
  const trackRef = useRef(null);
  const [copies, setCopies] = useState(2);

  const measure = useCallback(() => {
    const viewport = viewportRef.current;
    const track = trackRef.current;
    if (!viewport || !track || !ads.length) return;

    const items = track.children;
    if (items.length < ads.length * 2) return;

    // Distance between the first card of copy 1 and of copy 2 = one full set.
    const first = items[0].getBoundingClientRect();
    const next = items[ads.length].getBoundingClientRect();
    const setWidth = Math.abs(next.left - first.left);
    if (setWidth <= 1) return;

    // Enough copies to cover the viewport plus one spare set to slide into.
    const needed = Math.max(2, Math.ceil(viewport.clientWidth / setWidth) + 1);
    setCopies((prev) => (prev === needed ? prev : needed));
  }, [ads.length]);

  useLayoutEffect(() => {
    measure();
  }, [measure, ads]);

  useEffect(() => {
    const viewport = viewportRef.current;
    if (typeof ResizeObserver === 'undefined' || !viewport) {
      window.addEventListener('resize', measure);
      return () => window.removeEventListener('resize', measure);
    }
    const observer = new ResizeObserver(measure);
    observer.observe(viewport);
    return () => observer.disconnect();
  }, [measure]);

  const loop = useMemo(() => {
    const out = [];
    for (let copy = 0; copy < copies; copy += 1) {
      ads.forEach((ad) => out.push({ ad, copy }));
    }
    return out;
  }, [ads, copies]);

  if (!ads.length) return null;

  return (
    <section className="ads" aria-label={t('home.adsAria')}>
      <div className="container">
        <div className="ads-head">
          <h2>{t('home.promotions')}</h2>
          <span className="ads-hint">{t('home.swipe')}</span>
        </div>
      </div>
      <div className="ads-viewport" ref={viewportRef}>
        <div className="ads-track" ref={trackRef} style={{ '--ads-shift': `${100 / copies}%` }}>
          {loop.map(({ ad, copy }, index) => {
            const media =
              ad.type === 'video' ? (
                <video src={ad.mediaUrl} autoPlay muted loop playsInline preload="metadata" />
              ) : (
                <img
                  src={ad.mediaUrl}
                  alt={localizeAdTitle(ad.title, lang)}
                  loading={index < 4 ? 'eager' : 'lazy'}
                />
              );

            const inner = (
              <figure className="ads-card ads-card-lg">
                <div className="ads-media ads-media-lg">{media}</div>
                {ad.title && (
                  <figcaption className="ads-caption">
                    <strong>{localizeAdTitle(ad.title, lang)}</strong>
                    <span>{t('home.shopNow')} →</span>
                  </figcaption>
                )}
              </figure>
            );

            // Only the first set is real content: the extra copies are purely
            // visual repeats, so keep them out of the tab order and a11y tree.
            const duplicate = copy > 0;
            const key = `${ad.id}-${copy}`;

            return ad.linkUrl ? (
              <Link
                key={key}
                to={ad.linkUrl}
                className="ads-card-link"
                tabIndex={duplicate ? -1 : undefined}
                aria-hidden={duplicate || undefined}
              >
                {inner}
              </Link>
            ) : (
              <div key={key} className="ads-card-link" aria-hidden={duplicate || undefined}>
                {inner}
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}
