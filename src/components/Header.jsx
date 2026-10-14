import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useStore } from '../context/StoreContext.jsx';
import { useUI } from '../context/UIContext.jsx';
import { api } from '../api/client.js';
import { localizeCategory } from '../i18n/catalog.js';
import BrandLogo from './BrandLogo.jsx';
import {
  IconSearch,
  IconHeart,
  IconCart,
  IconMenu,
  IconClose,
  IconGlobe,
  IconMoon,
  IconSun,
} from './icons.jsx';

export default function Header() {
  const { cartCount, wishlist } = useStore();
  const { t, theme, toggleTheme, lang, toggleLang } = useUI();
  const navigate = useNavigate();
  const [settings, setSettings] = useState(null);
  const [query, setQuery] = useState('');
  const [menuOpen, setMenuOpen] = useState(false);

  // Reload store settings on every navigation, and whenever the admin saves new
  // ones, so categories added in the panel show up in the nav bar right away.
  useEffect(() => {
    let active = true;
    const load = () =>
      api
        .get('/api/settings.php')
        .then((data) => active && setSettings(data))
        .catch(() => active && setSettings(null));
    load();
    const onUpdated = () => {
      api
        .get('/api/settings.php')
        .then((data) => active && setSettings(data))
        .catch(() => {});
    };
    window.addEventListener('mb:settings-updated', onUpdated);
    return () => {
      active = false;
      window.removeEventListener('mb:settings-updated', onUpdated);
    };
  }, [location.pathname]);

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
          <BrandLogo />
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
            className="icon-link lang-toggle"
            onClick={toggleLang}
            title={lang === 'ar' ? 'Switch to English' : 'التبديل إلى العربية'}
            aria-label={lang === 'ar' ? 'Switch to English' : 'التبديل إلى العربية'}
          >
            <IconGlobe size={22} />
            <span className="lang-code">{lang === 'ar' ? 'EN' : 'ع'}</span>
          </button>

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
              {localizeCategory(category, lang)}
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