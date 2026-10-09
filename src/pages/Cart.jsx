import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { api, money } from '../api/client.js';
import { useStore } from '../context/StoreContext.jsx';
import { useUI } from '../context/UIContext.jsx';
import { IconTrash, IconMinus, IconPlus, IconTruck } from '../components/icons.jsx';

export default function Cart() {
  const { cart, updateCartQty, removeFromCart, clearCart, toast } = useStore();
  const { t } = useUI();
  const navigate = useNavigate();

  const [calculation, setCalculation] = useState(null);
  const [settings, setSettings] = useState(null);
  const [couponInput, setCouponInput] = useState('');
  const [appliedCoupon, setAppliedCoupon] = useState(null);
  const [couponError, setCouponError] = useState('');
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    api.get('/api/settings.php').then(setSettings).catch(() => {});
  }, []);

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

  const currency = settings?.currency?.symbol || '$';
  const freeOver = Number(settings?.shipping?.freeOver || 0);

  const applyCoupon = async (event) => {
    event.preventDefault();
    setCouponError('');
    if (!couponInput.trim()) return;
    try {
      const coupon = await api.post('/api/coupons.php', {
        code: couponInput.trim(),
        subtotal: calculation?.subtotal || 0,
      });
      setAppliedCoupon(coupon);
      toast(t('cart.couponApplied', { code: coupon.code }), 'success');
    } catch (error) {
      setAppliedCoupon(null);
      setCouponError(error.message);
    }
  };

  const removeCoupon = () => {
    setAppliedCoupon(null);
    setCouponInput('');
    setCouponError('');
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
                    <img src={item.image} alt={item.name} />
                  ) : (
                    <div className="product-placeholder" />
                  )}
                </Link>
                <div className="cart-item-info">
                  <Link to={`/products/${item.productId}`} className="cart-item-name">
                    {item.name}
                  </Link>
                  {(item.model || item.color) && (
                    <span className="cart-item-variant">
                      {[item.model, item.color].filter(Boolean).join(' · ')}
                    </span>
                  )}
                  <span className="cart-item-price">{money(item.price, currency)}</span>
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
                <div className="cart-item-total">{money(item.price * item.qty, currency)}</div>
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
          <form className="coupon-row" onSubmit={applyCoupon}>
            <input
              placeholder={t('cart.couponPlaceholder')}
              value={couponInput}
              onChange={(e) => setCouponInput(e.target.value.toUpperCase())}
              disabled={!!appliedCoupon}
            />
            {appliedCoupon ? (
              <button type="button" className="btn btn-outline btn-sm" onClick={removeCoupon}>
                {t('cart.remove')}
              </button>
            ) : (
              <button type="submit" className="btn btn-dark btn-sm">
                {t('cart.apply')}
              </button>
            )}
          </form>
          {couponError && <p className="stock-out small">{couponError}</p>}
          {appliedCoupon && (
            <p className="stock-in small">
              {appliedCoupon.code}: −
              {appliedCoupon.type === 'percent'
                ? `${appliedCoupon.value}%`
                : money(appliedCoupon.value, currency)}
            </p>
          )}

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

          {freeOver > 0 &&
            (calculation?.subtotal || 0) < freeOver &&
            (calculation?.shippingFee || 0) > 0 && (
              <p className="shipping-hint">
                <IconTruck size={15} />{' '}
                {t('cart.freeShippingHint', {
                  amount: money(freeOver - (calculation?.subtotal || 0), currency),
                })}
              </p>
            )}

          <div className="summary-row total">
            <span>{t('cart.total')}</span>
            <span>{money(calculation?.total || 0, currency)}</span>
          </div>

          <button
            className="btn btn-primary btn-block btn-lg"
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