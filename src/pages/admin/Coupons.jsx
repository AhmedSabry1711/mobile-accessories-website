import { useEffect, useState } from 'react';
import { api, money } from '../../api/client.js';
import { useStore } from '../../context/StoreContext.jsx';
import { useUI } from '../../context/UIContext.jsx';
import { IconTrash, IconTicket } from '../../components/icons.jsx';

const emptyForm = { code: '', type: 'percent', value: '', minOrder: '0', expiresAt: '', active: true };

export default function Coupons() {
  const { toast } = useStore();
  const { t, locale } = useUI();
  const [coupons, setCoupons] = useState([]);
  const [form, setForm] = useState(emptyForm);
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);

  const load = () =>
    api
      .get('/api/admin/coupons.php')
      .then((rows) => setCoupons(rows || []))
      .catch((error) => toast(error.message, 'error'));

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const create = async (event) => {
    event.preventDefault();
    setSaving(true);
    setErrors({});
    try {
      const created = await api.post('/api/admin/coupons.php', {
        code: form.code,
        type: form.type,
        value: parseFloat(form.value) || 0,
        minOrder: parseFloat(form.minOrder) || 0,
        expiresAt: form.expiresAt || '',
        active: form.active,
      });
      setCoupons((prev) => [created, ...prev]);
      setForm(emptyForm);
      toast(t('admin.coupons.created'), 'success');
    } catch (error) {
      if (error.data?.errors) setErrors(error.data.errors);
      toast(error.message, 'error');
    } finally {
      setSaving(false);
    }
  };

  const toggle = async (coupon) => {
    try {
      const res = await api.put(`/api/admin/coupons.php?id=${coupon.id}`, { active: !coupon.active });
      setCoupons((prev) => prev.map((c) => (c.id === coupon.id ? { ...c, active: res.active } : c)));
    } catch (error) {
      toast(error.message, 'error');
    }
  };

  const remove = async (coupon) => {
    if (!window.confirm(t('admin.coupons.deleteConfirm', { code: coupon.code }))) return;
    try {
      await api.del(`/api/admin/coupons.php?id=${coupon.id}`);
      setCoupons((prev) => prev.filter((c) => c.id !== coupon.id));
      toast(t('admin.coupons.deleted'), 'success');
    } catch (error) {
      toast(error.message, 'error');
    }
  };

  return (
    <div className="admin-page">
      <div className="admin-page-head">
        <div>
          <h1>{t('admin.coupons.title')}</h1>
          <p>{t('admin.coupons.subtitle')}</p>
        </div>
      </div>

      <form className="panel coupon-form" onSubmit={create}>
        <div className="form-grid">
          <div className="form-group">
            <label htmlFor="cp-code">{t('admin.coupons.code')}</label>
            <input
              id="cp-code"
              value={form.code}
              onChange={(e) => setForm({ ...form, code: e.target.value.toUpperCase() })}
              placeholder="WELCOME10"
              required
            />
            {errors.code && <span className="field-error">{errors.code}</span>}
          </div>
          <div className="form-group">
            <label htmlFor="cp-type">{t('admin.coupons.type')}</label>
            <select id="cp-type" value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })}>
              <option value="percent">{t('admin.coupons.percent')}</option>
              <option value="fixed">{t('admin.coupons.fixed')}</option>
            </select>
          </div>
          <div className="form-group">
            <label htmlFor="cp-value">
              {form.type === 'percent' ? t('admin.coupons.valuePercent') : t('admin.coupons.valueFixed')}
            </label>
            <input
              id="cp-value"
              type="number"
              min="0"
              step="0.01"
              value={form.value}
              onChange={(e) => setForm({ ...form, value: e.target.value })}
              required
            />
            {errors.value && <span className="field-error">{errors.value}</span>}
          </div>
          <div className="form-group">
            <label htmlFor="cp-min">{t('admin.coupons.minOrder')}</label>
            <input
              id="cp-min"
              type="number"
              min="0"
              step="0.01"
              value={form.minOrder}
              onChange={(e) => setForm({ ...form, minOrder: e.target.value })}
            />
          </div>
          <div className="form-group">
            <label htmlFor="cp-exp">{t('admin.coupons.expires')}</label>
            <input
              id="cp-exp"
              type="date"
              value={form.expiresAt}
              onChange={(e) => setForm({ ...form, expiresAt: e.target.value })}
            />
            {errors.expiresAt && <span className="field-error">{errors.expiresAt}</span>}
          </div>
          <div className="form-group align-end">
            <button className="btn btn-primary" type="submit" disabled={saving}>
              <IconTicket size={16} /> {saving ? t('admin.coupons.creating') : t('admin.coupons.createBtn')}
            </button>
          </div>
        </div>
      </form>

      {coupons.length ? (
        <div className="panel table-scroll">
          <table className="table">
            <thead>
              <tr>
                <th>{t('admin.coupons.thCode')}</th>
                <th>{t('admin.coupons.thDiscount')}</th>
                <th>{t('admin.coupons.thMin')}</th>
                <th>{t('admin.coupons.thExpires')}</th>
                <th>{t('admin.coupons.thActive')}</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {coupons.map((coupon) => (
                <tr key={coupon.id}>
                  <td>
                    <strong>{coupon.code}</strong>
                  </td>
                  <td>
                    {coupon.type === 'percent' ? `${coupon.value}%` : money(coupon.value)}
                  </td>
                  <td>{coupon.minOrder > 0 ? money(coupon.minOrder) : '—'}</td>
                  <td>
                    {coupon.expiresAt
                      ? new Date(coupon.expiresAt).toLocaleDateString(locale)
                      : t('admin.coupons.never')}
                  </td>
                  <td>
                    <label className="switch small">
                      <input type="checkbox" checked={coupon.active} onChange={() => toggle(coupon)} />
                      <span />
                    </label>
                  </td>
                  <td className="row-actions">
                    <button
                      className="icon-btn danger"
                      onClick={() => remove(coupon)}
                      title={t('admin.products.delete')}
                    >
                      <IconTrash size={16} />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="empty-state">
          <h3>{t('admin.coupons.empty.title')}</h3>
          <p>{t('admin.coupons.empty.text')}</p>
        </div>
      )}
    </div>
  );
}