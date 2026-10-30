<?php
require __DIR__ . '/_bootstrap.php';

function fetch_json($url){
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => ['User-Agent: MobiBoostSync/1.0']
  ]);
  $res = curl_exec($ch);
  if($res === false){
    echo "CURL ERR: ".curl_error($ch)."\n";
    curl_close($ch);
    exit;
  }
  curl_close($ch);
  return json_decode($res, true);
}

$base = 'https://mobiboost.store';
$res = fetch_json($base.'/api/products.php?filter=all');
$items = $res['data']['items'] ?? [];
echo "Found: ".count($items)."\n";

$p = db_prefix();
$db = db();

$db->exec("SET FOREIGN_KEY_CHECKS=0");
$db->exec("TRUNCATE TABLE `{$p}product_variant_colors`");
$db->exec("TRUNCATE TABLE `{$p}product_variants`");
$db->exec("TRUNCATE TABLE `{$p}product_images`");
$db->exec("TRUNCATE TABLE `{$p}products`");
$db->exec("SET FOREIGN_KEY_CHECKS=1");

foreach($items as $it){
  $doc = [
    '_id' => (string)$it['id'],
    'name' => $it['name'] ?? '',
    'category' => $it['category'] ?? '',
    'description' => $it['description'] ?? '',
    'price' => (float)($it['price'] ?? 0),
    'costPrice' => (float)($it['costPrice'] ?? 0),
    'compareAtPrice' => (float)($it['compareAtPrice'] ?? 0),
    'images' => $it['images'] ?? [],
    'variants' => $it['variants'] ?? [],
    'stock' => (int)($it['stock'] ?? 0),
    'status' => $it['status'] ?? 'active',
    'createdAt' => $it['createdAt'] ?? gmdate('Y-m-d\TH:i:s\Z'),
  ];
  db_replace('products', $doc['_id'], $doc);
  echo "OK: ".$doc['name']."\n";
}
echo "DONE\n";
