/**
 * The Mobi Boost lockup used in the header, the footer and the admin shell.
 *
 * The artwork is a dark-navy wordmark with an electric-blue accent, so it ships
 * in two files: `logo.png` for light surfaces and `logo-light.png` (white
 * wordmark, brighter blue accent) for dark ones. Which one is painted is decided
 * in styles.css per surface and per theme, so callers only drop this element in
 * and never think about it again.
 */
export default function BrandLogo() {
  return (
    <span className="brand-logo">
      <img className="logo-dark" src="/logo.png" alt="Mobi Boost" />
      <img className="logo-light" src="/logo-light.png" alt="" aria-hidden="true" />
    </span>
  );
}
