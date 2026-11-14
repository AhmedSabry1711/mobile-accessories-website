import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api/client.js';
import { useStore } from '../context/StoreContext.jsx';
import { useUI } from '../context/UIContext.jsx';
import ModelTabs from './ModelTabs.jsx';
import ProductCard from './ProductCard.jsx';
import { localizeModel, variantInStock } from '../lib/models.js';

const CASES_CATEGORY = 'CASES';
const MAX_VISIBLE = 8;

/**
 * "Pick your phone" home section.
 *
 * The model list and the matching cases are fetched once, then every tab switch
 * filters the already-loaded products in memory — so the result appears instantly
 * instead of waiting for a new request. All links keep the canonical English
 * category/model values so the shop filter opens on the same selection.
 */
export default function PhoneFinder() {
  const { toast } = useStore();
  const { t, lang } = useUI();

  const [models, setModels] = useState([]);
  const [cases, setCases] = useState([]);
  const [active, setActive] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let alive = true;
    const category = encodeURIComponent(CASES_CATEGORY);

    Promise.all([
      api.get(`/api/products.php?models=1&category=${category}`),
      api.get(`/api/products.php?category=${category}&filter=all&limit=100`),
    ])
      .then(([modelsRes, casesRes]) => {
        if (!alive) return;
        const list = (modelsRes.items || []).filter((item) => Number(item.products) > 0);
        setModels(list);
        setCases(casesRes.items || []);
        setActive(list[0]?.model || '');
      })
      .catch((error) => alive && toast(error.message, 'error'))
      .finally(() => alive && setLoading(false));

    return () => {
      alive = false;
    };
  }, [toast]);

  const matches = useMemo(
    () =>
      cases.filter((product) =>
        (product.variants || []).some(
          (variant) => variant.model === active && variantInStock(variant),
        ),
      ),
    [cases, active],
  );

  const visible = matches.slice(0, MAX_VISIBLE);

  if (!loading && !models.length) return null;

  return (
    <section className="phone-finder" aria-labelledby="phone-finder-title">
      <div className="container">
        <div className="phone-finder-shell">
          <div className="phone-finder-head">
            <div>
              <h2 id="phone-finder-title">{t('home.phoneFinder.title')}</h2>
              <p>{t('home.phoneFinder.subtitle')}</p>
            </div>
            {active && (
              <Link
                to={`/shop?category=${encodeURIComponent(CASES_CATEGORY)}&model=${encodeURIComponent(active)}`}
                className="btn btn-outline btn-sm"
              >
                {t('home.phoneFinder.viewAll', { model: localizeModel(active, lang) })}
              </Link>
            )}
          </div>

          {loading ? (
            <div className="model-tabs model-tabs-cards" aria-hidden="true">
              {Array.from({ length: 3 }).map((_, index) => (
                <div key={index} className="model-tab model-tab-skeleton" />
              ))}
            </div>
          ) : (
            <ModelTabs
              models={models}
              value={active}
              onChange={setActive}
              variant="cards"
              ariaLabel={t('home.phoneFinder.title')}
            />
          )}

          {loading ? (
            <div className="product-grid phone-finder-grid">
              {Array.from({ length: 4 }).map((_, index) => (
                <div key={index} className="skeleton-card" />
              ))}
            </div>
          ) : visible.length ? (
            <div className="product-grid phone-finder-grid">
              {visible.map((product) => (
                <ProductCard key={product.id} product={product} />
              ))}
            </div>
          ) : (
            <div className="empty-state phone-finder-empty">
              <h3>{t('home.phoneFinder.empty.title')}</h3>
              <p>{t('home.phoneFinder.empty.text')}</p>
            </div>
          )}
        </div>
      </div>
    </section>
  );
}
