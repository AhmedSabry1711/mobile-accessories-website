import { useEffect, useState } from 'react';
import { api, money } from '../../api/client.js';
import { useStore } from '../../context/StoreContext.jsx';
import { useUI } from '../../context/UIContext.jsx';
import { IconSearch, IconClose, IconPrinter } from '../../components/icons.jsx';
import ShippingLabel from '../../components/ShippingLabel.jsx';

const STATUSES = ['pending', 'confirmed', 'processing', 'completed', 'cancelled'];

export default function Orders() {
  const { toast } = useStore();
  const { t, locale } = useUI();
  const [orders, setOrders] = useState([]);
  const [statusFilter, setStatusFilter] = useState('');
  const [query, setQuery] = useState('');
  const [loading, setLoading] = useState(true);
  const [selected, setSelected] = useState(null);
  const [label, setLabel] = useState(null);
  const [labelLoading, setLabelLoading] = useState(null);

  const load = (status = '', search = '') => {
    setLoading(true);
    const params = new URLSearchParams();
    if (status) params.set('status', status);
    if (search) params.set('q', search);
    api
      .get(`/api/admin/orders.php?${params.toString()}`)
      .then((rows) => setOrders(rows || []))
      .catch((error) => toast(error.message, 'error'))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  /**
   * Fetch the signed confirmation code for this order, then open the label.
   * The code is generated server-side (and stored as a random salt), so the
   * printed QR is stable for reprints but useless for any other order.
   */
  const openLabel = async (order) => {
    setLabelLoading(order.id);
    try {
      const data = await api.get(`/api/admin/orders.php?id=${order.id}&label=1`);
      setLabel({ order, confirmUrl: data?.url || '', currency: data?.currency });
    } catch (error) {
      toast(error.message, 'error');
    } finally {
      setLabelLoading(null);
    }
  };

  const submitSearch = (event) => {
    event.preventDefault();
    load(statusFilter, query.trim());
  };

  const changeStatus = async (order, status) => {
    try {
      const updated = await api.put(`/api/admin/orders.php?id=${order.id}`, { status });
      setOrders((prev) => prev.map((o) => (o.id === order.id ? updated : o)));
      setSelected((prev) => (prev && prev.id === order.id ? updated : prev));
      toast(t('admin.orders.changeToast', { order: order.orderNumber, status: t(`admin.status.${status}`) }), 'success');
    } catch (error) {
      toast(error.message, 'error');
    }
  };

  return (
    <div className="admin-page">
      <div className="admin-page-head">
        <div>
          <h1>{t('admin.orders.title')}</h1>
          <p>{t('admin.orders.count', { n: orders.length })}</p>
        </div>
      </div>

      <form className="admin-toolbar" onSubmit={submitSearch}>
        <div className="admin-search">
          <IconSearch size={16} />
          <input
            placeholder={t('admin.orders.searchPlaceholder')}
            value={query}
            onChange={(e) => setQuery(e.target.value)}
          />
        </div>
        <select
          value={statusFilter}
          onChange={(e) => {
            setStatusFilter(e.target.value);
            load(e.target.value, query.trim());
          }}
        >
          <option value="">{t('admin.orders.allStatuses')}</option>
          {STATUSES.map((status) => (
            <option key={status} value={status}>
              {t(`admin.status.${status}`)}
            </option>
          ))}
        </select>
        <button className="btn btn-outline btn-sm" type="submit">
          {t('admin.orders.search')}
        </button>
      </form>

      {loading ? (
        <div className="spinner" />
      ) : orders.length ? (
        <div className="panel table-scroll">
          <table className="table">
            <thead>
              <tr>
                <th>{t('admin.orders.thOrder')}</th>
                <th>{t('admin.orders.thCustomer')}</th>
                <th>{t('admin.orders.thItems')}</th>
                <th>{t('admin.orders.thTotal')}</th>
                <th>{t('admin.orders.thDate')}</th>
                <th>{t('admin.orders.thStatus')}</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {orders.map((order) => (
                <tr key={order.id}>
                  <td>
                    <strong>{order.orderNumber}</strong>
                  </td>
                  <td>
                    {order.customer?.name}
                    <small className="muted block">{order.customer?.phone}</small>
                  </td>
                  <td>{order.items?.length || 0}</td>
                  <td>{money(order.total)}</td>
                  <td>{new Date(order.createdAt).toLocaleDateString(locale)}</td>
                  <td>
                    <select
                      className={`status-select status-${order.status}`}
                      value={order.status}
                      onChange={(e) => changeStatus(order, e.target.value)}
                    >
                      {STATUSES.map((status) => (
                        <option key={status} value={status}>
                          {t(`admin.status.${status}`)}
                        </option>
                      ))}
                    </select>
                  </td>
                  <td className="row-actions">
                    <button className="btn btn-outline btn-sm" onClick={() => setSelected(order)}>
                      {t('admin.orders.view')}
                    </button>
                    <button
                      className="btn btn-outline btn-sm"
                      onClick={() => openLabel(order)}
                      disabled={labelLoading === order.id}
                      title={t('admin.orders.label.shipping')}
                    >
                      <IconPrinter size={16} />
                      {t('admin.orders.label.short')}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="empty-state">
          <h3>{t('admin.orders.empty.title')}</h3>
          <p>{t('admin.orders.empty.text')}</p>
        </div>
      )}

      {selected && (
        <div className="modal-overlay" onClick={() => setSelected(null)}>
          <div className="modal" onClick={(e) => e.stopPropagation()}>
            <div className="modal-head">
              <div>
                <h2>{t('admin.orders.modalTitle', { order: selected.orderNumber })}</h2>
                <span className={`status status-${selected.status}`}>
                  {t(`admin.status.${selected.status}`)}
                </span>
              </div>
              <button className="icon-btn" onClick={() => setSelected(null)} aria-label={t('admin.orders.close')}>
                <IconClose size={20} />
              </button>
            </div>

            <div className="modal-section">
              <h3>{t('admin.orders.customer')}</h3>
              <p>
                <strong>{selected.customer?.name}</strong>
                <br />
                {selected.customer?.phone}
                <br />
                {selected.customer?.address}
              </p>
              {selected.confirmedAt && (
                <p className="muted">
                  {t('admin.orders.confirmedAt')}:{' '}
                  {new Date(selected.confirmedAt).toLocaleString(locale)}
                </p>
              )}
            </div>

            <div className="modal-section">
              <h3>{t('admin.orders.items')}</h3>
              <div className="checkout-lines">
                {selected.items?.map((item, index) => (
                  <div key={index} className="checkout-line">
                    <div className="checkout-line-media">
                      {item.image ? <img src={item.image} alt={item.name} /> : <div className="product-placeholder" />}
                      <span className="checkout-qty">{item.qty}</span>
                    </div>
                    <div className="checkout-line-info">
                      <span>{item.name}</span>
                      {(item.model || item.color) && (
                        <small>{[item.model, item.color].filter(Boolean).join(' · ')}</small>
                      )}
                    </div>
                    <span>{money(item.price * item.qty)}</span>
                  </div>
                ))}
              </div>
            </div>

            <div className="modal-summary">
              <div className="summary-row">
                <span>{t('cart.subtotal')}</span>
                <span>{money(selected.subtotal)}</span>
              </div>
              {selected.discount > 0 && (
                <div className="summary-row discount">
                  <span>{t('cart.discount')} {selected.couponCode ? `(${selected.couponCode})` : ''}</span>
                  <span>−{money(selected.discount)}</span>
                </div>
              )}
              <div className="summary-row">
                <span>{t('cart.shipping')}</span>
                <span>{selected.shippingFee > 0 ? money(selected.shippingFee) : t('common.free')}</span>
              </div>
              <div className="summary-row total">
                <span>{t('cart.total')}</span>
                <span>{money(selected.total)}</span>
              </div>
            </div>

            <div className="modal-actions">
              <button
                className="btn btn-primary btn-sm"
                onClick={() => openLabel(selected)}
                disabled={labelLoading === selected.id}
              >
                <IconPrinter size={16} />
                {t('admin.orders.label.print')}
              </button>
              {STATUSES.map((status) => (
                <button
                  key={status}
                  className={`btn btn-sm ${selected.status === status ? 'btn-primary' : 'btn-outline'}`}
                  onClick={() => changeStatus(selected, status)}
                >
                  {t(`admin.status.${status}`)}
                </button>
              ))}
            </div>
          </div>
        </div>
      )}

      {label && (
        <ShippingLabel
          order={label.order}
          settings={{ currency: label.currency }}
          confirmUrl={label.confirmUrl}
          onClose={() => setLabel(null)}
        />
      )}
    </div>
  );
}