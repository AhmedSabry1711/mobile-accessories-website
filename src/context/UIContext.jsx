import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { translate } from '../i18n/translations.js';

const UIContext = createContext(null);

function systemTheme() {
  try {
    return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  } catch {
    return 'light';
  }
}

export function UIProvider({ children }) {
  const [theme, setTheme] = useState(() => localStorage.getItem('mb_theme') || systemTheme());

  useEffect(() => {
    document.documentElement.dataset.theme = theme;
    localStorage.setItem('mb_theme', theme);
  }, [theme]);

  const toggleTheme = useCallback(() => setTheme((t) => (t === 'dark' ? 'light' : 'dark')), []);
  const t = useCallback((key, vars) => translate(key, vars), []);

  const value = useMemo(
    () => ({
      theme,
      toggleTheme,
      t,
      locale: 'en-US',
    }),
    [theme, toggleTheme, t]
  );

  return <UIContext.Provider value={value}>{children}</UIContext.Provider>;
}

export function useUI() {
  const ctx = useContext(UIContext);
  if (!ctx) {
    throw new Error('useUI must be used inside <UIProvider>');
  }
  return ctx;
}