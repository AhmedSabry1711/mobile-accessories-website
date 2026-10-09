import { Link } from 'react-router-dom';
import { useUI } from '../context/UIContext.jsx';

export default function NotFound() {
  const { t } = useUI();
  return (
    <div className="container empty-state">
      <h1>404</h1>
      <h3>{t('notFound.title')}</h3>
      <p>{t('notFound.text')}</p>
      <Link to="/" className="btn btn-primary">
        {t('notFound.action')}
      </Link>
    </div>
  );
}