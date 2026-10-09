import { Link } from 'react-router-dom';
import { useUI } from '../context/UIContext.jsx';
import ProductCard from './ProductCard.jsx';

/** A titled product shelf used on the home page. */
export default function ProductShelf({ title, subtitle, viewAllTo, products = [], currency = '$' }) {
  const { t } = useUI();
  if (!products.length) return null;

  return (
    <section className="shelf">
      <div className="container">
        <div className="shelf-head">
          <div>
            <h2>{title}</h2>
            {subtitle && <p>{subtitle}</p>}
          </div>
          {viewAllTo && (
            <Link to={viewAllTo} className="btn btn-outline btn-sm">
              {t('common.viewAll')}
            </Link>
          )}
        </div>
        <div className="product-grid">
          {products.map((product) => (
            <ProductCard key={product.id} product={product} currency={currency} />
          ))}
        </div>
      </div>
    </section>
  );
}