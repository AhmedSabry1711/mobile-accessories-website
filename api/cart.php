<?php
/**
 * Mobi Boost — Cart calculation API.
 * Re-validates every line against the catalog, applies coupons and shipping rules.
 *
 * POST /api/cart.php  { items: [{ productId, qty, model?, color? }], couponCode? }
 */
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

$data = json_input();
$items = array_values((array)($data['items'] ?? []));
$couponCode = isset($data['couponCode']) && $data['couponCode'] !== ''
    ? (string)$data['couponCode']
    : null;

if (empty($items)) {
    fail('Cart is empty', 400);
}
if (count($items) > 100) {
    fail('Too many items in cart', 400);
}

$result = build_cart($items, $couponCode);

// Surface stock/validation problems to the shopper without failing the request
ok($result);
