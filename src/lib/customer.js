/**
 * Remember the shopper's delivery details on their own device.
 *
 * Repeat purchases are the common case in cash-on-delivery stores, and asking
 * for the same name / phone / address every time is where carts get abandoned.
 * Only these three fields are stored, only in localStorage, and they never
 * leave the device until the shopper actually places an order.
 */
const KEY = 'mb_customer';

const EMPTY = { name: '', phone: '', address: '' };

const clean = (value) => (typeof value === 'string' ? value : '');

export function loadCustomer() {
  try {
    const raw = localStorage.getItem(KEY);
    if (!raw) return { ...EMPTY };
    const saved = JSON.parse(raw);
    if (!saved || typeof saved !== 'object') return { ...EMPTY };
    return {
      name: clean(saved.name),
      phone: clean(saved.phone),
      address: clean(saved.address),
    };
  } catch {
    return { ...EMPTY };
  }
}

export function saveCustomer({ name, phone, address }) {
  try {
    localStorage.setItem(
      KEY,
      JSON.stringify({ name: clean(name), phone: clean(phone), address: clean(address) })
    );
  } catch {
    // storage unavailable (private mode) — autofill simply won't happen
  }
}

export function clearCustomer() {
  try {
    localStorage.removeItem(KEY);
  } catch {
    /* ignore */
  }
}
