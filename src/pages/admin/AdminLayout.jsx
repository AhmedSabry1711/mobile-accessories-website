import { useEffect, useState } from 'react';
import { NavLink, Navigate, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { api } from '../../api/client.js';
import { useStore } from '../../context/StoreContext.jsx';
import { useUI } from '../../context/UIContext.jsx';
import {
  IconChart,
  IconBox,
  IconTruck,
  IconTicket,
  IconImage,
  IconStar,
  IconBolt,
  IconLogout,
} from '../../components/icons.jsx';

export default function AdminLayout() {
  const { admin, login, logout, toast } = useStore();
  const { t } = useUI();
  const navigate = useNavigate();
  const location = useLocation();
  const [checking, setChecking] = useState(true);

  useEffect(() => {
    let active = true;
    if (!admin) {
      setChecking(false);
      return;
    }
    api
      .get('/api/auth.php')
      .then((res) => active && res && login(admin.token, res.name))
      .catch(() => {
        if (active) {
          logout();
          toast(t('admin.sessionExpired'), 'error');
          navigate('/admin', { replace: true });
        }
      })
      .finally(() => active && setChecking(false));
    return () => {
      active = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [location.pathname]);

  if (!admin) return <Navigate to="/admin" replace />;

  if (checking) {
    return (
      <div className="container loading-page">
        <div className="spinner" />
      </div>
    );
  }

  const links = [
    { to: '/admin/panel', label: t('admin.layout.dashboard'), icon: <IconChart size={18} />, end: true },
    { to: '/admin/panel/products', label: t('admin.layout.products'), icon: <IconBox size={18} /> },
    { to: '/admin/panel/orders', label: t('admin.layout.orders'), icon: <IconTruck size={18} /> },
    { to: '/admin/panel/coupons', label: t('admin.layout.coupons'), icon: <IconTicket size={18} /> },
    { to: '/admin/panel/ads', label: t('admin.layout.ads'), icon: <IconImage size={18} /> },
    { to: '/admin/panel/reviews', label: t('admin.layout.reviews'), icon: <IconStar size={18} /> },
    { to: '/admin/panel/settings', label: t('admin.layout.settings'), icon: <IconBolt size={18} /> },
  ];

  return (
    <div className="admin-layout">
      <aside className="admin-sidebar">
        <div className="admin-brand">
          <span className="brand-logo">
            <IconBolt size={20} />
          </span>
          <span>
            Mobi<strong>Boost</strong>
          </span>
        </div>
        <nav className="admin-nav">
          {links.map((link) => (
            <NavLink
              key={link.to}
              to={link.to}
              end={link.end}
              className={({ isActive }) => `admin-nav-link ${isActive ? 'active' : ''}`}
            >
              {link.icon}
              <span>{link.label}</span>
            </NavLink>
          ))}
        </nav>
        <button className="admin-logout" onClick={logout}>
          <IconLogout size={18} /> {t('admin.layout.signOut')}
        </button>
      </aside>

      <div className="admin-content">
        <Outlet />
      </div>
    </div>
  );
}