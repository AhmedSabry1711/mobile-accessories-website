import { useState } from 'react';
import { localImageVariants } from '../lib/media.js';

/**
 * Responsive <picture> for uploaded product/ad photos.
 *
 * Modern browsers get the small WebP candidate from `/uploads/_opt`; browsers
 * without WebP support (older Safari/IE) get the JPEG candidate. If the
 * derivatives are missing — e.g. an upload made before the optimiser was
 * installed — the error handler swaps in the untouched original URL.
 *
 * Passing any non-upload URL (remote, SVG, GIF, video) simply renders the
 * original <img>, so existing data keeps working unchanged.
 */
export default function ResponsiveImage({
  src,
  alt = '',
  widths,
  sizes = '100vw',
  className,
  loading = 'lazy',
  decoding = 'async',
  onError,
  ...rest
}) {
  const [failedSrc, setFailedSrc] = useState(null);
  const variants = localImageVariants(src, widths);
  const useOriginal = !variants || failedSrc === src;

  const handleError = (event) => {
    setFailedSrc(src);
    onError?.(event);
  };

  if (useOriginal) {
    return (
      <img
        src={src}
        alt={alt}
        className={className}
        loading={loading}
        decoding={decoding}
        onError={onError}
        {...rest}
      />
    );
  }

  return (
    <picture className="responsive-image">
      <source type="image/webp" srcSet={variants.webp} sizes={sizes} />
      <img
        src={variants.fallback}
        srcSet={variants.jpg}
        sizes={sizes}
        alt={alt}
        className={className}
        loading={loading}
        decoding={decoding}
        onError={handleError}
        {...rest}
      />
    </picture>
  );
}
