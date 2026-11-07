import { useCallback, useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { api, money } from '../api/client.js';
import { useStore } from '../context/StoreContext.jsx';
import { useUI } from '../context/UIContext.jsx';
import { localizeColor, localizeName } from '../i18n/catalog.js';
import { loadCustomer } from '../lib/customer.js';
import { IconBox, IconTruck, IconClose, IconWhatsApp } from '../components/icons.jsx';

/** Happy-path statuses, in the order they happen. */
const FLOW = ['pending', 'confirmed', 'processing', 'completed'];

/** Orders the shopper can still cancel on their own (before preparation starts). */
const CANCELLABLE = ['pending', 'confirmed'];

const WHATSAPP = 'https://wa.me/201023538775';

/**
 * Customer-facing order tracking — `/track-order`.
 *
 * The order is looked up with the order number + the phone used at checkout
 * (the API never exposes an order to someone who knows only the number).
 * While the order is still pending or confirmed the customer can cancel it
 * themselves; once preparation starts only the shop can cancel it.
 */
export default function TrackOrder() {
  const [params, setParams] = useSearchParams();
  const { t, lang, locale } = useUI();
  const { toast } = useStore();

  const [orderNumber, setOrderNumber] = useState(params.get('order') || '');
  const [phone, setPhone] = useState(params.get('phone') || loadCustomer().phone);
  const [order, setOrder] = useState(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [confirming, setConfirming] = useState(false);
  const [cancelling, setCancelling] = useState(false);

  const lookup = useCallback(
    async (number, phoneValue) => {
      setLoading(true);
      setError('');
      setConfirming(false);
      try {
        const data = await api.get(
          `/api/orders.php?number=${encodeURIComponent(number.trim())}&phone=${encodeURIComponent(phoneValue.trim())}`
        );
        setOrder(data);
        // Keep the query string in sync so the page can be shared / refreshed.
        setParams(
          (prev) => {
            const next = new URLSearchParams(prev);
            next.set('order', number.trim());
            next.set('phone', phoneValue.trim());
            return next;
          },
          { replace: true }
        );
      } catch (err) {
        setOrder(null);
        setError(err?.status === 404 ? t('track.notFound') : t('track.error'));
      } finally {
        setLoading(false);
      }
    },
    [setParams, t]
  );

  // Deep link: /track-order?order=MB-…&phone=0100…
  useEffect(() => {
    const o = params.get('order');
    const p = params.get('phone');
    if (o && p) lookup(o, p);
    // Run once on mount only.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const submit = (event) => {
    event.preventDefault();
    if (!orderNumber.trim() || !phone.trim()) {
      setError(t('track.required'));
      setOrder(null);
      return;
    }
    lookup(orderNumber, phone);
  };

  const cancelOrder = async () => {
    setCancelling(true);
    try {
      const updated = await api.post('/api/orders.php?action=cancel', {
        orderNumber: order.orderNumber,
        phone: phone.trim(),
      });
      setOrder(updated);
      setConfirming(false);
      toast(t('track.cancelDone'), 'success');
    } catch (err) {
      toast(err?.status === 409 ? t('track.cancelTooLate') : t('track.cancelError'), 'error');
    } finally {
      setCancelling(false);
    }
  };

  const status = order?.status || '';
  const cancelled = status === 'cancelled';
  const stepIndex = FLOW.indexOf(status);
  const canCancel = CANCELLABLE.includes(status);

  const formatDate = (value) => {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' }).format(date);
  };

  return (
    <div className="container track-page">
      <div className="track-card">
        <div className="track-head">
          <span className="track-icon">
            <IconBox size={24} />
          </span>
          <div>
            <h1>{t('track.title')}</h1>
            <p className="track-intro">{t('track.intro')}</p>
          </div>
        </div>

        <form className="track-form" onSubmit={submit}>
          <div className="form-group">
            <label htmlFor="track-order-number">{t('track.orderNumber')}</label>
            <input
              id="track-order-number"
              type="text"
              value={orderNumber}
              onChange={(e) => setOrderNumber(e.target.value)}
              placeholder={t('track.orderNumberPlaceholder')}
              autoComplete="off"
            />
          </div>
          <div className="form-group">
            <label htmlFor="track-phone">{t('track.phone')}</label>
            <input
              id="track-phone"
              type="tel"
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              placeholder={t('track.phonePlaceholder')}
              autoComplete="tel"
            />
          </div>
          <button type="submit" className="btn btn-primary" disabled={loading}>
            {loading ? t('track.searching') : t('track.submit')}
          </button>
        </form>

        {error && <p className="track-error">{error}</p>}

        {order && (
          <div className="track-result">
            <div className="track-result-head">
              <div>
                <span className="track-label">{t('track.orderNumber')}</span>
                <strong className="track-number">{order.orderNumber}</strong>
              </div>
              <span className={`status status-${status}`}>{t(`track.status_${status}`)}</span>
            </div>

            <div className="track-dates">
              {order.createdAt && (
                <div className="track-date">
                  <span className="track-label">{t('track.placedAt')}</span>
                  <strong>{formatDate(order.createdAt)}</strong>
                </div>
              )}
              {order.updatedAt && (
                <div className="track-date">
                  <span className="track-label">{t('track.updatedAt')}</span>
                  <strong>{formatDate(order.updatedAt)}</strong>
                </div>
              )}
            </div>

            {cancelled ? (
              <p className="track-note track-note-cancelled">{t('track.cancelledNote')}</p>
            ) : (
              <ol className="track-steps">
                {FLOW.map((step, index) => {
                  const state =
                    index < stepIndex ? 'is-done' : index === stepIndex ? 'is-current' : 'is-todo';
                  return (
                    <li key={step} className={`track-step ${state}`}>
                      <span className="track-dot" aria-hidden="true" />
                      <span className="track-step-label">{t(`track.status_${step}`)}</span>
                    </li>
                  );
                })}
              </ol>
            )}

            {!cancelled && (
              <p className="track-note">
                {status === 'pending'
                  ? t('track.notePending')
                  : status === 'confirmed'
                    ? t('track.noteConfirmed')
                    : status === 'processing'
                      ? t('track.noteProcessing')
                      : t('track.noteCompleted')}
              </p>
            )}

            <h2 className="track-subtitle">{t('track.items')}</h2>
            <ul className="track-items">
              {order.items.map((item, index) => (
                <li className="track-item" key={`${item.productId}-${index}`}>
                  {item.image ? (
                    <img src={item.image} alt="" loading="lazy" />
                  ) : (
                    <span className="track-item-noimg">
                      <IconBox size={18} />
                    </span>
                  )}
                  <div className="track-item-body">
                    <span className="track-item-name">{localizeName(item.name, lang)}</span>
                    {(item.model || item.color) && (
                      <small>
                        {[item.model, localizeColor(item.color, lang)].filter(Boolean).join(' · ')}
                      </small>
                    )}
                  </div>
                  <span className="track-item-qty">×{item.qty}</span>
                  <span className="track-item-price">{money(item.price * item.qty)}</span>
                </li>
              ))}
            </ul>

            <div className="track-totals">
              <div className="summary-row">
                <span>{t('cart.subtotal')}</span>
                <span>{money(order.subtotal)}</span>
              </div>
              {order.discount > 0 && (
                <div className="summary-row discount">
                  <span>{t('cart.discount')}</span>
                  <span>−{money(order.discount)}</span>
                </div>
              )}
              <div className="summary-row">
                <span>{t('cart.shipping')}</span>
                <span>{order.shippingFee > 0 ? money(order.shippingFee) : t('cart.free')}</span>
              </div>
              <div className="summary-row track-total">
                <span>{t('track.total')}</span>
                <span>{money(order.total)}</span>
              </div>
            </div>

            <div className="track-delivery">
              <IconTruck size={18} />
              <div>
                <strong>{order.customer?.name}</strong>
                <span>{order.customer?.address}</span>
                <span>{order.customer?.phone}</span>
              </div>
            </div>

            {canCancel && (
              <div className="track-cancel">
                {confirming ? (
                  <div className="track-confirm">
                    <p>{t('track.confirmCancel')}</p>
                    <div className="track-actions">
                      <button
                        type="button"
                        className="btn btn-danger"
                        onClick={cancelOrder}
                        disabled={cancelling}
                      >
                        {cancelling ? t('track.cancelling') : t('track.cancelYes')}
                      </button>
                      <button
                        type="button"
                        className="btn btn-outline"
                        onClick={() => setConfirming(false)}
                        disabled={cancelling}
                      >
                        <IconClose size={16} /> {t('track.cancelNo')}
                      </button>
                    </div>
                  </div>
                ) : (
                  <button type="button" className="btn btn-danger" onClick={() => setConfirming(true)}>
                    <IconClose size={16} />
                    {t('track.cancel')}
                  </button>
                )}
              </div>
            )}

            {!cancelled && !canCancel && (
              <p className="track-note track-note-locked">{t('track.cannotCancel')}</p>
            )}
          </div>
        )}

        <div className="track-help">
          <span>{t('track.help')}</span>
          <a className="btn btn-wa btn-sm" href={WHATSAPP} target="_blank" rel="noreferrer">
            <IconWhatsApp size={18} />
            {t('track.chat')}
          </a>
        </div>

        <Link className="info-back" to="/">
          {t('track.backHome')}
        </Link>
      </div>
    </div>
  );
}
