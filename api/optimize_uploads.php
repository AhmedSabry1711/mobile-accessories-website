<?php
/**
 * Mobi Boost — one-off backfill for photos uploaded before image optimisation.
 *
 * Run once from the server terminal (CLI only, no admin login needed):
 *
 *   php /home/u118872087/domains/mobiboost.store/public_html/api/optimize_uploads.php
 *
 * It only reads images already in public_html/uploads and writes small files
 * into public_html/uploads/_opt. Existing uploads, products and DB rows are
 * never modified, so it is safe to run again at any time.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$config = require __DIR__ . '/config.php';
require __DIR__ . '/lib/images.php';

$dir = rtrim((string)($config['uploadDir'] ?? ''), '/\\');
if ($dir === '' || !is_dir($dir)) {
    fwrite(STDERR, "Uploads folder not found: {$dir}\n");
    exit(1);
}

$images = 0;
$files = 0;
foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
    if (!is_file($file) || !mb_img_is_raster($file)) {
        continue;
    }
    $images++;
    $files += mb_img_generate_variants($file);
}

echo "Scanned {$images} image(s); wrote {$files} optimized file(s) to {$dir}/_opt\n";
if (!mb_img_can_process()) {
    echo "WARNING: GD with WebP support is not enabled, so no derivatives were made.\n";
}
