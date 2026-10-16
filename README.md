# Mobi Boost

Full-stack e-commerce web app for **mobile accessories**, built for **Hostinger Unlimited Web Hosting**.

- **Frontend:** React 18 + Vite (static build)
- **Backend:** Pure PHP REST API (no Node server required)
- **Database:** MySQL/MariaDB via PDO (clean, normalized tables) — MongoDB Atlas remains
  supported as an alternative backend (`ext-mongodb`, no Composer needed)

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
> - **MySQL/MariaDB (PDO)** — the default, and what is deployed today. It needs only
>   `pdo_mysql` (standard on Hostinger) and stores everything in **clean, normalized tables** —
>   one column per field, readable straight from phpMyAdmin. All DB access is isolated in
>   `api/_bootstrap.php` and `api/lib/store.php`, so no endpoint changes are required.
>   [`api-mysql/`](./api-mysql/) holds a byte-identical mirror of those files to copy back if you
>   edit either copy. Step-by-step setup: **[`HOSTINGER-SQL-SETUP.md`](./HOSTINGER-SQL-SETUP.md)**.
> - **MongoDB Atlas** — still supported alongside it: enable `ext-mongodb`
>   (hPanel → Advanced → PHP Configuration) and point `mongoUri` at your Atlas cluster. The API
>   talks to it through `MongoDB\Driver\Manager`, so no Composer packages are needed.

---

## Local development

```bash
# 1. Install frontend deps
npm install

# 2. Configure the database
#    api/config.php is git-ignored, so create it from the template first:
#      copy api/config.example.php api/config.php
#    then set the 'mysql' block and 'jwtSecret'.
#    In production the real file lives at <account-home>/mobi-private/config.php
#    and api/_config.php finds it automatically (no editing needed).

# 3. Seed demo data (admin + products + coupons + ads)
php api/seed.php

# 4. Start the PHP API (serves repo root, so the API lives at /api)
php -S localhost:8000

# 5. In another terminal, start the React dev server
npm run dev        # http://localhost:5173  (proxies /api -> :8000)
```

Default admin login after seeding: **`admin` / `admin123`** (change it immediately).

> **Local database:** MySQL/MariaDB is the default — import `api/schema.sql` into a MySQL/MariaDB
> database and set the `mysql` block in `api/config.php`. XAMPP's MariaDB with `pdo_mysql` is all
> you need, no `ext-mongodb`. See [`api-mysql/README.md`](./api-mysql/README.md).

---

## Production deployment (Hostinger)

> 🚀 **Recommended: deploy via Git.** `main` is the source branch; a GitHub Action
> builds a **`deploy`** branch that is exactly what `public_html/` should contain,
> and Hostinger pulls that. This is the supported way to avoid the "api/ folder
> disappears after every deploy" trap — see
> **[`HOSTINGER-GIT-DEPLOY.md`](./HOSTINGER-GIT-DEPLOY.md)**.
>
> 🔐 Credentials never live in the web root: put the real config at
> `<account-home>/mobi-private/config.php` and `api/_config.php` finds it
> automatically. `api/config.php` is git-ignored and never shipped.
>
> 🧭 **Setting up the MySQL database?** The connection + schema + admin-account flow is written
> out click-by-click in **[`HOSTINGER-SQL-SETUP.md`](./HOSTINGER-SQL-SETUP.md)**.

### Option A — Git deployment (recommended)

1. `git push origin main` — the **Publish deploy branch** Action builds and
   force-pushes the `deploy` branch (`index.html`, `assets/`, `api/`, `.htaccess`,
   `uploads/.gitkeep`; never `api/config.php`).
2. hPanel → **Git** → repository `.../mobile-accessories-website.git`,
   **branch `deploy`**, directory blank (= `public_html`). The target folder must
   be empty for the very first deployment.
3. Put the credentials at `<account-home>/mobi-private/config.php` (one level
   **above** `public_html`).

Prefer no CI? Run `deploy.ps1 -Publish` locally — it produces the same branch.

Full walkthrough, verification checklist, troubleshooting table and rollback plan:
**[`HOSTINGER-GIT-DEPLOY.md`](./HOSTINGER-GIT-DEPLOY.md)**.

### Option B — Upload a ZIP by hand (fallback / one-off)

1. `npm install`
2. Run `deploy.ps1` (Windows) — it builds the frontend and produces
   **`mobiboost-deploy.zip`** containing exactly what goes into `public_html/`
   (`index.html`, `assets/`, `api/`, `.htaccess`, empty `uploads/`) and **no
   credentials**, so it is safe to extract over a live install.
   For **UI-only changes** use `deploy.ps1 -FrontendOnly` instead: it produces
   **`mobiboost-frontend.zip`** with just `index.html` + `assets/`.
3. Upload the **zip contents** to `public_html/` in hPanel File Manager and
   extract it there. If the host creates a wrapper folder, move its contents up
   into `public_html/` and delete the wrapper.
4. Then follow the manual steps below (uploads folder is included; create
   `~/mobi-private/config.php`, seed, change admin password).

### Manual steps (same result, step by step)

1. **Build** the frontend: `npm install && npm run build` → outputs `dist/`.
2. **Choose your database:**
   - **MySQL/MariaDB (default)** → nothing to install beyond `pdo_mysql`. Create the database in
     **hPanel → Databases → MySQL Databases**, then import **`api/schema.sql`** in phpMyAdmin.
     Full walkthrough with screenshots-level detail: **[`HOSTINGER-SQL-SETUP.md`](./HOSTINGER-SQL-SETUP.md)**.
   - **MongoDB** → enable `ext-mongodb` in hPanel for your PHP version, and have an Atlas
     connection string ready.
3. Upload the **contents of `dist/`** into `public_html/` (this puts `index.html` and `assets/` at the web root).
4. Upload the **`api/` folder** into `public_html/api/` — the MySQL edition is already baked in,
   so there is nothing to copy over it.
5. Upload the repo's **`.htaccess`** into `public_html/` (enables React client-side routing and
   forwards the `Authorization` header to PHP for the admin API).
6. Create a writable **`public_html/uploads/`** folder for product images and ad media.
7. **Put the credentials OUTSIDE the web root** — create
   **`<account-home>/mobi-private/config.php`** (one level above `public_html/`)
   from **`api/config.example.php`**:
   - `jwtSecret` → a long random secret
   - `corsOrigin` → your domain, e.g. `https://mobiboost.store`
   - **MySQL:** the `mysql` block → `host`, `database`, `username`, `password`, `tablePrefix`
   - **MongoDB (legacy):** `mongoUri` → your Atlas connection string

   `api/_config.php` looks there first, so no deploy can ever overwrite it.
   (Keeping a copy at `public_html/api/config.php` still works and takes
   precedence only if the outside file is absent.)
8. Seed once via SSH/CLI: `php public_html/api/seed.php`.
9. Visit your domain, then sign in at `/admin` and **change the admin password**.

### Folder layout after deployment

```
<account-home>/
├── mobi-private/
│   └── config.php        # 🔐 credentials — OUTSIDE the web root, never redeployed
└── public_html/
    ├── index.html            # React build
    ├── assets/               # JS/CSS hashed bundles
    ├── .htaccess             # SPA fallback + Authorization passthrough
    ├── uploads/              # product images / ad media (untracked runtime data)
    └── api/                  # PHP REST API
        ├── _config.php       # locates the credentials file (see above)
        ├── config.example.php
        ├── _bootstrap.php    # MySQL/PDO edition
        ├── lib/              # store.php + mongo_compat.php
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
