import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from './App.jsx';
import { StoreProvider } from './context/StoreContext.jsx';
import { UIProvider } from './context/UIContext.jsx';
import './styles.css';

// Apply the theme + language attributes before first paint to avoid flashing.
(function bootstrap() {
  try {
    const theme =
      localStorage.getItem('mb_theme') ||
      (window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    const lang = localStorage.getItem('mb_lang') || 'ar';
    document.documentElement.dataset.theme = theme;
    document.documentElement.lang = lang === 'ar' ? 'ar-EG' : 'en-US';
    document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
  } catch {
    // storage unavailable — keep defaults
  }
})();

createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <UIProvider>
      <StoreProvider>
        <BrowserRouter>
          <App />
        </BrowserRouter>
      </StoreProvider>
    </UIProvider>
  </React.StrictMode>
);