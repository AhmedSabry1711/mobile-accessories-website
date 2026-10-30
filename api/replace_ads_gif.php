<?php
require __DIR__ . '/_bootstrap.php';

$gifs = [
  ['title' => 'جرابات موبايل مميزة', 'mediaUrl' => 'https://media.giphy.com/media/v1.Y2lkPTc5MGI3NjExa2p3YmN3b2V6cXQ4Z2w5OHQwaGVxaHU3OW10bW9xdmR0NmdmcDAxaiZlcD12MV9naWZzX3NlYXJjaCZjdD1n/clzHmvis4MFktZdnNa/giphy.gif', 'linkUrl' => '/products.html'],
  ['title' => 'كفرات عصرية للحماية', 'mediaUrl' => 'https://media.giphy.com/media/v1.Y2lkPTc5MGI3NjExa2p3YmN3b2V6cXQ4Z2w5OHQwaGVxaHU3OW10bW9xdmR0NmdmcDAxaiZlcD12MV9naWZzX3NlYXJjaCZjdD1n/clzHmvis4MFktZdnNa/giphy.gif', 'linkUrl' => '/products.html'],
  ['title' => 'اكسسوارات موبايل', 'mediaUrl' => 'https://media.giphy.com/media/v1.Y2lkPTc5MGI3NjExa2p3YmN3b2V6cXQ4Z2w5OHQwaGVxaHU3OW10bW9xdmR0NmdmcDAxaiZlcD12MV9naWZzX3NlYXJjaCZjdD1n/BZGAYuekJf6DZx8k2F/giphy.gif', 'linkUrl' => '/products.html'],
  ['title' => 'جراب حماية قوي', 'mediaUrl' => 'https://media.giphy.com/media/v1.Y2lkPTc5MGI3NjExa2p3YmN3b2V6cXQ4Z2w5OHQwaGVxaHU3OW10bW9xdmR0NmdmcDAxaiZlcD12MV9naWZzX3NlYXJjaCZjdD1n/BZGAYuekJf6DZx8k2F/giphy.gif', 'linkUrl' => '/products.html'],
  ['title' => 'متجر اكسسوارات الموبايل', 'mediaUrl' => 'https://media.giphy.com/media/v1.Y2lkPTc5MGI3NjExa2p3YmN3b2V6cXQ4Z2w5OHQwaGVxaHU3OW10bW9xdmR0NmdmcDAxaiZlcD12MV9naWZzX3NlYXJjaCZjdD1n/z6WDdRidL8T4jEj1rT/giphy.gif', 'linkUrl' => '/products.html'],
  ['title' => 'احدث الجرابات', 'mediaUrl' => 'https://media.giphy.com/media/v1.Y2lkPTc5MGI3NjExa2p3YmN3b2V6cXQ4Z2w5OHQwaGVxaHU3OW10bW9xdmR0NmdmcDAxaiZlcD12MV9naWZzX3NlYXJjaCZjdD1n/wgPrxaptfwpUq6bRyd/giphy.gif', 'linkUrl' => '/products.html'],
];

$p = db_prefix();
$db = db();
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
  echo "AD: ".$doc['title']."\n";
}
echo "DONE\n";
