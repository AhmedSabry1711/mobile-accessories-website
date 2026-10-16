# Mobi Boost — PROJECT MAP

> Full-stack e-commerce app for mobile accessories.
> React (Vite) frontend · PHP REST API · **  MySQL       or MySQL/MariaDB (choose one)**.
> Deploy target: Hostinger Unlimited Web Hosting (static React files + PHP API in `public_html`).

> **Database editions.** The API exposes one storage backend — **MySQL/MariaDB via PDO** — through
> the Mongo-style helpers (`mongo_find`, `mongo_insert`, …) that every endpoint calls, so no
> endpoint knows about SQL. It needs no PHP extension beyond the standard `pdo_mysql`.
> - **MySQL/PDO** — `api/_bootstrap.php` + `api/lib/store.php`, with data in normalized tables
>   (see `api/schema.sql`). [`api-mysql/`](./api-mysql/) holds a byte-identical mirror of those
>   files; keep the two in sync when you edit either.
> - **MongoDB** — the same helpers can still drive Atlas via `ext-mongodb`; set `mongoUri`.
>
> Setting up the production database: **[`HOSTINGER-SQL-SETUP.md`](./HOSTINGER-SQL-SETUP.md)**.

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
│  served from public_html/   │        │  PDO                         │
└─────────────────────────────┘        └──────────────┬───────────────┘
                                                      │ TCP 3306 
                                               ┌──────▼───────┐
                                               │   MySQL      │
                                               └──────────────┘
```

- **Frontend:** React 18 + Vite + React Router. All pages are static after `npm run build`.
  `src/App.jsx` declares two shells: a **storefront layout** (Header + page + Footer +
  WhatsApp button) for `/`, `/shop`, `/products/:id`, `/cart`, `/checkout`,
  `/order-success/:id`, and a **separate admin shell** for `/admin` and `/admin/panel/*`
  that renders *no* storefront chrome — so the panel cannot be left except via Sign out.
- **Backend:** Stateless PHP scripts (`api/*.php`). Each file requires `api/_bootstrap.php`
  (CORS, JSON headers, DB connection, helpers, admin auth). No Composer needed.
  Storage is **MySQL/PDO** — clean, normalized tables (`api/schema.sql`), no JSON blobs.
  `api-mysql/` mirrors those files for the "edit either copy" workflow.
- **Cart/Wishlist:** stored in the browser (localStorage). Prices, coupons, shipping and
  stock are **always re-validated server-side** before an order is created.
- **Auth:** admin login returns an HMAC-signed token (`Bearer` header). Public storefront
  needs no accounts; checkout is Cash on Delivery (name / address / phone only).
- **Localization:** the storefront is bilingual — **Egyptian Arabic is the default** (`ar`),
  English (`en`) is one tap away via the header globe toggle. `UIContext` sets
  `<html lang>` and `dir` (RTL/LTR) and exposes `t(key)`, `lang`, `toggleLang`; strings live
  in `src/i18n/translations.js`. The choice is persisted in `localStorage('mb_lang')`.
  Product names/descriptions, category labels, color names and ad titles come from the
  database, so they get a separate English → Arabic map in `src/i18n/catalog.js`
  (`localizeProduct` / `localizeCategory` / `localizeColor` / `localizeName` /
  `localizeAdTitle` / `localizeVariant`). Category labels are stored as canonical English
  keys and translated in both directions (`localizeCategory`); the admin category editor
  passes a typed Arabic label through `canonicalCategory` before saving, so adding
  "جرابات" from the panel still stores/links as `Phone Cases`. Only display text is
  localized — URLs, cart lines and API filters keep the canonical English values, and
  unmapped values fall back to the stored text.
- **Store categories:** managed from **Admin → Settings → Categories** as an add/remove
  chip editor. The saved list drives the header nav bar; `Header.jsx` reloads
  `/api/settings.php` on every route change and on a `mb:settings-updated` window event
  (dispatched after an admin save), so new categories appear without a manual refresh.
- **Branding:** `src/components/BrandLogo.jsx` is the single lockup element used by the
  header, footer, admin sidebar and admin login. It renders **two** drop-in-replaceable
  files: `public/logo.png` (dark navy wordmark + electric-blue accent) for light surfaces
  and `public/logo-light.png` (white wordmark + brighter blue accent) for dark ones.
  `styles.css` decides which `<img>` is painted per surface and per theme, so no caller
  ever picks a variant. Both assets are transparent PNGs cropped to the lockup; size them
  with `.brand-logo { height }` (`img` fills it).
- **Browser tab icon (favicon):** a rounded tile with a navy→electric-blue diagonal
  gradient and a soft sheen, carrying a bold white **"M"** monogram (the brand's initial)
  so the tab is recognisable without the wordmark. `public/favicon.svg` is the vector source of truth and
  `public/favicon.ico` (16/32/48) the classic fallback that also overrides any hosting
  default at the domain root; `public/apple-touch-icon.png` (180) and
  `public/icon-192.png` / `icon-512.png` + `public/site.webmanifest` cover iOS/Android
  install. `index.html` declares them (with a `?v=` cache-buster) and a `theme-color`.
  Regenerate the whole set with `php tools/make-icons.php` — see **Build tooling** below.
- **Footer social:** the footer shows compact brand buttons (TikTok, Instagram,
  WhatsApp, Facebook) whose URLs live in the `SOCIALS` map at the top of
  `src/components/Footer.jsx`. Leaving `facebook` blank renders a dimmed
  "قريبًا" placeholder instead of a link.
- **One-tap add to cart:** every product card renders *Add To Cart*. Products with
  options (phone cases) add their first in-stock variant (model + colour) and confirm
  with a toast, instead of sending the shopper to the detail page.
- **Hero ads:** promo cards are portrait `9 / 16` (`.ads-card-lg`), sized small so the
  hero stays compact. `AdsCarousel.jsx` renders the ad list once per "copy", measures
  the rendered set width and repeats it enough times to always cover the viewport
  (minimum two copies), then sets `--ads-shift` to exactly one copy so the CSS
  `translateX` loop restarts on an identical frame — the train never shows a gap and
  the card that leaves one side re-enters on the other. Extra copies are marked
  `aria-hidden`/`tabIndex={-1}`.
- **Home page sections:** `Home.jsx` renders the ads carousel, a gradient hero
  (headline + delivery pill + perks + CTAs), a *Shop by category* strip built from
  `settings.categories` (each maps to an icon via `CATEGORY_ICONS`), then the deals /
  new / all shelves. Headline and icon copy live in `home.*` translations.
- **Delivery:** the store promises 24–48 hours (`home.delivery`, `home.perks.fast.text`).
- **WhatsApp:** the floating widget and contact links read `settings.whatsapp`
  (international format, e.g. `+201023538775`) from the admin Settings page.

## Directory Structure

```
/                              (repository root)
├── api/                       PHP REST API  ->  upload to public_html/api/
│   ├── config.php             DB settings (Mongo URI + MySQL creds), JWT secret, currency
│   ├── _bootstrap.php         CORS, JSON helpers, MySQL storage layer, token auth
│   ├── lib/store.php          Cart/coupon/stock logic
│   ├── lib/mongo_compat.php   BSON value-type polyfills (ObjectId/UTCDateTime/Regex)
│   ├── schema.sql             Normalized table definitions (copy of api-mysql/schema.sql)
│   ├── migrate.php            CLI: convert an old document-schema DB to the tables
│   ├── products.php           GET list/search/sale/new/related + GET single
│   ├── reviews.php            GET reviews + average, POST new review
│   ├── coupons.php            POST validate coupon against subtotal
│   ├── cart.php               POST calculate cart totals (prices, coupon, shipping)
│   ├── orders.php             POST create COD order (stock-checked), GET track
│   ├── settings.php           GET public settings (shipping, WhatsApp, categories, ads)
│   ├── auth.php               POST admin login, GET current admin
│   ├── upload.php             POST image/video upload (admin)
│   ├── health.php             Deployment diagnostics (delete once verified)
│   ├── seed.php               CLI demo-data seeder (admin, products, coupons, ads)
│   └── admin/
│       ├── dashboard.php      Revenue / profit / orders analytics
│       ├── products.php       Product CRUD (cost price + selling price + variants)
│       ├── coupons.php        Coupon CRUD
│       ├── ads.php            Hero banner (video/GIF) CRUD
│       ├── orders.php         Order list, detail, status update (restocks on cancel)
│       ├── reviews.php        Review moderation (list / delete)
│       └── settings.php       Shipping rules, WhatsApp number, categories
├── api-mysql/                 MySQL/PDO edition sources (no ext-mongodb needed)
│   ├── _bootstrap.php         PDO connection + storage helpers (same mongo_* API)
│   ├── lib/store.php          Cart/coupon/stock logic (MySQL edition)
│   ├── lib/mongo_compat.php   BSON value-type polyfills (ObjectId/UTCDateTime/Regex)
│   ├── schema.sql             Normalized tables (products/orders/reviews/...)
│   └── README.md              Activation + upgrade steps & trade-offs
├── src/                       React source
│   ├── main.jsx, App.jsx, styles.css
│   ├── api/client.js          fetch wrapper (auth header + error handling)
│   ├── context/StoreContext.jsx   cart, wishlist, admin session, toasts
│   ├── context/UIContext.jsx      theme + language (ar/en) + RTL/LTR direction
│   ├── i18n/translations.js   Egyptian-Arabic (default) & English UI strings
│   ├── i18n/catalog.js        English→Arabic map for DB content (products,
│   │                          categories, colors, ads)
│   ├── components/            Header, Footer, WhatsAppWidget, HeroTicker,
│   │                          ProductCard, ProductShelf, RatingStars, icons
│   └── pages/                 Home, Shop, ProductDetails, Cart, Checkout,
│       └── admin/             OrderSuccess + AdminLogin/Layout/Dashboard/Products/
│                              ProductForm/Coupons/Ads/Orders/Settings/Reviews
├── public/                    static assets copied verbatim to dist/
│   ├── logo.png               dark navy wordmark lockup (light surfaces)
│   ├── logo-light.png         white wordmark lockup (dark surfaces)
│   ├── favicon.svg            vector tab icon (source of truth)
│   ├── favicon.ico            16/32/48 fallback + root override
│   ├── apple-touch-icon.png, icon-192.png, icon-512.png
│   └── site.webmanifest       PWA install metadata (name/theme/icons)
├── tools/                     build-time helpers (not shipped)
│   ├── make-icons.php         regenerates the favicon/app-icon stack
│   └── diag-admin-auth.php    one-shot admin-auth diagnostic (copy to api/ when needed)
├── dist/                      npm run build output -> upload contents to public_html/
├── .htaccess                  SPA fallback for public_html/
├── package.json, vite.config.js, index.html
└── README.md, PROJECT_MAP.md, HOSTINGER-SQL-SETUP.md
```

## Data Model (database `mobi_boost`)

> The MySQL edition (the one deployed) stores this in **normalized tables** — one column per
> field, no JSON blobs: `mb_products` + `mb_product_images` / `mb_product_variants` /
> `mb_product_variant_colors`, `mb_orders` + `mb_order_items`, `mb_reviews`, `mb_coupons`,
> `mb_ads`, `mb_settings` (single row) + `mb_categories`, `mb_users`.
> `api/_bootstrap.php` maps the `mongo_*` helpers onto these tables, so the endpoints are
> unchanged; `api/migrate.php` upgrades an older document-schema database in place. The MongoDB
> edition keeps the same logical document shape, listed below.

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

> 📘 Step-by-step database walkthrough (hPanel → config → phpMyAdmin import → admin account):
> **[`HOSTINGER-SQL-SETUP.md`](./HOSTINGER-SQL-SETUP.md)**.

1. `npm install` → `npm run build` (produces `dist/`) — or just run `deploy.ps1`, which also
   stages the API, `.htaccess` and `uploads/` into `mobiboost-deploy.zip`. For UI-only changes
   run `.\deploy.ps1 -FrontendOnly` → `mobiboost-frontend.zip` (`index.html` + `assets/` only),
   which cannot overwrite the live `api/config.php`.
2. **Create the MySQL database:** hPanel → Databases → MySQL Databases. Import **`api/schema.sql`**
   in phpMyAdmin (or run `php api/migrate.php` over SSH) to create the 12 `mb_*` tables.
3. Upload **contents of `dist/`** into `public_html/` (includes `index.html`, assets).
4. Upload the **`api/` folder** into `public_html/api/` — the MySQL edition is already in place.
5. Upload **`.htaccess`** (repo root) into `public_html/` (SPA routing; it also forwards the
   `Authorization` header to PHP, which the admin Bearer tokens need).
6. Create `public_html/uploads/` (writable) for product images/ad media.
7. Edit `public_html/api/config.php`: set `jwtSecret`, `corsOrigin`, and the `mysql` block
   (host, port, database, username, password, `tablePrefix`).
8. Seed demo data once (SSH/CLI): `php public_html/api/seed.php` → admin `admin / admin123`
   (or insert the admin row by hand — see the guide's step 5c).
9. Visit the site → log into `/admin` and change the admin password immediately.

## Local Development

```bash
npm install
php -S localhost:8000          # serves repo root (API at /api)
npm run dev                    # React on http://localhost:5173 (proxies /api)
php api/seed.php               # seed demo data
```

The app runs on **MySQL** by default: import `api/schema.sql` into a MySQL/MariaDB database and set
the `mysql` block in `api/config.php`, then `php api/seed.php`. XAMPP's MariaDB + `pdo_mysql` is
enough. Returning to MongoDB is still possible by pointing `mongoUri` at Atlas and enabling
`ext-mongodb`.

## Build Tooling

- **`tools/make-icons.php`** — regenerates the whole favicon/app-icon stack in `public/`
  (`favicon.svg`, `favicon.ico`, `apple-touch-icon.png`, `icon-192.png`, `icon-512.png`).
  It needs no GD/Imagick: the tile gradient, sheen and "M" mark are rasterised by hand with
  supersampling, and the `.ico` is packed manually.

  ```bash
  php tools/make-icons.php                  # premium variant -> public/
  php tools/make-icons.php public navy      # alternative tile
  php tools/make-icons.php %TEMP% premium preview   # + preview-variants.png sheet
  ```

  Variants are `premium` (default — gradient tile + white "M"), `frame` (navy frame, white
  field, blue "M"), `blue` (electric-blue tile + white "M") and `navy`. Editing the icon
  means editing this script, not the binaries — running it reproduces the shipped files
  byte-for-byte. The mark itself is a bold geometric "M" defined as the union of four thick
  segments (two stems + two diagonals that meet at the baseline) clipped flat top and bottom,
  and is drawn identically in the SVG (four `<line>`s under a `<clipPath>`) and the raster.
  After running it, `npm run build` (or `deploy.ps1`) copies them to `dist/`.

## Pending / Backlog (post-MVP)

- Order tracking page for customers (endpoint exists: `GET /api/orders.php?number=&phone=`).
- Email/SMS order notifications (Hostinger can send via PHP `mail()` or SMTP).
- ~~Product slug URLs, multi-language, wishlist sharing.~~
  ✅ Multi-language partially done — bilingual **Egyptian Arabic (default) + English** with RTL/LTR
  switching via the header toggle (`src/context/UIContext.jsx`, `src/i18n/translations.js`).
  Slug URLs and wishlist sharing remain open.
- ~~MySQL/PDO fallback driver if MongoDB extension cannot be enabled on the hosting plan.~~
  ✅ Done — MySQL/PDO is now the **primary** store: `api/_bootstrap.php` + `api/lib/store.php`
  read and write normalized tables (`api/schema.sql`, 12 `mb_*` tables), mirrored in `api-mysql/`,
  verified end-to-end against MariaDB.
