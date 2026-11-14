import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { api } from '../api/client.js';
import { localizeCategory, canonicalCategory } from '../i18n/catalog.js';
import { localizeModel } from '../lib/models.js';
import ProductCard from '../components/ProductCard.jsx';
import { useStore } from '../context/StoreContext.jsx';
import { useUI } from '../context/UIContext.jsx';

const PAGE_SIZE = 24;
const CASES_CATEGORY = 'CASES';

export default function Shop() {
  const [searchParams, setSearchParams] = useSearchParams();
  const { toast } = useStore();
  const { t, lang } = useUI();
  const [products, setProducts] = useState([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [settings, setSettings] = useState(null);
  const [models, setModels] = useState([]);

  const q = searchParams.get('q') || '';
  const category = searchParams.get('category') || '';
  const filter = searchParams.get('filter') || 'all';
  const model = searchParams.get('model') || '';
  const activeCategory = canonicalCategory(category);

  const showModelFilter = !category || activeCategory === CASES_CATEGORY;

  useEffect(() => {
    api.get('/api/settings.php').then(setSettings).catch(() => {});
  }, []);

  useEffect(() => {
    let active = true;
    if (!showModelFilter) {
      setModels([]);
      return () => {
        active = false;
      };
    }
    api
      .get(`/api/products.php?models=1&category=${encodeURIComponent(CASES_CATEGORY)}`)
      .then((res) => active && setModels(res.items || []))
      .catch(() => {});
    return () => {
      active = false;
    };
  }, [showModelFilter]);

  useEffect(() => {
    let active = true;
    setLoading(true);
    const params = new URLSearchParams();
    params.set('limit', String(PAGE_SIZE));
    params.set('page', String(page));
    if (q) params.set('q', q);
    if (category) params.set('category', category);
    if (filter) params.set('filter', filter);
    if (model) params.set('model', model);

    api
      .get(`/api/products.php?${params.toString()}`)
      .then((res) => {
        if (!active) return;
        setProducts(res.items || []);
        setTotal(res.total || 0);
      })
      .catch((error) => active && toast(error.message, 'error'))
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, [q, category, filter, model, page, toast]);

  useEffect(() => {
    setPage(1);
  }, [q, category, filter, model]);

  const categories = settings?.categories || [];
  const pages = Math.max(1, Math.ceil(total / PAGE_SIZE));

  const updateParams = (patch) => {
    const next = Object.fromEntries(searchParams.entries());
    for (const [key, value] of Object.entries(patch)) {
      if (value === '' || value == null) delete next[key];
      else next[key] = value;
    }
    setSearchParams(next);
  };

  const title = q
    ? t('shop.title.search', { q })
    : model
      ? t('shop.title.model', { model: localizeModel(model, lang) })
      : category
        ? localizeCategory(category, lang)
        : filter === 'sale'
          ? t('shop.title.sale')
          : filter === 'new'
            ? t('shop.title.new')
            : t('shop.title.all');

  return (
    <div className="container shop">
      <div className="shop-head">
        <h1>{title}</h1>
        <p>{loading ? t('common.loading') : t('shop.count', { n: total })}</p>
      </div>

      <div className="shop-filters">
        <button
          className={`chip ${filter === 'all' && !category && !model ? 'chip-active' : ''}`}
          onClick={() => setSearchParams({})}
        >
          {t('shop.filters.all')}
        </button>
        <button
          className={`chip ${filter === 'sale' ? 'chip-active' : ''}`}
          onClick={() => updateParams({ filter: 'sale' })}
        >
          {t('shop.filters.sale')}
        </button>
        <button
          className={`chip ${filter === 'new' ? 'chip-active' : ''}`}
          onClick={() => updateParams({ filter: 'new' })}
        >
          {t('shop.filters.new')}
        </button>
        {categories.map((item) => (
          <button
            key={item}
            className={`chip ${activeCategory === item ? 'chip-active' : ''}`}
            onClick={() =>
              updateParams({
                category: item,
                model: item === CASES_CATEGORY ? model : '',
              })
            }
          >
            {localizeCategory(item, lang)}
          </button>
        ))}
      </div>

      {showModelFilter && models.length > 0 && (
        <div className="shop-model-filter">
          <span className="shop-filter-label">{t('shop.filters.model')}</span>
          <div className="shop-filters shop-model-chips" role="tablist" aria-label={t('shop.filters.model')}>
            <button
              type="button"
              role="tab"
              aria-selected={!model}
              className={`chip ${!model ? 'chip-active' : ''}`}
              onClick={() => updateParams({ model: '' })}
            >
              {t('shop.filters.allModels')}
            </button>
            {models.map((item) => (
              <button
                key={item.model}
                type="button"
                role="tab"
                aria-selected={model === item.model}
                className={`chip ${model === item.model ? 'chip-active' : ''}`}
                onClick={() =>
                  updateParams({ category: CASES_CATEGORY, model: item.model })
                }
              >
                {localizeModel(item.model, lang)}
                {item.products != null && (
                  <span className="chip-count">{item.products}</span>
                )}
              </button>
            ))}
          </div>
        </div>
      )}

      {loading ? (
        <div className="product-grid">
          {Array.from({ length: 8 }).map((_, i) => (
            <div key={i} className="skeleton-card" />
          ))}
        </div>
      ) : products.length ? (
        <>
          <div className="product-grid">
            {products.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>

          {pages > 1 && (
            <div className="pagination">
              <button
                className="btn btn-outline btn-sm"
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                disabled={page <= 1}
              >
                {t('shop.previous')}
              </button>
              <span>{t('shop.pageOf', { page, pages })}</span>
              <button
                className="btn btn-outline btn-sm"
                onClick={() => setPage((p) => Math.min(pages, p + 1))}
                disabled={page >= pages}
              >
                {t('shop.next')}
              </button>
            </div>
          )}
        </>
      ) : (
        <div className="empty-state">
          <h3>{t('shop.empty.title')}</h3>
          <p>{t('shop.empty.text')}</p>
          <button className="btn btn-primary btn-sm" onClick={() => setSearchParams({})}>
            {t('shop.empty.action')}
          </button>
        </div>
      )}
    </div>
  );
}