import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../../api/client.js';
import { useStore } from '../../context/StoreContext.jsx';
import { useUI } from '../../context/UIContext.jsx';
import BrandLogo from '../../components/BrandLogo.jsx';

export default function AdminLogin() {
  const { admin, login, toast } = useStore();
  const { t } = useUI();
  const navigate = useNavigate();
  const [form, setForm] = useState({ username: '', password: '' });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    if (admin) navigate('/admin/panel', { replace: true });
  }, [admin, navigate]);

  const submit = async (event) => {
    event.preventDefault();
    setLoading(true);
    setError('');
    try {
      const res = await api.post('/api/auth.php', form);
      login(res.token, res.name);
      toast(t('admin.login.welcome'), 'success');
      navigate('/admin/panel', { replace: true });
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="admin-login">
      <form className="admin-login-card" onSubmit={submit}>
        <div className="brand brand-center">
          <BrandLogo />
        </div>
        <h1>{t('admin.login.title')}</h1>
        <p className="muted">{t('admin.login.subtitle')}</p>

        <div className="form-group">
          <label htmlFor="admin-user">{t('admin.login.username')}</label>
          <input
            id="admin-user"
            value={form.username}
            onChange={(e) => setForm({ ...form, username: e.target.value })}
            autoComplete="username"
            required
          />
        </div>
        <div className="form-group">
          <label htmlFor="admin-pass">{t('admin.login.password')}</label>
          <input
            id="admin-pass"
            type="password"
            value={form.password}
            onChange={(e) => setForm({ ...form, password: e.target.value })}
            autoComplete="current-password"
            required
          />
        </div>

        {error && <p className="field-error">{error}</p>}

        <button className="btn btn-primary btn-block" type="submit" disabled={loading}>
          {loading ? t('admin.login.signingIn') : t('admin.login.signIn')}
        </button>
        <p className="admin-hint muted">{t('admin.login.hint')}</p>
      </form>
    </div>
  );
}