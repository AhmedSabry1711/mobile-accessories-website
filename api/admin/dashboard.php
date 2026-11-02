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

// Revenue & profit from all non-cancelled orders.
//
// Two rules keep the numbers honest:
//   • profit is earned on the goods, net of the coupon the shopper used;
//   • the shipping fee is money collected on behalf of the courier, so it is
//     reported separately instead of being counted as profit.
// Each line's revenue is scaled by the order's discount ratio, so the top
// products table adds up to the store total.
$orders = mongo_find('orders', ['status' => ['$ne' => 'cancelled']], [
    'sort' => ['createdAt' => -1],
    'limit' => 500,
]);

$revenue = 0.0;      // goods only, after discounts
$cost = 0.0;
$discountTotal = 0.0;
$shippingCollected = 0.0;
$completed = 0;
$pending = 0;
$confirmed = 0;
$processing = 0;
$productStats = [];

foreach ($orders as $order) {
    $status = (string)$order['status'];
    if ($status === 'completed') {
        $completed++;
    } elseif ($status === 'pending') {
        $pending++;
    } elseif ($status === 'confirmed') {
        $confirmed++;
    } elseif ($status === 'processing') {
        $processing++;
    }

    $subtotal = (float)($order['subtotal'] ?? 0);
    $discount = min((float)($order['discount'] ?? 0), $subtotal);
    $paidForGoods = max(0.0, $subtotal - $discount);
    $discountTotal += $discount;
    $shippingCollected += (float)($order['shippingFee'] ?? 0);
    $revenue += $paidForGoods;

    // Coupons apply to the whole order, so spread the reduction across the
    // lines proportionally to their price.
    $ratio = $subtotal > 0 ? $paidForGoods / $subtotal : 0.0;

    foreach ((array)$order['items'] as $item) {
        $qty = (int)($item['qty'] ?? 0);
        $lineCost = (float)($item['costPrice'] ?? 0) * $qty;
        $lineRevenue = (float)($item['price'] ?? 0) * $qty * $ratio;
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
        $productStats[$pid]['sold'] += $qty;
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
    'discountTotal' => round($discountTotal, 2),
    'shippingCollected' => round($shippingCollected, 2),
    'collectedTotal' => round($revenue + $shippingCollected, 2),
    'ordersCount' => count($orders),
    'completedCount' => $completed,
    'pendingCount' => $pending,
    'confirmedCount' => $confirmed,
    'processingCount' => $processing,
    'productsCount' => count($products),
    'reviewsCount' => mongo_count('reviews'),
    'couponsCount' => mongo_count('coupons', ['active' => true]),
    'lowStockCount' => $lowStock,
    'topProducts' => $topProducts,
    'recentOrders' => $recentOrders,
]);
