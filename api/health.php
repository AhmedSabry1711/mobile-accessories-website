<?php
/**
 * Mobi Boost — Temporary deployment diagnostics.
 *
 * Open https://YOURDOMAIN/api/health.php in a browser after deploying.
 * It always answers with JSON (HTTP 200) so you can read the reason even
 * when everything else is misconfigured. It never prints the DB password.
 *
 * ⚠️  DELETE THIS FILE once your deployment is verified.
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$report = [
    'ok'               => true,
    'php_version'      => PHP_VERSION,
    'sapi'             => PHP_SAPI,
    'server_software'  => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
    'extensions'       => [
        'pdo'       => extension_loaded('pdo'),
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'mbstring'  => extension_loaded('mbstring'),
    ],
    'uploads_writable' => null,
    'config'           => null,
    'db'               => null,
    'tables'           => null,
];

$CONFIG = null;

// ---- config --------------------------------------------------------------
try {
    $CONFIG = require __DIR__ . '/config.php';
    $m = $CONFIG['mysql'];
    $report['config'] = [
        'host'         => $m['host'],
        'port'         => (int)$m['port'],
        'database'     => $m['database'],
        'username'     => $m['username'],
        'tablePrefix'  => $m['tablePrefix'],
        'password_set' => ((string)$m['password'] !== ''),
    ];
} catch (\Throwable $e) {
    $report['ok'] = false;
    $report['config'] = ['error' => $e->getMessage()];
}

// ---- uploads folder ------------------------------------------------------
$uploadDir = dirname(__DIR__) . '/uploads';
$report['uploads_writable'] = is_dir($uploadDir)
    ? (is_writable($uploadDir) ? true : 'exists but NOT writable')
    : ('missing: ' . $uploadDir);

// ---- database ------------------------------------------------------------
if ($CONFIG && isset($CONFIG['mysql'])) {
    $m = $CONFIG['mysql'];
    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $m['host'],
            (int)$m['port'],
            $m['database']
        );
        $pdo = new PDO($dsn, $m['username'], (string)$m['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $report['db'] = ['connected' => true];

        $prefix = (string)$m['tablePrefix'];
        $tables = [];
        foreach ([
            'products', 'product_images', 'product_variants', 'product_variant_colors',
            'orders', 'order_items', 'reviews', 'coupons', 'ads',
            'settings', 'categories', 'users',
        ] as $t) {
            $name = $prefix . $t;
            try {
                $pdo->query("SELECT 1 FROM `$name` LIMIT 1");
                $tables[$name] = 'ok';
            } catch (\Throwable $e) {
                $tables[$name] = 'MISSING';
            }
        }
        $report['tables'] = $tables;
    } catch (\Throwable $e) {
        $report['ok'] = false;
        $report['db'] = [
            'connected' => false,
            'error'     => $e->getMessage(),
        ];
    }
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
