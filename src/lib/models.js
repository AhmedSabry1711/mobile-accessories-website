/**
 * Phone-model helpers.
 *
 * Variant models are free text in the database ("iPhone 15/16",
 * "Samsung Galaxy S24/S25", …). This module keeps the display name and the
 * matching WebP thumbnail in one place so the home picker, the shop filter and
 * the product page all render the same model exactly the same way.
 *
 * The canonical English value is always sent to the API / kept in the URL; only
 * the label is localized.
 */

const isArabic = (lang) => String(lang || 'ar').toLowerCase().startsWith('ar');

/** Known model families → thumbnail file under /models/. */
const MODEL_IMAGES = [
  { test: /iphone|apple/i, file: '/models/iphone.webp' },
  { test: /samsung|galaxy/i, file: '/models/samsung.webp' },
  { test: /xiaomi|redmi|poco/i, file: '/models/xiaomi.webp' },
];

/** Arabic labels for values that are not brand/model names. */
const MODEL_LABELS_AR = {
  'عام': 'جميع الموديلات',
  'TWS': 'سماعات TWS',
};

/**
 * Return the WebP thumbnail for a phone model, or null when the store does not
 * have artwork for it (the UI then falls back to a text-only tab).
 */
export function modelImage(model) {
  const value = String(model || '').trim();
  if (!value) return null;
  const match = MODEL_IMAGES.find((entry) => entry.test.test(value));
  return match ? match.file : null;
}

/** Localized model name, falling back to the canonical database value. */
export function localizeModel(model, lang) {
  const value = String(model || '');
  if (!isArabic(lang)) return value;
  return MODEL_LABELS_AR[value] || value;
}

/** Total stock across every colour of a variant. */
export function variantStock(variant) {
  return (variant?.colors || []).reduce((sum, color) => sum + Number(color.stock || 0), 0);
}

/** True when at least one colour of the variant is in stock. */
export function variantInStock(variant) {
  return (variant?.colors || []).some((color) => Number(color.stock || 0) > 0);
}
