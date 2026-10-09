import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { api, money } from '../../api/client.js';
import { useStore } from '../../context/StoreContext.jsx';
import { useUI } from '../../context/UIContext.jsx';
import { IconPlus, IconTrash, IconImage } from '../../components/icons.jsx';

const emptyColor = () => ({ name: '', hex: '#4f46e5', stock: 0 });
const emptyVariant = () => ({ model: '', colors: [emptyColor()] });

const initialForm = {
  name: '',
  category: '',
  description: '',
  price: '',
  costPrice: '',
  compareAtPrice: '',
  stock: '0',
  status: 'active',
  images: [],
};

export default function ProductForm() {
  const { id } = useParams();
  const isEdit = Boolean(id);
  const navigate = useNavigate();
  const { toast } = useStore();
  const { t } = useUI();

  const [form, setForm] = useState(initialForm);
  const [variants, setVariants] = useState([]);
  const [useVariants, setUseVariants] = useState(false);
  const [categories, setCategories] = useState([]);
  const [imageUrl, setImageUrl] = useState('');
  const [uploading, setUploading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState({});
  const [loading, setLoading] = useState(isEdit);

  useEffect(() => {
    api
      .get('/api/admin/settings.php')
      .then((s) => setCategories(s?.categories || []))
      .catch(() => {});
  }, []);

  useEffect(() => {
    if (!isEdit) return;
    let active = true;
    api
      .get(`/api/admin/products.php?limit=200`)
      .then((res) => {
        const product = (res.items || []).find((p) => p.id === id);
        if (!product) {
          toast(t('admin.productForm.notFound'), 'error');
          navigate('/admin/panel/products');
          return;
        }
        setForm({
          name: product.name,
          category: product.category,
          description: product.description,
          price: String(product.price),
          costPrice: String(product.costPrice),
          compareAtPrice: String(product.compareAtPrice || ''),
          stock: String(product.stockTotal || product.stock || 0),
          status: product.status,
          images: product.images || [],
        });
        setUseVariants(product.hasVariants);
        setVariants(product.variants || []);
      })
      .catch((error) => active && toast(error.message, 'error'))
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id, isEdit]);

  const profit = useMemo(() => {
    const price = parseFloat(form.price) || 0;
    const cost = parseFloat(form.costPrice) || 0;
    return price - cost;
  }, [form.price, form.costPrice]);

  const update = (field, value) => setForm((prev) => ({ ...prev, [field]: value }));

  const uploadFile = async (file) => {
    if (!file) return;
    setUploading(true);
    try {
      const data = new FormData();
      data.append('file', file);
      const res = await api.upload('/api/upload.php', data);
      return res.url;
    } catch (error) {
      toast(error.message, 'error');
      return null;
    } finally {
      setUploading(false);
    }
  };

  const onUploadImage = async (event) => {
    const url = await uploadFile(event.target.files?.[0]);
    if (url) update('images', [...form.images, url]);
    event.target.value = '';
  };

  const addImageUrl = () => {
    const url = imageUrl.trim();
    if (!url) return;
    update('images', [...form.images, url]);
    setImageUrl('');
  };

  const removeImage = (url) =>
    update(
      'images',
      form.images.filter((i) => i !== url)
    );

  // ---- Variant helpers -----------------------------------------------------
  const updateVariant = (index, field, value) =>
    setVariants((prev) => prev.map((v, i) => (i === index ? { ...v, [field]: value } : v)));

  const addVariant = () => setVariants((prev) => [...prev, emptyVariant()]);
  const removeVariant = (index) => setVariants((prev) => prev.filter((_, i) => i !== index));

  const addColor = (vIndex) =>
    setVariants((prev) =>
      prev.map((v, i) => (i === vIndex ? { ...v, colors: [...v.colors, emptyColor()] } : v))
    );

  const updateColor = (vIndex, cIndex, field, value) =>
    setVariants((prev) =>
      prev.map((v, i) =>
        i === vIndex
          ? {
              ...v,
              colors: v.colors.map((c, ci) => (ci === cIndex ? { ...c, [field]: value } : c)),
            }
          : v
      )
    );

  const removeColor = (vIndex, cIndex) =>
    setVariants((prev) =>
      prev.map((v, i) =>
        i === vIndex ? { ...v, colors: v.colors.filter((_, ci) => ci !== cIndex) } : v
      )
    );

  const submit = async (event) => {
    event.preventDefault();
    setSaving(true);
    setErrors({});

    const payload = {
      name: form.name.trim(),
      category: form.category.trim(),
      description: form.description.trim(),
      price: parseFloat(form.price) || 0,
      costPrice: parseFloat(form.costPrice) || 0,
      compareAtPrice: form.compareAtPrice === '' ? 0 : parseFloat(form.compareAtPrice) || 0,
      stock: parseInt(form.stock, 10) || 0,
      status: form.status,
      images: form.images,
      variants: useVariants
        ? variants.map((v) => ({
            model: v.model.trim(),
            colors: v.colors.map((c) => ({
              name: c.name.trim(),
              hex: c.hex,
              stock: parseInt(c.stock, 10) || 0,
            })),
          }))
        : [],
    };

    try {
      if (isEdit) {
        await api.put(`/api/admin/products.php?id=${id}`, payload);
        toast(t('admin.productForm.updated'), 'success');
      } else {
        await api.post('/api/admin/products.php', payload);
        toast(t('admin.productForm.created'), 'success');
      }
      navigate('/admin/panel/products');
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
          <h1>{isEdit ? t('admin.productForm.editTitle') : t('admin.productForm.addTitle')}</h1>
          <p>{t('admin.productForm.subtitle')}</p>
        </div>
        <Link to="/admin/panel/products" className="btn btn-outline btn-sm">
          {t('admin.productForm.backToProducts')}
        </Link>
      </div>

      <form className="panel product-form" onSubmit={submit}>
        <div className="form-grid">
          <div className="form-group span-2">
            <label htmlFor="pf-name">{t('admin.productForm.name')}</label>
            <input id="pf-name" value={form.name} onChange={(e) => update('name', e.target.value)} required />
            {errors.name && <span className="field-error">{errors.name}</span>}
          </div>

          <div className="form-group">
            <label htmlFor="pf-category">{t('admin.productForm.category')}</label>
            <input
              id="pf-category"
              list="pf-categories"
              value={form.category}
              onChange={(e) => update('category', e.target.value)}
              placeholder={t('admin.productForm.categoryPlaceholder')}
              required
            />
            <datalist id="pf-categories">
              {categories.map((c) => (
                <option key={c} value={c} />
              ))}
            </datalist>
            {errors.category && <span className="field-error">{errors.category}</span>}
          </div>

          <div className="form-group">
            <label htmlFor="pf-status">{t('admin.productForm.status')}</label>
            <select id="pf-status" value={form.status} onChange={(e) => update('status', e.target.value)}>
              <option value="active">{t('admin.productForm.active')}</option>
              <option value="draft">{t('admin.productForm.draft')}</option>
            </select>
          </div>

          <div className="form-group span-2">
            <label htmlFor="pf-desc">{t('admin.productForm.description')}</label>
            <textarea
              id="pf-desc"
              rows={5}
              value={form.description}
              onChange={(e) => update('description', e.target.value)}
              required
              minLength={10}
            />
            {errors.description && <span className="field-error">{errors.description}</span>}
          </div>

          <div className="form-group">
            <label htmlFor="pf-price">{t('admin.productForm.sellingPrice')}</label>
            <input
              id="pf-price"
              type="number"
              min="0"
              step="0.01"
              value={form.price}
              onChange={(e) => update('price', e.target.value)}
              required
            />
            {errors.price && <span className="field-error">{errors.price}</span>}
          </div>

          <div className="form-group">
            <label htmlFor="pf-cost">{t('admin.productForm.costPrice')}</label>
            <input
              id="pf-cost"
              type="number"
              min="0"
              step="0.01"
              value={form.costPrice}
              onChange={(e) => update('costPrice', e.target.value)}
              required
            />
            {errors.costPrice && <span className="field-error">{errors.costPrice}</span>}
            <span className="muted small">{t('admin.productForm.costNote')}</span>
          </div>

          <div className="form-group">
            <label htmlFor="pf-compare">{t('admin.productForm.compareAt')}</label>
            <input
              id="pf-compare"
              type="number"
              min="0"
              step="0.01"
              value={form.compareAtPrice}
              onChange={(e) => update('compareAtPrice', e.target.value)}
              placeholder={t('admin.productForm.comparePlaceholder')}
            />
            {errors.compareAtPrice && <span className="field-error">{errors.compareAtPrice}</span>}
            <span className="muted small">{t('admin.productForm.compareNote')}</span>
          </div>

          <div className="form-group">
            <label>{t('admin.productForm.profitUnit')}</label>
            <div className={`profit-preview ${profit < 0 ? 'stock-out' : ''}`}>{money(profit)}</div>
          </div>
        </div>

        {/* Images */}
        <section className="form-section">
          <h3>
            <IconImage size={18} /> {t('admin.productForm.images')}
          </h3>
          <div className="image-grid">
            {form.images.map((url) => (
              <div key={url} className="image-tile">
                <img src={url} alt={t('admin.productForm.imageAlt')} />
                <button type="button" className="image-remove" onClick={() => removeImage(url)}>
                  <IconTrash size={14} />
                </button>
              </div>
            ))}
          </div>
          <div className="image-controls">
            <label className="btn btn-outline btn-sm upload-btn">
              {uploading ? t('admin.productForm.uploading') : t('admin.productForm.uploadImage')}
              <input type="file" accept="image/*" onChange={onUploadImage} hidden />
            </label>
            <div className="inline-input">
              <input
                placeholder={t('admin.productForm.pasteImageUrl')}
                value={imageUrl}
                onChange={(e) => setImageUrl(e.target.value)}
              />
              <button type="button" className="btn btn-dark btn-sm" onClick={addImageUrl}>
                {t('admin.productForm.add')}
              </button>
            </div>
          </div>
          {errors.images && <span className="field-error">{errors.images}</span>}
        </section>

        {/* Inventory / variants */}
        <section className="form-section">
          <div className="section-head">
            <h3>{t('admin.productForm.inventory')}</h3>
            <label className="switch">
              <input
                type="checkbox"
                checked={useVariants}
                onChange={(e) => {
                  setUseVariants(e.target.checked);
                  if (e.target.checked && variants.length === 0) setVariants([emptyVariant()]);
                }}
              />
              <span>{t('admin.productForm.hasVariants')}</span>
            </label>
          </div>

          {!useVariants ? (
            <div className="form-group narrow">
              <label htmlFor="pf-stock">{t('admin.productForm.stockQty')}</label>
              <input
                id="pf-stock"
                type="number"
                min="0"
                value={form.stock}
                onChange={(e) => update('stock', e.target.value)}
              />
            </div>
          ) : (
            <div className="variants-editor">
              {variants.map((variant, vIndex) => (
                <div key={vIndex} className="variant-editor">
                  <div className="variant-editor-head">
                    <input
                      placeholder={t('admin.productForm.modelPlaceholder')}
                      value={variant.model}
                      onChange={(e) => updateVariant(vIndex, 'model', e.target.value)}
                    />
                    <button
                      type="button"
                      className="icon-btn danger"
                      onClick={() => removeVariant(vIndex)}
                      title={t('admin.productForm.removeModel')}
                    >
                      <IconTrash size={16} />
                    </button>
                  </div>

                  <div className="color-rows">
                    {variant.colors.map((color, cIndex) => (
                      <div key={cIndex} className="color-row">
                        <input
                          type="color"
                          value={color.hex}
                          onChange={(e) => updateColor(vIndex, cIndex, 'hex', e.target.value)}
                          title={t('admin.productForm.colorTitle')}
                        />
                        <input
                          placeholder={t('admin.productForm.colorName')}
                          value={color.name}
                          onChange={(e) => updateColor(vIndex, cIndex, 'name', e.target.value)}
                        />
                        <input
                          type="number"
                          min="0"
                          placeholder={t('admin.productForm.stock')}
                          value={color.stock}
                          onChange={(e) => updateColor(vIndex, cIndex, 'stock', e.target.value)}
                        />
                        <button
                          type="button"
                          className="icon-btn danger"
                          onClick={() => removeColor(vIndex, cIndex)}
                          title={t('admin.productForm.removeColor')}
                        >
                          <IconTrash size={15} />
                        </button>
                      </div>
                    ))}
                    <button type="button" className="link-btn" onClick={() => addColor(vIndex)}>
                      <IconPlus size={15} /> {t('admin.productForm.addColor')}
                    </button>
                  </div>
                </div>
              ))}
              <button type="button" className="btn btn-outline btn-sm" onClick={addVariant}>
                <IconPlus size={16} /> {t('admin.productForm.addModel')}
              </button>
            </div>
          )}
        </section>

        <div className="form-actions">
          <Link to="/admin/panel/products" className="btn btn-outline">
            {t('admin.productForm.cancel')}
          </Link>
          <button className="btn btn-primary" type="submit" disabled={saving || uploading}>
            {saving
              ? t('admin.productForm.saving')
              : isEdit
                ? t('admin.productForm.saveChanges')
                : t('admin.productForm.createProduct')}
          </button>
        </div>
      </form>
    </div>
  );
}