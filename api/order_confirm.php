<?php
/**
 * Mobi Boost — public order confirmation (scanned from the shipping-label QR).
 *
 * GET  /api/order_confirm.php?code=<code>           -> minimal order summary
 * POST /api/order_confirm.php  { code }              -> pending -> confirmed (atomic)
 * POST /api/order_confirm.php  { code, action }     -> deliver (confirmed/completed)
 *
 * Opening the page is read-only: the status only moves after an explicit POST.
 * The update is a single conditional SQL statement, so two phones confirming at
 * the same moment can never corrupt the order — one wins, the other is told the
 * order is already confirmed/completed.
 */
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    fail('Method not allowed', 405);
}

$ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
rate_limit('order-confirm:' . $method . ':' . $ip, $method === 'POST' ? 20 : 60, 600);

if ($method === 'GET') {
    $code = trim((string)($_GET['code'] ?? ''));
} else {
    $input = json_input();
    $code = trim((string)($input['code'] ?? ''));
    $action = trim((string)($input['action'] ?? ''));
}

$parsed = order_confirm_parse($code);
if ($parsed === null) {
    fail('Invalid or expired confirmation link', 404);
}

$order = mongo_find_one('orders', ['_id' => $parsed['id']]);
if (!$order || !order_confirm_matches($parsed['id'], $parsed['token'])) {
    // Same answer for a wrong order id and a wrong token: never leak which
    // orders exist to someone probing random links.
    fail('Invalid or expired confirmation link', 404);
}

/** Attach the UI flags to the safe payload. */
$payload = static function (array $doc): array {
    $data = order_confirm_payload($doc);
    $status = (string)($data['status'] ?? '');
    $data['canConfirm'] = $status === 'pending';
    $data['alreadyConfirmed'] = in_array($status, ['confirmed', 'processing', 'completed'], true);
    $data['canDeliver'] = $status === 'confirmed';
    $data['alreadyDelivered'] = $status === 'completed';
    return $data;
};

if ($method === 'GET') {
    ok($payload($order));
}

// ---------------------------------------------------------------- POST: confirm or deliver
$status = (string)($order['status'] ?? '');

// ---- Deliver action: change status to completed (only if not already completed)
if ($action === 'deliver') {
    if ($status === 'cancelled') {
        fail('This order has been cancelled and cannot be delivered', 409);
    }
    if ($status === 'completed') {
        ok($payload($order) + ['alreadyDelivered' => true]);
    }
    // Only an order the shopper already confirmed can be marked as delivered.
    $ready = order_columns_ready();
    if (!$ready['status_confirmed'] || !$ready['confirmed_at']) {
        fail('Order delivery confirmation is not available right now.', 503);
    }
    $p = db_prefix();
    $salt = order_confirm_salt($parsed['id']);
    if ($salt === null) {
        fail('Invalid or expired confirmation link', 404);
    }
    $stmt = db()->prepare(
        "UPDATE `{$p}orders`
            SET `status` = 'completed',
                `updated_at` = UTC_TIMESTAMP()
          WHERE `id` = ? AND `status` = 'confirmed' AND `confirm_salt` = ?"
    );
    $stmt->execute([$parsed['id'], $salt]);

    if ($stmt->rowCount() === 1) {
        $fresh = mongo_find_one('orders', ['_id' => $parsed['id']]);
        if (!$fresh) {
            fail('Order not found', 404);
        }
        ok(array_merge($payload($fresh), ['justDelivered' => true, 'alreadyDelivered' => false]));
    }

    // The order changed between our read and the update (or was already handled).
    $fresh = mongo_find_one('orders', ['_id' => $parsed['id']]);
    if (!$fresh) {
        fail('Invalid or expired confirmation link', 404);
    }
    $freshStatus = (string)($fresh['status'] ?? '');
    if ($freshStatus === 'cancelled') {
        fail('This order has been cancelled and cannot be delivered', 409);
    }
    if ($freshStatus === 'completed') {
        ok($payload($fresh) + ['alreadyDelivered' => true]);
    }
    if (in_array($freshStatus, ['confirmed', 'processing', 'completed'], true) && $freshStatus !== 'completed') {
        // Still not completed yet (race); show current state
        ok($payload($fresh) + ['alreadyDelivered' => false]);
    }

    fail('The order status changed. Please refresh the page and try again.', 409);
}

// ---- Confirm action: pending -> confirmed (original logic)
if ($status === 'cancelled') {
    fail('This order has been cancelled and cannot be confirmed', 409);
}
if (in_array($status, ['confirmed', 'processing', 'completed'], true)) {
    ok($payload($order) + ['alreadyConfirmed' => true]);
}

$ready = order_columns_ready();
if (!$ready['status_confirmed'] || !$ready['confirmed_at']) {
    fail('Order confirmation is not available right now. Please contact support.', 503);
}

$p = db_prefix();
$salt = order_confirm_salt($parsed['id']);
if ($salt === null) {
    fail('Invalid or expired confirmation link', 404);
}

// Atomic transition: only a still-pending order can move to confirmed. The salt
// is part of the WHERE clause as a second line of defence.
$stmt = db()->prepare(
    "UPDATE `{$p}orders`
        SET `status` = 'confirmed',
            `confirmed_at` = UTC_TIMESTAMP(),
            `updated_at` = UTC_TIMESTAMP()
      WHERE `id` = ? AND `status` = 'pending' AND `confirm_salt` = ?"
);
$stmt->execute([$parsed['id'], $salt]);

if ($stmt->rowCount() === 1) {
    $fresh = mongo_find_one('orders', ['_id' => $parsed['id']]);
    if (!$fresh) {
        fail('Order not found', 404);
    }
    ok(array_merge($payload($fresh), ['justConfirmed' => true, 'alreadyConfirmed' => false]));
}

// The order changed between our read and the update (or was already handled).
$fresh = mongo_find_one('orders', ['_id' => $parsed['id']]);
if (!$fresh) {
    fail('Invalid or expired confirmation link', 404);
}
$freshStatus = (string)($fresh['status'] ?? '');
if ($freshStatus === 'cancelled') {
    fail('This order has been cancelled and cannot be confirmed', 409);
}
if (in_array($freshStatus, ['confirmed', 'processing', 'completed'], true)) {
    ok($payload($fresh) + ['alreadyConfirmed' => true]);
}

fail('The order status changed. Please refresh the page and try again.', 409);
