import { useUI } from '../context/UIContext.jsx';
import { IconStar } from './icons.jsx';

/** 5-star display; supports fractional fill via overlay width. */
export default function RatingStars({ rating = 0, count, size = 15, showValue = false }) {
  const { t } = useUI();
  const clamped = Math.max(0, Math.min(5, Number(rating) || 0));
  const percent = (clamped / 5) * 100;

  return (
    <span className="rating" title={t('common.ratingOut', { n: clamped.toFixed(1) })}>
      <span className="rating-stars" style={{ '--rating-size': `${size}px` }}>
        <span className="rating-empty">
          {[0, 1, 2, 3, 4].map((i) => (
            <IconStar key={i} size={size} />
          ))}
        </span>
        <span className="rating-fill" style={{ width: `${percent}%` }}>
          {[0, 1, 2, 3, 4].map((i) => (
            <IconStar key={i} size={size} filled />
          ))}
        </span>
      </span>
      {showValue && <span className="rating-value">{clamped.toFixed(1)}</span>}
      {count !== undefined && <span className="rating-count">({count})</span>}
    </span>
  );
}