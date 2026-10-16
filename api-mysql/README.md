# Mobi Boost — MySQL/PDO storage layer

This folder is the **mirror** of the live MySQL storage files in `../api/`. The API
already runs on MySQL/PDO, so there is nothing to "swap in" — this copy exists so
the storage layer can be reviewed as a unit and so the two stay easy to diff.

Keep the copies **byte-identical** when you edit either one:

| File in `api-mysql/`        | Mirrors                    | Purpose                                          |
| --------------------------- | -------------------------- | ------------------------------------------------ |
| `_bootstrap.php`            | `api/_bootstrap.php`       | MySQL/PDO connection + storage helpers           |
| `_config.php`               | `api/_config.php`          | Credentials-file resolver (env → `~/mobi-private` → `api/`) |
| `lib/store.php`             | `api/lib/store.php`        | Cart/coupon/stock logic (dates as ISO strings)   |
| `lib/mongo_compat.php`      | `api/lib/mongo_compat.php` | BSON value-type polyfills                        |
| `schema.sql`                | `api/schema.sql`           | Creates the 12 normalized tables                 |

`api/migrate.php` is the one-off upgrade script for databases created by the older
document schema (see below). For the full Hostinger walkthrough see
[`../HOSTINGER-SQL-SETUP.md`](../HOSTINGER-SQL-SETUP.md).

> 🔐 **Where the credentials file belongs.** `api/config.php` is **not tracked by
> git** and is **not part of any deploy artifact**. On the live site put the file
> at `<account-home>/mobi-private/config.php` — one level **above** `public_html`
> — so a redeploy that replaces the whole web root cannot delete it.
> `api/_config.php` resolves it automatically, in this order:
> `$MOBI_CONFIG` → `<account-home>/mobi-private/config.php` → `api/config.php`.
> Start from the committable template, `api/config.example.php`.
> See [`../HOSTINGER-GIT-DEPLOY.md`](../HOSTINGER-GIT-DEPLOY.md).

## Set it up

1. **Add MySQL credentials.** For local development, copy the template and edit it:

   ```bash
   cp api/config.example.php api/config.php
   ```

   In production, create `<account-home>/mobi-private/config.php` instead (outside
   the web root). The `'mysql' => [...]` block looks like this — every value can
   also be supplied as an environment variable, which takes precedence:

   ```php
   'mysql' => [
       'host' => getenv('MYSQL_HOST') ?: 'localhost',
       'port' => (int)(getenv('MYSQL_PORT') ?: 3306),
       'database' => getenv('MYSQL_DATABASE') ?: 'mobi_boost',
       'username' => getenv('MYSQL_USER') ?: 'root',
       'password' => getenv('MYSQL_PASSWORD') ?: '',
       'tablePrefix' => getenv('MYSQL_TABLE_PREFIX') ?: 'mb_',
   ],
   ```

2. **Create the tables** — import `schema.sql` via phpMyAdmin, or:

   ```bash
   mysql -u USER -p DATABASE < api/schema.sql
   ```

3. **Seed the demo catalog** (optional — skip it if you'd rather add your own
   products; you'll still need an admin account, see the guide):

   ```bash
   php api/seed.php
   ```

   Admin login: `admin` / `admin123` (change it after the first login).
   Coupons: `WELCOME10` (10% off), `SAVE20` ($20 off orders $80+).

## How it works

The data lives in clean, normalized tables — one column per field, readable
straight from phpMyAdmin (see `schema.sql`):

| Collection | Tables |
| ---------- | ------ |
| products   | `mb_products` + `mb_product_images` + `mb_product_variants` + `mb_product_variant_colors` |
| orders     | `mb_orders` + `mb_order_items` |
| reviews    | `mb_reviews` |
| coupons    | `mb_coupons` |
| ads        | `mb_ads` |
| settings   | `mb_settings` (single row) + `mb_categories` |
| users      | `mb_users` |

Every endpoint still calls `mongo_find` / `mongo_find_one` / `mongo_insert` /
`mongo_update` / `mongo_delete` / `mongo_count`, and the bootstrap translates
them: a document is rebuilt from the tables on read and split back into rows on
write. The REST surface never changed, while the database no longer stores JSON
blobs. Mongo-style filters (`$ne`, `$gt`, `$gte`, `$in`, `$or`, `$expr`, `Regex`,
dotted paths) and updates (`$set`, `$unset`, `$inc`, upserts) behave as before;
`_id` equality is pushed to SQL.

All `created_at` / `updated_at` / `expires_at` values are UTC `DATETIME`s and are
returned as ISO-8601 strings, which the frontend already parses with `new Date(...)`.

## Upgrading an existing install

If your database was created by the older document schema, do **not** import
`schema.sql` by hand — convert it in place:

```bash
php api/migrate.php
```

That script renames each old table to `mb_legacy_<collection>` (nothing is
deleted), creates the new tables, and copies every document across. It is safe to
re-run. Once you are happy, drop the `*legacy_*` tables in phpMyAdmin.

## Notes & trade-offs

- **Scale:** filters other than `_id` are still evaluated in PHP, so a query
  reads the whole collection into documents. That is fine for a store with
  thousands of products/orders; for very large catalogs, add pushed-down SQL
  predicates.
- **Writes** resync a row's child rows (images / variants / order items) — cheap
  at this scale, and there are only a handful of writes per order.
- **No MongoDB needed:** `lib/mongo_compat.php` defines tiny stand-ins for
  `MongoDB\BSON\ObjectId`, `UTCDateTime` and `Regex`. They are each guarded with
  `class_exists`, so if the real extension *is* loaded they are skipped and the
  endpoints use the genuine classes.
- **Order numbering** stays collision-checked against the orders table.

## Reverting to MongoDB

Keep a copy of the original `api/_bootstrap.php` and `api/lib/store.php`; restore
them and delete `api/lib/mongo_compat.php` (or just leave it — it is harmless
with the real extension present).
