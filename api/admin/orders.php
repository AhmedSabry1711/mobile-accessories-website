<?php
/**
 * Mobi Boost — Admin order management.
 *
 * GET  /api/admin/orders.php?status=&q=   list
 * GET  /api/admin/orders.php?id=          single order
 * PUT  /api/admin/orders.php?id=          update status (cancelled orders restock)
 */
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';

$STATUSES = ['pending', 'confirmed', 'processing', 'completed', 'cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_admin();

    $id = $_GET['id'] ?? '';
    if ($id !== '') {
        $oid = object_id((string)$id);
        if (!$oid) {
            fail('Invalid order id', 400);
        }
        $order = mongo_find_one('orders', ['_id' => $oid]);
        if (!$order) {
            fail('Order not found', 404);
        }

        // ?label=1 — data needed to print the shipping label: a stable, signed
        // confirmation code + the absolute QR URL + the store currency. The raw
        // salt never leaves the server.
        if (!empty($_GET['label'])) {
            $ready = order_columns_ready();
            if (!$ready['status_confirmed']) {
                fail('Order confirmation is not available right now.', 503);
            }
            $salt = order_confirm_salt($oid, true);
            if ($salt === null) {
                fail('Could not create a confirmation code for this order.', 500);
            }
            $code = order_confirm_code($oid, $salt);
            $settings = store_settings();
            ok([
                'code' => $code,
                'url' => order_confirm_url($code),
                'currency' => [
                    'symbol' => (string)($settings['currency']['symbol'] ?? '$'),
                    'code' => (string)($settings['currency']['code'] ?? 'USD'),
                ],
            ]);
        }

        ok(order_payload($order));
    }

    $status = (string)($_GET['status'] ?? '');
    $q = trim((string)($_GET['q'] ?? ''));

    $filter = [];
    if ($status !== '' && in_array($status, $STATUSES, true)) {
        $filter['status'] = $status;
    }
    if ($q !== '') {
        $regex = new MongoDB\BSON\Regex(preg_quote($q, '/'), 'i');
        $filter['$or'] = [
            ['orderNumber' => $regex],
            ['customer.name' => $regex],
            ['customer.phone' => $regex],
        ];
    }

    $rows = mongo_find('orders', $filter, [
        'sort' => ['createdAt' => -1],
        'limit' => 200,
    ]);

    ok(array_map('order_payload', $rows));
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    require_admin();

    $id = $_GET['id'] ?? '';
    $oid = object_id((string)$id);
    if (!$oid) {
        fail('Invalid order id', 400);
    }
    $order = mongo_find_one('orders', ['_id' => $oid]);
    if (!$order) {
        fail('Order not found', 404);
    }

    $data = json_input();
    $status = (string)($data['status'] ?? '');
    if (!in_array($status, $STATUSES, true)) {
        fail('Invalid status', 422, ['status' => 'Allowed: ' . implode(', ', $STATUSES)]);
    }

    if ($status === $order['status']) {
        ok(order_payload($order));
    }

    // Cancelling an order returns stock to inventory
    if ($status === 'cancelled' && $order['status'] !== 'cancelled') {
        restore_stock((array)$order['items']);
    }

    $changes = ['status' => $status, 'updatedAt' => new MongoDB\BSON\UTCDateTime()];
    mongo_update('orders', ['_id' => $oid], ['$set' => $changes]);

    // Stamp the confirmation time when an admin confirms an order manually, so
    // the dashboard and the public page show the same information. Written as a
    // direct SQL update because the generic order writer does not touch this
    // column (and must never clear it on unrelated updates).
    if ($status === 'confirmed' && ($order['status'] ?? '') !== 'confirmed') {
        $ready = order_columns_ready();
        if ($ready['confirmed_at']) {
            $p = db_prefix();
            $stmt = db()->prepare(
                "UPDATE `{$p}orders` SET `confirmed_at` = UTC_TIMESTAMP()
                  WHERE `id` = ? AND `confirmed_at` IS NULL"
            );
            $stmt->execute([$oid]);
        }
    }
    $updated = mongo_find_one('orders', ['_id' => $oid]);
    ok(order_payload($updated));
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_admin();
    $id = $_GET['id'] ?? '';
    $oid = object_id((string)$id);
    if (!$oid) {
        fail('Invalid order id', 400);
    }
    $deleted = mongo_delete('orders', ['_id' => $oid]);
    if (!$deleted) {
        fail('Order not found', 404);
    }
    ok(['deleted' => true]);
}

fail('Method not allowed', 405);

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
