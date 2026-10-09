-- ============================================================================
-- Mobi Boost — MySQL / MariaDB schema (document store over JSON rows)
-- ============================================================================
-- Import ONCE via phpMyAdmin (or `mysql db < schema.sql`) before running
-- `php api/seed.php`. The MySQL API layer keeps each "collection" in one table:
--
--   id         CHAR(24)  24-hex document id (BSON ObjectId compatible)
--   doc        LONGTEXT  the document itself, stored as JSON
--   created_at DATETIME  copy of the document's createdAt (for indexing)
--
-- Filtering/sorting/pagination happen in PHP (datasets are small); _id lookups
-- hit the primary key. No other columns are required.
--
-- If you use a database/table prefix on Hostinger (e.g. u123456_mobi), create
-- the database there and keep the `mb_` table prefix — or change
-- `mysql.tablePrefix` in api/config.php to match.
-- ============================================================================

SET NAMES utf8mb4;

-- Optional: create the database (comment out if it already exists / no rights)
-- CREATE DATABASE IF NOT EXISTS `mobi_boost`
--   DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `mobi_boost`;

CREATE TABLE IF NOT EXISTS `mb_products` (
  `id`         CHAR(24) NOT NULL,
  `doc`        LONGTEXT NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mb_products_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mb_reviews` (
  `id`         CHAR(24) NOT NULL,
  `doc`        LONGTEXT NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mb_reviews_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mb_orders` (
  `id`         CHAR(24) NOT NULL,
  `doc`        LONGTEXT NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mb_orders_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mb_coupons` (
  `id`         CHAR(24) NOT NULL,
  `doc`        LONGTEXT NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mb_coupons_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mb_ads` (
  `id`         CHAR(24) NOT NULL,
  `doc`        LONGTEXT NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mb_ads_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mb_settings` (
  `id`         CHAR(24) NOT NULL,
  `doc`        LONGTEXT NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mb_users` (
  `id`         CHAR(24) NOT NULL,
  `doc`        LONGTEXT NOT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mb_users_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
