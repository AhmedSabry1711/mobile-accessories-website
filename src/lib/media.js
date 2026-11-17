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

/* ------------------------------------------------------------------ uploads */
// Widths written by api/lib/images.php at upload time.
export const CARD_IMAGE_WIDTHS = [400, 800];
export const GALLERY_IMAGE_WIDTHS = [400, 800, 1600];
export const THUMB_IMAGE_WIDTHS = [400];
export const AD_IMAGE_WIDTHS = [800, 1600];

const LOCAL_UPLOAD = /^\/uploads\/([A-Za-z0-9_-]+)\.(jpe?g|png|webp)$/i;

/**
 * Map a locally uploaded photo to the derivatives created by the API.
 * Remote URLs, SVGs, GIFs and videos return null so the caller renders the
 * original <img> instead.
 */
export function localImageVariants(url, widths = GALLERY_IMAGE_WIDTHS) {
  if (!url || typeof url !== 'string') return null;

  let pathname = url;
  try {
    const parsed = new URL(url, window.location.origin);
    if (parsed.origin !== window.location.origin) return null;
    pathname = parsed.pathname;
  } catch {
    return null;
  }

  const match = LOCAL_UPLOAD.exec(pathname);
  if (!match) return null;

  const stem = match[1];
  const list = widths.length ? widths : GALLERY_IMAGE_WIDTHS;
  const largest = list[list.length - 1];
  return {
    webp: list.map((w) => `/uploads/_opt/${stem}-${w}.webp ${w}w`).join(', '),
    jpg: list.map((w) => `/uploads/_opt/${stem}-${w}.jpg ${w}w`).join(', '),
    fallback: `/uploads/_opt/${stem}-${largest}.jpg`,
  };
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
