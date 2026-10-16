import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useUI } from '../context/UIContext.jsx';
import { IconLock, IconClose } from './icons.jsx';

/**
 * Owner gate in front of the admin area.
 *
 * The little lock in the footer opens this dialog, which asks for the *owner*
 * password only (no username). A correct entry unlocks the admin routes for the
 * current browser tab, so the admin login page can then be reached. Leaving the
 * admin area re-locks it, so coming back asks for the password again.
 *
 * NOTE: this is a convenience gate, not real security — the value ships inside
 * the JS bundle. The real authentication is still the admin login (username +
 * password verified by the API). Change the admin password from its default!
 */
const GATE_KEY = 'mb_admin_gate';
const OWNER_PASSWORD = 'Pa$$w0rd';

export function isGateOpen() {
  try {
    return sessionStorage.getItem(GATE_KEY) === '1';
  } catch {
    return false;
  }
}

export function openGate() {
  try {
    sessionStorage.setItem(GATE_KEY, '1');
  } catch {
    /* storage unavailable — ignore */
  }
}

export function closeGate() {
  try {
    sessionStorage.removeItem(GATE_KEY);
  } catch {
    /* storage unavailable — ignore */
  }
}

export default function AdminGate({ open, onClose }) {
  const { t } = useUI();
  const navigate = useNavigate();
  const [value, setValue] = useState('');
  const [error, setError] = useState(false);
  const inputRef = useRef(null);

  useEffect(() => {
    if (!open) return undefined;
    setValue('');
    setError(false);
    const id = window.setTimeout(() => inputRef.current?.focus(), 0);
    return () => window.clearTimeout(id);
  }, [open]);

  if (!open) return null;

  const submit = (event) => {
    event.preventDefault();
    if (value === OWNER_PASSWORD) {
      openGate();
      onClose();
      navigate('/admin');
      return;
    }
    setError(true);
  };

  return (
    <div className="modal-overlay" onMouseDown={onClose} role="presentation">
      <form
        className="modal admin-gate"
        onMouseDown={(event) => event.stopPropagation()}
        onSubmit={submit}
      >
        <div className="modal-head">
          <div>
            <h2>{t('admin.gate.title')}</h2>
            <p className="muted">{t('admin.gate.subtitle')}</p>
          </div>
          <button
            type="button"
            className="btn btn-outline btn-sm"
            onClick={onClose}
            aria-label={t('admin.gate.cancel')}
          >
            <IconClose size={14} />
          </button>
        </div>

        <div className="gate-badge">
          <IconLock size={20} />
        </div>

        <div className="form-group">
          <input
            ref={inputRef}
            type="password"
            value={value}
            onChange={(event) => {
              setValue(event.target.value);
              setError(false);
            }}
            placeholder={t('admin.gate.placeholder')}
            autoComplete="off"
            required
          />
        </div>

        {error && <p className="field-error">{t('admin.gate.error')}</p>}

        <button className="btn btn-primary btn-block" type="submit">
          {t('admin.gate.submit')}
        </button>
      </form>
    </div>
  );
}
