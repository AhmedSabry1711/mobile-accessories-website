<?php
/**
 * Mobi Boost — Products API.
 *
 * GET /api/products.php?id=<id>            product detail
 * GET /api/products.php?related_to=<id>    products from the same category
 * GET /api/products.php?filter=all|sale|new&category=&q=&limit=&page=
 */
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    fail('Method not allowed', 405);
}

$id = $_GET['id'] ?? null;
$relatedTo = $_GET['related_to'] ?? null;

// ---- Single product -------------------------------------------------------
if ($id !== null && $id !== '') {
    $oid = object_id((string)$id);
    if (!$oid) {
        fail('Invalid product id', 400);
    }
    $product = mongo_find_one('products', ['_id' => $oid, 'status' => 'active']);
    if (!$product) {
        fail('Product not found', 404);
    }
    ok(enrich_product($product));
}

// ---- Related products (same category, exclude self) -----------------------
if ($relatedTo !== null && $relatedTo !== '') {
    $oid = object_id((string)$relatedTo);
    if (!$oid) {
        fail('Invalid product id', 400);
    }
    $source = mongo_find_one('products', ['_id' => $oid, 'status' => 'active']);
    if (!$source || empty($source['category'])) {
        ok([]);
    }
    $rows = mongo_find(
        'products',
        ['_id' => ['$ne' => $oid], 'status' => 'active', 'category' => $source['category']],
        ['sort' => ['createdAt' => -1], 'limit' => 8]
    );
    ok(array_map('enrich_product', $rows));
}

// ---- List / search / filters ----------------------------------------------
$filter = (string)($_GET['filter'] ?? 'all');
$category = trim((string)($_GET['category'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));
$limit = max(1, min(100, (int)($_GET['limit'] ?? $CONFIG['pageSize'])));
$page = max(1, (int)($_GET['page'] ?? 1));

$query = ['status' => 'active'];

if ($filter === 'sale') {
    $query = [
        'status' => 'active',
        'compareAtPrice' => ['$gt' => 0],
        '$expr' => ['$gt' => ['$compareAtPrice', '$price']],
    ];
}

if ($category !== '') {
    $query['category'] = $category;
}

if ($q !== '') {
    $regex = new MongoDB\BSON\Regex(preg_quote($q, '/'), 'i');
    $query['$or'] = [
        ['name' => $regex],
        ['description' => $regex],
        ['category' => $regex],
    ];
}

$sort = ['createdAt' => -1];
if ($filter === 'sale') {
    // Biggest savings first
    $sort = ['compareAtPrice' => -1];
}

$total = mongo_count('products', $query);
$rows = mongo_find('products', $query, [
    'sort' => $sort,
    'limit' => $limit,
    'skip' => ($page - 1) * $limit,
]);

ok([
    'items' => array_map('enrich_product', $rows),
    'total' => $total,
    'page' => $page,
    'limit' => $limit,
]);
