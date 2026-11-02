import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { useUI } from '../context/UIContext.jsx';
import BrandLogo from './BrandLogo.jsx';
import { IconLock } from './icons.jsx';
import { isGateOpen, openGate, verifyOwnerPassword } from './AdminGate.jsx';

/**
 * Route guard for the whole admin area.
 *
 * Opening `/admin` or `/admin/panel` directly (a shared link, browser history,
 * a bookmark…) lands on the owner-password screen instead of the admin UI. Only
 * a correct password unlocks the area for the rest of the browser session and
 * reveals the login form; the storefront lock in the footer uses the same gate.
 */
export default function RequireOwnerGate({ children }) {
  const { t } = useUI();
  const [unlocked, setUnlocked] = useState(() => isGateOpen());
  const [value, setValue] = useState('');
  const [error, setError] = useState(false);
  const inputRef = useRef(null);

  useEffect(() => {
    if (!unlocked) inputRef.current?.focus();
  }, [unlocked]);

  if (unlocked) return children;

  const submit = (event) => {
    event.preventDefault();
    if (!verifyOwnerPassword(value)) {
      setError(true);
      setValue('');
      inputRef.current?.focus();
      return;
    }
    openGate();
    setUnlocked(true);
  };

  return (
    <div className="admin-login">
      <form className="admin-login-card admin-gate" onSubmit={submit}>
        <div className="brand brand-center">
          <BrandLogo />
        </div>

        <div className="gate-badge">
          <IconLock size={22} />
        </div>

        <h1>{t('admin.gate.title')}</h1>
        <p className="muted">{t('admin.gate.subtitle')}</p>

        <div className="form-group">
          <input
            ref={inputRef}
            id="owner-gate-password"
            type="password"
            value={value}
            onChange={(event) => {
              setValue(event.target.value);
              setError(false);
            }}
            placeholder={t('admin.gate.placeholder')}
            autoComplete="off"
            aria-label={t('admin.gate.placeholder')}
            required
          />
        </div>

        {error && <p className="field-error">{t('admin.gate.error')}</p>}

        <button className="btn btn-primary btn-block" type="submit">
          {t('admin.gate.submit')}
        </button>

        <Link to="/" className="btn btn-outline btn-block admin-login-back">
          {t('admin.login.back')}
        </Link>
      </form>
    </div>
  );
}
