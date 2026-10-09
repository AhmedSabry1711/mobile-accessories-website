<?php
/**
 * Mobi Boost — Coupon validation API.
 *
 * POST /api/coupons.php  { code, subtotal }
 */
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed', 405);
}

$data = json_input();
$code = (string)($data['code'] ?? '');
$subtotal = (float)($data['subtotal'] ?? 0);

if ($subtotal < 0) {
    fail('Invalid subtotal', 400);
}

try {
    ok(validate_coupon($code, $subtotal));
} catch (RuntimeException $e) {
    fail($e->getMessage(), 400);
}
