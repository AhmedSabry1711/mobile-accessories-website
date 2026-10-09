import { useEffect, useState } from 'react';
import { Link, useLocation, useParams } from 'react-router-dom';
import { api, money } from '../api/client.js';
import { useUI } from '../context/UIContext.jsx';
import { IconTruck, IconPhone, IconShield } from '../components/icons.jsx';

export default function OrderSuccess() {
  const { orderNumber } = useParams();
  const location = useLocation();
  const { t } = useUI();
  const [order] = useState(location.state?.order || null);
  const [settings, setSettings] = useState(null);

  useEffect(() => {
    api.get('/api/settings.php').then(setSettings).catch(() => {});
  }, []);

  const currency = settings?.currency?.symbol || '$';
  const waNumber = (settings?.whatsapp || '').replace(/[^\d]/g, '');

  return (
    <div className="container success-page">
      <div className="success-card">
        <div className="success-icon">✓</div>
        <h1>{t('success.title')}</h1>
        <p>{t('success.text', { order: orderNumber })}</p>

        {!order && <p className="muted">{t('success.noOrder', { order: orderNumber })}</p>}

        {order && (
          <div className="success-details">
            <h2>{t('success.summary')}</h2>
            <div className="checkout-lines">
              {order.items.map((item, index) => (
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
                  <span>{money(item.price * item.qty, currency)}</span>
                </div>
              ))}
            </div>
            <div className="summary-row">
              <span>{t('cart.subtotal')}</span>
              <span>{money(order.subtotal, currency)}</span>
            </div>
            {order.discount > 0 && (
              <div className="summary-row discount">
                <span>{t('cart.discount')}</span>
                <span>−{money(order.discount, currency)}</span>
              </div>
            )}
            <div className="summary-row">
              <span>{t('cart.shipping')}</span>
              <span>{order.shippingFee > 0 ? money(order.shippingFee, currency) : t('common.free')}</span>
            </div>
            <div className="summary-row total">
              <span>{t('success.totalPay')}</span>
              <span>{money(order.total, currency)}</span>
            </div>
          </div>
        )}

        <div className="success-perks">
          <div>
            <IconTruck size={20} />
            <span>{t('success.perks.delivery')}</span>
          </div>
          <div>
            <IconPhone size={20} />
            <span>{t('success.perks.call')}</span>
          </div>
          <div>
            <IconShield size={20} />
            <span>{t('success.perks.returns')}</span>
          </div>
        </div>

        <div className="success-actions">
          <Link to="/shop" className="btn btn-primary">
            {t('success.continue')}
          </Link>
          {waNumber && (
            <a
              className="btn btn-wa"
              href={`https://wa.me/${waNumber}?text=${encodeURIComponent(t('wa.orderMessage', { order: orderNumber }))}`}
              target="_blank"
              rel="noreferrer"
            >
              {t('success.chat')}
            </a>
          )}
        </div>
      </div>
    </div>
  );
}