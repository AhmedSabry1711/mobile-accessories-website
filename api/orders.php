<?php
/**
 * Mobi Boost — Orders API (Cash on Delivery).
 *
 * POST /api/orders.php                  place an order
 * GET  /api/orders.php?number=&phone=   track an order
 */
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

// ---- Track order ----------------------------------------------------------
if ($method === 'GET') {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    rate_limit('order-track:' . $ip, 60, 600);

    $number = trim((string)($_GET['number'] ?? ''));
    $phone = trim((string)($_GET['phone'] ?? ''));
    if ($number === '' || $phone === '') {
        fail('Order number and phone are required', 400);
    }
    $order = mongo_find_one('orders', ['orderNumber' => $number]);
    if (!$order || !phone_matches($order, $phone)) {
        fail('Order not found', 404);
    }
    ok(order_payload($order));
}

if ($method !== 'POST') {
    fail('Method not allowed', 405);
}

$data = json_input();

// ---- Cancel order (customer) ----------------------------------------------
if (($_GET['action'] ?? '') === 'cancel') {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    rate_limit('order-cancel:' . $ip, 20, 600);

    $number = trim((string)($data['orderNumber'] ?? ''));
    $phone = trim((string)($data['phone'] ?? ''));
    if ($number === '' || $phone === '') {
        fail('Order number and phone are required', 400);
    }

    $order = mongo_find_one('orders', ['orderNumber' => $number]);
    if (!$order || !phone_matches($order, $phone)) {
        fail('Order not found', 404);
    }

    $status = (string)($order['status'] ?? '');
    // Already cancelled: answer with the order so a double tap is harmless.
    if ($status === 'cancelled') {
        ok(order_payload($order));
    }
    // Once the order is being prepared or has been delivered, only the shop can
    // cancel it (see api/admin/orders.php).
    if (!in_array($status, ['pending', 'confirmed'], true)) {
        fail(
            'This order can no longer be cancelled',
            409,
            ['status' => 'Only pending or confirmed orders can be cancelled']
        );
    }

    $oid = (string)$order['_id'];
    $prefix = db_prefix();
    // Conditional update: two simultaneous taps can never cancel twice, because
    // only the first one changes a row (and therefore restores the stock).
    $stmt = db()->prepare(
        "UPDATE `{$prefix}orders`
            SET `status` = 'cancelled', `updated_at` = UTC_TIMESTAMP()
          WHERE `id` = ? AND `status` IN ('pending', 'confirmed')"
    );
    $stmt->execute([$oid]);

    if ($stmt->rowCount() === 1) {
        restore_stock((array)$order['items']);
    }

    $fresh = mongo_find_one('orders', ['_id' => $oid]);
    if (!$fresh) {
        fail('Order not found', 404);
    }
    if ((string)($fresh['status'] ?? '') !== 'cancelled') {
        fail('This order can no longer be cancelled', 409);
    }
    ok(order_payload($fresh));
}

// ---- Place order ----------------------------------------------------------
$rawItems = array_values((array)($data['items'] ?? []));
$customer = (array)($data['customer'] ?? []);
$couponCode = isset($data['couponCode']) && $data['couponCode'] !== ''
    ? (string)$data['couponCode']
    : null;

$errors = [];
$name = trim((string)($customer['name'] ?? ''));
$address = trim((string)($customer['address'] ?? ''));
$phone = trim((string)($customer['phone'] ?? ''));

if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
    $errors['name'] = 'Full name is required (2-80 characters)';
}
if (mb_strlen($address) < 5 || mb_strlen($address) > 300) {
    $errors['address'] = 'Delivery address is required (5-300 characters)';
}
if (!preg_match('/^[+]?[\d\s\-().]{6,20}$/', $phone)) {
    $errors['phone'] = 'Enter a valid phone number';
}
if (empty($rawItems)) {
    $errors['items'] = 'Your cart is empty';
}
if ($errors) {
    fail('Validation failed', 422, $errors);
}

// Server-side price/stock/coupon/shipping validation
$cart = build_cart($rawItems, $couponCode);

// The shopper typed a code, so never place the order at full price silently:
// reject it and let the checkout show the reason.
if ($couponCode !== null && $cart['coupon'] === null && $cart['couponError'] !== null) {
    fail('Coupon could not be applied', 422, ['couponCode' => $cart['couponError']]);
}

if (empty($cart['items'])) {
    fail('Your cart could not be validated', 422, ['items' => implode('; ', $cart['warnings'])]);
}

$problems = [];
foreach ($cart['items'] as $item) {
    if (!$item['inStock']) {
        $problems[] = sprintf('"%s"%s only %d in stock',
            $item['name'],
            $item['model'] ? " ({$item['model']} / {$item['color']})" : '',
            $item['available']
        );
    }
}
if ($problems) {
    fail('Some items are out of stock', 409, ['stock' => $problems]);
}

$order = mongo_insert('orders', [
    'orderNumber' => generate_order_number(),
    'items' => array_map(fn($i) => [
        'productId' => $i['productId'],
        'name' => $i['name'],
        'image' => $i['image'],
        'price' => $i['price'],
        'costPrice' => $i['costPrice'],
        'qty' => $i['qty'],
        'model' => $i['model'],
        'color' => $i['color'],
    ], $cart['items']),
    'subtotal' => $cart['subtotal'],
    'discount' => $cart['discount'],
    'couponCode' => $cart['coupon'] ? $cart['coupon']['code'] : null,
    'shippingFee' => $cart['shippingFee'],
    'total' => $cart['total'],
    'customer' => ['name' => $name, 'address' => $address, 'phone' => $phone],
    'status' => 'pending', // pending -> processing -> completed | cancelled
    'createdAt' => new MongoDB\BSON\UTCDateTime(),
]);

decrement_stock($cart['items']);

// Best-effort alerts to the owner. Each channel fails silently: the order is
// already saved, so a notification problem must never turn a sale into an error.
notify_order_whatsapp($order);
notify_order_telegram($order);

ok(order_payload($order), 201);

/** Shape an order document for the public API (tracking + order placement). */
function order_payload(array $order): array {
    $order = bson_clean($order);
    $order['id'] = $order['_id'];
    unset($order['_id']);
    $order['subtotal'] = (float)$order['subtotal'];
    $order['discount'] = (float)$order['discount'];
    $order['shippingFee'] = (float)$order['shippingFee'];
    $order['total'] = (float)$order['total'];
    foreach ($order['items'] as &$item) {
        $item['price'] = (float)$item['price'];
        $item['qty'] = (int)$item['qty'];
        // Cost is shop-internal data: it must never reach the customer's browser.
        unset($item['costPrice']);
    }
    return $order;
}

/**
 * Does the phone typed by the shopper match the one stored on the order?
 * Formatting (spaces, dashes, +) is ignored, and a country-code prefix is
 * accepted: "+20 101 234 5678" and "01012345678" both end with the same
 * 10-digit subscriber number.
 */
function phone_matches(array $order, string $phone): bool {
    $saved = preg_replace('/\D+/', '', (string)($order['customer']['phone'] ?? ''));
    $typed = preg_replace('/\D+/', '', $phone);
    if ($saved === '' || $typed === '') {
        return false;
    }
    if ($saved === $typed) {
        return true;
    }
    return strlen($saved) >= 10 && strlen($typed) >= 10 && substr($saved, -10) === substr($typed, -10);
}
