import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';

/**
 * Jump back to the top of the page on every navigation.
 *
 * Without this, following a footer link kept the viewport parked at the bottom
 * of the new page (the footer stayed under the cursor). The global
 * `scroll-behavior: smooth` is temporarily turned off so the jump is instant
 * rather than an animated scroll from the footer. A hash (#section) is left
 * alone so in-page anchors still work.
 */
export default function ScrollToTop() {
  const { pathname, search, hash } = useLocation();

  useEffect(() => {
    if (hash) return;
    const root = document.documentElement;
    const previous = root.style.scrollBehavior;
    root.style.scrollBehavior = 'auto';
    window.scrollTo(0, 0);
    root.style.scrollBehavior = previous;
  }, [pathname, search, hash]);

  return null;
}
