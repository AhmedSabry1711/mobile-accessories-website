/**
 * Mobi Boost — catalog localization.
 *
 * Product names/descriptions, category labels, color names and ad titles live in
 * the database as free text (authored in English). The UI chrome is translated by
 * `i18n/translations.js`, but this content needs its own English → Arabic map.
 *
 * Anything not listed here simply falls back to the original database value, so
 * adding new products/categories never breaks — you just add a translation entry.
 * Only *display* strings are localized; URLs, cart lines and API filters keep the
 * canonical English values.
 */

const isArabic = (lang) => String(lang || 'ar').toLowerCase().startsWith('ar');

const categoryPairs = [
  ['Phone Cases', 'جرابات'],
  ['Chargers', 'شواحن'],
  ['Screen Protectors', 'واقيات شاشة'],
  ['Earbuds', 'سماعات'],
  ['Holders', 'حوامل'],
  ['Cables', 'كابلات'],
  ['Accessories', 'إكسسوارات'],
];

const categoryToAr = {};
const categoryToEn = {};
for (const [en, ar] of categoryPairs) {
  categoryToAr[en] = ar;
  categoryToAr[ar] = ar;
  categoryToEn[ar] = en;
  categoryToEn[en] = en;
}

const colorAr = {
  Black: 'أسود',
  'Midnight Blue': 'أزرق منتصف الليل',
  Forest: 'أخضر غامق',
  Crimson: 'قرمزي',
  Clear: 'شفاف',
  Standard: 'عادي',
};

const productAr = {
  'AirSound Pro Wireless Earbuds': {
    name: 'سماعات AirSound Pro اللاسلكية',
    description:
      'خاصية إلغاء الضوضاء النشطة، وعمر بطارية إجمالي 36 ساعة مع جرابة الشحن، ومقاومة للعرق IPX5، وميكروفونات نقية للمكالمات.',
  },
  'Magnetic Car Phone Mount': {
    name: 'حامل هاتف مغناطيسي للسيارة',
    description:
      'مغناطيس قوي N52 يثبّت أي هاتف على فتحة التكييف حتى مع وجود الكفر. تركيب بيد واحدة، ودوران 360 درجة، وبدون لاصقات.',
  },
  'Armor Shockproof Bumper Case': {
    name: 'كفر أرمور مقاوم للصدمات',
    description:
      'حماية عسكرية ضد السقوط بحواف مقوّاة، وحافة مرتفعة لحماية الشاشة والكاميرا، وملمس مانع للانزلاق. فتحات دقيقة تُبقي كل المنافذ والأزرار متاحة بالكامل.',
  },
  '65W GaN Fast Charger': {
    name: 'شاحن سريع 65 واط بتقنية GaN',
    description:
      'تقنية GaN II المدمجة تشحن اللابتوب والهاتف في الوقت نفسه. منافذ USB-C و USB-A، وأطراف قابلة للطي، وحماية من التيار الزائد والحرارة.',
  },
  'Clear MagSafe Compatible Case': {
    name: 'كفر شفاف متوافق مع MagSafe',
    description:
      'ظهر شفاف من مادة PC يحافظ على شكل هاتفك كأنه جديد، مع حلقة مغناطيسية متوافقة مع شواحن ومحافظ MagSafe. طبقة مقاومة للاصفرار.',
  },
  'Tempered Glass Screen Protector (2-Pack)': {
    name: 'واقي شاشة زجاجي مقوّى (قطعتين)',
    description:
      'صلابة 9H، ووضوح 99.9%، وطبقة مقاومة لبصمات الأصابع. يشمل إطار تركيب سهل، ومتوافق مع أغلب الكفرات.',
  },
  'Braided USB-C to Lightning Cable (2m)': {
    name: 'كابل USB-C إلى Lightning مجدول (٢ متر)',
    description:
      'جديلة نايلون مزدوجة تتحمّل أكثر من 30,000 انثناء. نقل بيانات سريع وشحن حتى 100 واط. ضمان مدى الحياة.',
  },
  'Clip-On Selfie Ring Light': {
    name: 'إضاءة سيلفي دائرية بمشبك',
    description:
      'إضاءة دائرية قابلة للتعديل 360 درجة بثلاث درجات حرارة للون وستة مستويات سطوع. قابلة للشحن عبر USB وتُثبَّت على أي هاتف.',
  },
  '10000mAh Slim Power Bank': {
    name: 'باور بانك رفيع 10000 مللي أمبير',
    description:
      'بطارية بحجم الجيب بسعة 10000 مللي أمبير وشحن سريع بقدرة 22.5 واط. مؤشر LED لمستوى الشحن، ومنفذان للخروج، وخلايا آمنة على الطائرات.',
  },
  'Adjustable Desk Phone Stand': {
    name: 'حامل هاتف مكتبي قابل للتعديل',
    description:
      'حامل من الألومنيوم قابل للطي للهواتف والتابلت. شاهد الفيديوهات أو اتصل بالفيديو أو اقرأ بيدين فارغتين. يناسب الأجهزة من 4.7 إلى 12.9 بوصة.',
  },
};

const adAr = {
  'Summer Sale — Up to 40% Off': 'تخفيضات الصيف — خصم يصل إلى 40%',
  'Free Shipping Over 50': 'شحن مجاني للطلبات فوق 50',
  'New iPhone 17 Collection': 'تشكيلة iPhone 17 الجديدة',
};

/** Localize a category label (e.g. "Phone Cases" ⇄ "جرابات"). */
export function localizeCategory(name, lang) {
  if (!name) return name;
  return isArabic(lang) ? categoryToAr[name] || name : categoryToEn[name] || name;
}

/**
 * Map a category label typed in the admin to its canonical stored value.
 * Known Arabic labels (e.g. "جرابات") become their English key ("Phone Cases") so
 * existing products keep matching; anything else is stored exactly as typed.
 */
export function canonicalCategory(label) {
  const value = String(label ?? '').trim();
  for (const [en, ar] of categoryPairs) {
    if (value === ar || value === en) return en;
  }
  return value;
}

/** Localize a color name (e.g. "Black" → "أسود"). */
export function localizeColor(name, lang) {
  if (!name || !isArabic(lang)) return name;
  return colorAr[name] || name;
}

/** Localize a catalog product name (e.g. cart / order lines). */
export function localizeName(name, lang) {
  if (!name || !isArabic(lang)) return name;
  return productAr[name]?.name || name;
}

/** Localize a product description (falls back to the stored value). */
export function localizeDescription(name, description, lang) {
  if (!description || !isArabic(lang)) return description;
  return productAr[name]?.description || description;
}

/** Localize an ad title (falls back to the stored title). */
export function localizeAdTitle(title, lang) {
  if (!title || !isArabic(lang)) return title;
  return adAr[title] || title;
}

/** Localize a catalog product's display fields (name / description / category). */
export function localizeProduct(product, lang) {
  if (!product || !isArabic(lang)) return product;
  const entry = productAr[product.name];
  return {
    ...product,
    name: entry?.name || product.name,
    description: entry?.description || product.description,
    category: localizeCategory(product.category, lang),
  };
}

/** Localize a modal / color label. Model names are brand names and stay as-is. */
export function localizeVariant(model, color, lang) {
  return [model, localizeColor(color, lang)].filter(Boolean).join(' · ');
}
