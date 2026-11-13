import { useUI } from '../context/UIContext.jsx';
import { localizeModel, modelImage, variantStock, variantInStock } from '../lib/models.js';

/**
 * Interactive phone-model tabs.
 *
 * Replaces the old <select> dropdown with a visual, keyboard-friendly group.
 * `variant="inline"` is the compact row used on the product page;
 * `variant="cards"` is the illustrated picker used on the home page.
 *
 * Props:
 *   models     [{ model, colors?, products?, stock? }]
 *   value      currently selected canonical model
 *   onChange   (model) => void
 *   variant    'inline' | 'cards'
 *   ariaLabel  accessible name for the tab list
 *   showCounts render the product count (cards variant)
 */
export default function ModelTabs({
  models = [],
  value,
  onChange,
  variant = 'inline',
  ariaLabel,
  showCounts = false,
}) {
  const { t, lang } = useUI();

  if (!models.length) return null;

  return (
    <div
      className={`model-tabs model-tabs-${variant}`}
      role="tablist"
      aria-label={ariaLabel || t('product.model')}
    >
      {models.map((item) => {
        const model = item.model;
        const image = modelImage(model);
        const stock =
          item.stock != null ? Number(item.stock) : variantStock(item);
        const inStock =
          item.products != null ? Number(item.products) > 0 : variantInStock(item);
        const active = value === model;

        return (
          <button
            key={model}
            type="button"
            role="tab"
            aria-selected={active}
            className={`model-tab ${active ? 'model-tab-active' : ''} ${
              inStock ? '' : 'model-tab-out'
            }`}
            onClick={() => onChange(model)}
            title={localizeModel(model, lang)}
          >
            {image && (
              <img
                className="model-tab-img"
                src={image}
                alt=""
                width={variant === 'cards' ? 96 : 44}
                height={variant === 'cards' ? 96 : 44}
                loading="lazy"
                decoding="async"
              />
            )}
            <span className="model-tab-text">
              <span className="model-tab-label">{localizeModel(model, lang)}</span>
              {showCounts && item.products != null && (
                <span className="model-tab-count">
                  {t('home.phoneFinder.count', { n: Number(item.products) })}
                </span>
              )}
              {!showCounts && !inStock && (
                <span className="model-tab-count">{t('product.stockOut')}</span>
              )}
            </span>
          </button>
        );
      })}
    </div>
  );
}
