import { useUI } from '../context/UIContext.jsx';
import { localizeModel, variantInStock } from '../lib/models.js';

/**
 * Interactive phone-model chips.
 *
 * A compact, keyboard-friendly row of model names — no artwork, so a long list
 * of phones stays scannable. `variant="inline"` is used on the product page and
 * `variant="cards"` keeps the home picker on the same chip design; both scroll
 * horizontally on phones instead of growing into a tall grid.
 *
 * Props:
 *   models     [{ model, colors?, products?, stock? }]
 *   value      currently selected canonical model
 *   onChange   (model) => void
 *   variant    'inline' | 'cards'
 *   ariaLabel  accessible name for the tab list
 */
export default function ModelTabs({
  models = [],
  value,
  onChange,
  variant = 'inline',
  ariaLabel,
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
            <span className="model-tab-label">{localizeModel(model, lang)}</span>
          </button>
        );
      })}
    </div>
  );
}
