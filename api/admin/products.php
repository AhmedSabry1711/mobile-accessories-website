<?php
/**
 * Mobi Boost — Admin product management.
 *
 * GET    /api/admin/products.php?page=&q=   list
 * POST   /api/admin/products.php            create
 * PUT    /api/admin/products.php?id=        update
 * DELETE /api/admin/products.php?id=        delete
 */
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_admin();
    $q = trim((string)($_GET['q'] ?? ''));
    $limit = max(1, min(200, (int)($_GET['limit'] ?? 100)));
    $page = max(1, (int)($_GET['page'] ?? 1));

    $filter = [];
    if ($q !== '') {
        $regex = new MongoDB\BSON\Regex(preg_quote($q, '/'), 'i');
        $filter['$or'] = [['name' => $regex], ['category' => $regex], ['sku' => $regex]];
    }

    $total = mongo_count('products', $filter);
    $rows = mongo_find('products', $filter, [
        'sort' => ['createdAt' => -1],
        'limit' => $limit,
        'skip' => ($page - 1) * $limit,
    ]);

    ok([
        'items' => array_map('enrich_product', $rows),
        'total' => $total,
        'page' => $page,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_admin();
    $data = json_input();
    $product = validate_product($data);
    $product['createdAt'] = new MongoDB\BSON\UTCDateTime();
    $inserted = mongo_insert('products', $product);
    ok(enrich_product($inserted), 201);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    require_admin();
    $id = $_GET['id'] ?? '';
    $oid = object_id((string)$id);
    if (!$oid) {
        fail('Invalid product id', 400);
    }
    $existing = mongo_find_one('products', ['_id' => $oid]);
    if (!$existing) {
        fail('Product not found', 404);
    }
    $product = validate_product(json_input());
    mongo_update('products', ['_id' => $oid], ['$set' => $product]);
    $updated = mongo_find_one('products', ['_id' => $oid]);
    ok(enrich_product($updated));
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    require_admin();
    $id = $_GET['id'] ?? '';
    $oid = object_id((string)$id);
    if (!$oid) {
        fail('Invalid product id', 400);
    }
    $deleted = mongo_delete('products', ['_id' => $oid]);
    if (!$deleted) {
        fail('Product not found', 404);
    }
    ok(['deleted' => true]);
}

fail('Method not allowed', 405);

/**
 * Validate and normalize product input.
 * @return array ready-to-store product document (without _id/createdAt)
 */
function validate_product(array $data): array {
    $errors = [];

    $name = trim((string)($data['name'] ?? ''));
    $category = trim((string)($data['category'] ?? ''));
    $description = trim((string)($data['description'] ?? ''));
    $price = (float)($data['price'] ?? 0);
    $costPrice = (float)($data['costPrice'] ?? 0);
    $compareAtPrice = (float)($data['compareAtPrice'] ?? 0);
    $stock = (int)($data['stock'] ?? 0);
    $status = in_array($data['status'] ?? 'active', ['active', 'draft'], true)
        ? $data['status']
        : 'active';

    if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
        $errors['name'] = 'Name must be 2-120 characters';
    }
    if (mb_strlen($category) < 2 || mb_strlen($category) > 60) {
        $errors['category'] = 'Category must be 2-60 characters';
    }
    if (mb_strlen($description) < 10 || mb_strlen($description) > 5000) {
        $errors['description'] = 'Description must be 10-5000 characters';
    }
    if ($price <= 0) {
        $errors['price'] = 'Selling price must be greater than zero';
    }
    if ($costPrice < 0) {
        $errors['costPrice'] = 'Cost price cannot be negative';
    }
    if ($compareAtPrice < 0) {
        $errors['compareAtPrice'] = 'Compare-at price cannot be negative';
    }
    if ($compareAtPrice > 0 && $compareAtPrice <= $price) {
        $errors['compareAtPrice'] = 'Compare-at price must be higher than the selling price to show a discount';
    }

    $images = array_values(array_filter(array_map('strval', (array)($data['images'] ?? [])), fn($i) => $i !== ''));
    if (count($images) < 1 || count($images) > 10) {
        $errors['images'] = 'Add 1-10 product images';
    }

    // Variants: [{ model, colors: [{ name, hex, stock }] }]
    $variants = array_values((array)($data['variants'] ?? []));
    $hasVariants = !empty($variants);
    $cleanVariants = [];
    if ($hasVariants) {
        foreach ($variants as $i => $variant) {
            $model = trim((string)($variant['model'] ?? ''));
            if ($model === '') {
                $errors["variants[$i]"] = 'Variant model name is required';
                continue;
            }
            $colors = array_values((array)($variant['colors'] ?? []));
            if (empty($colors)) {
                $errors["variants[$i].colors"] = 'Add at least one color option';
                continue;
            }
            $cleanColors = [];
            foreach ($colors as $j => $color) {
                $cname = trim((string)($color['name'] ?? ''));
                $hex = trim((string)($color['hex'] ?? '#000000'));
                $cstock = (int)($color['stock'] ?? 0);
                if ($cname === '') {
                    $errors["variants[$i].colors[$j]"] = 'Color name is required';
                    continue;
                }
                if (!preg_match('/^#([a-f0-9]{3}|[a-f0-9]{6})$/i', $hex)) {
                    $hex = '#000000';
                }
                if ($cstock < 0) {
                    $cstock = 0;
                }
                $cleanColors[] = ['name' => $cname, 'hex' => strtolower($hex), 'stock' => $cstock];
            }
            if (empty($cleanColors)) {
                continue;
            }
            $cleanVariants[] = ['model' => $model, 'colors' => $cleanColors];
        }
        if (empty($cleanVariants)) {
            $errors['variants'] = 'Configure at least one variant with colors';
        }
    }

    if ($errors) {
        fail('Validation failed', 422, $errors);
    }

    $product = [
        'name' => $name,
        'category' => $category,
        'description' => $description,
        'price' => round($price, 2),
        'costPrice' => round($costPrice, 2),
        'compareAtPrice' => round($compareAtPrice, 2),
        'images' => $images,
        'status' => $status,
    ];

    if ($hasVariants) {
        $product['variants'] = $cleanVariants;
        unset($product['stock']);
    } else {
        $product['stock'] = max(0, $stock);
        $product['variants'] = [];
    }

    return $product;
}
