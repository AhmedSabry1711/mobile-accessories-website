import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { api } from '../api/client.js';
import { localizeCategory } from '../i18n/catalog.js';
import ProductCard from '../components/ProductCard.jsx';
import { useStore } from '../context/StoreContext.jsx';
import { useUI } from '../context/UIContext.jsx';

const PAGE_SIZE = 24;

export default function Shop() {
  const [searchParams, setSearchParams] = useSearchParams();
  const { toast } = useStore();
  const { t, lang } = useUI();
  const [products, setProducts] = useState([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [settings, setSettings] = useState(null);

  const q = searchParams.get('q') || '';
  const category = searchParams.get('category') || '';
  const filter = searchParams.get('filter') || 'all';

  useEffect(() => {
    api.get('/api/settings.php').then(setSettings).catch(() => {});
  }, []);

  useEffect(() => {
    let active = true;
    setLoading(true);
    const params = new URLSearchParams();
    params.set('limit', String(PAGE_SIZE));
    params.set('page', String(page));
    if (q) params.set('q', q);
    if (category) params.set('category', category);
    if (filter) params.set('filter', filter);

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
  }, [q, category, filter, page, toast]);

  useEffect(() => {
    setPage(1);
  }, [q, category, filter]);

  const categories = settings?.categories || [];
  const pages = Math.max(1, Math.ceil(total / PAGE_SIZE));

  const title = q
    ? t('shop.title.search', { q })
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
          className={`chip ${filter === 'all' && !category ? 'chip-active' : ''}`}
          onClick={() => setSearchParams({})}
        >
          {t('shop.filters.all')}
        </button>
        <button
          className={`chip ${filter === 'sale' ? 'chip-active' : ''}`}
          onClick={() => setSearchParams({ filter: 'sale' })}
        >
          {t('shop.filters.sale')}
        </button>
        <button
          className={`chip ${filter === 'new' ? 'chip-active' : ''}`}
          onClick={() => setSearchParams({ filter: 'new' })}
        >
          {t('shop.filters.new')}
        </button>
        {categories.map((item) => (
          <button
            key={item}
            className={`chip ${category === item ? 'chip-active' : ''}`}
            onClick={() => setSearchParams({ category: item })}
          >
            {localizeCategory(item, lang)}
          </button>
        ))}
      </div>

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