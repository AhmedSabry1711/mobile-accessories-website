<?php
/**
 * Mobi Boost — one-time fixed-categories migration.
 *
 * Run from the project root after pulling this change:
 *
 *     php api/migrate_fixed_categories.php
 *
 * It maps the old category labels on existing products onto the five canonical
 * keys and rewrites the navigation table with the same fixed list. Safe to run
 * more than once: rows already on a canonical key are left untouched.
 */
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fail('This migration can only be run from the command line.', 403);
}

$p = db_prefix();

$rows = db()->query("SELECT `id`, `category` FROM `{$p}products`")->fetchAll();
$update = db()->prepare("UPDATE `{$p}products` SET `category` = ? WHERE `id` = ?");
$updated = 0;
$unknown = [];

foreach ($rows as $row) {
    $current = (string)$row['category'];
    $canonical = mb_canonical_category($current);
    if ($canonical === '') {
        $unknown[$current] = true;
        continue;
    }
    if ($canonical !== $current) {
        $update->execute([$canonical, (string)$row['id']]);
        $updated++;
    }
}

db()->exec("DELETE FROM `{$p}categories`");
$insert = db()->prepare("INSERT INTO `{$p}categories` (`name`, `position`) VALUES (?, ?)");
foreach (mb_fixed_categories() as $position => $category) {
    $insert->execute([$category, $position]);
}

echo "Products updated: {$updated}\n";
echo 'Fixed categories: ' . implode(', ', mb_fixed_categories()) . "\n";
if ($unknown) {
    echo 'Left unchanged (unknown category): ' . implode(', ', array_keys($unknown)) . "\n";
}
