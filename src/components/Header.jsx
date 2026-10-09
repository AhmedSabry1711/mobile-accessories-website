import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useStore } from '../context/StoreContext.jsx';
import { useUI } from '../context/UIContext.jsx';
import { api } from '../api/client.js';
import {
  IconSearch,
  IconHeart,
  IconCart,
  IconMenu,
  IconClose,
  IconBolt,
  IconMoon,
  IconSun,
} from './icons.jsx';

export default function Header() {
  const { cartCount, wishlist } = useStore();
  const { t, theme, toggleTheme } = useUI();
  const navigate = useNavigate();
  const [settings, setSettings] = useState(null);
  const [query, setQuery] = useState('');
  const [menuOpen, setMenuOpen] = useState(false);

  useEffect(() => {
    api.get('/api/settings.php').then(setSettings).catch(() => setSettings(null));
  }, []);

  const categories = settings?.categories || [];

  const submitSearch = (event) => {
    event.preventDefault();
    setMenuOpen(false);
    const q = query.trim();
    navigate(q ? `/shop?q=${encodeURIComponent(q)}` : '/shop');
  };

  return (
    <header className="header">
      <div className="container header-inner">
        <Link to="/" className="brand" onClick={() => setMenuOpen(false)}>
          <span className="brand-logo">
            <IconBolt size={22} />
          </span>
          <span className="brand-name">
            Mobi<strong>Boost</strong>
          </span>
        </Link>

        <form className="search" onSubmit={submitSearch} role="search">
          <IconSearch size={18} />
          <input
            type="search"
            placeholder={t('header.searchPlaceholder')}
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            aria-label={t('header.searchAria')}
          />
          <button type="submit" className="btn btn-primary btn-sm">
            {t('common.search')}
          </button>
        </form>

        <div className="header-actions">
          <button
            className="icon-link theme-toggle"
            onClick={toggleTheme}
            title={theme === 'dark' ? t('header.themeLight') : t('header.themeDark')}
            aria-label={theme === 'dark' ? t('header.themeLight') : t('header.themeDark')}
          >
            {theme === 'dark' ? <IconSun size={22} /> : <IconMoon size={22} />}
          </button>

          <Link to="/shop" className="icon-link" title={t('header.wishlist')} aria-label={t('header.wishlist')}>
            <IconHeart size={22} />
            {wishlist.length > 0 && <span className="count-badge">{wishlist.length}</span>}
          </Link>

          <Link to="/cart" className="icon-link" title={t('header.cart')} aria-label={t('header.cart')}>
            <IconCart size={22} />
            {cartCount > 0 && <span className="count-badge">{cartCount}</span>}
          </Link>

          <button
            className="icon-link menu-toggle"
            onClick={() => setMenuOpen((v) => !v)}
            aria-label={t('header.menu')}
          >
            {menuOpen ? <IconClose size={24} /> : <IconMenu size={24} />}
          </button>
        </div>
      </div>

      <nav className={`categories ${menuOpen ? 'categories-open' : ''}`}>
        <div className="container categories-inner">
          <Link to="/shop" onClick={() => setMenuOpen(false)}>
            {t('header.allProducts')}
          </Link>
          {categories.map((category) => (
            <Link
              key={category}
              to={`/shop?category=${encodeURIComponent(category)}`}
              onClick={() => setMenuOpen(false)}
            >
              {category}
            </Link>
          ))}
          <Link to="/shop?filter=sale" className="sale-link" onClick={() => setMenuOpen(false)}>
            {t('header.saleLink')}
          </Link>
        </div>
      </nav>
    </header>
  );
}