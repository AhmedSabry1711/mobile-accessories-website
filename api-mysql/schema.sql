-- ============================================================================
-- Mobi Boost — MySQL / MariaDB schema (normalized tables)
-- ============================================================================
-- One table per entity, one column per field. Open any table in phpMyAdmin and
-- read the data directly — no JSON blobs.
--
-- FRESH INSTALL
--     mysql -u USER -p DBNAME < api/schema.sql      (or import via phpMyAdmin)
--     php api/seed.php                              (demo catalog + admin user)
--
-- UPGRADING from the old "document" schema (mb_* tables shaped as
-- id/doc/created_at)?  Do NOT import this file by hand — run
--     php api/migrate.php
-- instead: it renames the old tables, creates these, and copies the data over.
--
-- Everything is InnoDB + utf8mb4 (Arabic text and emoji safe) and uses the
-- `mb_` table prefix from api/config.php → mysql.tablePrefix.
-- All DATETIME values are UTC.
-- ============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------------------------------------------------------------- catalogue

CREATE TABLE IF NOT EXISTS `mb_products` (
  `id`               CHAR(24)      NOT NULL,
  `name`             VARCHAR(120)  NOT NULL,
  `category`         VARCHAR(60)   NOT NULL,
  `description`      TEXT          NOT NULL,
  `price`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `cost_price`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `compare_at_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `stock`            INT           NOT NULL DEFAULT 0,
  `status`           ENUM('active','draft') NOT NULL DEFAULT 'active',
  `created_at`       DATETIME      NULL DEFAULT NULL,
  `updated_at`       DATETIME      NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_products_status` (`status`),
  KEY `idx_products_category` (`status`, `category`),
  KEY `idx_products_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gallery images, in display order.
CREATE TABLE IF NOT EXISTS `mb_product_images` (
  `id`         BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `product_id` CHAR(24)          NOT NULL,
  `url`        VARCHAR(500)      NOT NULL,
  `position`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_product_images_product` (`product_id`, `position`),
  CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`)
    REFERENCES `mb_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mb_product_variants` (
  `id`         BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `product_id` CHAR(24)          NOT NULL,
  `model`      VARCHAR(120)      NOT NULL,
  `position`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_variants_product` (`product_id`, `position`),
  CONSTRAINT `fk_variants_product` FOREIGN KEY (`product_id`)
    REFERENCES `mb_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mb_product_variant_colors` (
  `id`         BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `variant_id` BIGINT UNSIGNED   NOT NULL,
  `name`       VARCHAR(60)       NOT NULL,
  `hex`        VARCHAR(7)         NOT NULL DEFAULT '#000000',
  `stock`      INT               NOT NULL DEFAULT 0,
  `image`      VARCHAR(500)      NOT NULL DEFAULT '',
  `position`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_variant_colors_variant` (`variant_id`, `position`),
  CONSTRAINT `fk_variant_colors_variant` FOREIGN KEY (`variant_id`)
    REFERENCES `mb_product_variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- sales

CREATE TABLE IF NOT EXISTS `mb_orders` (
  `id`               CHAR(24)      NOT NULL,
  `order_number`     VARCHAR(32)   NOT NULL,
  `status`           ENUM('pending','processing','completed','cancelled') NOT NULL DEFAULT 'pending',
  `subtotal`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `coupon_code`      VARCHAR(20)   NULL DEFAULT NULL,
  `shipping_fee`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `customer_name`    VARCHAR(80)   NOT NULL,
  `customer_address` VARCHAR(300)  NOT NULL,
  `customer_phone`   VARCHAR(20)   NOT NULL,
  `created_at`       DATETIME      NULL DEFAULT NULL,
  `updated_at`       DATETIME      NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_orders_number` (`order_number`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_orders_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Line items keep a *snapshot* of the product (name/price at purchase time),
-- so they must survive the product being deleted → no foreign key here.
CREATE TABLE IF NOT EXISTS `mb_order_items` (
  `id`         BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `order_id`   CHAR(24)          NOT NULL,
  `product_id` CHAR(24)          NOT NULL,
  `name`       VARCHAR(120)      NOT NULL,
  `image`      VARCHAR(500)      NOT NULL DEFAULT '',
  `price`      DECIMAL(10,2)     NOT NULL DEFAULT 0.00,
  `cost_price` DECIMAL(10,2)     NOT NULL DEFAULT 0.00,
  `qty`        INT               NOT NULL DEFAULT 1,
  `model`      VARCHAR(120)      NULL DEFAULT NULL,
  `color`      VARCHAR(60)       NULL DEFAULT NULL,
  `position`   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order` (`order_id`, `position`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`)
    REFERENCES `mb_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mb_reviews` (
  `id`         CHAR(24)     NOT NULL,
  `product_id` CHAR(24)     NOT NULL,
  `user_name`  VARCHAR(80)  NOT NULL,
  `rating`     TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `comment`    TEXT         NOT NULL,
  `created_at` DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  -- Index only (no FK): reviews are kept even if their product is removed,
  -- matching the storefront's existing behaviour.
  KEY `idx_reviews_product` (`product_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mb_coupons` (
  `id`         CHAR(24)      NOT NULL,
  `code`       VARCHAR(20)   NOT NULL,
  `type`       ENUM('percent','fixed') NOT NULL,
  `value`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `min_order`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `expires_at` DATETIME      NULL DEFAULT NULL,
  `active`     TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at` DATETIME      NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_coupons_code` (`code`),
  KEY `idx_coupons_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- marketing

CREATE TABLE IF NOT EXISTS `mb_ads` (
  `id`         CHAR(24)     NOT NULL,
  `title`      VARCHAR(120) NOT NULL,
  `media_url`  VARCHAR(500) NOT NULL,
  `type`       ENUM('image','video') NOT NULL,
  `link_url`   VARCHAR(500) NOT NULL DEFAULT '',
  `sort_order` INT          NOT NULL DEFAULT 0,
  `active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ads_active` (`active`, `sort_order`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- settings

-- Single-row table: the store's own settings (always id = 1).
CREATE TABLE IF NOT EXISTS `mb_settings` (
  `id`                 TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `whatsapp`           VARCHAR(20)   NOT NULL DEFAULT '',
  `shipping_enabled`   TINYINT(1)    NOT NULL DEFAULT 1,
  `shipping_flat_rate` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `shipping_free_over` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency_symbol`    VARCHAR(8)    NOT NULL DEFAULT '$',
  `currency_code`      VARCHAR(8)    NOT NULL DEFAULT 'USD',
  `updated_at`         DATETIME      NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The storefront's navigation categories (one row each, ordered).
CREATE TABLE IF NOT EXISTS `mb_categories` (
  `id`       SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`     VARCHAR(60)       NOT NULL,
  `position` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_categories_position` (`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- accounts

CREATE TABLE IF NOT EXISTS `mb_users` (
  `id`            CHAR(24)     NOT NULL,
  `username`      VARCHAR(60)  NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at`    DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
