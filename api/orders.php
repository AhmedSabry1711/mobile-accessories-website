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
    $number = trim((string)($_GET['number'] ?? ''));
    $phone = trim((string)($_GET['phone'] ?? ''));
    if ($number === '' || $phone === '') {
        fail('Order number and phone are required', 400);
    }
    $order = mongo_find_one('orders', ['orderNumber' => $number]);
    if (!$order || preg_replace('/[^\d+]/', '', (string)$order['customer']['phone']) !== preg_replace('/[^\d+]/', '', $phone)) {
        fail('Order not found', 404);
    }
    ok(order_payload($order));
}

if ($method !== 'POST') {
    fail('Method not allowed', 405);
}

// ---- Place order ----------------------------------------------------------
$data = json_input();
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

ok(order_payload($order), 201);

/** Shape an order document for API output. */
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
        $item['costPrice'] = (float)$item['costPrice'];
        $item['qty'] = (int)$item['qty'];
    }
    return $order;
}
