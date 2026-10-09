<?php
/**
 * Mobi Boost — Public settings API (shipping rules, WhatsApp, categories, ads).
 *
 * GET /api/settings.php
 */
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    fail('Method not allowed', 405);
}

$settings = store_settings();

$ads = mongo_find('ads', ['active' => true], ['sort' => ['order' => 1, 'createdAt' => 1]]);
$settings['ads'] = array_map(fn($a) => [
    'id' => (string)$a['_id'],
    'title' => $a['title'],
    'mediaUrl' => $a['mediaUrl'],
    'type' => $a['type'],
    'linkUrl' => $a['linkUrl'] ?? '',
], $ads);

ok($settings);
