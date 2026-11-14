/**
 * Phone-model helpers.
 *
 * Variant models are free text in the database ("iPhone 15/16",
 * "Samsung Galaxy S24/S25", …). This module keeps the display name in one place
 * so the home picker, the shop filter and the product page all render the same
 * model exactly the same way.
 *
 * The canonical English value is always sent to the API / kept in the URL; only
 * the label is localized.
 */

const isArabic = (lang) => String(lang || 'ar').toLowerCase().startsWith('ar');

/** Arabic labels for values that are not brand/model names. */
const MODEL_LABELS_AR = {
  'عام': 'جميع الموديلات',
  'TWS': 'سماعات TWS',
};

/** Localized model name, falling back to the canonical database value. */
export function localizeModel(model, lang) {
  const value = String(model || '');
  if (!isArabic(lang)) return value;
  return MODEL_LABELS_AR[value] || value;
}

/** True when at least one colour of the variant is in stock. */
export function variantInStock(variant) {
  return (variant?.colors || []).some((color) => Number(color.stock || 0) > 0);
}
