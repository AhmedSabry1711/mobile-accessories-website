import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, money } from '../../api/client.js';
import { useStore } from '../../context/StoreContext.jsx';
import { useUI } from '../../context/UIContext.jsx';
import { IconPlus, IconEdit, IconTrash, IconSearch } from '../../components/icons.jsx';

export default function Products() {
  const { toast } = useStore();
  const { t } = useUI();
  const [products, setProducts] = useState([]);
  const [query, setQuery] = useState('');
  const [loading, setLoading] = useState(true);

  const load = (search = '') => {
    setLoading(true);
    api
      .get(`/api/admin/products.php?q=${encodeURIComponent(search)}`)
      .then((res) => setProducts(res.items || []))
      .catch((error) => toast(error.message, 'error'))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    load('');
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const submitSearch = (event) => {
    event.preventDefault();
    load(query.trim());
  };

  const remove = async (product) => {
    if (!window.confirm(t('admin.products.deleteConfirm', { name: product.name }))) return;
    try {
      await api.del(`/api/admin/products.php?id=${product.id}`);
      setProducts((prev) => prev.filter((p) => p.id !== product.id));
      toast(t('admin.products.deleted'), 'success');
    } catch (error) {
      toast(error.message, 'error');
    }
  };

  return (
    <div className="admin-page">
      <div className="admin-page-head">
        <div>
          <h1>{t('admin.products.title')}</h1>
          <p>{t('admin.products.count', { n: products.length })}</p>
        </div>
        <Link to="/admin/panel/products/new" className="btn btn-primary">
          <IconPlus size={16} /> {t('admin.products.add')}
        </Link>
      </div>

      <form className="admin-toolbar" onSubmit={submitSearch}>
        <div className="admin-search">
          <IconSearch size={16} />
          <input
            placeholder={t('admin.products.searchPlaceholder')}
            value={query}
            onChange={(e) => setQuery(e.target.value)}
          />
        </div>
        <button className="btn btn-outline btn-sm" type="submit">
          {t('admin.products.search')}
        </button>
      </form>

      {loading ? (
        <div className="spinner" />
      ) : products.length ? (
        <div className="panel table-scroll">
          <table className="table">
            <thead>
              <tr>
                <th></th>
                <th>{t('admin.products.thProduct')}</th>
                <th>{t('admin.products.thCategory')}</th>
                <th>{t('admin.products.thSelling')}</th>
                <th>{t('admin.products.thCost')}</th>
                <th>{t('admin.products.thProfitUnit')}</th>
                <th>{t('admin.products.thStock')}</th>
                <th>{t('admin.products.thStatus')}</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {products.map((product) => (
                <tr key={product.id}>
                  <td>
                    <div className="table-thumb">
                      {product.image ? <img src={product.image} alt="" /> : null}
                    </div>
                  </td>
                  <td>
                    <strong>{product.name}</strong>
                    {product.hasVariants && (
                      <small className="muted block">{t('admin.products.variants')}</small>
                    )}
                  </td>
                  <td>{product.category}</td>
                  <td>{money(product.price)}</td>
                  <td>{money(product.costPrice)}</td>
                  <td className="profit">
                    {money(product.profitUnit)}
                    {product.discountPercent > 0 && (
                      <small className="muted block">
                        -{product.discountPercent}% {t('admin.products.off')}
                      </small>
                    )}
                  </td>
                  <td className={product.stockTotal <= 5 ? 'stock-out' : ''}>{product.stockTotal}</td>
                  <td>
                    <span className={`status status-${product.status}`}>
                      {product.status === 'active' ? t('admin.productForm.active') : t('admin.productForm.draft')}
                    </span>
                  </td>
                  <td className="row-actions">
                    <Link
                      to={`/admin/panel/products/${product.id}/edit`}
                      className="icon-btn"
                      title={t('admin.products.edit')}
                    >
                      <IconEdit size={16} />
                    </Link>
                    <button
                      className="icon-btn danger"
                      onClick={() => remove(product)}
                      title={t('admin.products.delete')}
                    >
                      <IconTrash size={16} />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="empty-state">
          <h3>{t('admin.products.empty.title')}</h3>
          <p>{t('admin.products.empty.text')}</p>
        </div>
      )}
    </div>
  );
}