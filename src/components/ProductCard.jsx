import { Link } from 'react-router-dom';
import { useStore } from '../context/StoreContext.jsx';
import { useUI } from '../context/UIContext.jsx';
import { money } from '../api/client.js';
import { IconHeart, IconCart } from './icons.jsx';

export default function ProductCard({ product, currency = '$' }) {
  const { addToCart, wishlist, toggleWishlist, toast } = useStore();
  const { t } = useUI();
  const wished = wishlist.includes(product.id);
  const outOfStock = product.stockTotal <= 0;

  const quickAdd = (event) => {
    event.preventDefault();
    if (outOfStock) {
      toast(t('common.outOfStock'), 'error');
      return;
    }
    addToCart({
      productId: product.id,
      name: product.name,
      image: product.image,
      price: product.price,
      model: null,
      color: null,
      qty: 1,
    });
  };

  return (
    <article className="product-card">
      <div className="product-media">
        <Link to={`/products/${product.id}`} className="product-media-link">
          {product.image ? (
            <img src={product.image} alt={product.name} loading="lazy" />
          ) : (
            <div className="product-placeholder">{t('common.noImage')}</div>
          )}
        </Link>

        {product.discountPercent > 0 && (
          <span className="badge badge-sale">-{product.discountPercent}%</span>
        )}
        {outOfStock && <span className="badge badge-out">{t('common.outOfStock')}</span>}

        <button
          className={`wish-btn ${wished ? 'wished' : ''}`}
          onClick={() => toggleWishlist(product.id)}
          aria-label={wished ? t('wishlist.removed') : t('wishlist.added')}
          title={wished ? t('wishlist.removed') : t('wishlist.added')}
        >
          <IconHeart size={18} filled={wished} />
        </button>

        <div className="quick-actions">
          {product.hasVariants ? (
            <Link to={`/products/${product.id}`} className="btn btn-dark btn-sm">
              {t('common.selectOptions')}
            </Link>
          ) : (
            <button className="btn btn-dark btn-sm" onClick={quickAdd} disabled={outOfStock}>
              <IconCart size={16} /> {t('common.addToCart')}
            </button>
          )}
        </div>
      </div>

      <div className="product-info">
        <Link to={`/shop?category=${encodeURIComponent(product.category)}`} className="product-cat">
          {product.category}
        </Link>
        <Link to={`/products/${product.id}`} className="product-name">
          {product.name}
        </Link>
        <div className="price-row">
          <span className="price">{money(product.price, currency)}</span>
          {product.compareAtPrice > product.price && (
            <span className="price-old">{money(product.compareAtPrice, currency)}</span>
          )}
        </div>
      </div>
    </article>
  );
}