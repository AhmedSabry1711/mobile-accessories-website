import { Link } from 'react-router-dom';
import { useUI } from '../context/UIContext.jsx';

/**
 * Hero Ads — a continuous card carousel (card next to card, smoothly
 * scrolling with an animation). The list is rendered twice so the CSS
 * animation loops seamlessly. Hover pauses the motion.
 */
export default function AdsCarousel({ ads = [] }) {
  const { t } = useUI();
  if (!ads.length) return null;

  const loop = [...ads, ...ads];

  return (
    <section className="ads" aria-label={t('home.adsAria')}>
      <div className="container">
        <div className="ads-head">
          <h2>{t('home.promotions')}</h2>
          <span className="ads-hint">{t('home.swipe')}</span>
        </div>
      </div>
      <div className="ads-viewport">
        <div className="ads-track">
          {loop.map((ad, index) => {
            const media =
              ad.type === 'video' ? (
                <video src={ad.mediaUrl} autoPlay muted loop playsInline preload="metadata" />
              ) : (
                <img src={ad.mediaUrl} alt={ad.title} loading={index < 4 ? 'eager' : 'lazy'} />
              );

            const inner = (
              <figure className="ads-card">
                <div className="ads-media">{media}</div>
                <figcaption>
                  <strong>{ad.title}</strong>
                  <span>{t('home.shopNow')} →</span>
                </figcaption>
              </figure>
            );

            return ad.linkUrl ? (
              <Link key={`${ad.id}-${index}`} to={ad.linkUrl} className="ads-card-link">
                {inner}
              </Link>
            ) : (
              <div key={`${ad.id}-${index}`} className="ads-card-link">
                {inner}
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}