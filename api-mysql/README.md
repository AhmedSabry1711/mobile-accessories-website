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

Each MongoDB collection becomes one MySQL table:

```
mb_products, mb_reviews, mb_orders, mb_coupons, mb_ads, mb_settings, mb_users
  id CHAR(24) PK   -- 24-hex id (BSON ObjectId compatible)
  doc LONGTEXT     -- the document as JSON
  created_at DATETIME NULL
```

`mongo_find` / `mongo_find_one` / `mongo_insert` / `mongo_update` /
`mongo_delete` / `mongo_count` keep their names and signatures (so no endpoint
changes), and translate the Mongo-style filters (`$ne`, `$gt`, `$gte`, `$in`,
`$or`, `$expr`, `Regex`, dotted paths) to PHP evaluation over the JSON rows.
`_id` equality is pushed to SQL. `$set` / `$unset` / `$inc` and upserts are
supported.

Dates (`createdAt`, `updatedAt`, `expiresAt`) are stored as ISO-8601 strings and
read back as strings — the frontend already handles that via `new Date(...)`.

## Notes & trade-offs

- **Scale:** filters other than `_id` are evaluated in PHP, so a query reads the
  whole table. That is fine for a store with thousands of products/orders; for
  very large catalogs, add pushed-down SQL predicates or normalized columns.
- **No MongoDB needed:** `lib/mongo_compat.php` defines tiny stand-ins for
  `MongoDB\BSON\ObjectId`, `UTCDateTime` and `Regex`. They are each guarded with
  `class_exists`, so if the real extension *is* loaded they are skipped and the
  endpoints use the genuine classes.
- **Order numbering** stays collision-checked against the orders table.

## Reverting to MongoDB

Keep a copy of the original `api/_bootstrap.php` and `api/lib/store.php`; restore
them and delete `api/lib/mongo_compat.php` (or just leave it — it is harmless
with the real extension present).
