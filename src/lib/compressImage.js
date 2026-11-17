/**
 * Admin-side image preparation.
 *
 * Phone photos are often 4000–6000 px straight from the camera. Shrinking them
 * in the browser before upload saves bandwidth, server time and disk space.
 * The API still creates the small storefront derivatives — this just stops a
 * 10 MB original from ever being uploaded.
 *
 * Files that are already small are returned untouched (no quality loss), and
 * anything we cannot process (video, canvas failure, old browser) is returned
 * as-is so an upload never fails because of this helper.
 */
const MAX_EDGE = 2000;
const MAX_BYTES = 400_000;
const WEBP_QUALITY = 0.84;

function loadImage(url) {
  return new Promise((resolve, reject) => {
    const image = new Image();
    image.onload = () => resolve(image);
    image.onerror = reject;
    image.src = url;
  });
}

export async function compressImage(file) {
  if (!file || !/^image\/(jpe?g|png|webp)$/i.test(file.type)) return file;

  const objectUrl = URL.createObjectURL(file);
  try {
    const image = await loadImage(objectUrl);
    const width = image.naturalWidth || 0;
    const height = image.naturalHeight || 0;
    if (!width || !height) return file;

    const scale = Math.min(1, MAX_EDGE / Math.max(width, height));
    if (scale === 1 && file.size <= MAX_BYTES) return file;

    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(width * scale));
    canvas.height = Math.max(1, Math.round(height * scale));
    const ctx = canvas.getContext('2d');
    if (!ctx) return file;

    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(image, 0, 0, canvas.width, canvas.height);

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/webp', WEBP_QUALITY));
    if (!blob) return file;

    const name = file.name.replace(/\.[^.]+$/, '') + '.webp';
    const compressed = new File([blob], name, { type: 'image/webp' });
    return compressed.size < file.size ? compressed : file;
  } catch {
    return file;
  } finally {
    URL.revokeObjectURL(objectUrl);
  }
}
