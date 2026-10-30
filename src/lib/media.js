/**
 * Media helpers — keep remote artwork cheap on mobile.
 *
 * Catalogue photos and ads can be stored as full-size remote URLs (Unsplash,
 * Giphy). The cards that render them are only a few hundred pixels wide, so
 * asking the CDN for a 1200px file wastes bandwidth on every visit.
 *
 * These helpers rewrite a URL to a smaller variant *without touching the
 * stored value*, so the admin keeps full-size media while the storefront
 * downloads what it actually shows.
 */

const GIPHY_GIF = /^(https?:\/\/media\d*\.giphy\.com\/media\/.+\/)giphy\.gif(\?.*)?$/i;
const UNSPLASH = /^https?:\/\/images\.unsplash\.com\//i;

/**
 * Giphy ships an optimised animated WebP next to every GIF (same path, the
 * `200w.webp` file name). It is typically 4–8× smaller than the GIF, which
 * matters a lot for the homepage ad strip.
 */
export function adMediaUrl(url) {
  if (!url) return url;
  const match = url.match(GIPHY_GIF);
  return match ? `${match[1]}200w.webp` : url;
}

/** The original GIF, used as an onError fallback for adMediaUrl(). */
export function adMediaFallback(url) {
  return url || '';
}

/**
 * Build a resized Unsplash URL. Non-Unsplash URLs (local uploads, SVGs)
 * are returned untouched.
 */
export function imageUrl(url, width = 640, quality = 72) {
  if (!url || !UNSPLASH.test(url) || typeof url !== 'string') return url;
  try {
    const parsed = new URL(url);
    parsed.searchParams.set('w', String(width));
    parsed.searchParams.set('h', String(width));
    parsed.searchParams.set('fit', 'crop');
    parsed.searchParams.set('q', String(quality));
    return parsed.toString();
  } catch {
    return url;
  }
}

/** Responsive srcset for Unsplash artwork; null for anything else. */
export function imageSrcSet(url, widths = [320, 480, 640], quality = 72) {
  if (!url || !UNSPLASH.test(url) || typeof url !== 'string') return undefined;
  return widths.map((width) => `${imageUrl(url, width, quality)} ${width}w`).join(', ');
}

/**
 * Legacy static links in seeded ads (`/products.html`) point at the old
 * PHP pages and land on the React 404 page. Send those to the catalogue.
 */
export function adLinkUrl(url) {
  if (!url) return '';
  if (/\.html?($|\?)/i.test(url)) return '/shop';
  return url;
}
