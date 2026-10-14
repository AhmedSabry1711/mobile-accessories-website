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
  const [lang, setLang] = useState(() => localStorage.getItem('mb_lang') || 'ar');

  useEffect(() => {
    document.documentElement.dataset.theme = theme;
    document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
    document.documentElement.lang = lang === 'ar' ? 'ar-EG' : 'en-US';
    localStorage.setItem('mb_theme', theme);
    localStorage.setItem('mb_lang', lang);
  }, [theme, lang]);

  const toggleTheme = useCallback(() => setTheme((t) => (t === 'dark' ? 'light' : 'dark')), []);
  const toggleLang = useCallback(() => setLang((l) => (l === 'ar' ? 'en' : 'ar')), []);
  const t = useCallback((key, vars) => translate(key, vars, lang), [lang]);

  const value = useMemo(
    () => ({
      theme,
      toggleTheme,
      lang,
      toggleLang,
      t,
      locale: lang === 'ar' ? 'ar-EG' : 'en-US',
    }),
    [theme, toggleTheme, lang, toggleLang, t]
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