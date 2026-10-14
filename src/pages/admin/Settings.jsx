import { useEffect, useState } from 'react';
import { api } from '../../api/client.js';
import { useStore } from '../../context/StoreContext.jsx';
import { useUI } from '../../context/UIContext.jsx';
import { canonicalCategory, localizeCategory } from '../../i18n/catalog.js';
import { IconClose, IconPlus } from '../../components/icons.jsx';

export default function Settings() {
  const { toast } = useStore();
  const { t, lang } = useUI();
  const [form, setForm] = useState({
    whatsapp: '',
    shippingEnabled: true,
    flatRate: '0',
    freeOver: '0',
    currencySymbol: '$',
    currencyCode: 'USD',
    categories: [],
  });
  const [newCategory, setNewCategory] = useState('');
  const [errors, setErrors] = useState({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    let active = true;
    api
      .get('/api/admin/settings.php')
      .then((settings) => {
        if (!active) return;
        setForm({
          whatsapp: settings.whatsapp || '',
          shippingEnabled: !!settings.shipping?.enabled,
          flatRate: String(settings.shipping?.flatRate ?? 0),
          freeOver: String(settings.shipping?.freeOver ?? 0),
          currencySymbol: settings.currency?.symbol || '$',
          currencyCode: settings.currency?.code || 'USD',
          categories: (settings.categories || []).map((c) => canonicalCategory(c)),
        });
      })
      .catch((error) => active && toast(error.message, 'error'))
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, [toast]);

  const update = (field, value) => setForm((prev) => ({ ...prev, [field]: value }));

  const addCategory = () => {
    const value = canonicalCategory(newCategory);
    if (!value) return;
    setForm((prev) =>
      prev.categories.includes(value) ? prev : { ...prev, categories: [...prev.categories, value] },
    );
    setNewCategory('');
  };

  const removeCategory = (value) =>
    setForm((prev) => ({ ...prev, categories: prev.categories.filter((c) => c !== value) }));

  const save = async (event) => {
    event.preventDefault();
    setSaving(true);
    setErrors({});
    try {
      await api.put('/api/admin/settings.php', {
        whatsapp: form.whatsapp.trim(),
        shipping: {
          enabled: form.shippingEnabled,
          flatRate: parseFloat(form.flatRate) || 0,
          freeOver: parseFloat(form.freeOver) || 0,
        },
        currency: {
          symbol: form.currencySymbol.trim() || '$',
          code: form.currencyCode.trim().toUpperCase() || 'USD',
        },
        categories: form.categories,
      });
      toast(t('admin.settings.saved'), 'success');
      window.dispatchEvent(new Event('mb:settings-updated'));
    } catch (error) {
      if (error.data?.errors) setErrors(error.data.errors);
      toast(error.message, 'error');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="admin-page">
        <div className="spinner" />
      </div>
    );
  }

  return (
    <div className="admin-page">
      <div className="admin-page-head">
        <div>
          <h1>{t('admin.settings.title')}</h1>
          <p>{t('admin.settings.subtitle')}</p>
        </div>
      </div>

      <form className="panel settings-form" onSubmit={save}>
        <section className="form-section">
          <h3>{t('admin.settings.shippingRules')}</h3>
          <label className="switch">
            <input
              type="checkbox"
              checked={form.shippingEnabled}
              onChange={(e) => update('shippingEnabled', e.target.checked)}
            />
            <span>{t('admin.settings.chargeShipping')}</span>
          </label>

          <div className="form-grid">
            <div className="form-group">
              <label htmlFor="st-flat">{t('admin.settings.flatRate')}</label>
              <input
                id="st-flat"
                type="number"
                min="0"
                step="0.01"
                value={form.flatRate}
                onChange={(e) => update('flatRate', e.target.value)}
                disabled={!form.shippingEnabled}
              />
              {errors.flatRate && <span className="field-error">{errors.flatRate}</span>}
            </div>
            <div className="form-group">
              <label htmlFor="st-free">{t('admin.settings.freeOver')}</label>
              <input
                id="st-free"
                type="number"
                min="0"
                step="0.01"
                value={form.freeOver}
                onChange={(e) => update('freeOver', e.target.value)}
                disabled={!form.shippingEnabled}
              />
              {errors.freeOver && <span className="field-error">{errors.freeOver}</span>}
              <span className="muted small">{t('admin.settings.freeOverNote')}</span>
            </div>
          </div>
        </section>

        <section className="form-section">
          <h3>{t('admin.settings.contact')}</h3>
          <div className="form-grid">
            <div className="form-group">
              <label htmlFor="st-wa">{t('admin.settings.whatsapp')}</label>
              <input
                id="st-wa"
                value={form.whatsapp}
                onChange={(e) => update('whatsapp', e.target.value)}
                placeholder={t('admin.settings.whatsappPlaceholder')}
              />
              {errors.whatsapp && <span className="field-error">{errors.whatsapp}</span>}
              <span className="muted small">{t('admin.settings.whatsappNote')}</span>
            </div>
            <div className="form-group">
              <label htmlFor="st-symbol">{t('admin.settings.currencySymbol')}</label>
              <input
                id="st-symbol"
                value={form.currencySymbol}
                onChange={(e) => update('currencySymbol', e.target.value)}
                maxLength={4}
              />
            </div>
            <div className="form-group">
              <label htmlFor="st-code">{t('admin.settings.currencyCode')}</label>
              <input
                id="st-code"
                value={form.currencyCode}
                onChange={(e) => update('currencyCode', e.target.value)}
                maxLength={5}
              />
            </div>
          </div>
        </section>

        <section className="form-section">
          <h3>{t('admin.settings.categories')}</h3>
          <div className="form-group">
            <label htmlFor="st-cats">{t('admin.settings.storeCategories')}</label>
            <div className="category-add">
              <input
                id="st-cats"
                value={newCategory}
                onChange={(e) => setNewCategory(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') {
                    e.preventDefault();
                    addCategory();
                  }
                }}
                placeholder={t('admin.settings.categoriesPlaceholder')}
              />
              <button className="btn btn-primary" type="button" onClick={addCategory}>
                <IconPlus /> {t('admin.settings.categoriesAdd')}
              </button>
            </div>
            {form.categories.length > 0 && (
              <div className="category-chips">
                {form.categories.map((category) => (
                  <span key={category} className="category-chip">
                    {localizeCategory(category, lang)}
                    <button
                      type="button"
                      onClick={() => removeCategory(category)}
                      aria-label={t('admin.settings.categoriesRemove')}
                      title={t('admin.settings.categoriesRemove')}
                    >
                      <IconClose />
                    </button>
                  </span>
                ))}
              </div>
            )}
            {errors.categories && <span className="field-error">{errors.categories}</span>}
            <span className="muted small">{t('admin.settings.categoriesNote')}</span>
          </div>
        </section>

        <div className="form-actions">
          <button className="btn btn-primary" type="submit" disabled={saving}>
            {saving ? t('admin.settings.saving') : t('admin.settings.save')}
          </button>
        </div>
      </form>
    </div>
  );
}