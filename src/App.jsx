import { lazy, Suspense, useEffect } from 'react';
import { Routes, Route, Outlet, useLocation } from 'react-router-dom';
import Header from './components/Header.jsx';
import Footer from './components/Footer.jsx';
import WhatsAppWidget from './components/WhatsAppWidget.jsx';
import ScrollToTop from './components/ScrollToTop.jsx';
import { closeGate } from './components/AdminGate.jsx';
import RequireOwnerGate from './components/RequireOwnerGate.jsx';
import { useStore } from './context/StoreContext.jsx';
import Home from './pages/Home.jsx';
import Shop from './pages/Shop.jsx';
import ProductDetails from './pages/ProductDetails.jsx';
import Cart from './pages/Cart.jsx';
import Checkout from './pages/Checkout.jsx';
import OrderSuccess from './pages/OrderSuccess.jsx';
import OrderConfirm from './pages/OrderConfirm.jsx';
import InfoPage from './pages/InfoPage.jsx';
import NotFound from './pages/NotFound.jsx';

// The admin panel is a separate chunk: shoppers should not download it (or the
// QR-code library used by the shipping label) just to browse the store.
const AdminLogin = lazy(() => import('./pages/admin/AdminLogin.jsx'));
const AdminLayout = lazy(() => import('./pages/admin/AdminLayout.jsx'));
const Dashboard = lazy(() => import('./pages/admin/Dashboard.jsx'));
const Products = lazy(() => import('./pages/admin/Products.jsx'));
const ProductForm = lazy(() => import('./pages/admin/ProductForm.jsx'));
const Coupons = lazy(() => import('./pages/admin/Coupons.jsx'));
const Ads = lazy(() => import('./pages/admin/Ads.jsx'));
const Orders = lazy(() => import('./pages/admin/Orders.jsx'));
const Settings = lazy(() => import('./pages/admin/Settings.jsx'));
const Reviews = lazy(() => import('./pages/admin/Reviews.jsx'));

export default function App() {
  const location = useLocation();

  // Leaving the admin area re-locks the owner gate, so the next visit to the
  // admin panel asks for the owner password again (see components/AdminGate).
  useEffect(() => {
    if (!location.pathname.startsWith('/admin')) closeGate();
  }, [location.pathname]);

  return (
    <div className="app">
      <ScrollToTop />
      <Routes>
        {/* Public order confirmation — opened by scanning the shipping-label
            QR. Kept outside the storefront shell so it stays focused and fast
            on a phone. Opening it is read-only; see pages/OrderConfirm.jsx. */}
        <Route path="/confirm/:code" element={<OrderConfirm />} />

        {/* Admin: its own chrome — no storefront header, footer or WhatsApp button.
            Every entry point (footer lock, bookmark, shared link) passes through
            the owner-password gate before the login form is even rendered. */}
        <Route path="/admin" element={<AdminArea><AdminLogin /></AdminArea>} />
        <Route path="/admin/panel" element={<AdminArea><AdminLayout /></AdminArea>}>
          <Route index element={<Dashboard />} />
          <Route path="products" element={<Products />} />
          <Route path="products/new" element={<ProductForm />} />
          <Route path="products/:id/edit" element={<ProductForm />} />
          <Route path="orders" element={<Orders />} />
          <Route path="coupons" element={<Coupons />} />
          <Route path="ads" element={<Ads />} />
          <Route path="reviews" element={<Reviews />} />
          <Route path="settings" element={<Settings />} />
        </Route>

        {/* Storefront: everything else, wrapped in the public header/footer. */}
        <Route element={<StorefrontLayout />}>
          <Route path="/" element={<Home />} />
          <Route path="/shop" element={<Shop />} />
          <Route path="/products/:id" element={<ProductDetails />} />
          <Route path="/cart" element={<Cart />} />
          <Route path="/checkout" element={<Checkout />} />
          <Route path="/order-success/:orderNumber" element={<OrderSuccess />} />
          <Route path="/help" element={<InfoPage kind="help" />} />
          <Route path="/shipping" element={<InfoPage kind="shipping" />} />
          <Route path="/returns" element={<InfoPage kind="returns" />} />
          <Route path="/contact" element={<InfoPage kind="contact" />} />
          <Route path="*" element={<NotFound />} />
        </Route>
      </Routes>
      <ToastStack />
    </div>
  );
}

/** Owner gate + lazy-loading fallback for the whole admin area. */
function AdminArea({ children }) {
  return (
    <RequireOwnerGate>
      <Suspense
        fallback={
          <div className="container loading-page">
            <div className="spinner" />
          </div>
        }
      >
        {children}
      </Suspense>
    </RequireOwnerGate>
  );
}

/** Public storefront shell: header, page body, footer and the WhatsApp button. */
function StorefrontLayout() {
  return (
    <>
      <Header />
      <main className="main">
        <Outlet />
      </main>
      <Footer />
      <WhatsAppWidget />
    </>
  );
}

function ToastStack() {
  const { toasts } = useStore();
  if (!toasts.length) return null;
  return (
    <div className="toast-stack">
      {toasts.map((toast) => (
        <div key={toast.id} className={`toast toast-${toast.type}`}>
          {toast.message}
        </div>
      ))}
    </div>
  );
}
