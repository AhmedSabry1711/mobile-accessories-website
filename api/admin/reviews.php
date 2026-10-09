<?php
/**
 * Mobi Boost — Admin review moderation.
 *
 * GET    /api/admin/reviews.php?product_id=   list (newest first)
 * DELETE /api/admin/reviews.php?id=             delete
 */
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_admin();

    $filter = [];
    $productId = $_GET['product_id'] ?? '';
    if ($productId !== '') {
        $filter['productId'] = (string)$productId;
    }

    $rows = mongo_find('reviews', $filter, [
        'sort' => ['createdAt' => -1],
        'limit' => 200,
    ]);

    ok(array_map(fn($r) => [
        'id' => (string)$r['_id'],
        'productId' => $r['productId'],
        'userName' => $r['userName'],
        'rating' => (int)$r['rating'],
        'comment' => $r['comment'],
        'createdAt' => $r['createdAt'],
    ], $rows));
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_admin();
    $id = $_GET['id'] ?? '';
    $oid = object_id((string)$id);
    if (!$oid) {
        fail('Invalid review id', 400);
    }
    $deleted = mongo_delete('reviews', ['_id' => $oid]);
    if (!$deleted) {
        fail('Review not found', 404);
    }
    ok(['deleted' => true]);
}

fail('Method not allowed', 405);
