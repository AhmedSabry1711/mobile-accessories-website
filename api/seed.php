<?php
/**
 * Mobi Boost — Demo data seeder (CLI only).
 *
 * Usage:  php api/seed.php
 *
 * Creates: store settings, admin account (admin / admin123),
 * coupons, hero ads, product catalog with variants, and reviews.
 * Product images are generated locally as SVG files in uploads/seed/.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Seeding is CLI-only']);
    exit(1);
}

require __DIR__ . '/_bootstrap.php';

$CONFIG = $GLOBALS['CONFIG'];

// ---------------------------------------------------------------- helpers
function seed_image(string $label, string $c1, string $c2): string {
    global $CONFIG;
    $dir = $CONFIG['uploadDir'] . '/seed';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($label))) . '.svg';
    $path = $dir . '/' . $slug;

    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="800" viewBox="0 0 800 800">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="$c1"/>
      <stop offset="1" stop-color="$c2"/>
    </linearGradient>
  </defs>
  <rect width="800" height="800" fill="url(#g)"/>
  <circle cx="400" cy="330" r="150" fill="rgba(255,255,255,0.14)"/>
  <circle cx="400" cy="330" r="105" fill="rgba(255,255,255,0.18)"/>
  <text x="400" y="360" font-family="Segoe UI, Arial, sans-serif" font-size="120"
        text-anchor="middle" fill="#ffffff">&#9742;</text>
  <text x="400" y="620" font-family="Segoe UI, Arial, sans-serif" font-size="52"
        text-anchor="middle" fill="#ffffff" font-weight="600"><![CDATA[$label]]></text>
  <text x="400" y="680" font-family="Segoe UI, Arial, sans-serif" font-size="30"
        text-anchor="middle" fill="rgba(255,255,255,0.85)">Mobi Boost</text>
</svg>
SVG;

    file_put_contents($path, $svg);
    return '/uploads/seed/' . $slug;
}

function seed_product(string $name, string $category, string $desc, float $price, float $cost, float $compare, array $images, array $variants = [], int $stock = 0): void {
    mongo_insert('products', [
        'name' => $name,
        'category' => $category,
        'description' => $desc,
        'price' => $price,
        'costPrice' => $cost,
        'compareAtPrice' => $compare,
        'images' => $images,
        'variants' => $variants,
        'stock' => $stock,
        'status' => 'active',
        'createdAt' => new MongoDB\BSON\UTCDateTime(),
    ]);
}

// ---------------------------------------------------------------- checks
$adminExists = mongo_find_one('users', ['username' => 'admin']);
$productCount = mongo_count('products');

if ($productCount > 0) {
    fwrite(STDOUT, "Catalog already seeded ($productCount products). Nothing to do.\n");
    exit(0);
}

// ---------------------------------------------------------------- settings
save_settings([
    'whatsapp' => '+15550123456',
    'shipping' => ['enabled' => true, 'flatRate' => 4.99, 'freeOver' => 50.0],
    'currency' => ['symbol' => '$', 'code' => 'USD'],
    'categories' => ['Phone Cases', 'Chargers', 'Screen Protectors', 'Earbuds', 'Holders', 'Cables'],
]);

// ---------------------------------------------------------------- admin
if (!$adminExists) {
    mongo_insert('users', [
        'username' => 'admin',
        'passwordHash' => password_hash('admin123', PASSWORD_DEFAULT),
        'createdAt' => new MongoDB\BSON\UTCDateTime(),
    ]);
}

// ---------------------------------------------------------------- coupons
mongo_insert('coupons', [
    'code' => 'WELCOME10', 'type' => 'percent', 'value' => 10.0,
    'minOrder' => 0.0, 'expiresAt' => null, 'active' => true,
    'createdAt' => new MongoDB\BSON\UTCDateTime(),
]);
mongo_insert('coupons', [
    'code' => 'SAVE20', 'type' => 'fixed', 'value' => 20.0,
    'minOrder' => 80.0,
    'expiresAt' => new MongoDB\BSON\UTCDateTime((time() + 30 * 86400) * 1000),
    'active' => true,
    'createdAt' => new MongoDB\BSON\UTCDateTime(),
]);

// ---------------------------------------------------------------- ads
$adMedia1 = seed_image('Summer Sale — Up to 40% Off', '#4f46e5', '#7c3aed');
$adMedia2 = seed_image('Free Shipping Over $50', '#059669', '#10b981');
$adMedia3 = seed_image('New iPhone 17 Collection', '#d97706', '#f59e0b');

mongo_insert('ads', [
    'title' => 'Summer Sale — Up to 40% Off', 'mediaUrl' => $adMedia1,
    'type' => 'image', 'linkUrl' => '/shop?filter=sale', 'order' => 1, 'active' => true,
    'createdAt' => new MongoDB\BSON\UTCDateTime(),
]);
mongo_insert('ads', [
    'title' => 'Free Shipping Over $50', 'mediaUrl' => $adMedia2,
    'type' => 'image', 'linkUrl' => '/shop', 'order' => 2, 'active' => true,
    'createdAt' => new MongoDB\BSON\UTCDateTime(),
]);
mongo_insert('ads', [
    'title' => 'New iPhone 17 Collection', 'mediaUrl' => $adMedia3,
    'type' => 'image', 'linkUrl' => '/shop?category=Phone%20Cases', 'order' => 3, 'active' => true,
    'createdAt' => new MongoDB\BSON\UTCDateTime(),
]);

// ---------------------------------------------------------------- products
$caseModels = ['iPhone 17 Pro Max', 'iPhone 16 Pro', 'iPhone 15', 'Samsung Galaxy S25 Ultra', 'Galaxy A55'];

function variant_set(array $models, array $colors): array {
    $variants = [];
    foreach ($models as $model) {
        $modelColors = [];
        foreach ($colors as $name => $hex) {
            $modelColors[] = ['name' => $name, 'hex' => $hex, 'stock' => random_int(4, 40)];
        }
        $variants[] = ['model' => $model, 'colors' => $modelColors];
    }
    return $variants;
}

$imgCase1 = [seed_image('Armor Bumper Case', '#1e293b', '#4f46e5'), seed_image('Armor Bumper Case', '#312e81', '#7c3aed')];
$imgCase2 = [seed_image('Clear MagSafe Case', '#0f172a', '#0ea5e9')];
$imgProtector = [seed_image('Tempered Glass Protector', '#334155', '#64748b')];
$imgCharger = [seed_image('65W GaN Fast Charger', '#7c2d12', '#ea580c')];
$imgCable = [seed_image('Braided USB-C Cable', '#14532d', '#22c55e')];
$imgEarbuds = [seed_image('AirSound Pro Earbuds', '#4c1d95', '#a78bfa')];
$imgHolder = [seed_image('Magnetic Car Mount', '#0c4a6e', '#0284c7')];
$imgStand = [seed_image('Adjustable Phone Stand', '#7f1d1d', '#ef4444')];
$imgRing = [seed_image('Glow Ring Light', '#831843', '#ec4899')];
$imgBattery = [seed_image('10000mAh Power Bank', '#3f3f46', '#71717a')];

seed_product(
    'Armor Shockproof Bumper Case', 'Phone Cases',
    'Military-grade drop protection with reinforced corners, raised bezel for screen and camera safety, and a non-slip grip finish. Precision cutouts keep every port and button fully accessible.',
    19.99, 6.40, 34.99, $imgCase1,
    variant_set($caseModels, ['Black' => '#111827', 'Midnight Blue' => '#1e3a8a', 'Forest' => '#14532d', 'Crimson' => '#991b1b'])
);

seed_product(
    'Clear MagSafe Compatible Case', 'Phone Cases',
    'Crystal-clear PC back panel that keeps your phone looking brand new, with magnetic ring alignment for MagSafe chargers and wallets. Yellowing-resistant coating.',
    14.99, 4.20, 24.99, $imgCase2,
    variant_set(['iPhone 17 Pro Max', 'iPhone 16 Pro', 'iPhone 15', 'Samsung Galaxy S25 Ultra'], ['Clear' => '#e2e8f0'])
);

seed_product(
    'Tempered Glass Screen Protector (2-Pack)', 'Screen Protectors',
    '9H hardness, 99.9% clarity, oleophobic anti-fingerprint coating. Easy alignment frame included. Compatible with most cases.',
    9.99, 2.30, 17.99, $imgProtector,
    variant_set($caseModels, ['Standard' => '#94a3b8'])
);

seed_product(
    '65W GaN Fast Charger', 'Chargers',
    'Compact GaN II technology charges a laptop and phone at once. USB-C + USB-A ports, foldable prongs, over-current and temperature protection built in.',
    29.99, 11.50, 49.99, $imgCharger, [], 64
);

seed_product(
    'Braided USB-C to Lightning Cable (2m)', 'Cables',
    'Double-braided nylon jacket rated for 30,000+ bends. Fast data sync and charging up to 100W. Lifetime warranty.',
    12.99, 3.80, 21.99, $imgCable, [], 120
);

seed_product(
    'AirSound Pro Wireless Earbuds', 'Earbuds',
    'Active noise cancellation, 36-hour total battery life with the charging case, IPX5 sweat resistance, and crystal-call microphones.',
    45.99, 18.75, 79.99, $imgEarbuds, [], 38
);

seed_product(
    'Magnetic Car Phone Mount', 'Holders',
    'Strong N52 magnets hold any phone on the vent, even with a case. One-handed mount, 360-degree rotation, no messy pads.',
    16.99, 5.60, 27.99, $imgHolder, [], 52
);

seed_product(
    'Adjustable Desk Phone Stand', 'Holders',
    'Foldable aluminium stand for phones and tablets. Watch, video-call, or read hands-free. Fits devices from 4.7 to 12.9 inches.',
    11.99, 3.90, 19.99, $imgStand, [], 87
);

seed_product(
    'Clip-On Selfie Ring Light', 'Accessories',
    '360-degree adjustable ring light with three color temperatures and six brightness levels. USB rechargeable, clips onto any phone.',
    13.99, 4.60, 22.99, $imgRing, [], 61
);

seed_product(
    '10000mAh Slim Power Bank', 'Chargers',
    'Pocket-sized 10000mAh battery with 22.5W fast charging output. LED charge indicator, dual output ports, airline-safe cells.',
    24.99, 9.90, 39.99, $imgBattery, [], 45
);

// ---------------------------------------------------------------- reviews
$reviews = [
    ['iPhone 16 Pro', 'Sarah M.', 5, 'Fits perfectly, the cutouts are exact and it survived a drop off my desk. Love the grip.'],
    ['iPhone 16 Pro', 'Daniel K.', 4, 'Great quality for the price. Shipping took 4 days, arrived well packed.'],
    ['Samsung Galaxy S25 Ultra', 'Priya R.', 5, 'The camera cutout is perfect. Looks premium and feels solid.'],
    ['iPhone 15', 'Marco T.', 5, 'Bought two, one for me and one for my wife. Both are excellent.'],
    ['iPhone 17 Pro Max', 'Aisha L.', 4, 'Nice case, color is exactly like the photos. Slightly firm buttons but fine.'],
];

$products = mongo_find('products', [], ['limit' => 20]);
foreach ($reviews as $i => [$model, $user, $rating, $comment]) {
    $product = $products[$i % count($products)];
    mongo_insert('reviews', [
        'productId' => (string)$product['_id'],
        'userName' => $user,
        'rating' => $rating,
        'comment' => $comment . " ($model)",
        'createdAt' => new MongoDB\BSON\UTCDateTime(),
    ]);
}

fwrite(STDOUT, "Mobi Boost seeded successfully.\n");
fwrite(STDOUT, "Admin login: admin / admin123  (change this after first login!)\n");
fwrite(STDOUT, "Coupons: WELCOME10 (10% off), SAVE20 ($20 off orders $80+)\n");
