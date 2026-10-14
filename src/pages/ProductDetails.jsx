import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { api, money } from '../api/client.js';
import { useStore } from '../context/StoreContext.jsx';
import { useUI } from '../context/UIContext.jsx';
import ProductCard from '../components/ProductCard.jsx';
import { localizeColor, localizeProduct } from '../i18n/catalog.js';
import RatingStars from '../components/RatingStars.jsx';
import { IconHeart, IconCart, IconMinus, IconPlus, IconStar } from '../components/icons.jsx';

export default function ProductDetails() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { addToCart, wishlist, toggleWishlist, toast } = useStore();
  const { t, locale, lang } = useUI();

  const [product, setProduct] = useState(null);
  const [reviews, setReviews] = useState({ average: 0, count: 0, reviews: [] });
  const [related, setRelated] = useState([]);
  const [loading, setLoading] = useState(true);
  const [activeImage, setActiveImage] = useState(0);
  const [colorImage, setColorImage] = useState(null);
  const [qty, setQty] = useState(1);

  const [selectedModel, setSelectedModel] = useState('');
  const [selectedColor, setSelectedColor] = useState('');

  const [reviewForm, setReviewForm] = useState({ userName: '', rating: 5, comment: '' });
  const [submittingReview, setSubmittingReview] = useState(false);

  useEffect(() => {
    let active = true;
    setLoading(true);
    setActiveImage(0);
    setColorImage(null);
    setQty(1);

    Promise.all([
      api.get(`/api/products.php?id=${id}`),
      api.get(`/api/reviews.php?product_id=${id}`),
      api.get(`/api/products.php?related_to=${id}`),
    ])
      .then(([productRes, reviewsRes, relatedRes]) => {
        if (!active) return;
        setProduct(productRes);
        setReviews(reviewsRes);
        setRelated(relatedRes || []);

        const variants = productRes?.variants || [];
        if (variants.length) {
          setSelectedModel(variants[0].model);
          setSelectedColor(variants[0].colors?.[0]?.name || '');
          setColorImage(variants[0].colors?.[0]?.image || null);
        } else {
          setSelectedModel('');
          setSelectedColor('');
          setColorImage(null);
        }
      })
      .catch((error) => {
        if (active) toast(error.message, 'error');
      })
      .finally(() => active && setLoading(false));

    return () => {
      active = false;
    };
  }, [id, toast]);

  const variants = product?.variants || [];
  const hasVariants = variants.length > 0;

  const colorsForModel = useMemo(() => {
    if (!hasVariants) return [];
    const variant = variants.find((v) => v.model === selectedModel);
    return variant?.colors || [];
  }, [variants, selectedModel, hasVariants]);

  const activeColor = colorsForModel.find((c) => c.name === selectedColor) || null;
  const availableStock = hasVariants ? activeColor?.stock ?? 0 : product?.stockTotal ?? 0;
  const outOfStock = availableStock <= 0;
  const wished = product ? wishlist.includes(product.id) : false;

  const selectModel = (model) => {
    setSelectedModel(model);
    const variant = variants.find((v) => v.model === model);
    const firstColor = variant?.colors?.[0] || null;
    setSelectedColor(firstColor?.name || '');
    setColorImage(firstColor?.image || null);
    setQty(1);
  };

  const selectColor = (color) => {
    setSelectedColor(color.name);
    setColorImage(color.image || null);
    setQty(1);
  };

  const addToCartHandler = () => {
    if (!product) return;
    if (hasVariants && (!selectedModel || !selectedColor)) {
      toast(t('product.toast.selectOption'), 'error');
      return;
    }
    if (outOfStock) {
      toast(t('product.toast.outOfStock'), 'error');
      return;
    }
    if (qty > availableStock) {
      toast(t('product.toast.onlyLeft', { n: availableStock }), 'error');
      return;
    }
    addToCart({
      productId: product.id,
      name: product.name,
      image: product.image,
      price: product.price,
      model: hasVariants ? selectedModel : null,
      color: hasVariants ? selectedColor : null,
      qty,
    });
  };

  const submitReview = async (event) => {
    event.preventDefault();
    setSubmittingReview(true);
    try {
      const created = await api.post('/api/reviews.php', {
        product_id: product.id,
        userName: reviewForm.userName,
        rating: reviewForm.rating,
        comment: reviewForm.comment,
      });
      const list = [created, ...reviews.reviews];
      const average = list.reduce((sum, r) => sum + r.rating, 0) / list.length;
      setReviews({ average: Number(average.toFixed(1)), count: list.length, reviews: list });
      setReviewForm({ userName: '', rating: 5, comment: '' });
      toast(t('product.toast.reviewPosted'), 'success');
    } catch (error) {
      toast(error.message, 'error');
    } finally {
      setSubmittingReview(false);
    }
  };

  if (loading) {
    return (
      <div className="container loading-page">
        <div className="spinner" />
        <p>{t('product.loading')}</p>
      </div>
    );
  }

  if (!product) {
    return (
      <div className="container empty-state">
        <h3>{t('product.notFound.title')}</h3>
        <Link to="/shop" className="btn btn-primary btn-sm">
          {t('product.notFound.action')}
        </Link>
      </div>
    );
  }

  const images = product.images?.length ? product.images : [product.image].filter(Boolean);
  const mainImage = colorImage || images[activeImage];
  const display = localizeProduct(product, lang);

  return (
    <div className="container product-page">
      <nav className="breadcrumbs">
        <Link to="/">{t('product.breadcrumbs.home')}</Link>
        <span>/</span>
        <Link to={`/shop?category=${encodeURIComponent(product.category)}`}>{display.category}</Link>
        <span>/</span>
        <span>{display.name}</span>
      </nav>

      <div className="product-main">
        <div className="gallery">
          <div className="gallery-main">
            {mainImage ? (
              <img src={mainImage} alt={display.name} />
            ) : (
              <div className="product-placeholder">{t('common.noImage')}</div>
            )}
            {product.discountPercent > 0 && (
              <span className="badge badge-sale">-{product.discountPercent}%</span>
            )}
          </div>
          {images.length > 1 && (
            <div className="gallery-thumbs">
              {images.map((image, index) => (
                <button
                  key={index}
                  className={`thumb ${!colorImage && index === activeImage ? 'thumb-active' : ''}`}
                  onClick={() => {
                    setActiveImage(index);
                    setColorImage(null);
                  }}
                >
                  <img src={image} alt={`${display.name} ${index + 1}`} />
                </button>
              ))}
            </div>
          )}
          {hasVariants && colorsForModel.length > 0 && (
            <div className="gallery-swatches">
              {colorsForModel.map((color) => (
                <button
                  key={color.name}
                  type="button"
                  className={`gallery-swatch ${
                    selectedColor === color.name ? 'gallery-swatch-active' : ''
                  } ${color.stock <= 0 ? 'gallery-swatch-out' : ''}`}
                  style={{ background: color.hex }}
                  title={`${localizeColor(color.name, lang)} — ${t('product.stockIn', { n: color.stock })}`}
                  aria-label={localizeColor(color.name, lang)}
                  onClick={() => selectColor(color)}
                />
              ))}
            </div>
          )}
        </div>

        <div className="product-summary">
          <span className="product-cat">{display.category}</span>
          <h1>{display.name}</h1>

          <div className="product-rating">
            <RatingStars rating={reviews.average} size={17} showValue />
            <a href="#reviews">{t('product.reviewCount', { n: reviews.count })}</a>
          </div>

          <div className="product-price">
            <span className="price-lg">{money(product.price)}</span>
            {product.compareAtPrice > product.price && (
              <>
                <span className="price-old-lg">{money(product.compareAtPrice)}</span>
                <span className="save-pill">
                  {t('product.save', { amount: money(product.compareAtPrice - product.price) })}
                </span>
              </>
            )}
          </div>

          <p className="product-desc">{display.description}</p>

          {hasVariants && (
            <div className="variant-block">
              <label className="variant-label" htmlFor="variant-model">
                {t('product.model')}
              </label>
              <select
                id="variant-model"
                className="variant-select"
                value={selectedModel}
                onChange={(e) => selectModel(e.target.value)}
              >
                {variants.map((variant) => (
                  <option key={variant.model} value={variant.model}>
                    {variant.model}
                  </option>
                ))}
              </select>

              <label className="variant-label">{t('product.color')}</label>
              <div className="variant-colors">
                {colorsForModel.map((color) => (
                  <button
                    key={color.name}
                    className={`color-chip ${selectedColor === color.name ? 'color-active' : ''} ${
                      color.stock <= 0 ? 'color-out' : ''
                    }`}
                    onClick={() => selectColor(color)}
                    title={`${localizeColor(color.name, lang)} — ${t('product.stockIn', { n: color.stock })}`}
                  >
                    <span className="swatch" style={{ background: color.hex }} />
                    {localizeColor(color.name, lang)}
                  </button>
                ))}
              </div>

              <p className={`stock-line ${outOfStock ? 'stock-out' : 'stock-in'}`}>
                {outOfStock
                  ? t('product.stockOutOption')
                  : t('product.stockIn', { n: availableStock })}
              </p>
            </div>
          )}

          {!hasVariants && (
            <p className={`stock-line ${outOfStock ? 'stock-out' : 'stock-in'}`}>
              {outOfStock
                ? t('product.stockOut')
                : t('product.stockInSimple', { n: product.stockTotal })}
            </p>
          )}

          <div className="buy-row">
            <div className="qty-selector">
              <button
                onClick={() => setQty((v) => Math.max(1, v - 1))}
                aria-label={t('product.decreaseQty')}
              >
                <IconMinus size={16} />
              </button>
              <span>{qty}</span>
              <button
                onClick={() => setQty((v) => Math.min(availableStock || 1, v + 1))}
                aria-label={t('product.increaseQty')}
              >
                <IconPlus size={16} />
              </button>
            </div>

            <button className="btn btn-primary btn-lg btn-pulse" onClick={addToCartHandler} disabled={outOfStock}>
              <IconCart size={18} /> {t('common.addToCart')}
            </button>

            <button
              className={`btn btn-outline btn-icon ${wished ? 'wished' : ''}`}
              onClick={() => toggleWishlist(product.id)}
              title={wished ? t('wishlist.removed') : t('wishlist.added')}
            >
              <IconHeart size={20} filled={wished} />
            </button>
          </div>

          <button
            className="btn btn-dark btn-block btn-pulse"
            onClick={() => {
              addToCartHandler();
              if (!outOfStock) navigate('/checkout');
            }}
            disabled={outOfStock}
          >
            {t('product.buyNow')}
          </button>
        </div>
      </div>

      {related.length > 0 && (
        <section className="related">
          <h2>{t('product.relatedTitle')}</h2>
          <div className="product-grid">
            {related.map((item) => (
              <ProductCard key={item.id} product={item} />
            ))}
          </div>
        </section>
      )}

      <section className="reviews" id="reviews">
        <h2>{t('product.reviewsTitle')}</h2>
        {reviews.count > 0 ? (
          <div className="reviews-summary">
            <div className="reviews-score">
              <strong>{reviews.average.toFixed(1)}</strong>
              <RatingStars rating={reviews.average} size={16} />
              <span>{t('product.reviewCount', { n: reviews.count })}</span>
            </div>
            <div className="reviews-list">
              {reviews.reviews.map((review) => (
                <article key={review.id} className="review">
                  <div className="review-head">
                    <strong>{review.userName}</strong>
                    <RatingStars rating={review.rating} size={14} />
                  </div>
                  <p>{review.comment}</p>
                  <time>{new Date(review.createdAt).toLocaleDateString(locale)}</time>
                </article>
              ))}
            </div>
          </div>
        ) : (
          <p className="muted">{t('product.noReviews')}</p>
        )}

        <form className="review-form" onSubmit={submitReview}>
          <h3>{t('product.writeReview')}</h3>
          <div className="form-row">
            <div className="form-group">
              <label htmlFor="review-name">{t('product.reviewFields.name')}</label>
              <input
                id="review-name"
                value={reviewForm.userName}
                onChange={(e) => setReviewForm({ ...reviewForm, userName: e.target.value })}
                required
                minLength={2}
                maxLength={50}
                placeholder={t('product.reviewFields.namePlaceholder')}
              />
            </div>
            <div className="form-group">
              <label htmlFor="review-rating">{t('product.reviewFields.rating')}</label>
              <div className="rating-input" id="review-rating">
                {[1, 2, 3, 4, 5].map((value) => (
                  <button
                    key={value}
                    type="button"
                    className={value <= reviewForm.rating ? 'star-on' : ''}
                    onClick={() => setReviewForm({ ...reviewForm, rating: value })}
                    aria-label={t('common.ratingOut', { n: value })}
                  >
                    <IconStar size={22} filled={value <= reviewForm.rating} />
                  </button>
                ))}
              </div>
            </div>
          </div>
          <div className="form-group">
            <label htmlFor="review-comment">{t('product.reviewFields.review')}</label>
            <textarea
              id="review-comment"
              rows={4}
              value={reviewForm.comment}
              onChange={(e) => setReviewForm({ ...reviewForm, comment: e.target.value })}
              required
              minLength={3}
              maxLength={1000}
              placeholder={t('product.reviewFields.commentPlaceholder')}
            />
          </div>
          <button type="submit" className="btn btn-primary" disabled={submittingReview}>
            {submittingReview
              ? t('product.reviewFields.posting')
              : t('product.reviewFields.post')}
          </button>
        </form>
      </section>

    </div>
  );
}