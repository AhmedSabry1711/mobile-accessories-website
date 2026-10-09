<?php
/**
 * Mobi Boost — shared store logic (MySQL / PDO edition).
 *
 * Drop-in replacement for the MongoDB version of this file: same public
 * functions, but settings/coupon dates are stored as ISO strings and stock is
 * adjusted by rewriting the product document (no arrayFilters / BulkWrite).
 */

/** Default settings, merged over the stored 'store' document. */
function default_settings(): array {
    global $CONFIG;
    return [
        'whatsapp' => '',
        'shipping' => ['enabled' => true, 'flatRate' => 4.99, 'freeOver' => 50.0],
        'currency' => ['symbol' => $CONFIG['currencySymbol'], 'code' => $CONFIG['currencyCode']],
        'categories' => [],
    ];
}

/** Read merged store settings from the settings collection. */
function store_settings(): array {
    $stored = mongo_find_one('settings', ['key' => 'store']);
    $defaults = default_settings();
    if (!$stored) {
        return $defaults;
    }
    foreach (['whatsapp', 'shipping', 'currency', 'categories'] as $field) {
        if (isset($stored[$field]) && $stored[$field] !== null && $stored[$field] !== []) {
            $defaults[$field] = $stored[$field];
        }
    }
    return $defaults;
}

/** Save store settings (upsert the 'store' document). */
function save_settings(array $settings): void {
    $clean = [];
    foreach (['whatsapp', 'shipping', 'currency', 'categories'] as $field) {
        if (array_key_exists($field, $settings)) {
            $clean[$field] = $settings[$field];
        }
    }
    $clean['updatedAt'] = gmdate('c');
    mongo_update('settings', ['key' => 'store'], ['$set' => $clean], ['upsert' => true]);
}

/** Normalize a product document for API output (ids, floats, derived fields). */
function enrich_product(array $p): array {
    $p['id'] = (string)$p['_id'];
    unset($p['_id']);
    $p['price'] = (float)$p['price'];
    $p['costPrice'] = (float)($p['costPrice'] ?? 0);
    $p['compareAtPrice'] = (float)($p['compareAtPrice'] ?? 0);
    $p['discountPercent'] = $p['compareAtPrice'] > $p['price']
        ? (int)round(($p['compareAtPrice'] - $p['price']) / $p['compareAtPrice'] * 100)
        : 0;
    $images = array_values(array_filter(array_map('strval', (array)($p['images'] ?? [])), fn($i) => $i !== ''));
    $p['images'] = $images;
    $p['image'] = $images[0] ?? '';
    $p['variants'] = array_values((array)($p['variants'] ?? []));
    $p['hasVariants'] = !empty($p['variants']);
    $units = 0;
    if ($p['hasVariants']) {
        foreach ($p['variants'] as $variant) {
            foreach ((array)($variant['colors'] ?? []) as $color) {
                $units += (int)($color['stock'] ?? 0);
            }
        }
    } else {
        $units = (int)($p['stock'] ?? 0);
    }
    $p['stockTotal'] = $units;
    $p['profitUnit'] = round($p['price'] - $p['costPrice'], 2);
    return $p;
}

/**
 * Resolve the available stock for a cart line.
 * Returns ['available' => int, 'resolvedModel' => ?string, 'resolvedColor' => ?string]
 */
function resolve_stock(array $product, ?string $model, ?string $color): array {
    $variants = array_values((array)($product['variants'] ?? []));
    if (empty($variants)) {
        return ['available' => (int)($product['stock'] ?? 0), 'resolvedModel' => null, 'resolvedColor' => null];
    }
    $chosenModel = null;
    foreach ($variants as $variant) {
        if (strcasecmp((string)$variant['model'], (string)$model) === 0) {
            $chosenModel = (string)$variant['model'];
            foreach ((array)($variant['colors'] ?? []) as $c) {
                if (strcasecmp((string)$c['name'], (string)$color) === 0) {
                    return ['available' => (int)($c['stock'] ?? 0), 'resolvedModel' => $chosenModel, 'resolvedColor' => (string)$c['name']];
                }
            }
            return ['available' => 0, 'resolvedModel' => $chosenModel, 'resolvedColor' => null];
        }
    }
    return ['available' => 0, 'resolvedModel' => null, 'resolvedColor' => null];
}

/**
 * Validate raw cart items against the catalog and compute totals.
 * Raw items: [{ productId, qty, model?, color? }]
 * Returns ['items' => [...], 'subtotal' => float, 'coupon' => ?array, 'discount' => float,
 *          'shippingFee' => float, 'total' => float, 'warnings' => string[]]
 */
function build_cart(array $rawItems, ?string $couponCode): array {
    $warnings = [];
    $lineItems = [];
    $subtotal = 0.0;

    foreach ($rawItems as $raw) {
        $productId = (string)($raw['productId'] ?? '');
        $qty = (int)($raw['qty'] ?? 0);
        $oid = object_id($productId);
        if (!$oid || $qty < 1 || $qty > 99) {
            $warnings[] = 'Invalid cart item';
            continue;
        }
        $product = mongo_find_one('products', ['_id' => $oid, 'status' => 'active']);
        if (!$product) {
            $warnings[] = 'A product is no longer available';
            continue;
        }
        $product = enrich_product($product);
        $model = isset($raw['model']) && $raw['model'] !== '' ? (string)$raw['model'] : null;
        $color = isset($raw['color']) && $raw['color'] !== '' ? (string)$raw['color'] : null;
        $stock = resolve_stock($product, $model, $color);
        if ($product['hasVariants'] && ($stock['resolvedModel'] === null || $stock['resolvedColor'] === null)) {
            $warnings[] = '"' . $product['name'] . '" — select a valid model and color';
            continue;
        }
        $lineItems[] = [
            'productId' => $product['id'],
            'name' => $product['name'],
            'image' => $product['image'],
            'price' => $product['price'],
            'costPrice' => $product['costPrice'],
            'qty' => $qty,
            'model' => $stock['resolvedModel'],
            'color' => $stock['resolvedColor'],
            'available' => $stock['available'],
            'inStock' => $stock['available'] >= $qty,
        ];
        $subtotal += $product['price'] * $qty;
    }

    $subtotal = round($subtotal, 2);
    $coupon = null;
    $discount = 0.0;
    if ($couponCode !== null && $couponCode !== '') {
        try {
            $coupon = validate_coupon($couponCode, $subtotal);
            $discount = $coupon['discount'];
        } catch (RuntimeException $e) {
            $warnings[] = $e->getMessage();
        }
    }

    $discounted = max(0.0, $subtotal - $discount);
    $settings = store_settings();
    $shippingRules = $settings['shipping'];
    $shippingFee = 0.0;
    if (!empty($shippingRules['enabled']) && $lineItems !== [] && $discounted < (float)$shippingRules['freeOver']) {
        $shippingFee = (float)$shippingRules['flatRate'];
    }

    return [
        'items' => $lineItems,
        'subtotal' => $subtotal,
        'coupon' => $coupon,
        'discount' => round($discount, 2),
        'shippingFee' => round($shippingFee, 2),
        'total' => round($discounted + $shippingFee, 2),
        'warnings' => $warnings,
    ];
}

/**
 * Validate a coupon code against a subtotal.
 * @throws RuntimeException when invalid
 */
function validate_coupon(string $code, float $subtotal): array {
    $code = strtoupper(trim($code));
    if ($code === '') {
        throw new RuntimeException('Enter a coupon code');
    }
    $coupon = mongo_find_one('coupons', ['code' => $code, 'active' => true]);
    if (!$coupon) {
        throw new RuntimeException('Invalid or inactive coupon code');
    }
    // expiresAt is stored as an ISO-8601 string (or a UTCDateTime on the Mongo path).
    if (!empty($coupon['expiresAt'])) {
        $expires = $coupon['expiresAt'] instanceof MongoDB\BSON\UTCDateTime
            ? $coupon['expiresAt']->toDateTime()->getTimestamp()
            : strtotime((string)$coupon['expiresAt']);
        if ($expires !== false && $expires < time()) {
            throw new RuntimeException('This coupon has expired');
        }
    }
    if ((float)($coupon['minOrder'] ?? 0) > $subtotal) {
        throw new RuntimeException('This coupon requires a minimum order of ' . number_format((float)$coupon['minOrder'], 2));
    }
    $discount = $coupon['type'] === 'percent'
        ? round($subtotal * (float)$coupon['value'] / 100, 2)
        : min((float)$coupon['value'], $subtotal);
    return [
        'code' => $coupon['code'],
        'type' => $coupon['type'],
        'value' => (float)$coupon['value'],
        'discount' => $discount,
    ];
}

/** Decrement stock for a list of cart line items (already validated). */
function decrement_stock(array $lineItems): void {
    foreach ($lineItems as $item) {
        $productId = (string)($item['productId'] ?? '');
        $qty = (int)($item['qty'] ?? 0);
        if ($productId === '' || $qty < 1) {
            continue;
        }
        $product = mongo_find_one('products', ['_id' => $productId]);
        if (!$product) {
            continue;
        }
        if (!empty($item['model'])) {
            $variants = array_values((array)($product['variants'] ?? []));
            foreach ($variants as $vi => $variant) {
                if (strcasecmp((string)($variant['model'] ?? ''), (string)$item['model']) !== 0) {
                    continue;
                }
                $colors = array_values((array)($variant['colors'] ?? []));
                foreach ($colors as $ci => $color) {
                    if (strcasecmp((string)($color['name'] ?? ''), (string)$item['color']) === 0) {
                        $colors[$ci]['stock'] = max(0, (int)($color['stock'] ?? 0) - $qty);
                    }
                }
                $variants[$vi]['colors'] = $colors;
            }
            mongo_update('products', ['_id' => $productId], ['$set' => ['variants' => $variants]]);
        } else {
            $stock = max(0, (int)($product['stock'] ?? 0) - $qty);
            mongo_update('products', ['_id' => $productId], ['$set' => ['stock' => $stock]]);
        }
    }
}

/** Restore stock for a list of order line items (used when an order is cancelled). */
function restore_stock(array $lineItems): void {
    foreach ($lineItems as $item) {
        $productId = (string)($item['productId'] ?? '');
        $qty = (int)($item['qty'] ?? 0);
        if ($productId === '' || $qty < 1) {
            continue;
        }
        $product = mongo_find_one('products', ['_id' => $productId]);
        if (!$product) {
            continue;
        }
        if (!empty($item['model'])) {
            $variants = array_values((array)($product['variants'] ?? []));
            foreach ($variants as $vi => $variant) {
                if (strcasecmp((string)($variant['model'] ?? ''), (string)$item['model']) !== 0) {
                    continue;
                }
                $colors = array_values((array)($variant['colors'] ?? []));
                foreach ($colors as $ci => $color) {
                    if (strcasecmp((string)($color['name'] ?? ''), (string)$item['color']) === 0) {
                        $colors[$ci]['stock'] = (int)($color['stock'] ?? 0) + $qty;
                    }
                }
                $variants[$vi]['colors'] = $colors;
            }
            mongo_update('products', ['_id' => $productId], ['$set' => ['variants' => $variants]]);
        } else {
            $stock = (int)($product['stock'] ?? 0) + $qty;
            mongo_update('products', ['_id' => $productId], ['$set' => ['stock' => $stock]]);
        }
    }
}

/** Generate a unique order number like MB-2610-04821. */
function generate_order_number(): string {
    do {
        $number = 'MB-' . gmdate('ym') . '-' . str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    } while (mongo_find_one('orders', ['orderNumber' => $number]));
    return $number;
}
