<?php
/**
 * Mobi Boost — Admin hero-ads management (videos / GIFs for the ticker).
 *
 * GET    /api/admin/ads.php        list
 * POST   /api/admin/ads.php        create
 * PUT    /api/admin/ads.php?id=    update (or toggle active)
 * DELETE /api/admin/ads.php?id=    delete
 */
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_admin();
    $rows = mongo_find('ads', [], ['sort' => ['order' => 1, 'createdAt' => 1], 'limit' => 100]);
    ok(array_map(fn($a) => [
        'id' => (string)$a['_id'],
        'title' => $a['title'],
        'mediaUrl' => $a['mediaUrl'],
        'type' => $a['type'],
        'linkUrl' => (string)($a['linkUrl'] ?? ''),
        'order' => (int)$a['order'],
        'active' => !empty($a['active']),
        'createdAt' => $a['createdAt'],
    ], $rows));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    $ad = validate_ad_input(json_input());
    $ad['createdAt'] = new MongoDB\BSON\UTCDateTime();
    $inserted = mongo_insert('ads', $ad);
    ok([
        'id' => (string)$inserted['_id'],
        'title' => $inserted['title'],
        'mediaUrl' => $inserted['mediaUrl'],
        'type' => $inserted['type'],
        'linkUrl' => $inserted['linkUrl'],
        'order' => (int)$inserted['order'],
        'active' => !empty($inserted['active']),
    ], 201);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    require_admin();
    $id = $_GET['id'] ?? '';
    $oid = object_id((string)$id);
    if (!$oid) {
        fail('Invalid ad id', 400);
    }
    $existing = mongo_find_one('ads', ['_id' => $oid]);
    if (!$existing) {
        fail('Ad not found', 404);
    }

    $data = json_input();
    // Toggle-only shortcut used by the admin UI switch
    if (array_key_exists('active', $data) && count($data) === 1) {
        mongo_update('ads', ['_id' => $oid], ['$set' => ['active' => (bool)$data['active']]]);
        ok(['active' => (bool)$data['active']]);
    }

    $ad = validate_ad_input($data);
    mongo_update('ads', ['_id' => $oid], ['$set' => $ad]);
    $updated = mongo_find_one('ads', ['_id' => $oid]);
    ok([
        'id' => (string)$updated['_id'],
        'title' => $updated['title'],
        'mediaUrl' => $updated['mediaUrl'],
        'type' => $updated['type'],
        'linkUrl' => $updated['linkUrl'],
        'order' => (int)$updated['order'],
        'active' => !empty($updated['active']),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_admin();
    $id = $_GET['id'] ?? '';
    $oid = object_id((string)$id);
    if (!$oid) {
        fail('Invalid ad id', 400);
    }
    $deleted = mongo_delete('ads', ['_id' => $oid]);
    if (!$deleted) {
        fail('Ad not found', 404);
    }
    ok(['deleted' => true]);
}

fail('Method not allowed', 405);

/** @return array validated ad document (without _id/createdAt) */
function validate_ad_input(array $data): array {
    $errors = [];

    $title = trim((string)($data['title'] ?? ''));
    $mediaUrl = trim((string)($data['mediaUrl'] ?? ''));
    $linkUrl = trim((string)($data['linkUrl'] ?? ''));
    $type = in_array($data['type'] ?? '', ['image', 'video'], true) ? $data['type'] : null;
    $order = (int)($data['order'] ?? 0);
    $active = array_key_exists('active', $data) ? (bool)$data['active'] : true;

    if (mb_strlen($title) < 2 || mb_strlen($title) > 80) {
        $errors['title'] = 'Title must be 2-80 characters';
    }
    // Accept a relative /uploads/... path OR a full http(s):// URL. (The old
    // pattern required the string to start with "/", so pasted https:// links
    // were always rejected — see "Upload a media file or provide ..." error.)
    if (!preg_match('~^(/uploads/|/seed/|https?://)~', $mediaUrl)) {
        $errors['mediaUrl'] = 'Upload a media file or provide an /uploads/ or http(s) URL';
    }
    if ($type === null) {
        $errors['type'] = 'Choose image (GIF/PNG) or video (MP4/WebM)';
    }
    if ($linkUrl !== '' && !preg_match('~^(https?://|/)~', $linkUrl)) {
        $errors['linkUrl'] = 'Link must start with http(s):// or /';
    }

    if ($errors) {
        fail('Validation failed', 422, $errors);
    }

    return [
        'title' => $title,
        'mediaUrl' => $mediaUrl,
        'type' => $type,
        'linkUrl' => $linkUrl,
        'order' => max(0, $order),
        'active' => $active,
    ];
}
