# Mobi Boost

Full-stack e-commerce web app for **mobile accessories**, built for **Hostinger Unlimited Web Hosting**.

- **Frontend:** React 18 + Vite (static build)
- **Backend:** Pure PHP REST API (no Node server required)
- **Database:** MongoDB Atlas (`ext-mongodb` driver) **or MySQL/MariaDB via PDO** — pick one at deploy time
  (MongoDB needs no Composer; the MySQL edition is a drop-in in [`api-mysql/`](./api-mysql/))

📋 **Progress tracking → [`PROJECT_MAP.md`](./PROJECT_MAP.md)**

---

## Features

### Storefront
- Header with logo, **global search**, and **category navigation**
- **Hero ads ticker** — animated right-to-left marquee of promotional GIFs / short videos
- Home sections: **Deals & Discounts**, **Recently Added**, **All Products**
- Quick actions on every product: wishlist (heart), add to cart, view details
- **Product details:** image gallery, original vs. discounted price, **variant selector**
  (phone model → color) with live **stock checking**, **customer reviews & ratings**,
  and **related products**
- **Cart** with dynamic totals, server-side shipping rules, and coupon codes
- **Cash-on-Delivery checkout** — name, address, phone only
- Comprehensive footer + **persistent floating WhatsApp widget** on every page

### Admin panel (`/admin`)
- **Dashboard:** revenue, **net profit** (cost vs. selling price), order stats, low stock, top products
- **Products:** create/edit with cost price, selling price, images, discounts, and per-model/color variants
- **Orders:** view details and update status (cancelling restocks inventory automatically)
- **Coupons:** percent or fixed discounts with minimum order and expiry
- **Ads/Banners:** upload GIFs / short videos for the hero ticker
- **Shipping control:** flat rate + free-shipping threshold
- **Reviews:** moderate and delete customer reviews
- **Settings:** WhatsApp number, currency, categories

---

## Requirements

| Component | Requirement |
|-----------|-------------|
| PHP | 8.1+ |
| Database | **Either** MongoDB Atlas (with the **`mongodb`** extension) **or** MySQL 5.7+/MariaDB (with **`pdo_mysql`**, enabled by default on most hosts) |
| Node | 18+ (build-time only) |

> ⚠️ **Which database?**
> - **MongoDB Atlas** — talks to Atlas through the official `ext-mongodb` driver
>   (`MongoDB\Driver\Manager`), so no Composer packages are needed. On Hostinger you can enable
>   PHP extensions from **hPanel → Advanced → PHP Configuration**.
> - **MySQL/PDO** — if your plan does **not** allow the `mongodb` extension, use the drop-in
>   edition in [`api-mysql/`](./api-mysql/). It needs only `pdo_mysql` (standard on Hostinger)
>   and stores one JSON document per row. All DB access is isolated in `api/_bootstrap.php` and
>   `api/lib/store.php`, so no endpoint changes are required. See that folder's README.

---

## Local development

```bash
# 1. Install frontend deps
npm install

# 2. Configure the database
#    Edit api/config.php -> 'mongoUri' and 'jwtSecret'

# 3. Seed demo data (admin + products + coupons + ads)
php api/seed.php

# 4. Start the PHP API (serves repo root, so the API lives at /api)
php -S localhost:8000

# 5. In another terminal, start the React dev server
npm run dev        # http://localhost:5173  (proxies /api -> :8000)
```

Default admin login after seeding: **`admin` / `admin123`** (change it immediately).

> **Using MySQL locally instead of MongoDB:** import `api-mysql/schema.sql` into a MySQL/MariaDB
> database, copy the three drop-in files from `api-mysql/` over `api/` (see
> [`api-mysql/README.md`](./api-mysql/README.md)), and set the `mysql` block in `api/config.php`.
> XAMPP's MariaDB with `pdo_mysql` is all you need — no `ext-mongodb`.

---

## Production deployment (Hostinger)

### From this repository (quickest)
The repository is fully self-contained (React + PHP, MySQL edition already in
`api/`). After cloning on any machine with Node + PHP:

1. `npm install`
2. Run `deploy.ps1` (Windows) — it builds the frontend and produces
   **`mobiboost-deploy.zip`** containing exactly what goes into `public_html/`
   (`index.html`, `assets/`, `api/`, `.htaccess`, empty `uploads/`).
3. Upload the **zip contents** to `public_html/` in hPanel File Manager.
4. Then follow steps 6–9 below (uploads folder is included; configure
   `api/config.php`, seed, change admin password).

### Manual steps (same result, step by step)

1. **Build** the frontend: `npm install && npm run build` → outputs `dist/`.
2. **Choose your database:**
   - **MongoDB** → enable `ext-mongodb` in hPanel for your PHP version, and have an Atlas
     connection string ready.
   - **MySQL/MariaDB** → no extension needed. Create a database (phpMyAdmin) and import
     `api-mysql/schema.sql`.
3. Upload the **contents of `dist/`** into `public_html/` (this puts `index.html` and `assets/` at the web root).
4. Upload the **`api/` folder** into `public_html/api/`.
   - **MySQL edition:** also copy `api-mysql/_bootstrap.php` → `api/_bootstrap.php`,
     `api-mysql/lib/store.php` → `api/lib/store.php`, and
     `api-mysql/lib/mongo_compat.php` → `api/lib/mongo_compat.php`.
5. Upload the repo's **`.htaccess`** into `public_html/` (enables React client-side routing).
6. Create a writable **`public_html/uploads/`** folder for product images and ad media.
7. Edit **`public_html/api/config.php`**:
   - `jwtSecret` → a long random secret
   - `corsOrigin` → your domain, e.g. `https://mobiboost.com`
   - **MongoDB:** `mongoUri` → your Atlas connection string
   - **MySQL:** the `mysql` block → `host`, `database`, `username`, `password`, `tablePrefix`
8. Seed once via SSH/CLI: `php public_html/api/seed.php`.
9. Visit your domain, then sign in at `/admin` and **change the admin password**.

### Folder layout after deployment

```
public_html/
├── index.html            # React build
├── assets/               # JS/CSS hashed bundles
├── .htaccess             # SPA fallback
├── uploads/              # product images / ad media
└── api/                  # PHP REST API
    ├── config.php
    ├── _bootstrap.php    # MongoDB or MySQL edition
    ├── lib/              # store.php + (MySQL) mongo_compat.php
    ├── products.php …    # public endpoints
    └── admin/…           # protected endpoints
```

---

## API overview

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/settings.php` | Store settings + active ads |
| `GET` | `/api/products.php?filter=sale\|new\|all&category=&q=&limit=&page=` | Catalog |
| `GET` | `/api/products.php?id=` / `?related_to=` | Detail / related |
| `GET` / `POST` | `/api/reviews.php` | List / add reviews |
| `POST` | `/api/coupons.php` | Validate a coupon |
| `POST` | `/api/cart.php` | Server-side cart totals |
| `POST` / `GET` | `/api/orders.php` | Place / track COD order |
| `POST` / `GET` | `/api/auth.php` | Admin login / session |
| `POST` | `/api/upload.php` | Upload media (admin) |
| `*` | `/api/admin/*.php` | Admin operations (Bearer token) |

## Security notes

- Prices, stock, coupons, and shipping are **always recalculated server-side** before an order is saved.
- Admin tokens are HMAC-SHA256 signed; change `jwtSecret` before going live.
- Product stock is validated and decremented atomically per line item.
- Passwords use PHP `password_hash()` (bcrypt).
