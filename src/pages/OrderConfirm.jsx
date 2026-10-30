import { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api, money } from '../api/client.js';
import { useUI } from '../context/UIContext.jsx';
import { localizeColor, localizeName } from '../i18n/catalog.js';
import { IconBox } from '../components/icons.jsx';

/**
 * Public order-confirmation page — opened by scanning the QR code printed on
 * the shipping label. Opening the link is read-only; the order only moves to
 * `confirmed` after the shopper taps the button (see api/order_confirm.php).
 * A delivery person can also mark the order as delivered, which changes the
 * status to `completed`.
 */
export default function OrderConfirm() {
  const { code = '' } = useParams();
  const { t, lang, locale } = useUI();

  const [order, setOrder] = useState(null);
  const [loading, setLoading] = useState(true);
  const [invalid, setInvalid] = useState(false);
  const [error, setError] = useState('');
  const [actionError, setActionError] = useState('');
  const [confirming, setConfirming] = useState(false);
  const [justConfirmed, setJustConfirmed] = useState(false);
  const [justDelivered, setJustDelivered] = useState(false);

  const load = useCallback(
    async (mode = 'initial') => {
      if (mode === 'initial') setLoading(true);
      try {
        const data = await api.get(`/api/order_confirm.php?code=${encodeURIComponent(code)}`);
        setOrder(data);
        setInvalid(false);
        setError('');
      } catch (err) {
        if (err?.status === 404) {
          setInvalid(true);
        } else if (mode === 'initial') {
          setError(t('orderConfirm.error'));
        }
      } finally {
        if (mode === 'initial') setLoading(false);
      }
    },
    [code, t],
  );

  useEffect(() => {
    load();
  }, [load]);

  const confirm = async () => {
    if (confirming) return;
    setConfirming(true);
    setActionError('');
    try {
      const data = await api.post('/api/order_confirm.php', { code });
      setOrder(data);
      setJustConfirmed(!data.alreadyConfirmed);
    } catch (err) {
      if (err?.status === 404) {
        setInvalid(true);
      } else if (err?.status === 429) {
        setActionError(t('orderConfirm.rateLimited'));
      } else {
        // The order was cancelled, confirmed elsewhere or moved on while this
        // page was open — re-read it and show the current state.
        await load('silent');
        setActionError(err?.status === 409 ? t('orderConfirm.changed') : t('orderConfirm.error'));
      }
    } finally {
      setConfirming(false);
    }
  };

  const deliver = async () => {
    if (confirming) return;
    setConfirming(true);
    setActionError('');
    try {
      const data = await api.post('/api/order_confirm.php', { code, action: 'deliver' });
      setOrder(data);
      setJustDelivered(Boolean(data.justDelivered));
    } catch (err) {
      if (err?.status === 404) {
        setInvalid(true);
      } else if (err?.status === 429) {
        setActionError(t('orderConfirm.rateLimited'));
      } else {
        // The order was cancelled, delivered elsewhere or moved on while this
        // page was open — re-read it and show the current state.
        await load('silent');
        setActionError(err?.status === 409 ? t('orderConfirm.changed') : t('orderConfirm.error'));
      }
    } finally {
      setConfirming(false);
    }
  };

  const formatDate = (iso) => {
    if (!iso) return '';
    const date = new Date(iso);
    return Number.isNaN(date.getTime())
      ? ''
      : date.toLocaleDateString(locale, { day: '2-digit', month: '2-digit', year: 'numeric' });
  };

  const formatDateTime = (iso) => {
    if (!iso) return '';
    const date = new Date(iso);
    return Number.isNaN(date.getTime())
      ? ''
      : date.toLocaleString(locale, { dateStyle: 'medium', timeStyle: 'short' });
  };

  if (loading) {
    return (
      <div className="container success-page">
        <div className="spinner" />
      </div>
    );
  }

  if (invalid) {
    return (
      <div className="container success-page">
        <div className="success-card confirm-card">
          <div className="success-icon confirm-warn">!</div>
          <h1>{t('orderConfirm.invalidTitle')}</h1>
          <p className="confirm-lead">{t('orderConfirm.invalidText')}</p>
          <div className="success-actions">
            <Link to="/" className="btn btn-primary">
              {t('orderConfirm.backHome')}
            </Link>
          </div>
        </div>
      </div>
    );
  }

  if (!order) {
    return (
      <div className="container success-page">
        <div className="success-card confirm-card">
          <div className="success-icon confirm-warn">!</div>
          <h1>{t('orderConfirm.invalidTitle')}</h1>
          <p className="confirm-lead">{error || t('orderConfirm.error')}</p>
          <div className="success-actions">
            <button type="button" className="btn btn-primary" onClick={() => load()}>
              {t('orderConfirm.retry')}
            </button>
          </div>
        </div>
      </div>
    );
  }

  const status = order.status || 'pending';
  const done = ['confirmed', 'processing', 'completed'].includes(status);
  const cancelled = status === 'cancelled';
  const pending = status === 'pending';

  let lead = t('orderConfirm.pendingText');
  if (status === 'completed') {
    lead = justDelivered ? t('orderConfirm.deliveredText') : t('orderConfirm.alreadyCompletedText');
  } else if (done) {
    lead = justConfirmed ? t('orderConfirm.confirmedText') : t('orderConfirm.alreadyText');
  } else if (cancelled) {
    lead = t('orderConfirm.cancelledText');
  }

  // Determine which action button(s) to show
  let actionSection = null;
  if (justDelivered) {
    actionSection = (
      <p className="field-success confirm-success">{t('orderConfirm.deliveredText')}</p>
    );
  } else if (pending) {
    // Order is still pending — show "Confirm order" button
    actionSection = (
      <>
        <button
          type="button"
          className="btn btn-primary confirm-btn"
          onClick={confirm}
          disabled={confirming}
        >
          {confirming ? t('orderConfirm.confirming') : t('orderConfirm.confirm')}
        </button>
        <p className="field-hint confirm-note">{t('orderConfirm.pendingNote')}</p>
      </>
    );
  } else if (status === 'confirmed') {
    // Order is confirmed but not yet delivered — show "Mark as delivered"
    actionSection = (
      <>
        {justDelivered ? (
          <p className="field-success confirm-success">
            {t('orderConfirm.deliveredText')}
          </p>
        ) : (
          <>
            <button
              type="button"
              className="btn btn-primary confirm-btn"
              onClick={deliver}
              disabled={confirming}
            >
              {confirming ? t('orderConfirm.delivering') : t('orderConfirm.deliver')}
            </button>
            {order.alreadyDelivered && (
              <p className="field-info confirm-info">
                {t('orderConfirm.alreadyDeliveredText')}
              </p>
            )}
          </>
        )}
      </>
    );
  } else if (status === 'processing') {
    // Confirmed by the store and being prepared — nothing for the shopper to do
    actionSection = (
      <p className="field-info">
        {t('orderConfirm.processingText')}
      </p>
    );
  }

  return (
    <div className="container success-page">
      <div className="success-card confirm-card">
        <div className={`success-icon ${cancelled ? 'confirm-warn' : done ? '' : 'confirm-pending'}`}>
          {cancelled ? '!' : done ? '✓' : <IconBox size={26} />}
        </div>
        <h1>
          {status === 'completed'
            ? t('orderConfirm.deliveredTitle')
            : done
              ? t('orderConfirm.confirmedTitle')
              : cancelled
                ? t('orderConfirm.cancelledTitle')
                : t('orderConfirm.title')}
        </h1>
        <p className="confirm-lead">{lead}</p>

        <div className="confirm-meta">
          <div>
            <span>{t('orderConfirm.orderNumber')}</span>
            <strong dir="ltr">{order.orderNumber}</strong>
          </div>
          <div>
            <span>{t('orderConfirm.date')}</span>
            <strong>{formatDate(order.createdAt)}</strong>
          </div>
          <div>
            <span>{t('orderConfirm.status')}</span>
            <strong>
              <span className={`status status-${status}`}>{t(`admin.status.${status}`)}</span>
            </strong>
          </div>
          {order.confirmedAt && (
            <div>
              <span>{t('orderConfirm.confirmedAt')}</span>
              <strong>{formatDateTime(order.confirmedAt)}</strong>
            </div>
          )}
        </div>

        {order.items?.length > 0 && (
          <div className="success-details confirm-details">
            <h2>{t('orderConfirm.items')}</h2>
            <ul className="confirm-items">
              {order.items.map((item, index) => (
                <li key={`${item.name}-${index}`}>
                  <span className="confirm-item-name">
                    {localizeName(item.name, lang)}
                    {(item.model || item.color) && (
                      <small>{[item.model, localizeColor(item.color, lang)].filter(Boolean).join(' · ')}</small>
                    )}
                  </span>
                  <span className="confirm-item-qty" dir="ltr">
                    ×{item.qty}
                  </span>
                </li>
              ))}
            </ul>
            <div className="summary-row total">
              <span>{t('orderConfirm.total')}</span>
              <span dir="ltr">
                {money(order.total)} {order.currency?.symbol}
              </span>
            </div>
          </div>
        )}

        {actionError && (
          <p className="field-error confirm-error" role="alert">
            {actionError}
          </p>
        )}

        {actionSection}
      </div>
    </div>
  );
}