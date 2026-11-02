import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { api, money } from '../api/client.js';
import { useStore } from '../context/StoreContext.jsx';
import { useUI } from '../context/UIContext.jsx';
import { localizeColor, localizeName } from '../i18n/catalog.js';
import { useCouponAutoApply } from '../lib/useCouponAutoApply.js';
import { IconTrash, IconMinus, IconPlus, IconTruck } from '../components/icons.jsx';

export default function Cart() {
  const {
    cart,
    updateCartQty,
    removeFromCart,
    clearCart,
    toast,
    coupon: appliedCoupon,
    applyCoupon,
    removeCoupon,
  } = useStore();
  const { t, lang } = useUI();
  const navigate = useNavigate();

  const [calculation, setCalculation] = useState(null);
  const [loading, setLoading] = useState(false);

  // Validated as soon as the shopper stops typing — no "apply" tap needed.
  const couponEntry = useCouponAutoApply({
    subtotal: calculation?.subtotal,
    applied: appliedCoupon,
    applyCoupon,
    onApplied: (result, { silent }) => {
      if (!silent) toast(t('cart.couponApplied', { code: result.code }), 'success');
    },
  });

  useEffect(() => {
    if (!cart.length) {
      setCalculation(null);
      return;
    }
    let active = true;
    setLoading(true);
    api
      .post('/api/cart.php', {
        items: cart.map((item) => ({
          productId: item.productId,
          qty: item.qty,
          model: item.model,
          color: item.color,
        })),
        couponCode: appliedCoupon?.code || null,
      })
      .then((res) => active && setCalculation(res))
      .catch((error) => active && toast(error.message, 'error'))
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, [cart, appliedCoupon, toast]);

  // The threshold is measured against what the shopper pays after the coupon.
  const freeShippingRemaining = calculation
    ? Number(calculation.freeShippingRemaining ?? 0)
    : 0;

  const applyCouponCode = (event) => {
    event.preventDefault();
    couponEntry.applyNow();
  };

  const removeAppliedCoupon = () => {
    removeCoupon();
    couponEntry.reset();
  };

  if (!cart.length) {
    return (
      <div className="container empty-state">
        <h2>{t('cart.empty.title')}</h2>
        <p>{t('cart.empty.text')}</p>
        <Link to="/shop" className="btn btn-primary">
          {t('cart.empty.action')}
        </Link>
      </div>
    );
  }

  const lines = calculation?.items || [];

  return (
    <div className="container cart-page">
      <div className="page-head">
        <h1>{t('cart.title')}</h1>
        <button className="link-btn" onClick={clearCart}>
          {t('cart.clear')}
        </button>
      </div>

      <div className="cart-layout">
        <div className="cart-items">
          {lines.length === 0 && loading && <p className="muted">{t('cart.calculating')}</p>}
          {cart.map((item) => {
            const line = lines.find(
              (l) =>
                l.productId === item.productId &&
                (l.model || '') === (item.model || '') &&
                (l.color || '') === (item.color || '')
            );
            const available = line?.available ?? item.qty;
            const short = line && !line.inStock;
            return (
              <article key={`${item.productId}|${item.model}|${item.color}`} className="cart-item">
                <Link to={`/products/${item.productId}`} className="cart-item-media">
                  {item.image ? (
                    <img src={item.image} alt={localizeName(item.name, lang)} />
                  ) : (
                    <div className="product-placeholder" />
                  )}
                </Link>
                <div className="cart-item-info">
                  <Link to={`/products/${item.productId}`} className="cart-item-name">
                    {localizeName(item.name, lang)}
                  </Link>
                  {(item.model || item.color) && (
                    <span className="cart-item-variant">
                      {[item.model, localizeColor(item.color, lang)].filter(Boolean).join(' · ')}
                    </span>
                  )}
                  <span className="cart-item-price">{money(item.price)}</span>
                  {short && (
                    <span className="stock-out small">
                      {t('cart.onlyLeft', { n: available })}
                    </span>
                  )}
                </div>
                <div className="qty-selector">
                  <button
                    onClick={() => updateCartQty(item, item.qty - 1)}
                    aria-label={t('cart.decrease')}
                  >
                    <IconMinus size={15} />
                  </button>
                  <span>{item.qty}</span>
                  <button
                    onClick={() => updateCartQty(item, item.qty + 1)}
                    disabled={line && item.qty >= available}
                    aria-label={t('cart.increase')}
                  >
                    <IconPlus size={15} />
                  </button>
                </div>
                <div className="cart-item-total">{money(item.price * item.qty)}</div>
                <button
                  className="icon-btn danger"
                  onClick={() => removeFromCart(item)}
                  aria-label={t('cart.removeItem')}
                >
                  <IconTrash size={18} />
                </button>
              </article>
            );
          })}
        </div>

        <aside className="cart-summary">
          <h2>{t('cart.summary')}</h2>
          <form className="coupon-row" onSubmit={applyCouponCode}>
            <input
              placeholder={t('cart.couponPlaceholder')}
              value={appliedCoupon ? appliedCoupon.code : couponEntry.input}
              onChange={(e) => couponEntry.change(e.target.value)}
              disabled={!!appliedCoupon}
              autoComplete="off"
              autoCapitalize="characters"
              spellCheck={false}
              aria-label={t('cart.couponPlaceholder')}
            />
            {appliedCoupon ? (
              <button type="button" className="btn btn-outline btn-sm" onClick={removeAppliedCoupon}>
                {t('cart.remove')}
              </button>
            ) : (
              <button type="submit" className="btn btn-dark btn-sm" disabled={couponEntry.checking}>
                {couponEntry.checking ? t('cart.applying') : t('cart.apply')}
              </button>
            )}
          </form>
          {!appliedCoupon && !couponEntry.error && !calculation?.couponError && (
            <p className="muted small coupon-hint">{t('cart.couponAutoHint')}</p>
          )}
          {couponEntry.error && <p className="stock-out small">{couponEntry.error}</p>}
          {!couponEntry.error && calculation?.couponError && (
            <p className="stock-out small">{calculation.couponError}</p>
          )}
          {appliedCoupon && (
            <p className="stock-in small">
              {appliedCoupon.code}: −
              {appliedCoupon.type === 'percent'
                ? `${appliedCoupon.value}%`
                : money(appliedCoupon.value)}
            </p>
          )}

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

          {calculation &&
            calculation.shippingEnabled !== false &&
            !calculation.freeShipping &&
            freeShippingRemaining > 0 && (
            <p className="shipping-hint">
              <IconTruck size={15} />{' '}
              {t('cart.freeShippingHint', { amount: money(freeShippingRemaining) })}
            </p>
          )}

          <div className="summary-row total">
            <span>{t('cart.total')}</span>
            <span>{money(calculation?.total || 0)}</span>
          </div>

          <button
            className="btn btn-primary btn-block btn-lg btn-pulse"
            onClick={() => navigate('/checkout')}
            disabled={loading || (calculation?.items || []).some((l) => !l.inStock)}
          >
            {t('cart.checkout')}
          </button>
          <p className="cod-note">{t('cart.codNote')}</p>
        </aside>
      </div>
    </div>
  );
}