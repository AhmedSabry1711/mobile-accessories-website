<?php
/**
 * Mobi Boost — Reviews API.
 *
 * GET  /api/reviews.php?product_id=<id>   list + average rating
 * POST /api/reviews.php                   { product_id, userName, rating, comment }
 */
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

// product_id arrives as ?product_id= (GET), form-encoded (POST), or inside the
// JSON body — which is what the storefront submits ("product_id").
$body = $method === 'POST' ? json_input() : [];
$productId = $_GET['product_id']
    ?? (string)($_POST['product_id'] ?? $body['product_id'] ?? $body['productId'] ?? '');
$oid = object_id((string)$productId);
if (!$oid) {
    fail('Invalid product id', 400);
}

$product = mongo_find_one('products', ['_id' => $oid, 'status' => 'active']);
if (!$product) {
    fail('Product not found', 404);
}

// Raw documents carry _id (not an `id` alias) — use its string form so reviews
// match the seeded data (seed.php stores productId = (string)$product['_id']).
$productId = (string)$product['_id'];

if ($method === 'GET') {
    $reviews = mongo_find('reviews', ['productId' => $productId], [
        'sort' => ['createdAt' => -1],
        'limit' => 100,
    ]);
    $ratings = array_map(fn($r) => (int)$r['rating'], $reviews);
    $average = $ratings ? round(array_sum($ratings) / count($ratings), 1) : 0.0;
    ok([
        'average' => $average,
        'count' => count($reviews),
        'reviews' => array_map(fn($r) => [
            'id' => (string)$r['_id'],
            'userName' => $r['userName'],
            'rating' => (int)$r['rating'],
            'comment' => $r['comment'],
            'createdAt' => $r['createdAt'],
        ], $reviews),
    ]);
}

if ($method === 'POST') {
    $data = $body;
    $userName = trim((string)($data['userName'] ?? ''));
    $rating = (int)($data['rating'] ?? 0);
    $comment = trim((string)($data['comment'] ?? ''));

    $errors = [];
    if (mb_strlen($userName) < 2 || mb_strlen($userName) > 50) {
        $errors['userName'] = 'Name must be 2-50 characters';
    }
    if ($rating < 1 || $rating > 5) {
        $errors['rating'] = 'Rating must be between 1 and 5';
    }
    if (mb_strlen($comment) < 3 || mb_strlen($comment) > 1000) {
        $errors['comment'] = 'Comment must be 3-1000 characters';
    }
    if ($errors) {
        fail('Validation failed', 422, $errors);
    }

    $review = mongo_insert('reviews', [
        'productId' => $productId,
        'userName' => $userName,
        'rating' => $rating,
        'comment' => $comment,
        'createdAt' => new MongoDB\BSON\UTCDateTime(),
    ]);

    ok([
        'id' => (string)$review['_id'],
        'userName' => $review['userName'],
        'rating' => (int)$review['rating'],
        'comment' => $review['comment'],
        'createdAt' => bson_clean($review['createdAt']),
    ], 201);
}

fail('Method not allowed', 405);
