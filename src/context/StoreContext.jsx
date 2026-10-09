import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { useUI } from './UIContext.jsx';

const StoreContext = createContext(null);

function load(key, fallback) {
  try {
    const raw = localStorage.getItem(key);
    return raw ? JSON.parse(raw) : fallback;
  } catch {
    return fallback;
  }
}

function save(key, value) {
  try {
    localStorage.setItem(key, JSON.stringify(value));
  } catch {
    // storage unavailable — ignore
  }
}

const cartKey = (item) => `${item.productId}|${item.model || ''}|${item.color || ''}`;

export function StoreProvider({ children }) {
  const { t } = useUI();
  const [cart, setCart] = useState(() => load('mb_cart', []));
  const [wishlist, setWishlist] = useState(() => load('mb_wishlist', []));
  const [admin, setAdmin] = useState(() => {
    const token = localStorage.getItem('mb_admin_token');
    return token ? { token, name: localStorage.getItem('mb_admin_name') || 'admin' } : null;
  });
  const [toasts, setToasts] = useState([]);

  useEffect(() => save('mb_cart', cart), [cart]);
  useEffect(() => save('mb_wishlist', wishlist), [wishlist]);

  const toast = useCallback((message, type = 'info') => {
    const id = Date.now() + Math.random();
    setToasts((prev) => [...prev.slice(-3), { id, message, type }]);
    window.setTimeout(() => {
      setToasts((prev) => prev.filter((t) => t.id !== id));
    }, 3600);
  }, []);

  // ---- Cart ------------------------------------------------------------
  const addToCart = useCallback((item) => {
    setCart((prev) => {
      const key = cartKey(item);
      const existing = prev.find((c) => cartKey(c) === key);
      if (existing) {
        return prev.map((c) =>
          cartKey(c) === key ? { ...c, qty: Math.min(99, c.qty + (item.qty || 1)) } : c
        );
      }
      return [...prev, { ...item, qty: item.qty || 1 }];
    });
  }, []);

  const updateCartQty = useCallback((item, qty) => {
    const key = cartKey(item);
    setCart((prev) =>
      qty <= 0
        ? prev.filter((c) => cartKey(c) !== key)
        : prev.map((c) => (cartKey(c) === key ? { ...c, qty: Math.min(99, qty) } : c))
    );
  }, []);

  const removeFromCart = useCallback((item) => {
    const key = cartKey(item);
    setCart((prev) => prev.filter((c) => cartKey(c) !== key));
  }, []);

  const clearCart = useCallback(() => setCart([]), []);

  const cartCount = useMemo(() => cart.reduce((sum, c) => sum + c.qty, 0), [cart]);

  // ---- Wishlist ----------------------------------------------------------
  const toggleWishlist = useCallback(
    (productId) => {
      setWishlist((prev) => {
        if (prev.includes(productId)) {
          toast(t('wishlist.removed'), 'info');
          return prev.filter((id) => id !== productId);
        }
        toast(t('wishlist.added'), 'success');
        return [...prev, productId];
      });
    },
    [toast, t]
  );

  // ---- Admin session -----------------------------------------------------
  const login = useCallback((token, name) => {
    localStorage.setItem('mb_admin_token', token);
    localStorage.setItem('mb_admin_name', name);
    setAdmin({ token, name });
  }, []);

  const logout = useCallback(() => {
    localStorage.removeItem('mb_admin_token');
    localStorage.removeItem('mb_admin_name');
    setAdmin(null);
  }, []);

  const value = {
    cart,
    addToCart,
    updateCartQty,
    removeFromCart,
    clearCart,
    cartCount,
    wishlist,
    toggleWishlist,
    admin,
    login,
    logout,
    toast,
    toasts,
  };

  return <StoreContext.Provider value={value}>{children}</StoreContext.Provider>;
}

export function useStore() {
  const ctx = useContext(StoreContext);
  if (!ctx) {
    throw new Error('useStore must be used inside <StoreProvider>');
  }
  return ctx;
}
