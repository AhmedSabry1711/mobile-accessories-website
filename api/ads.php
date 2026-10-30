<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$ads = mongo_find('ads', ['active' => true], ['sort' => ['order' => 1, 'createdAt' => 1], 'limit' => 50]);
ok(array_map(fn($a) => [
    'id' => (string)$a['_id'],
    'title' => $a['title'],
    'mediaUrl' => $a['mediaUrl'],
    'type' => $a['type'],
    'linkUrl' => (string)($a['linkUrl'] ?? ''),
    'order' => (int)$a['order'],
    'active' => !empty($a['active']),
], $ads));
