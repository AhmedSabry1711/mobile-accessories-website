<?php
require __DIR__ . '/_bootstrap.php';

$gifs = [
  ['title' => 'كفرات موبايل عصرية', 'mediaUrl' => 'https://media.giphy.com/media/3oKIPnAiaMCws8nOsE/giphy.gif', 'linkUrl' => '/products.html'],
  ['title' => 'حماية كاملة للهاتف', 'mediaUrl' => 'https://media.giphy.com/media/l3q2K5jinAlChoCLS/giphy.gif', 'linkUrl' => '/products.html'],
  ['title' => 'اكسسوارات موبيل مميزة', 'mediaUrl' => 'https://media.giphy.com/media/26BRuo6sLetdllPAQ/giphy.gif', 'linkUrl' => '/products.html'],
  ['title' => 'جرابات أنيقة', 'mediaUrl' => 'https://media.giphy.com/media/xTiTnqUxyWbsAXq7Ju/giphy.gif', 'linkUrl' => '/products.html'],
];

$p = db_prefix();
$db = db();
// clear existing ads
$db->exec("TRUNCATE TABLE `{$p}ads`");

$i = 0;
foreach($gifs as $a){
  $doc = [
    '_id' => new_id(),
    'title' => $a['title'],
    'mediaUrl' => $a['mediaUrl'],
    'type' => 'image',
    'linkUrl' => $a['linkUrl'],
    'order' => $i++,
    'active' => 1,
    'createdAt' => gmdate('Y-m-d\TH:i:s\Z'),
  ];
  db_replace('ads', $doc['_id'], $doc);
  echo "GIF AD: ".$doc['title']."\n";
}
echo "DONE\n";
