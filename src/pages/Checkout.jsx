import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { api, money } from '../api/client.js';
import { useStore } from '../context/StoreContext.jsx';
import { useUI } from '../context/UIContext.jsx';
import { localizeColor, localizeName } from '../i18n/catalog.js';
import { useCouponAutoApply } from '../lib/useCouponAutoApply.js';
import { loadCustomer, saveCustomer } from '../lib/customer.js';

export default function Checkout() {
  const { cart, clearCart, toast, coupon, applyCoupon, removeCoupon } = useStore();
  const { t, lang } = useUI();
  const navigate = useNavigate();

  const [calculation, setCalculation] = useState(null);
  // Prefilled with the details from the previous order on this device.
  const [form, setForm] = useState(loadCustomer);
  const [errors, setErrors] = useState({});
  const [placing, setPlacing] = useState(false);

  // The coupon is shared with the cart (and persisted), so a discount applied
  // there is still honoured here instead of being lost on navigation.
  const couponCode = coupon?.code || '';

  const couponEntry = useCouponAutoApply({
    subtotal: calculation?.subtotal,
    applied: coupon,
    applyCoupon,
    onApplied: (result, { silent }) => {
      if (!silent) toast(t('cart.couponApplied', { code: result.code }), 'success');
      setErrors((prev) => {
        const next = { ...prev };
        delete next.couponCode;
        return next;
      });
    },
  });

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

  const applyCouponCode = (event) => {
    event.preventDefault();
    couponEntry.applyNow();
  };

  // Pressing Enter in the coupon field must check the code, not submit the
  // whole checkout form (the coupon input lives inside it).
  const onCouponKeyDown = (event) => {
    if (event.key !== 'Enter') return;
    event.preventDefault();
    couponEntry.applyNow();
  };

  const removeAppliedCoupon = () => {
    removeCoupon();
    couponEntry.reset();
    setErrors((prev) => {
      const next = { ...prev };
      delete next.couponCode;
      return next;
    });
  };

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
      // Remember the delivery details for the next order on this device.
      saveCustomer(form);
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
          <p className="delivery-time" dir="rtl">من 24 ساعة لـ 48 ساعة أقصى حاجة</p>

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
            {coupon ? (
              <div className="coupon-row">
                <input id="co-coupon" value={coupon.code} readOnly />
                <button type="button" className="btn btn-outline btn-sm" onClick={removeAppliedCoupon}>
                  {t('cart.remove')}
                </button>
              </div>
            ) : (
              <div className="coupon-row">
                <input
                  id="co-coupon"
                  value={couponEntry.input}
                  onChange={(e) => couponEntry.change(e.target.value)}
                  onKeyDown={onCouponKeyDown}
                  placeholder={t('checkout.fields.couponPlaceholder')}
                  autoComplete="off"
                  autoCapitalize="characters"
                  spellCheck={false}
                />
                <button
                  type="button"
                  className="btn btn-dark btn-sm"
                  onClick={applyCouponCode}
                  disabled={couponEntry.checking}
                >
                  {couponEntry.checking ? t('cart.applying') : t('cart.apply')}
                </button>
              </div>
            )}
            {!coupon && !couponEntry.error && !errors.couponCode && !calculation?.couponError && (
              <span className="muted small coupon-hint">{t('cart.couponAutoHint')}</span>
            )}
            {couponEntry.error && <span className="field-error">{couponEntry.error}</span>}
            {errors.couponCode && <span className="field-error">{errors.couponCode}</span>}
            {!errors.couponCode && calculation?.couponError && (
              <span className="field-error">{calculation.couponError}</span>
            )}
          </div>

          <button className="btn btn-primary btn-lg btn-block btn-pulse" type="submit" disabled={placing || hasStockIssue}>
            {placing
              ? t('checkout.placing')
              : t('checkout.place', { total: money(calculation?.total || 0) })}
          </button>
          {hasStockIssue && <p className="field-error">{t('checkout.stockIssue')}</p>}
        </form>

        <aside className="checkout-summary">
          <h2>{t('checkout.order')}</h2>
          <div className="checkout-lines">
            {(calculation?.items || []).map((line) => (
              <div key={`${line.productId}|${line.model}|${line.color}`} className="checkout-line">
                <div className="checkout-line-media">
                  {line.image ? <img src={line.image} alt={localizeName(line.name, lang)} /> : <div className="product-placeholder" />}
                  <span className="checkout-qty">{line.qty}</span>
                </div>
                <div className="checkout-line-info">
                  <span>{localizeName(line.name, lang)}</span>
                  {(line.model || line.color) && (
                    <small>{[line.model, localizeColor(line.color, lang)].filter(Boolean).join(' · ')}</small>
                  )}
                </div>
                <span>{money(line.price * line.qty)}</span>
              </div>
            ))}
          </div>

          <div className="summary-row">
            <span>{t('cart.subtotal')}</span>
            <span>{money(calculation?.subtotal || 0)}</span>
          </div>
          {(calculation?.discount || 0) > 0 && (
            <div className="summary-row discount">
              <span>{t('cart.discount')}</span>
              <span>−{money(calculation.discount)}</span>
            </div>
          )}
          <div className="summary-row">
            <span>{t('cart.shipping')}</span>
            <span>{calculation?.shippingFee > 0 ? money(calculation.shippingFee) : t('common.free')}</span>
          </div>
          <div className="summary-row total">
            <span>{t('cart.total')}</span>
            <span>{money(calculation?.total || 0)}</span>
          </div>
        </aside>
      </div>
    </div>
  );
}