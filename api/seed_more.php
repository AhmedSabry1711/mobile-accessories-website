<?php
require __DIR__ . '/_bootstrap.php';

$p = db_prefix();
$db = db();

// Add ads
$ads = [
  ['title' => 'خصم على كفرات الموبايل', 'mediaUrl' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=1200&h=400&fit=crop', 'type' => 'image', 'linkUrl' => '/products.html', 'order' => 0, 'active' => 1],
  ['title' => 'إكسسوارات أصلية بأفضل الأسعار', 'mediaUrl' => 'https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?w=1200&h=400&fit=crop', 'type' => 'image', 'linkUrl' => '/products.html', 'order' => 1, 'active' => 1],
  ['title' => 'شحن سريع لجميع المحافظات', 'mediaUrl' => 'https://images.unsplash.com/photo-1556656793-08538906a9f8?w=1200&h=400&fit=crop', 'type' => 'image', 'linkUrl' => '/products.html', 'order' => 2, 'active' => 1],
];

foreach($ads as $a){
  $doc = [
    '_id' => new_id(),
    'title' => $a['title'],
    'mediaUrl' => $a['mediaUrl'],
    'type' => $a['type'],
    'linkUrl' => $a['linkUrl'],
    'order' => $a['order'],
    'active' => $a['active'],
    'createdAt' => gmdate('Y-m-d\TH:i:s\Z'),
  ];
  db_replace('ads', $doc['_id'], $doc);
  echo "AD OK: ".$doc['title']."\n";
}

// Add more products
$prods = [
  [
    '_id' => new_id(),
    'name' => 'جراب شفاف مقاوم للصدمات',
    'category' => 'Phone Cases',
    'description' => 'جراب شفاف عالي الجودة يحمي هاتفك من الصدمات والخدوش',
    'price' => 150,
    'costPrice' => 70,
    'compareAtPrice' => 199,
    'images' => ['https://images.unsplash.com/photo-1601784551446-20c9e07cdbdb?w=800&h=800&fit=crop','https://images.unsplash.com/photo-1556656793-08538906a9f8?w=800&h=800&fit=crop'],
    'variants' => [
      ['model' => 'iPhone 14/15/16', 'colors' => [
        ['name' => 'شفاف', 'hex' => '#ffffff', 'stock' => 50, 'image' => 'https://images.unsplash.com/photo-1601784551446-20c9e07cdbdb?w=600&h=600&fit=crop'],
        ['name' => 'أسود', 'hex' => '#000000', 'stock' => 40, 'image' => 'https://images.unsplash.com/photo-1556656793-08538906a9f8?w=600&h=600&fit=crop'],
      ]],
      ['model' => 'Samsung S23/S24', 'colors' => [
        ['name' => 'شفاف', 'hex' => '#ffffff', 'stock' => 30, 'image' => 'https://images.unsplash.com/photo-1601784551446-20c9e07cdbdb?w=600&h=600&fit=crop'],
      ]],
    ],
    'stock' => 120,
    'status' => 'active',
    'createdAt' => gmdate('Y-m-d\TH:i:s\Z'),
  ],
  [
    '_id' => new_id(),
    'name' => 'حامل موبايل للسيارة مغناطيسي',
    'category' => 'Accessories',
    'description' => 'حامل مغناطيسي قوي يناسب جميع السيارات وسهل التركيب',
    'price' => 95,
    'costPrice' => 40,
    'compareAtPrice' => 149,
    'images' => ['https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?w=800&h=800&fit=crop','https://images.unsplash.com/photo-1556656793-08538906a9f8?w=800&h=800&fit=crop'],
    'variants' => [
      ['model' => 'عام', 'colors' => [
        ['name' => 'أسود', 'hex' => '#000000', 'stock' => 80, 'image' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?w=600&h=600&fit=crop'],
        ['name' => 'فضي', 'hex' => '#c0c0c0', 'stock' => 60, 'image' => 'https://images.unsplash.com/photo-1556656793-08538906a9f8?w=600&h=600&fit=crop'],
      ]],
    ],
    'stock' => 140,
    'status' => 'active',
    'createdAt' => gmdate('Y-m-d\TH:i:s\Z'),
  ],
  [
    '_id' => new_id(),
    'name' => 'سماعات بلوتوث TWS',
    'category' => 'Audio',
    'description' => 'سماعات لاسلكية بجودة صوت عالية ومدة بطارية طويلة',
    'price' => 450,
    'costPrice' => 200,
    'compareAtPrice' => 650,
    'images' => ['https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=800&h=800&fit=crop','https://images.unsplash.com/photo-1484704849700-f032a568e944?w=800&h=800&fit=crop'],
    'variants' => [
      ['model' => 'قياسي', 'colors' => [
        ['name' => 'أبيض', 'hex' => '#ffffff', 'stock' => 40, 'image' => 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=600&h=600&fit=crop'],
        ['name' => 'أسود', 'hex' => '#000000', 'stock' => 35, 'image' => 'https://images.unsplash.com/photo-1484704849700-f032a568e944?w=600&h=600&fit=crop'],
      ]],
    ],
    'stock' => 75,
    'status' => 'active',
    'createdAt' => gmdate('Y-m-d\TH:i:s\Z'),
  ],
  [
    '_id' => new_id(),
    'name' => 'كابل شحن سريع Type-C',
    'category' => 'Cables',
    'description' => 'كابل شحن سريع 3A يدعم الشحن السريع ونقل البيانات',
    'price' => 49,
    'costPrice' => 15,
    'compareAtPrice' => 79,
    'images' => ['https://images.unsplash.com/photo-1583863788434-e58a36330cf0?w=800&h=800&fit=crop'],
    'variants' => [
      ['model' => '1 متر', 'colors' => [
        ['name' => 'أبيض', 'hex' => '#ffffff', 'stock' => 100, 'image' => 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?w=600&h=600&fit=crop'],
        ['name' => 'أسود', 'hex' => '#000000', 'stock' => 100, 'image' => 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?w=600&h=600&fit=crop'],
      ]],
      ['model' => '2 متر', 'colors' => [
        ['name' => 'أسود', 'hex' => '#000000', 'stock' => 80, 'image' => 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?w=600&h=600&fit=crop'],
      ]],
    ],
    'stock' => 280,
    'status' => 'active',
    'createdAt' => gmdate('Y-m-d\TH:i:s\Z'),
  ],
];

foreach($prods as $pdoc){
  db_replace('products', $pdoc['_id'], $pdoc);
  echo "PROD OK: ".$pdoc['name']."\n";
}

echo "ALL DONE\n";
