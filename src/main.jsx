import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from './App.jsx';
import { StoreProvider } from './context/StoreContext.jsx';
import { UIProvider } from './context/UIContext.jsx';
import './styles.css';

// Apply the theme attribute before first paint to avoid flashing.
// (Language switching was removed — the storefront is English, LTR.)
(function bootstrap() {
  try {
    const theme =
      localStorage.getItem('mb_theme') ||
      (window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.dataset.theme = theme;
    document.documentElement.lang = 'en';
    document.documentElement.dir = 'ltr';
    localStorage.removeItem('mb_lang');
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