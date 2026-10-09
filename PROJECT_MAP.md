# Mobi Boost — PROJECT MAP

> Full-stack e-commerce app for mobile accessories.
> React (Vite) frontend · PHP REST API · **MongoDB Atlas or MySQL/MariaDB (choose one)**.
> Deploy target: Hostinger Unlimited Web Hosting (static React files + PHP API in `public_html`).

> **Database editions.** The API ships with two interchangeable storage backends that expose the
> same helpers (`mongo_find`, `mongo_insert`, …) to the endpoints:
> - **MongoDB** — `api/_bootstrap.php` + `api/lib/store.php` (needs `ext-mongodb`).
> - **MySQL/PDO** — the drop-in files in `api-mysql/` (no extension required; works on standard
>   shared hosting). See [`api-mysql/README.md`](./api-mysql/README.md) and the Deployment section.

---

## Step Status

| Step | Goal | Status |
|------|------|--------|
| 1 | Project structure + database config | ✅ Done |
| 2 | PHP backend API (Products, Profit, Cart/Checkout, Auth, Reviews, Admin) | ✅ Done |
| 3 | React frontend (Home, Product Details, Cart, Checkout, WhatsApp widget, Admin Panel) | ✅ Done |
| 4 | This PROJECT_MAP.md | ✅ Done |

---

## Architecture

```
┌─────────────────────────────┐        ┌──────────────────────────────┐
│  React SPA (static files)   │  /api  │  PHP REST API (no Node srv)  │
│  built by Vite -> dist/     │───────>│  plain .php scripts, JSON    │
│  served from public_html/   │        │  MongoDB Atlas driver        │
└─────────────────────────────┘        └──────────────┬───────────────┘
                                                      │ TLS / SRV
                                               ┌──────▼───────┐
                                               │ MongoDB Atlas│
                                               └──────────────┘
```

- **Frontend:** React 18 + Vite + React Router. All pages are static after `npm run build`.
- **Backend:** Stateless PHP scripts (`api/*.php`). Each file requires `api/_bootstrap.php`
  (CORS, JSON headers, DB connection, helpers, admin auth). No Composer needed.
  Two interchangeable editions:
  - **MongoDB** (`api/_bootstrap.php`, `api/lib/store.php`) using the `ext-mongodb` driver directly.
  - **MySQL/PDO** (`api-mysql/`) storing one JSON document per row — see its `README.md`.
- **Cart/Wishlist:** stored in the browser (localStorage). Prices, coupons, shipping and
  stock are **always re-validated server-side** before an order is created.
- **Auth:** admin login returns an HMAC-signed token (`Bearer` header). Public storefront
  needs no accounts; checkout is Cash on Delivery (name / address / phone only).

## Directory Structure

```
/                              (repository root)
├── api/                       PHP REST API  ->  upload to public_html/api/
│   ├── config.php             DB settings (Mongo URI + MySQL creds), JWT secret, currency
│   ├── _bootstrap.php         CORS, JSON helpers, DB helpers, token auth (MongoDB edition)
│   ├── lib/store.php          Cart/coupon/stock logic (MongoDB edition)
│   ├── products.php           GET list/search/sale/new/related + GET single
│   ├── reviews.php            GET reviews + average, POST new review
│   ├── coupons.php            POST validate coupon against subtotal
│   ├── cart.php               POST calculate cart totals (prices, coupon, shipping)
│   ├── orders.php             POST create COD order (stock-checked), GET track
│   ├── settings.php           GET public settings (shipping, WhatsApp, categories, ads)
│   ├── auth.php               POST admin login, GET current admin
│   ├── upload.php             POST image/video upload (admin)
│   ├── seed.php               CLI demo-data seeder (admin, products, coupons, ads)
│   └── admin/
│       ├── dashboard.php      Revenue / profit / orders analytics
│       ├── products.php       Product CRUD (cost price + selling price + variants)
│       ├── coupons.php        Coupon CRUD
│       ├── ads.php            Hero banner (video/GIF) CRUD
│       ├── orders.php         Order list, detail, status update (restocks on cancel)
│       ├── reviews.php        Review moderation (list / delete)
│       └── settings.php       Shipping rules, WhatsApp number, categories
├── api-mysql/                 MySQL/PDO drop-in edition (no ext-mongodb needed)
│   ├── _bootstrap.php         PDO connection + storage helpers (same mongo_* API)
│   ├── lib/store.php          Cart/coupon/stock logic (MySQL edition)
│   ├── lib/mongo_compat.php   BSON value-type polyfills (ObjectId/UTCDateTime/Regex)
│   ├── schema.sql             Creates the 7 JSON-document tables
│   └── README.md              Activation steps + trade-offs
├── src/                       React source
│   ├── main.jsx, App.jsx, styles.css
│   ├── api/client.js          fetch wrapper (auth header + error handling)
│   ├── context/StoreContext.jsx   cart, wishlist, admin session, toasts
│   ├── components/            Header, Footer, WhatsAppWidget, HeroTicker,
│   │                          ProductCard, ProductShelf, RatingStars, icons
│   └── pages/                 Home, Shop, ProductDetails, Cart, Checkout,
│       └── admin/             OrderSuccess + AdminLogin/Layout/Dashboard/Products/
│                              ProductForm/Coupons/Ads/Orders/Settings/Reviews
├── public/                    static assets copied to dist/
├── dist/                      npm run build output -> upload contents to public_html/
├── .htaccess                  SPA fallback for public_html/
├── package.json, vite.config.js, index.html
└── README.md, PROJECT_MAP.md
```

## Data Model (database `mobi_boost`)

> MongoDB edition stores these as collections. The MySQL edition stores each collection as one
> table (`mb_products`, `mb_reviews`, `mb_orders`, `mb_coupons`, `mb_ads`, `mb_settings`,
> `mb_users`) with `id CHAR(24) PK · doc LONGTEXT (JSON) · created_at DATETIME`; the document
> shape below is identical in both editions.

- **products**: `name, slug?, category, description, price (selling), compareAtPrice, costPrice,
  images[], variants[{ model, colors[{ name, hex, stock }] }], stock (simple), status, createdAt`
- **reviews**: `productId, userName, rating (1-5), comment, createdAt`
- **orders**: `orderNumber, items[{ productId, name, image, price, costPrice, qty, model, color }],
  subtotal, discount, couponCode, shippingFee, total, customer{name,address,phone}, status, createdAt`
- **coupons**: `code, type (percent|fixed), value, minOrder, expiresAt?, active, createdAt`
- **ads**: `title, mediaUrl, type (image|video), linkUrl, order, active`
- **settings**: single doc `store { whatsapp, shipping{enabled,flatRate,freeOver}, currency, categories[] }`
- **users**: admin accounts `username, passwordHash`

### Profit calculation
`unitProfit = price − costPrice` (computed per order line). Dashboard aggregates:
`netProfit = Σ(total) − Σ(costPrice × qty)` over non-cancelled orders.

### Shipping rules (configurable in Admin → Settings)
`shippingFee = (shipping.enabled && discountedSubtotal < freeOver) ? flatRate : 0`

## API Endpoints

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/settings.php` | Public settings + active ads |
| GET | `/api/products.php?filter=sale\|new\|all&category=&q=&limit=&page=` | Catalog |
| GET | `/api/products.php?id=` | Product detail |
| GET | `/api/products.php?related_to=` | Same-category products |
| GET | `/api/reviews.php?product_id=` | Reviews + average |
| POST | `/api/reviews.php` | Add review |
| POST | `/api/coupons.php` | Validate coupon |
| POST | `/api/cart.php` | Calculate totals (server-side prices/shipping) |
| POST | `/api/orders.php` | Place COD order |
| GET | `/api/orders.php?number=&phone=` | Track order |
| POST | `/api/auth.php` | Admin login → token |
| GET | `/api/auth.php` | Current admin (Bearer) |
| POST | `/api/upload.php` | Upload image/GIF/video (multipart, admin) |
| * | `/api/admin/{dashboard,products,coupons,ads,orders,reviews,settings}.php` | Admin panel (Bearer required) |

## Deployment to Hostinger (Unlimited Plan)

1. `npm install` → `npm run build` (produces `dist/`).
2. **Pick a database edition:**
   - **MongoDB** — in hPanel **MultiPHP INI Editor**, enable `ext-mongodb`.
   - **MySQL/MariaDB** — no extension needed. Copy the drop-in files (step 4b) and import
     `api-mysql/schema.sql` via phpMyAdmin.
3. Upload **contents of `dist/`** into `public_html/` (includes `index.html`, assets).
4. Upload the **`api/` folder** into `public_html/api/`.
   - **4b. MySQL edition:** copy `api-mysql/_bootstrap.php` → `api/_bootstrap.php`,
     `api-mysql/lib/store.php` → `api/lib/store.php`, and
     `api-mysql/lib/mongo_compat.php` → `api/lib/mongo_compat.php` (creates a new file).
5. Upload **`.htaccess`** (repo root) into `public_html/` (SPA routing).
6. Create `public_html/uploads/` (writable) for product images/ad media.
7. Edit `public_html/api/config.php`: set `jwtSecret`; then set **either** `mongoUri` (Atlas)
   **or** the `mysql` block (host, database, user, password, `tablePrefix`).
8. Seed demo data once (SSH/CLI): `php public_html/api/seed.php` → admin `admin / admin123`.
9. Visit the site → log into `/admin` and change the admin password immediately.

## Local Development

```bash
npm install
php -S localhost:8000          # serves repo root (API at /api)
npm run dev                    # React on http://localhost:5173 (proxies /api)
php api/seed.php               # seed demo data
```

To run locally on **MySQL** instead of MongoDB: import `api-mysql/schema.sql`, copy the three
drop-in files over `api/` (see the api-mysql README), set the `mysql` block in `api/config.php`,
then `php api/seed.php`. XAMPP's MariaDB + `pdo_mysql` is enough.

## Pending / Backlog (post-MVP)

- Order tracking page for customers (endpoint exists: `GET /api/orders.php?number=&phone=`).
- Email/SMS order notifications (Hostinger can send via PHP `mail()` or SMTP).
- Product slug URLs, multi-language, wishlist sharing.
- ~~MySQL/PDO fallback driver if MongoDB extension cannot be enabled on the hosting plan.~~
  ✅ Done — see `api-mysql/` (drop-in `_bootstrap.php` + `lib/store.php` + `lib/mongo_compat.php`,
  `schema.sql`, verified end-to-end against MariaDB).
