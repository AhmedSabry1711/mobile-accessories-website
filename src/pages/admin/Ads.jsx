import { useEffect, useState } from 'react';
import { api } from '../../api/client.js';
import { useStore } from '../../context/StoreContext.jsx';
import { useUI } from '../../context/UIContext.jsx';
import { IconTrash, IconPlus, IconImage } from '../../components/icons.jsx';

const emptyForm = { title: '', mediaUrl: '', type: 'image', linkUrl: '', order: '0', active: true };

export default function Ads() {
  const { toast } = useStore();
  const { t } = useUI();
  const [ads, setAds] = useState([]);
  const [form, setForm] = useState(emptyForm);
  const [errors, setErrors] = useState({});
  const [uploading, setUploading] = useState(false);
  const [saving, setSaving] = useState(false);

  const load = () =>
    api
      .get('/api/admin/ads.php')
      .then((rows) => setAds(rows || []))
      .catch((error) => toast(error.message, 'error'));

  useEffect(() => {
    load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const upload = async (event) => {
    const file = event.target.files?.[0];
    if (!file) return;
    setUploading(true);
    try {
      const data = new FormData();
      data.append('file', file);
      const res = await api.upload('/api/upload.php', data);
      setForm((prev) => ({ ...prev, mediaUrl: res.url, type: res.type }));
    } catch (error) {
      toast(error.message, 'error');
    } finally {
      setUploading(false);
      event.target.value = '';
    }
  };

  const create = async (event) => {
    event.preventDefault();
    setSaving(true);
    setErrors({});
    try {
      const created = await api.post('/api/admin/ads.php', {
        title: form.title,
        mediaUrl: form.mediaUrl,
        type: form.type,
        linkUrl: form.linkUrl,
        order: parseInt(form.order, 10) || 0,
        active: form.active,
      });
      setAds((prev) => [...prev, created]);
      setForm(emptyForm);
      toast(t('admin.ads.added'), 'success');
    } catch (error) {
      if (error.data?.errors) setErrors(error.data.errors);
      toast(error.message, 'error');
    } finally {
      setSaving(false);
    }
  };

  const toggle = async (ad) => {
    try {
      const res = await api.put(`/api/admin/ads.php?id=${ad.id}`, { active: !ad.active });
      setAds((prev) => prev.map((a) => (a.id === ad.id ? { ...a, active: res.active } : a)));
    } catch (error) {
      toast(error.message, 'error');
    }
  };

  const remove = async (ad) => {
    if (!window.confirm(t('admin.ads.deleteConfirm', { title: ad.title }))) return;
    try {
      await api.del(`/api/admin/ads.php?id=${ad.id}`);
      setAds((prev) => prev.filter((a) => a.id !== ad.id));
      toast(t('admin.ads.deleted'), 'success');
    } catch (error) {
      toast(error.message, 'error');
    }
  };

  return (
    <div className="admin-page">
      <div className="admin-page-head">
        <div>
          <h1>{t('admin.ads.title')}</h1>
          <p>{t('admin.ads.subtitle')}</p>
        </div>
      </div>

      <form className="panel" onSubmit={create}>
        <div className="form-grid">
          <div className="form-group span-2">
            <label htmlFor="ad-title">{t('admin.ads.titleLabel')}</label>
            <input
              id="ad-title"
              value={form.title}
              onChange={(e) => setForm({ ...form, title: e.target.value })}
              placeholder={t('admin.ads.titlePlaceholder')}
              required
            />
            {errors.title && <span className="field-error">{errors.title}</span>}
          </div>

          <div className="form-group span-2">
            <label>{t('admin.ads.media')}</label>
            <div className="image-controls">
              <label className="btn btn-outline btn-sm upload-btn">
                <IconImage size={16} /> {uploading ? t('admin.ads.uploading') : t('admin.ads.upload')}
                <input
                  type="file"
                  accept="image/*,video/mp4,video/webm"
                  onChange={upload}
                  hidden
                />
              </label>
              <div className="inline-input">
                <input
                  placeholder={t('admin.ads.pasteMediaUrl')}
                  value={form.mediaUrl}
                  onChange={(e) => setForm({ ...form, mediaUrl: e.target.value })}
                />
              </div>
            </div>
            {errors.mediaUrl && <span className="field-error">{errors.mediaUrl}</span>}
            {form.mediaUrl && (
              <div className="ad-preview">
                {form.type === 'video' ? (
                  <video src={form.mediaUrl} muted autoPlay loop playsInline />
                ) : (
                  <img src={form.mediaUrl} alt={t('admin.ads.preview')} />
                )}
              </div>
            )}
          </div>

          <div className="form-group">
            <label htmlFor="ad-type">{t('admin.ads.type')}</label>
            <select id="ad-type" value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })}>
              <option value="image">{t('admin.ads.imageGif')}</option>
              <option value="video">{t('admin.ads.video')}</option>
            </select>
          </div>
          <div className="form-group">
            <label htmlFor="ad-order">{t('admin.ads.order')}</label>
            <input
              id="ad-order"
              type="number"
              min="0"
              value={form.order}
              onChange={(e) => setForm({ ...form, order: e.target.value })}
            />
          </div>
          <div className="form-group span-2">
            <label htmlFor="ad-link">{t('admin.ads.link')}</label>
            <input
              id="ad-link"
              value={form.linkUrl}
              onChange={(e) => setForm({ ...form, linkUrl: e.target.value })}
              placeholder={t('admin.ads.linkPlaceholder')}
            />
            {errors.linkUrl && <span className="field-error">{errors.linkUrl}</span>}
          </div>
          <div className="form-group align-end">
            <button className="btn btn-primary" type="submit" disabled={saving || uploading}>
              <IconPlus size={16} /> {saving ? t('admin.ads.saving') : t('admin.ads.addBtn')}
            </button>
          </div>
        </div>
      </form>

      {ads.length ? (
        <div className="ad-list">
          {ads.map((ad) => (
            <article key={ad.id} className={`ad-item ${ad.active ? '' : 'ad-inactive'}`}>
              <div className="ad-thumb">
                {ad.type === 'video' ? (
                  <video src={ad.mediaUrl} muted loop playsInline />
                ) : (
                  <img src={ad.mediaUrl} alt={ad.title} />
                )}
              </div>
              <div className="ad-info">
                <strong>{ad.title}</strong>
                <small className="muted">
                  {ad.type} · {t('admin.ads.order')} {ad.order} {ad.linkUrl ? `· ${ad.linkUrl}` : ''}
                </small>
              </div>
              <label className="switch small" title={t('admin.ads.active')}>
                <input type="checkbox" checked={ad.active} onChange={() => toggle(ad)} />
                <span />
              </label>
              <button className="icon-btn danger" onClick={() => remove(ad)} title={t('admin.ads.delete')}>
                <IconTrash size={16} />
              </button>
            </article>
          ))}
        </div>
      ) : (
        <div className="empty-state">
          <h3>{t('admin.ads.empty.title')}</h3>
          <p>{t('admin.ads.empty.text')}</p>
        </div>
      )}
    </div>
  );
}