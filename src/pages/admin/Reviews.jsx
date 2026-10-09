import { useEffect, useState } from 'react';
import { api } from '../../api/client.js';
import { useStore } from '../../context/StoreContext.jsx';
import { useUI } from '../../context/UIContext.jsx';
import { IconTrash } from '../../components/icons.jsx';
import RatingStars from '../../components/RatingStars.jsx';

export default function Reviews() {
  const { toast } = useStore();
  const { t, locale } = useUI();
  const [reviews, setReviews] = useState([]);
  const [productNames, setProductNames] = useState({});
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let active = true;
    Promise.all([api.get('/api/admin/reviews.php'), api.get('/api/admin/products.php?limit=200')])
      .then(([reviewRows, productRes]) => {
        if (!active) return;
        setReviews(reviewRows || []);
        const map = {};
        (productRes.items || []).forEach((p) => {
          map[p.id] = p.name;
        });
        setProductNames(map);
      })
      .catch((error) => active && toast(error.message, 'error'))
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, [toast]);

  const remove = async (review) => {
    if (!window.confirm(t('admin.reviews.deleteConfirm'))) return;
    try {
      await api.del(`/api/admin/reviews.php?id=${review.id}`);
      setReviews((prev) => prev.filter((r) => r.id !== review.id));
      toast(t('admin.reviews.deleted'), 'success');
    } catch (error) {
      toast(error.message, 'error');
    }
  };

  return (
    <div className="admin-page">
      <div className="admin-page-head">
        <div>
          <h1>{t('admin.reviews.title')}</h1>
          <p>{t('admin.reviews.count', { n: reviews.length })}</p>
        </div>
      </div>

      {loading ? (
        <div className="spinner" />
      ) : reviews.length ? (
        <div className="review-admin-list">
          {reviews.map((review) => (
            <article key={review.id} className="review review-admin">
              <div className="review-head">
                <div>
                  <strong>{review.userName}</strong>
                  <small className="muted block">
                    {productNames[review.productId] || t('admin.reviews.unknown')} ·{' '}
                    {new Date(review.createdAt).toLocaleDateString(locale)}
                  </small>
                </div>
                <RatingStars rating={review.rating} size={15} />
              </div>
              <p>{review.comment}</p>
              <button
                className="icon-btn danger"
                onClick={() => remove(review)}
                title={t('admin.reviews.deleteTitle')}
              >
                <IconTrash size={16} />
              </button>
            </article>
          ))}
        </div>
      ) : (
        <div className="empty-state">
          <h3>{t('admin.reviews.empty.title')}</h3>
          <p>{t('admin.reviews.empty.text')}</p>
        </div>
      )}
    </div>
  );
}