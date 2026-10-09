<?php
/**
 * Mobi Boost — Admin coupon management.
 *
 * GET    /api/admin/coupons.php        list
 * POST   /api/admin/coupons.php        create
 * PUT    /api/admin/coupons.php?id=    update (or toggle active)
 * DELETE /api/admin/coupons.php?id=    delete
 */
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_admin();
    $rows = mongo_find('coupons', [], ['sort' => ['createdAt' => -1], 'limit' => 200]);
    ok(array_map(fn($c) => [
        'id' => (string)$c['_id'],
        'code' => $c['code'],
        'type' => $c['type'],
        'value' => (float)$c['value'],
        'minOrder' => (float)$c['minOrder'],
        'expiresAt' => !empty($c['expiresAt']) ? $c['expiresAt'] : null,
        'active' => !empty($c['active']),
        'createdAt' => $c['createdAt'],
    ], $rows));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    $coupon = validate_coupon_input(json_input());
    $coupon['createdAt'] = new MongoDB\BSON\UTCDateTime();
    $inserted = mongo_insert('coupons', $coupon);
    ok([
        'id' => (string)$inserted['_id'],
        'code' => $inserted['code'],
        'type' => $inserted['type'],
        'value' => (float)$inserted['value'],
        'minOrder' => (float)$inserted['minOrder'],
        'expiresAt' => !empty($inserted['expiresAt']) ? bson_clean($inserted['expiresAt']) : null,
        'active' => !empty($inserted['active']),
    ], 201);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    require_admin();
    $id = $_GET['id'] ?? '';
    $oid = object_id((string)$id);
    if (!$oid) {
        fail('Invalid coupon id', 400);
    }
    $existing = mongo_find_one('coupons', ['_id' => $oid]);
    if (!$existing) {
        fail('Coupon not found', 404);
    }

    $data = json_input();
    // Toggle-only shortcut used by the admin UI switch
    if (array_key_exists('active', $data) && count($data) === 1) {
        mongo_update('coupons', ['_id' => $oid], ['$set' => ['active' => (bool)$data['active']]]);
        ok(['active' => (bool)$data['active']]);
    }

    $coupon = validate_coupon_input($data);
    mongo_update('coupons', ['_id' => $oid], ['$set' => $coupon]);
    $updated = mongo_find_one('coupons', ['_id' => $oid]);
    ok([
        'id' => (string)$updated['_id'],
        'code' => $updated['code'],
        'type' => $updated['type'],
        'value' => (float)$updated['value'],
        'minOrder' => (float)$updated['minOrder'],
        'expiresAt' => !empty($updated['expiresAt']) ? bson_clean($updated['expiresAt']) : null,
        'active' => !empty($updated['active']),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_admin();
    $id = $_GET['id'] ?? '';
    $oid = object_id((string)$id);
    if (!$oid) {
        fail('Invalid coupon id', 400);
    }
    $deleted = mongo_delete('coupons', ['_id' => $oid]);
    if (!$deleted) {
        fail('Coupon not found', 404);
    }
    ok(['deleted' => true]);
}

fail('Method not allowed', 405);

/** @return array validated coupon document (without _id/createdAt) */
function validate_coupon_input(array $data): array {
    $errors = [];

    $code = strtoupper(trim(preg_replace('/\s+/', '', (string)($data['code'] ?? ''))));
    $type = in_array($data['type'] ?? '', ['percent', 'fixed'], true) ? $data['type'] : null;
    $value = (float)($data['value'] ?? 0);
    $minOrder = (float)($data['minOrder'] ?? 0);
    $active = array_key_exists('active', $data) ? (bool)$data['active'] : true;
    $expiresAt = null;

    if (!preg_match('/^[A-Z0-9]{3,20}$/', $code)) {
        $errors['code'] = 'Code: 3-20 letters/numbers';
    } else {
        $duplicate = mongo_find_one('coupons', ['code' => $code]);
        if ($duplicate) {
            $errors['code'] = 'This code already exists';
        }
    }
    if ($type === null) {
        $errors['type'] = 'Choose percent or fixed';
    }
    if ($type === 'percent' && ($value <= 0 || $value > 100)) {
        $errors['value'] = 'Percent must be 1-100';
    }
    if ($type === 'fixed' && $value <= 0) {
        $errors['value'] = 'Amount must be greater than zero';
    }
    if ($minOrder < 0) {
        $errors['minOrder'] = 'Minimum order cannot be negative';
    }

    $expiresRaw = trim((string)($data['expiresAt'] ?? ''));
    if ($expiresRaw !== '') {
        $ts = strtotime($expiresRaw);
        if ($ts === false) {
            $errors['expiresAt'] = 'Invalid date';
        } else {
            $expiresAt = new MongoDB\BSON\UTCDateTime($ts * 1000);
        }
    }

    if ($errors) {
        fail('Validation failed', 422, $errors);
    }

    return [
        'code' => $code,
        'type' => $type,
        'value' => round($value, 2),
        'minOrder' => round($minOrder, 2),
        'expiresAt' => $expiresAt,
        'active' => $active,
    ];
}
