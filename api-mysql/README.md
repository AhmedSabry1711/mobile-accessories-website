# Mobi Boost — MySQL/PDO API edition

This folder is a **drop-in replacement** for the MongoDB-based API. Use it when
your host (e.g. Hostinger shared hosting) provides MySQL but **not** the
`mongodb` PHP extension.

The public/admin endpoints in `../api/` don't change — only these files do:

| File in `api-mysql/`        | Copy over                  | Purpose                                          |
| --------------------------- | -------------------------- | ------------------------------------------------ |
| `_bootstrap.php`            | `api/_bootstrap.php`       | MySQL/PDO connection + storage helpers           |
| `lib/store.php`             | `api/lib/store.php`        | Cart/coupon/stock logic (dates as ISO strings)   |
| `lib/mongo_compat.php`      | `api/lib/mongo_compat.php` | BSON value-type polyfills (new file)             |
| `schema.sql`                | —                          | Import once to create the tables                 |

The live copies already sit in `api/` — keep them byte-identical when you edit
either one. `api/migrate.php` is the one-off upgrade script (see below).

## Activate it

1. **Add MySQL credentials** to `api/config.php` (a `'mysql' => [...]` block is
   already included). Example:

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
   mysql -u USER -p DATABASE < api-mysql/schema.sql
   ```

3. **Swap the files**:

   ```bash
   cp api-mysql/_bootstrap.php        api/_bootstrap.php
   cp api-mysql/lib/store.php         api/lib/store.php
   cp api-mysql/lib/mongo_compat.php  api/lib/mongo_compat.php
   ```

4. **Seed the demo catalog**:

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
