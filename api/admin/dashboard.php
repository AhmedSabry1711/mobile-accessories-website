<?php
/**
 * Mobi Boost — Admin dashboard analytics.
 *
 * GET /api/admin/dashboard.php  (Bearer admin token required)
 */
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    fail('Method not allowed', 405);
}

require_admin();

// Revenue & profit from all non-cancelled orders
$orders = mongo_find('orders', ['status' => ['$ne' => 'cancelled']], [
    'sort' => ['createdAt' => -1],
    'limit' => 500,
]);

$revenue = 0.0;
$cost = 0.0;
$completed = 0;
$pending = 0;
$processing = 0;
$productStats = [];

foreach ($orders as $order) {
    $status = (string)$order['status'];
    if ($status === 'completed') {
        $completed++;
    } elseif ($status === 'pending') {
        $pending++;
    } elseif ($status === 'processing') {
        $processing++;
    }
    $revenue += (float)$order['total'];
    foreach ((array)$order['items'] as $item) {
        $lineCost = (float)$item['costPrice'] * (int)$item['qty'];
        $lineRevenue = (float)$item['price'] * (int)$item['qty'];
        $cost += $lineCost;
        $pid = (string)$item['productId'];
        if (!isset($productStats[$pid])) {
            $productStats[$pid] = [
                'productId' => $pid,
                'name' => (string)$item['name'],
                'sold' => 0,
                'revenue' => 0.0,
                'cost' => 0.0,
            ];
        }
        $productStats[$pid]['sold'] += (int)$item['qty'];
        $productStats[$pid]['revenue'] += $lineRevenue;
        $productStats[$pid]['cost'] += $lineCost;
    }
}

$profit = $revenue - $cost;

// Top products by profit
usort($productStats, fn($a, $b) => ($b['revenue'] - $b['cost']) <=> ($a['revenue'] - $a['cost']));
$topProducts = array_slice(array_map(fn($s) => [
    'productId' => $s['productId'],
    'name' => $s['name'],
    'sold' => $s['sold'],
    'revenue' => round($s['revenue'], 2),
    'profit' => round($s['revenue'] - $s['cost'], 2),
], $productStats), 0, 5);

// Store overview
$products = mongo_find('products', [], ['limit' => 1000]);
$lowStock = 0;
foreach ($products as $product) {
    $product = enrich_product($product);
    if ($product['stockTotal'] <= 5) {
        $lowStock++;
    }
}

$recentOrders = array_slice(array_map(fn($o) => [
    'id' => (string)$o['_id'],
    'orderNumber' => $o['orderNumber'],
    'customer' => $o['customer'],
    'total' => (float)$o['total'],
    'status' => $o['status'],
    'createdAt' => $o['createdAt'],
], $orders), 0, 8);

ok([
    'revenue' => round($revenue, 2),
    'profit' => round($profit, 2),
    'cost' => round($cost, 2),
    'ordersCount' => count($orders),
    'completedCount' => $completed,
    'pendingCount' => $pending,
    'processingCount' => $processing,
    'productsCount' => count($products),
    'reviewsCount' => mongo_count('reviews'),
    'couponsCount' => mongo_count('coupons', ['active' => true]),
    'lowStockCount' => $lowStock,
    'topProducts' => $topProducts,
    'recentOrders' => $recentOrders,
]);
