import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { api, money } from '../api/client.js';
import { useStore } from '../context/StoreContext.jsx';
import { useUI } from '../context/UIContext.jsx';

export default function Checkout() {
  const { cart, clearCart, toast } = useStore();
  const { t } = useUI();
  const navigate = useNavigate();

  const [calculation, setCalculation] = useState(null);
  const [settings, setSettings] = useState(null);
  const [couponCode, setCouponCode] = useState('');
  const [form, setForm] = useState({ name: '', phone: '', address: '' });
  const [errors, setErrors] = useState({});
  const [placing, setPlacing] = useState(false);

  useEffect(() => {
    api.get('/api/settings.php').then(setSettings).catch(() => {});
  }, []);

  useEffect(() => {
    if (!cart.length) return;
    let active = true;
    api
      .post('/api/cart.php', {
        items: cart.map((item) => ({
          productId: item.productId,
          qty: item.qty,
          model: item.model,
          color: item.color,
        })),
        couponCode: couponCode || null,
      })
      .then((res) => active && setCalculation(res))
      .catch((error) => active && toast(error.message, 'error'));
    return () => {
      active = false;
    };
  }, [cart, couponCode, toast]);

  const currency = settings?.currency?.symbol || '$';

  const update = (field, value) => setForm((prev) => ({ ...prev, [field]: value }));

  const placeOrder = async (event) => {
    event.preventDefault();
    const validation = {};
    if (form.name.trim().length < 2) validation.name = t('checkout.errors.name');
    if (!/^[+]?[\d\s\-().]{6,20}$/.test(form.phone.trim())) validation.phone = t('checkout.errors.phone');
    if (form.address.trim().length < 5) validation.address = t('checkout.errors.address');
    setErrors(validation);
    if (Object.keys(validation).length) return;

    setPlacing(true);
    try {
      const order = await api.post('/api/orders.php', {
        items: cart.map((item) => ({
          productId: item.productId,
          qty: item.qty,
          model: item.model,
          color: item.color,
        })),
        customer: {
          name: form.name.trim(),
          phone: form.phone.trim(),
          address: form.address.trim(),
        },
        couponCode: couponCode || null,
      });
      clearCart();
      toast(t('success.title'), 'success');
      navigate(`/order-success/${order.orderNumber}`, { state: { order } });
    } catch (error) {
      const apiErrors = error.data?.errors;
      if (apiErrors && typeof apiErrors === 'object') {
        setErrors(apiErrors);
      }
      toast(error.message, 'error');
    } finally {
      setPlacing(false);
    }
  };

  if (!cart.length) {
    return (
      <div className="container empty-state">
        <h2>{t('checkout.empty.title')}</h2>
        <p>{t('checkout.empty.text')}</p>
        <Link to="/shop" className="btn btn-primary">
          {t('checkout.empty.action')}
        </Link>
      </div>
    );
  }

  const hasStockIssue = (calculation?.items || []).some((l) => !l.inStock);

  return (
    <div className="container checkout-page">
      <nav className="breadcrumbs">
        <Link to="/cart">{t('checkout.breadcrumbs.cart')}</Link>
        <span>/</span>
        <span>{t('checkout.title')}</span>
      </nav>
      <h1>{t('checkout.title')}</h1>

      <div className="checkout-layout">
        <form className="checkout-form" onSubmit={placeOrder}>
          <h2>{t('checkout.delivery')}</h2>
          <p className="muted">{t('checkout.muted')}</p>

          <div className="form-group">
            <label htmlFor="co-name">{t('checkout.fields.name')}</label>
            <input
              id="co-name"
              value={form.name}
              onChange={(e) => update('name', e.target.value)}
              placeholder={t('checkout.fields.namePlaceholder')}
              autoComplete="name"
            />
            {errors.name && <span className="field-error">{errors.name}</span>}
          </div>

          <div className="form-group">
            <label htmlFor="co-phone">{t('checkout.fields.phone')}</label>
            <input
              id="co-phone"
              value={form.phone}
              onChange={(e) => update('phone', e.target.value)}
              placeholder={t('checkout.fields.phonePlaceholder')}
              autoComplete="tel"
            />
            {errors.phone && <span className="field-error">{errors.phone}</span>}
          </div>

          <div className="form-group">
            <label htmlFor="co-address">{t('checkout.fields.address')}</label>
            <textarea
              id="co-address"
              rows={4}
              value={form.address}
              onChange={(e) => update('address', e.target.value)}
              placeholder={t('checkout.fields.addressPlaceholder')}
              autoComplete="street-address"
            />
            {errors.address && <span className="field-error">{errors.address}</span>}
          </div>

          <div className="form-group">
            <label htmlFor="co-coupon">{t('checkout.fields.coupon')}</label>
            <input
              id="co-coupon"
              value={couponCode}
              onChange={(e) => setCouponCode(e.target.value.toUpperCase())}
              placeholder={t('checkout.fields.couponPlaceholder')}
            />
          </div>

          <button className="btn btn-primary btn-lg btn-block" type="submit" disabled={placing || hasStockIssue}>
            {placing
              ? t('checkout.placing')
              : t('checkout.place', { total: money(calculation?.total || 0, currency) })}
          </button>
          {hasStockIssue && <p className="field-error">{t('checkout.stockIssue')}</p>}
        </form>

        <aside className="checkout-summary">
          <h2>{t('checkout.order')}</h2>
          <div className="checkout-lines">
            {(calculation?.items || []).map((line) => (
              <div key={`${line.productId}|${line.model}|${line.color}`} className="checkout-line">
                <div className="checkout-line-media">
                  {line.image ? <img src={line.image} alt={line.name} /> : <div className="product-placeholder" />}
                  <span className="checkout-qty">{line.qty}</span>
                </div>
                <div className="checkout-line-info">
                  <span>{line.name}</span>
                  {(line.model || line.color) && (
                    <small>{[line.model, line.color].filter(Boolean).join(' · ')}</small>
                  )}
                </div>
                <span>{money(line.price * line.qty, currency)}</span>
              </div>
            ))}
          </div>

          <div className="summary-row">
            <span>{t('cart.subtotal')}</span>
            <span>{money(calculation?.subtotal || 0, currency)}</span>
          </div>
          {(calculation?.discount || 0) > 0 && (
            <div className="summary-row discount">
              <span>{t('cart.discount')}</span>
              <span>−{money(calculation.discount, currency)}</span>
            </div>
          )}
          <div className="summary-row">
            <span>{t('cart.shipping')}</span>
            <span>{calculation?.shippingFee > 0 ? money(calculation.shippingFee, currency) : t('common.free')}</span>
          </div>
          <div className="summary-row total">
            <span>{t('cart.total')}</span>
            <span>{money(calculation?.total || 0, currency)}</span>
          </div>
        </aside>
      </div>
    </div>
  );
}