import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useStore } from '../context/StoreContext.jsx';
import { useUI } from '../context/UIContext.jsx';
import { money } from '../api/client.js';
import { localizeProduct } from '../i18n/catalog.js';
import { IconHeart, IconCart } from './icons.jsx';

export default function ProductCard({ product }) {
  const { addToCart, wishlist, toggleWishlist, toast } = useStore();
  const { t, lang } = useUI();
  const [imageFailed, setImageFailed] = useState(false);
  const wished = wishlist.includes(product.id);
  const outOfStock = product.stockTotal <= 0;
  const display = localizeProduct(product, lang);

  const quickAdd = (event) => {
    event.preventDefault();
    if (outOfStock) {
      toast(t('common.outOfStock'), 'error');
      return;
    }

    let model = null;
    let color = null;
    let image = product.image;

    // Products with options (e.g. phone cases) add their first in-stock variant so
    // the card keeps the same one-tap "Add To Cart" as the rest of the catalogue.
    if (product.hasVariants && Array.isArray(product.variants)) {
      const variant = product.variants.find((v) => (v.colors || []).some((c) => (c.stock ?? 0) > 0));
      if (!variant) {
        toast(t('product.toast.outOfStock'), 'error');
        return;
      }
      const inStockColor = (variant.colors || []).find((c) => (c.stock ?? 0) > 0);
      model = variant.model;
      color = inStockColor?.name || null;
      if (inStockColor?.image) image = inStockColor.image;
    }

    addToCart({
      productId: product.id,
      name: product.name,
      image,
      price: product.price,
      model,
      color,
      qty: 1,
    });

    toast(t('product.toast.added', { name: [model, color].filter(Boolean).join(' · ') || display.name }), 'success');
  };

  return (
    <article className="product-card">
      <div className="product-media">
        <Link to={`/products/${product.id}`} className="product-media-link">
          {product.image && !imageFailed ? (
            <img src={product.image} alt={display.name} loading="lazy" onError={() => setImageFailed(true)} />
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
          <button className="btn btn-dark btn-sm" onClick={quickAdd} disabled={outOfStock}>
            <IconCart size={16} /> {t('common.addToCart')}
          </button>
        </div>
      </div>

      <div className="product-info">
        <Link to={`/shop?category=${encodeURIComponent(product.category)}`} className="product-cat">
          {display.category}
        </Link>
        <Link to={`/products/${product.id}`} className="product-name">
          {display.name}
        </Link>
        <div className="price-row">
          <span className="price">{money(product.price)}</span>
          {product.compareAtPrice > product.price && (
            <span className="price-old">{money(product.compareAtPrice)}</span>
          )}
        </div>
      </div>
    </article>
  );
}