<?php
require __DIR__ . '/_bootstrap.php';

$p = db_prefix();
$db = db();

$db->exec("SET FOREIGN_KEY_CHECKS=0");
$db->exec("TRUNCATE TABLE `{$p}product_variant_colors`");
$db->exec("TRUNCATE TABLE `{$p}product_variants`");
$db->exec("TRUNCATE TABLE `{$p}product_images`");
$db->exec("TRUNCATE TABLE `{$p}products`");
$db->exec("SET FOREIGN_KEY_CHECKS=1");

$cats = ['Phone Cases','Chargers','Cables','Earbuds'];

function img($q,$seed){ return "https://images.unsplash.com/photo-$seed?w=800&h=800&fit=crop&q=80"; }

// Phone Cases
$cases = [
  ['name'=>'جراب سيليكون ناعم','desc'=>'جراب سيليكون مرن يحمي من الصدمات','price'=>129,'old'=>179,'imgs'=>['1511707171634-5f897ff02aa9','1605236453806-b3ebc01d68e2','1556656793-08538906a9f8']],
  ['name'=>'جراب TPU شفاف','desc'=>'شفاف مقاوم للخدش مع حماية زوايا','price'=>149,'old'=>199,'imgs'=>['1601784551446-20c9e07cdbdb','1512941937669-90a1b58e7e9c','1616348436165-f650ad4e69a5']],
  ['name'=>'جراب جلد طبيعي','desc'=>'جراب جلد فاخر بإحساس ناعم','price'=>249,'old'=>349,'imgs'=>['1527864550417-7fd91fc51a46','1553062407-98eeb64c6a62','1523275335684-37898b6baf30']],
  ['name'=>'جراب مقاوم للصدمات','desc'=>'دفاع مزدوج مع حماية كاملة','price'=>199,'old'=>279,'imgs'=>['1580910051074-3eb694886505','1505740420928-5e560c06d30e','1519389950473-0fac6ce630ee']],
  ['name'=>'جراب مغناطيسي Magsafe','desc'=>'متوافق مع الشحن المغناطيسي','price'=>279,'old'=>399,'imgs'=>['1615655406736-3bf5ef7ca916','1572569511254-d8f378a1ca36','1541807084-07c60387c8c1']],
];

// Chargers
$chargers = [
  ['name'=>'شاحن جداري سريع 20W','desc'=>'شحن سريع مع حماية ذكية','price'=>149,'old'=>199,'imgs'=>['1583863788434-e58a36330cf0','1517336714731-489689fd1ca8','1496181133206-80ce5a1b7d0a']],
  ['name'=>'شاحن سريع GaN 33W','desc'=>'صغير الحجم قوي الأداء','price'=>249,'old'=>329,'imgs'=>['1616348436165-f650ad4e69a5','1605236453806-b3ebc01d68e2','1511707171634-5f897ff02aa9']],
  ['name'=>'شاحن سيارة سريع','desc'=>'يدعم QC 3.0 سريع','price'=>129,'old'=>169,'imgs'=>['1556656793-08538906a9f8','1527864550417-7fd91fc51a46','1601784551446-20c9e07cdbdb']],
  ['name'=>'شاحن جداري مزدوج','desc'=>'منفذين شحن سريع','price'=>199,'old'=>249,'imgs'=>['1512941937669-90a1b58e7e9c','1580910051074-3eb694886505','1505740420928-5e560c06d30e']],
  ['name'=>'شاحن لاسلكي سريع','desc'=>'قاعدة شحن بدون كابلات','price'=>329,'old'=>449,'imgs'=>['1615655406736-3bf5ef7ca916','1572569511254-d8f378a1ca36','1541807084-07c60387c8c1']],
];

// Cables
$cables = [
  ['name'=>'كابل Type-C سريع','desc'=>'3A نقل بيانات سريع','price'=>49,'old'=>79,'imgs'=>['1583863788434-e58a36330cf0','1517336714731-489689fd1ca8','1496181133206-80ce5a1b7d0a']],
  ['name'=>'كابل Lightning سريع','desc'=>'معتمد للشحن السريع','price'=>69,'old'=>99,'imgs'=>['1605236453806-b3ebc01d68e2','1511707171634-5f897ff02aa9','1556656793-08538906a9f8']],
  ['name'=>'كابل Micro USB قوي','desc'=>'متين للغاية','price'=>39,'old'=>59,'imgs'=>['1527864550417-7fd91fc51a46','1601784551446-20c9e07cdbdb','1616348436165-f650ad4e69a5']],
  ['name'=>'كابل مغناطيسي 3 في 1','desc'=>'للشحن المتعدد','price'=>149,'old'=>199,'imgs'=>['1512941937669-90a1b58e7e9c','1580910051074-3eb694886505','1505740420928-5e560c06d30e']],
  ['name'=>'كابل قماش مضفر','desc'=>'مقاوم للقطع مطول العمر','price'=>59,'old'=>89,'imgs'=>['1615655406736-3bf5ef7ca916','1572569511254-d8f378a1ca36','1541807084-07c60387c8c1']],
];

// Earbuds
$earbuds = [
  ['name'=>'سماعات TWS بلوتوث','desc'=>'عزل ضوضاء ذكي','price'=>599,'old'=>799,'imgs'=>['1590658268037-6bf12165a8df','1484704849700-f032a568e944','1572569511254-d8f378a1ca36']],
  ['name'=>'سماعات رياضية مقاومة للماء','desc'=>'IPX7 مثالية للجيم','price'=>499,'old'=>699,'imgs'=>['1546435770-f68e5c5435b2','1484704849700-f032a568e944','1590658268037-6bf12165a8df']],
  ['name'=>'سماعات بلوتوث مع شحن سريع','desc'=>'صوت نقي وبطارية طويلة','price'=>449,'old'=>599,'imgs'=>['1572569511254-d8f378a1ca36','1546435770-f68e5c5435b2','1484704849700-f032a568e944']],
  ['name'=>'سماعات لاسلكية ميني','desc'=>'صغيرة وخفيفة جداً','price'=>399,'old'=>549,'imgs'=>['1484704849700-f032a568e944','1590658268037-6bf12165a8df','1546435770-f68e5c5435b2']],
  ['name'=>'سماعات ANC احترافية','desc'=>'إلغاء ضوضاء فعال','price'=>699,'old'=>899,'imgs'=>['1590658268037-6bf12165a8df','1572569511254-d8f378a1ca36','1484704849700-f032a568e944']],
];

$all = [
  ['cat'=>'Phone Cases','items'=>$cases,'colors'=>[['name'=>'شفاف','hex'=>'#ffffff'],['name'=>'أسود','hex'=>'#000000'],['name'=>'أزرق','hex'=>'#2563eb'],['name'=>'أحمر','hex'=>'#dc2626'],['name'=>'ذهبي','hex'=>'#f59e0b']]],
  ['cat'=>'Chargers','items'=>$chargers,'colors'=>[['name'=>'أبيض','hex'=>'#ffffff'],['name'=>'أسود','hex'=>'#000000'],['name'=>'أزرق','hex'=>'#3b82f6']]],
  ['cat'=>'Cables','items'=>$cables,'colors'=>[['name'=>'أبيض','hex'=>'#ffffff'],['name'=>'أسود','hex'=>'#000000'],['name'=>'أحمر','hex'=>'#ef4444'],['name'=>'أزرق','hex'=>'#06b6d4']]],
  ['cat'=>'Earbuds','items'=>$earbuds,'colors'=>[['name'=>'أبيض','hex'=>'#ffffff'],['name'=>'أسود','hex'=>'#000000'],['name'=>'أزرق داكن','hex'=>'#1e3a8a']]],
];

foreach($all as $group){
  $cat = $group['cat'];
  $colorsList = $group['colors'];
  foreach($group['items'] as $idx=>$it){
    $id = new_id();
    $imgs = [];
    foreach($it['imgs'] as $s){ $imgs[] = img('', $s); }
    // ensure at least 2 images
    if(count($imgs)<2){ $imgs[] = img('', $it['imgs'][0]); }
    $variants = [];
    $models = ['iPhone 15/16','Samsung Galaxy S24/S25','Xiaomi/Redmi'];
    if($cat=='Chargers' || $cat=='Cables'){ $models = ['عام']; }
    if($cat=='Earbuds'){ $models = ['TWS']; }
    foreach($models as $m){
      $cols = [];
      $cCount = rand(2, min(count($colorsList), 5));
      for($ci=0;$ci<$cCount;$ci++){
        $c = $colorsList[$ci];
        $cols[] = [
          'name' => $c['name'],
          'hex' => $c['hex'],
          'stock' => rand(15, 60),
          'image' => $imgs[$ci % count($imgs)],
        ];
      }
      $variants[] = ['model'=>$m, 'colors'=>$cols];
    }
    $doc = [
      '_id' => $id,
      'name' => $it['name'],
      'category' => $cat,
      'description' => $it['desc'],
      'price' => (float)$it['price'],
      'costPrice' => (float)max(10, $it['price']-70),
      'compareAtPrice' => (float)$it['old'],
      'images' => $imgs,
      'variants' => $variants,
      'stock' => 200,
      'status' => 'active',
      'createdAt' => gmdate('Y-m-d\TH:i:s\Z'),
    ];
    db_replace('products', $id, $doc);
    echo "P: $cat - ".$it['name']."\n";
  }
}
echo "DONE\n";
