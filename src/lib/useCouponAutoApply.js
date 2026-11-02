import { useCallback, useEffect, useRef, useState } from 'react';
import { api } from '../api/client.js';

/**
 * Coupon entry with auto-apply while typing.
 *
 * Shoppers should not have to hunt for an "apply" button: the code is checked
 * once the shopper stops typing (short debounce) and, if it is valid, the
 * discount appears in the summary immediately. Manual apply is still supported
 * (Enter / the button) as an accessible fallback.
 *
 * The hook also guards against out-of-order responses: typing invalidates the
 * request in flight so a slow answer for an old code can never win.
 */
const MIN_LENGTH = 4;
const DEBOUNCE_MS = 650;

export function useCouponAutoApply({ subtotal, applied, applyCoupon, onApplied }) {
  const [input, setInput] = useState('');
  const [error, setError] = useState('');
  const [checking, setChecking] = useState(false);

  const seq = useRef(0);
  const latest = useRef('');
  // Code whose check is currently on the wire — stops Enter + the debounce
  // timer (or a double Enter) from firing the same request twice.
  const inFlight = useRef(null);
  // Latest values read from inside the async call, so `run` stays stable and
  // the debounce timer is not restarted by unrelated re-renders.
  const live = useRef({ subtotal, appliedCode: applied?.code, onApplied });
  live.current = { subtotal, appliedCode: applied?.code, onApplied };

  const run = useCallback(
    async (raw, { silent = false } = {}) => {
      const code = String(raw ?? latest.current).trim().toUpperCase();
      if (!code || live.current.appliedCode === code || inFlight.current === code) return;
      const ticket = ++seq.current;
      inFlight.current = code;
      setError('');
      setChecking(true);
      try {
        const result = await api.post('/api/coupons.php', {
          code,
          subtotal: Number(live.current.subtotal) || 0,
        });
        // Ignore an answer for a code the shopper has already edited.
        if (seq.current !== ticket || latest.current.trim().toUpperCase() !== code) return;
        applyCoupon(result);
        latest.current = '';
        setInput('');
        live.current.onApplied?.(result, { silent });
      } catch (err) {
        if (seq.current !== ticket) return;
        setError(err.message);
      } finally {
        if (inFlight.current === code) inFlight.current = null;
        if (seq.current === ticket) setChecking(false);
      }
    },
    [applyCoupon]
  );

  const change = useCallback(
    (value) => {
      const next = String(value).toUpperCase();
      latest.current = next;
      setInput(next);
      setError('');
      // Editing means any pending/queued check is about a code we no longer
      // have, so drop it instead of letting it land later.
      seq.current++;
      setChecking(false);
    },
    []
  );

  useEffect(() => {
    const code = input.trim();
    if (code.length < MIN_LENGTH) return;
    if (live.current.appliedCode === code) return;
    // Wait for a real subtotal: coupons are validated against the minimum
    // order, so checking against an empty cart would report a fake failure.
    if (!(Number(live.current.subtotal) > 0)) return;
    const timer = setTimeout(() => run(code), DEBOUNCE_MS);
    return () => clearTimeout(timer);
  }, [input, subtotal, run]);

  /** Apply right now — Enter key or the (optional) button. */
  const applyNow = useCallback(() => {
    run(latest.current);
  }, [run]);

  /** Forget the typed code (used after removing an applied coupon). */
  const reset = useCallback(() => {
    latest.current = '';
    seq.current++;
    setInput('');
    setError('');
    setChecking(false);
  }, []);

  return { input, change, error, setError, checking, applyNow, reset };
}
