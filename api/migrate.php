<?php
/**
 * Mobi Boost — migrate a database from the old JSON-document tables to the
 * normalized schema in api/schema.sql.
 *
 *     php api/migrate.php
 *
 * What it does, per collection:
 *   1. detects the legacy table (the one shaped as `id` / `doc` / `created_at`),
 *   2. renames it to `<prefix>legacy_<collection>` (nothing is dropped),
 *   3. creates the new normalized tables (api/schema.sql),
 *   4. copies every document into its new table.
 *
 * Re-running it is safe: collections that are already normalized are skipped and
 * no data is duplicated. When you are happy with the result you can drop the
 * `*legacy_*` tables in phpMyAdmin.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("This one-off upgrade must be run from the command line:\n\n    php api/migrate.php\n");
}

require __DIR__ . '/_config.php';

/** @var array $CONFIG */
$CONFIG = mobi_config();
$mysql = $CONFIG['mysql'];
$prefix = (string)$mysql['tablePrefix'];
$dbName = (string)$mysql['database'];
$COLLECTIONS = ['products', 'reviews', 'orders', 'coupons', 'ads', 'settings', 'users'];

function out(string $line = ''): void {
    fwrite(STDOUT, $line . "\n");
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $mysql['host'], (int)$mysql['port'], $dbName),
        (string)$mysql['username'],
        (string)$mysql['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Throwable $e) {
    exit("Cannot connect to MySQL ({$mysql['host']} / {$dbName}): " . $e->getMessage() . "\n");
}

out("Mobi Boost — schema migration   ({$mysql['host']} / {$dbName}, prefix '{$prefix}')");
out(str_repeat('=', 70));

// ---------------------------------------------------------------- helpers
function table_exists(PDO $pdo, string $db, string $table): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
    $stmt->execute([$db, $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function column_exists(PDO $pdo, string $db, string $table, string $column): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?');
    $stmt->execute([$db, $table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

/** Split a .sql file into individual statements (comment lines stripped). */
function sql_statements(string $sql): array {
    $statements = [];
    $buffer = '';
    foreach (preg_split('/\R/', $sql) as $line) {
        $trimmed = ltrim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '--')) {
            continue;
        }
        $buffer .= $line . "\n";
        if (str_ends_with(rtrim($line), ';')) {
            $statements[] = trim($buffer);
            $buffer = '';
        }
    }
    if (trim($buffer) !== '') {
        $statements[] = trim($buffer);
    }
    return $statements;
}

// ---------------------------------------------------------------- 1. rename
$legacy = [];
foreach ($COLLECTIONS as $collection) {
    $table = $prefix . $collection;
    if (!table_exists($pdo, $dbName, $table) || !column_exists($pdo, $dbName, $table, 'doc')) {
        continue;
    }
    $backup = $prefix . 'legacy_' . $collection;
    if (table_exists($pdo, $dbName, $backup)) {
        $pdo->exec("DROP TABLE `$backup`");
    }
    $pdo->exec("RENAME TABLE `$table` TO `$backup`");
    $legacy[] = $collection;
}

if ($legacy === []) {
    out('No legacy document tables found — nothing to convert.');
} else {
    out('Renamed legacy tables (kept as a backup):');
    foreach ($legacy as $collection) {
        out("  {$prefix}{$collection}  ->  {$prefix}legacy_{$collection}");
    }
}

// ---------------------------------------------------------------- 2. schema
$schemaFile = __DIR__ . '/schema.sql';
if (!is_file($schemaFile)) {
    $schemaFile = dirname(__DIR__) . '/api-mysql/schema.sql';
}
if (!is_file($schemaFile)) {
    exit("\nERROR: schema.sql not found next to this script.\n");
}
foreach (sql_statements((string)file_get_contents($schemaFile)) as $statement) {
    $pdo->exec($statement);
}
out('Normalized tables are in place (api/schema.sql).');

// ---------------------------------------------------------------- 3. copy
if ($legacy !== []) {
    require __DIR__ . '/_bootstrap.php'; // the new, table-backed storage layer

    out('');
    out('Copying documents into the normalized tables:');
    foreach ($legacy as $collection) {
        $backup = $prefix . 'legacy_' . $collection;
        $rows = $pdo->query("SELECT `doc` FROM `$backup`")->fetchAll(PDO::FETCH_COLUMN);
        $copied = 0;
        $problems = [];
        foreach ($rows as $json) {
            $doc = json_decode((string)$json, true);
            if (!is_array($doc) || $doc === []) {
                $problems[] = 'unreadable JSON row';
                continue;
            }
            try {
                mongo_insert($collection, $doc);
                $copied++;
            } catch (Throwable $e) {
                $problems[] = $e->getMessage();
            }
        }
        out(sprintf('  %-9s %3d copied%s', $collection, $copied, $problems ? '  (' . count($problems) . ' failed)' : ''));
        foreach (array_slice(array_unique($problems), 0, 5) as $problem) {
            out('      ! ' . $problem);
        }
    }
}

out(str_repeat('=', 68));
out('Done. The old tables are still there as *legacy_* — drop them once you are');
out('happy with the result. Then open /admin to check the catalogue.');
