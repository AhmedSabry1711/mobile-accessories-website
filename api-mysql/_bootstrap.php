<?php
/**
 * Mobi Boost — API bootstrap (MySQL / PDO edition).
 *
 * Exposes the helper surface every endpoint is written against (mongo_find,
 * mongo_find_one, mongo_insert, mongo_update, mongo_delete, mongo_count,
 * object_id, bson_clean, require_admin, ...) but persists the data in clean,
 * normalized MySQL tables — no JSON blobs:
 *
 *   products ...... mb_products + mb_product_images + mb_product_variants
 *                                + mb_product_variant_colors
 *   orders ........ mb_orders + mb_order_items
 *   reviews ....... mb_reviews
 *   coupons ....... mb_coupons
 *   ads ........... mb_ads
 *   settings ...... mb_settings + mb_categories
 *   users ......... mb_users
 *
 * The helpers keep their MongoDB-style names ("collections", dotted filters,
 * $set/$unset/$inc updates) because every endpoint is written against them; this
 * file translates that API to the relational tables (see api/schema.sql). A
 * document is rebuilt from / decomposed into rows on each read / write, so the
 * REST surface is unchanged while the database stays readable in phpMyAdmin.
 */
declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');

/** @var array $CONFIG */
$CONFIG = require __DIR__ . '/config.php';

// Value-type polyfills — no-op when the real mongodb extension is loaded.
require __DIR__ . '/lib/mongo_compat.php';

// Uploads folder self-heal: re-create it on any API hit if it is missing (a
// fresh deployment or a manual cleanup can remove it). Existing files are never
// touched — only the (empty) directory is recreated so new uploads keep working.
if (!empty($CONFIG['uploadDir']) && !is_dir($CONFIG['uploadDir'])) {
    @mkdir($CONFIG['uploadDir'], 0755, true);
}

// ---------------------------------------------------------------- HTTP setup
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . $CONFIG['corsOrigin']);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---------------------------------------------------------------- Responses
/** Send a success JSON response and stop. */
function ok($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Send an error JSON response and stop. */
function fail(string $message, int $code = 400, $errors = null): void {
    http_response_code($code);
    $payload = ['success' => false, 'message' => $message];
    if ($errors !== null) {
        $payload['errors'] = $errors;
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Read the JSON request body as an array. */
function json_input(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Extract the Bearer token from the request headers. */
function bearer_token(): ?string {
    $value = '';
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $hvalue) {
            if (strtolower((string)$name) === 'authorization') {
                $value = (string)$hvalue;
                break;
            }
        }
    }
    if ($value === '' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $value = (string)$_SERVER['HTTP_AUTHORIZATION'];
    }
    if ($value === '' && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $value = (string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    return $value !== '' ? $value : null;
}

// ---------------------------------------------------------------- MySQL / PDO
/** Lazily connect to MySQL and remember the handle. */
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    global $CONFIG;
    $c = $CONFIG['mysql'];
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $c['host'], (int)$c['port'], $c['database']);
    try {
        $pdo = new PDO($dsn, $c['username'], $c['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        fail('Database connection failed', 500);
    }
    return $pdo;
}

/** Resolve a collection name to its (prefixed, whitelisted) table name. */
function db_table(string $collection): string {
    static $allowed = ['products', 'reviews', 'orders', 'coupons', 'ads', 'settings', 'users'];
    if (!in_array($collection, $allowed, true)) {
        throw new RuntimeException('Unknown collection: ' . $collection);
    }
    global $CONFIG;
    return $CONFIG['mysql']['tablePrefix'] . $collection;
}

/** Generate a 24-hex document id (BSON ObjectId compatible). */
function new_id(): string {
    return bin2hex(random_bytes(12));
}

// ---------------------------------------------------------------- Value coercion
/** Normalise a stored/filter value for comparison (BSON -> scalar). */
function db_scalar($value) {
    if ($value instanceof \MongoDB\BSON\ObjectId) {
        return (string)$value;
    }
    if ($value instanceof \MongoDB\BSON\UTCDateTime) {
        return $value->toDateTime()->getTimestamp();
    }
    return $value;
}

/** Recursively convert BSON value objects to JSON-storable scalars. */
function db_encode_value($value) {
    if ($value instanceof \MongoDB\BSON\ObjectId) {
        return (string)$value;
    }
    if ($value instanceof \MongoDB\BSON\UTCDateTime) {
        return $value->toDateTime()->format(\DateTimeInterface::ATOM);
    }
    if ($value instanceof \MongoDB\BSON\Regex) {
        return $value->getPattern();
    }
    if (is_array($value)) {
        $out = [];
        foreach ($value as $k => $v) {
            $out[$k] = db_encode_value($v);
        }
        return $out;
    }
    return $value;
}

// ---------------------------------------------------------------- Timestamps
/** Parse a document date (UTCDateTime, ISO-8601 or naive UTC) to a unix timestamp. */
function db_parse_datetime($value): ?int {
    if ($value instanceof \MongoDB\BSON\UTCDateTime) {
        return $value->toDateTime()->getTimestamp();
    }
    if ($value === null || $value === '') {
        return null;
    }
    if (is_int($value) || is_float($value)) {
        return (int)$value;
    }
    $text = trim((string)$value);
    if ($text === '') {
        return null;
    }
    // Keep the zone when the value carries one; a bare DATETIME is UTC.
    $ts = preg_match('/(Z|[+-]\d{2}:?\d{2})$/', $text) ? strtotime($text) : strtotime($text . ' UTC');
    return $ts === false ? null : $ts;
}

/** Document date -> `Y-m-d H:i:s` (UTC) for a DATETIME column, or null. */
function db_datetime_col($value): ?string {
    $ts = db_parse_datetime($value);
    return $ts === null ? null : gmdate('Y-m-d H:i:s', $ts);
}

/** `Y-m-d H:i:s` (UTC) column -> ISO-8601 string for API output, or null. */
function db_iso_col(?string $value): ?string {
    $ts = db_parse_datetime($value);
    return $ts === null ? null : gmdate('c', $ts);
}

/** createdAt value for the `created_at` column (defaults to now). */
function db_created_at(array $doc): string {
    return db_datetime_col($doc['createdAt'] ?? null) ?? gmdate('Y-m-d H:i:s');
}

// ---------------------------------------------------------------- Nested access
/** Read a dotted path (a.b.c) from a document, or null. */
function db_get_path(array $doc, string $path) {
    $value = $doc;
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
        } else {
            return null;
        }
    }
    return $value;
}

/** Set a dotted path (a.b.c) in a document, creating arrays as needed. */
function db_set_path(array &$doc, string $path, $value): void {
    $parts = explode('.', $path);
    $ref = &$doc;
    $last = count($parts) - 1;
    foreach ($parts as $i => $part) {
        if ($i === $last) {
            $ref[$part] = $value;
            return;
        }
        if (!isset($ref[$part]) || !is_array($ref[$part])) {
            $ref[$part] = [];
        }
        $ref = &$ref[$part];
    }
}

/** Remove a dotted path from a document. */
function db_unset_path(array &$doc, string $path): void {
    $parts = explode('.', $path);
    $ref = &$doc;
    $last = count($parts) - 1;
    foreach ($parts as $i => $part) {
        if (!is_array($ref) || !array_key_exists($part, $ref)) {
            return;
        }
        if ($i === $last) {
            unset($ref[$part]);
            return;
        }
        $ref = &$ref[$part];
    }
}

// ---------------------------------------------------------------- Filter engine
/** Three-way comparison of two scalar-ish values. */
function db_compare($a, $b): int {
    $a = db_scalar($a);
    $b = db_scalar($b);
    if (is_numeric($a) && is_numeric($b)) {
        $x = (float)$a;
        $y = (float)$b;
        return $x < $y ? -1 : ($x > $y ? 1 : 0);
    }
    return strcmp((string)$a, (string)$b);
}

/** Loose equality used by Mongo-style filters. */
function db_eq($a, $b): bool {
    $a = db_scalar($a);
    $b = db_scalar($b);
    if (is_bool($a) || is_bool($b)) {
        return (bool)$a === (bool)$b;
    }
    if (is_numeric($a) && is_numeric($b)) {
        return (float)$a == (float)$b;
    }
    if ($a === null || $b === null) {
        return $a === $b;
    }
    return (string)$a === (string)$b;
}

/** Case-aware PCRE match for a MongoDB Regex condition. */
function db_regex_match(string $pattern, string $flags, $value): bool {
    if ($value === null) {
        return false;
    }
    $delimiter = '/' . str_replace('/', '\/', $pattern) . '/' . $flags;
    return @preg_match($delimiter, (string)db_scalar($value)) === 1;
}

/** True when an array is an operator document (has any $-prefixed key). */
function db_is_operator_doc(array $arr): bool {
    foreach ($arr as $k => $_) {
        if (is_string($k) && $k !== '' && $k[0] === '$') {
            return true;
        }
    }
    return false;
}

/** Evaluate a single field condition against a value. */
function db_match_condition($value, $condition): bool {
    if ($condition instanceof \MongoDB\BSON\Regex) {
        return db_regex_match($condition->getPattern(), $condition->getFlags(), $value);
    }
    if (is_array($condition) && db_is_operator_doc($condition)) {
        foreach ($condition as $op => $operand) {
            switch ($op) {
                case '$eq':
                    if (!db_eq($value, $operand)) return false;
                    break;
                case '$ne':
                    if (db_eq($value, $operand)) return false;
                    break;
                case '$gt':
                    if (db_compare($value, $operand) <= 0) return false;
                    break;
                case '$gte':
                    if (db_compare($value, $operand) < 0) return false;
                    break;
                case '$lt':
                    if (db_compare($value, $operand) >= 0) return false;
                    break;
                case '$lte':
                    if (db_compare($value, $operand) > 0) return false;
                    break;
                case '$in':
                    $found = false;
                    foreach ((array)$operand as $candidate) {
                        if (db_eq($value, $candidate)) { $found = true; break; }
                    }
                    if (!$found) return false;
                    break;
                case '$nin':
                    foreach ((array)$operand as $candidate) {
                        if (db_eq($value, $candidate)) return false;
                    }
                    break;
                case '$exists':
                    if ((bool)$operand !== ($value !== null)) return false;
                    break;
                case '$regex':
                    $flags = (string)($condition['$options'] ?? '');
                    if (!db_regex_match((string)$operand, $flags, $value)) return false;
                    break;
                default:
                    return false;
            }
        }
        return true;
    }
    return db_eq($value, $condition);
}

/** Resolve a $expr operand (a "$field" reference or a literal). */
function db_expr_operand(array $doc, $operand) {
    if (is_string($operand) && strlen($operand) > 1 && $operand[0] === '$') {
        return db_get_path($doc, substr($operand, 1));
    }
    return $operand;
}

/** Evaluate an $expr expression (field-vs-field comparisons). */
function db_match_expr(array $doc, $expr): bool {
    if (!is_array($expr)) {
        return false;
    }
    foreach ($expr as $op => $args) {
        if (!is_array($args) || count($args) !== 2) {
            return false;
        }
        $cmp = db_compare(db_expr_operand($doc, $args[0]), db_expr_operand($doc, $args[1]));
        switch ($op) {
            case '$gt':  if ($cmp <= 0) return false; break;
            case '$gte': if ($cmp < 0)  return false; break;
            case '$lt':  if ($cmp >= 0) return false; break;
            case '$lte': if ($cmp > 0)  return false; break;
            case '$eq':  if ($cmp !== 0) return false; break;
            case '$ne':  if ($cmp === 0) return false; break;
            default:     return false;
        }
    }
    return true;
}

/** Test a document against a Mongo-style filter. */
function db_matches(array $doc, array $filter): bool {
    foreach ($filter as $key => $condition) {
        if ($key === '$or') {
            $any = false;
            foreach ((array)$condition as $sub) {
                if (db_matches($doc, (array)$sub)) { $any = true; break; }
            }
            if (!$any) return false;
            continue;
        }
        if ($key === '$and') {
            foreach ((array)$condition as $sub) {
                if (!db_matches($doc, (array)$sub)) return false;
            }
            continue;
        }
        if ($key === '$expr') {
            if (!db_match_expr($doc, $condition)) return false;
            continue;
        }
        if (!db_match_condition(db_get_path($doc, (string)$key), $condition)) {
            return false;
        }
    }
    return true;
}

// ---------------------------------------------------------------- Table names
/** Configured table prefix (api/config.php → mysql.tablePrefix). */
function db_prefix(): string {
    global $CONFIG;
    return (string)$CONFIG['mysql']['tablePrefix'];
}

/** Stable document id reported for the single-row settings table. */
function db_settings_id(): string {
    return '000000000000000000000001';
}

/** The id a Mongo-style filter pins the query to (null = whole collection). */
function db_filter_id(array $filter): ?string {
    if (isset($filter['_id']) && !is_array($filter['_id'])) {
        return (string)db_scalar($filter['_id']);
    }
    return null;
}

/** SQL placeholder list for an IN (...) clause. */
function db_placeholders(array $values): string {
    return implode(',', array_fill(0, count($values), '?'));
}

/**
 * Rebuild a collection's documents from its relational tables. `_id` equality is
 * pushed to SQL; every other filter is evaluated in PHP by the caller
 * (db_matches) — exactly like the document store did.
 */
function db_load_docs(string $collection, array $filter): array {
    $id = db_filter_id($filter);
    switch ($collection) {
        case 'products': return db_load_products($id);
        case 'orders':   return db_load_orders($id);
        case 'reviews':  return db_load_reviews($id);
        case 'coupons':  return db_load_coupons($id);
        case 'ads':      return db_load_ads($id);
        case 'settings': return db_load_settings();
        case 'users':    return db_load_users($id);
    }
    throw new RuntimeException('Unknown collection: ' . $collection);
}

// ---------------------------------------------------------------- Loaders
/** Fetch the parent rows of a collection, optionally pinned to one id. */
function db_load_rows(string $table, ?string $id): array {
    if ($id !== null) {
        $stmt = db()->prepare("SELECT * FROM `$table` WHERE `id` = ?");
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }
    return db()->query("SELECT * FROM `$table`")->fetchAll();
}

/** Products, with images + variants + colourways. */
function db_load_products(?string $id): array {
    $p = db_prefix();
    $rows = db_load_rows($p . 'products', $id);
    if (!$rows) {
        return [];
    }
    $ids = array_map(fn($r) => (string)$r['id'], $rows);
    $in = db_placeholders($ids);

    $images = [];
    $stmt = db()->prepare("SELECT `product_id`, `url` FROM `{$p}product_images` WHERE `product_id` IN ($in) ORDER BY `product_id`, `position`, `id`");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $img) {
        $images[(string)$img['product_id']][] = (string)$img['url'];
    }

    $stmt = db()->prepare("SELECT `id`, `product_id`, `model` FROM `{$p}product_variants` WHERE `product_id` IN ($in) ORDER BY `product_id`, `position`, `id`");
    $stmt->execute($ids);
    $variantRows = $stmt->fetchAll();

    $colors = [];
    if ($variantRows) {
        $vids = array_map(fn($v) => (string)$v['id'], $variantRows);
        $vin = db_placeholders($vids);
        $stmt = db()->prepare("SELECT * FROM `{$p}product_variant_colors` WHERE `variant_id` IN ($vin) ORDER BY `variant_id`, `position`, `id`");
        $stmt->execute($vids);
        foreach ($stmt->fetchAll() as $c) {
            $colors[(string)$c['variant_id']][] = [
                'name' => (string)$c['name'],
                'hex' => (string)$c['hex'],
                'stock' => (int)$c['stock'],
                'image' => (string)$c['image'],
            ];
        }
    }

    $docs = [];
    foreach ($rows as $r) {
        $pid = (string)$r['id'];
        $variants = [];
        foreach ($variantRows as $v) {
            if ((string)$v['product_id'] === $pid) {
                $variants[] = [
                    'model' => (string)$v['model'],
                    'colors' => $colors[(string)$v['id']] ?? [],
                ];
            }
        }
        $doc = [
            '_id' => $pid,
            'name' => (string)$r['name'],
            'category' => (string)$r['category'],
            'description' => (string)$r['description'],
            'price' => (float)$r['price'],
            'costPrice' => (float)$r['cost_price'],
            'compareAtPrice' => (float)$r['compare_at_price'],
            'images' => $images[$pid] ?? [],
            'variants' => $variants,
            'stock' => (int)$r['stock'],
            'status' => (string)$r['status'],
            'createdAt' => db_iso_col($r['created_at']),
        ];
        if ($r['updated_at'] !== null) {
            $doc['updatedAt'] = db_iso_col($r['updated_at']);
        }
        $docs[] = $doc;
    }
    return $docs;
}

/** Orders, with their line items. */
function db_load_orders(?string $id): array {
    $p = db_prefix();
    $rows = db_load_rows($p . 'orders', $id);
    if (!$rows) {
        return [];
    }
    $ids = array_map(fn($r) => (string)$r['id'], $rows);
    $in = db_placeholders($ids);

    $items = [];
    $stmt = db()->prepare("SELECT * FROM `{$p}order_items` WHERE `order_id` IN ($in) ORDER BY `order_id`, `position`, `id`");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $it) {
        $items[(string)$it['order_id']][] = [
            'productId' => (string)$it['product_id'],
            'name' => (string)$it['name'],
            'image' => (string)$it['image'],
            'price' => (float)$it['price'],
            'costPrice' => (float)$it['cost_price'],
            'qty' => (int)$it['qty'],
            'model' => $it['model'] !== null ? (string)$it['model'] : null,
            'color' => $it['color'] !== null ? (string)$it['color'] : null,
        ];
    }

    $docs = [];
    foreach ($rows as $r) {
        $oid = (string)$r['id'];
        $doc = [
            '_id' => $oid,
            'orderNumber' => (string)$r['order_number'],
            'items' => $items[$oid] ?? [],
            'subtotal' => (float)$r['subtotal'],
            'discount' => (float)$r['discount'],
            'couponCode' => $r['coupon_code'] !== null ? (string)$r['coupon_code'] : null,
            'shippingFee' => (float)$r['shipping_fee'],
            'total' => (float)$r['total'],
            'customer' => [
                'name' => (string)$r['customer_name'],
                'address' => (string)$r['customer_address'],
                'phone' => (string)$r['customer_phone'],
            ],
            'status' => (string)$r['status'],
            'createdAt' => db_iso_col($r['created_at']),
        ];
        if ($r['updated_at'] !== null) {
            $doc['updatedAt'] = db_iso_col($r['updated_at']);
        }
        $docs[] = $doc;
    }
    return $docs;
}

/** Product reviews. */
function db_load_reviews(?string $id): array {
    return array_map(fn($r) => [
        '_id' => (string)$r['id'],
        'productId' => (string)$r['product_id'],
        'userName' => (string)$r['user_name'],
        'rating' => (int)$r['rating'],
        'comment' => (string)$r['comment'],
        'createdAt' => db_iso_col($r['created_at']),
    ], db_load_rows(db_prefix() . 'reviews', $id));
}

/** Discount coupons. */
function db_load_coupons(?string $id): array {
    return array_map(fn($r) => [
        '_id' => (string)$r['id'],
        'code' => (string)$r['code'],
        'type' => (string)$r['type'],
        'value' => (float)$r['value'],
        'minOrder' => (float)$r['min_order'],
        'expiresAt' => db_iso_col($r['expires_at']),
        'active' => (bool)$r['active'],
        'createdAt' => db_iso_col($r['created_at']),
    ], db_load_rows(db_prefix() . 'coupons', $id));
}

/** Homepage ticker ads. */
function db_load_ads(?string $id): array {
    return array_map(fn($r) => [
        '_id' => (string)$r['id'],
        'title' => (string)$r['title'],
        'mediaUrl' => (string)$r['media_url'],
        'type' => (string)$r['type'],
        'linkUrl' => (string)$r['link_url'],
        'order' => (int)$r['sort_order'],
        'active' => (bool)$r['active'],
        'createdAt' => db_iso_col($r['created_at']),
    ], db_load_rows(db_prefix() . 'ads', $id));
}

/** The single settings row plus the navigation categories, as one document. */
function db_load_settings(): array {
    $p = db_prefix();
    $row = db()->query("SELECT * FROM `{$p}settings` WHERE `id` = 1")->fetch();
    if (!$row) {
        return [];
    }
    $categories = db()->query("SELECT `name` FROM `{$p}categories` ORDER BY `position`, `id`")->fetchAll(PDO::FETCH_COLUMN);
    return [[
        '_id' => db_settings_id(),
        'key' => 'store',
        'whatsapp' => (string)$row['whatsapp'],
        'shipping' => [
            'enabled' => (bool)$row['shipping_enabled'],
            'flatRate' => (float)$row['shipping_flat_rate'],
            'freeOver' => (float)$row['shipping_free_over'],
        ],
        'currency' => [
            'symbol' => (string)$row['currency_symbol'],
            'code' => (string)$row['currency_code'],
        ],
        'categories' => array_map('strval', $categories),
        'updatedAt' => db_iso_col($row['updated_at']),
    ]];
}

/** Admin accounts. */
function db_load_users(?string $id): array {
    return array_map(fn($r) => [
        '_id' => (string)$r['id'],
        'username' => (string)$r['username'],
        'passwordHash' => (string)$r['password_hash'],
        'createdAt' => db_iso_col($r['created_at']),
    ], db_load_rows(db_prefix() . 'users', $id));
}

/** Apply a Mongo-style update document ($set / $unset / $inc) to a doc. */
function db_apply_update(array $doc, array $update): array {
    foreach ($update as $op => $fields) {
        if (!is_array($fields)) {
            continue;
        }
        if ($op === '$set') {
            foreach ($fields as $path => $value) {
                db_set_path($doc, (string)$path, db_encode_value($value));
            }
        } elseif ($op === '$unset') {
            foreach ($fields as $path => $_) {
                db_unset_path($doc, (string)$path);
            }
        } elseif ($op === '$inc') {
            foreach ($fields as $path => $delta) {
                $current = db_get_path($doc, (string)$path);
                if (is_int($current) && is_int($delta)) {
                    $result = $current + $delta;
                } else {
                    $result = (is_numeric($current) ? (float)$current : 0.0) + (float)$delta;
                }
                db_set_path($doc, (string)$path, $result);
            }
        }
    }
    return $doc;
}

// ---------------------------------------------------------------- Writers
/** Insert or update a document in its relational tables. */
function db_replace(string $collection, string $id, array $doc): void {
    if (!isset($doc['_id'])) {
        $doc['_id'] = $id;
    }
    switch ($collection) {
        case 'products': db_write_product($doc); break;
        case 'orders':   db_write_order($doc); break;
        case 'reviews':  db_write_review($doc); break;
        case 'coupons':  db_write_coupon($doc); break;
        case 'ads':      db_write_ad($doc); break;
        case 'settings': db_write_settings($doc); break;
        case 'users':    db_write_user($doc); break;
        default: throw new RuntimeException('Unknown collection: ' . $collection);
    }
}

/** Products: upsert the row, then resync images + variants (colours cascade). */
function db_write_product(array $doc): void {
    $p = db_prefix();
    $id = (string)$doc['_id'];
    $stmt = db()->prepare(
        "INSERT INTO `{$p}products`
            (`id`, `name`, `category`, `description`, `price`, `cost_price`,
             `compare_at_price`, `stock`, `status`, `created_at`, `updated_at`)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            `name` = VALUES(`name`), `category` = VALUES(`category`),
            `description` = VALUES(`description`), `price` = VALUES(`price`),
            `cost_price` = VALUES(`cost_price`),
            `compare_at_price` = VALUES(`compare_at_price`),
            `stock` = VALUES(`stock`), `status` = VALUES(`status`),
            `updated_at` = VALUES(`updated_at`)"
    );
    $stmt->execute([
        $id,
        (string)($doc['name'] ?? ''),
        (string)($doc['category'] ?? ''),
        (string)($doc['description'] ?? ''),
        (float)($doc['price'] ?? 0),
        (float)($doc['costPrice'] ?? 0),
        (float)($doc['compareAtPrice'] ?? 0),
        (int)($doc['stock'] ?? 0),
        ($doc['status'] ?? 'active') === 'draft' ? 'draft' : 'active',
        db_created_at($doc),
        db_datetime_col($doc['updatedAt'] ?? null),
    ]);

    if (array_key_exists('images', $doc)) {
        $stmt = db()->prepare("DELETE FROM `{$p}product_images` WHERE `product_id` = ?");
        $stmt->execute([$id]);
        $stmt = db()->prepare("INSERT INTO `{$p}product_images` (`product_id`, `url`, `position`) VALUES (?, ?, ?)");
        $pos = 0;
        foreach (array_values(array_filter(array_map('strval', (array)($doc['images'] ?? [])), fn($u) => $u !== '')) as $url) {
            $stmt->execute([$id, $url, $pos++]);
        }
    }

    if (array_key_exists('variants', $doc)) {
        $stmt = db()->prepare("DELETE FROM `{$p}product_variants` WHERE `product_id` = ?");
        $stmt->execute([$id]);
        $insertVariant = db()->prepare("INSERT INTO `{$p}product_variants` (`product_id`, `model`, `position`) VALUES (?, ?, ?)");
        $insertColor = db()->prepare("INSERT INTO `{$p}product_variant_colors` (`variant_id`, `name`, `hex`, `stock`, `image`, `position`) VALUES (?, ?, ?, ?, ?, ?)");
        $vpos = 0;
        foreach (array_values((array)($doc['variants'] ?? [])) as $variant) {
            $model = trim((string)($variant['model'] ?? ''));
            if ($model === '') {
                continue;
            }
            $insertVariant->execute([$id, $model, $vpos++]);
            $variantId = (int)db()->lastInsertId();
            $cpos = 0;
            foreach (array_values((array)($variant['colors'] ?? [])) as $color) {
                $name = trim((string)($color['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $hex = (string)($color['hex'] ?? '#000000');
                if (!preg_match('/^#([a-f0-9]{3}|[a-f0-9]{6})$/i', $hex)) {
                    $hex = '#000000';
                }
                $insertColor->execute([
                    $variantId,
                    $name,
                    strtolower($hex),
                    max(0, (int)($color['stock'] ?? 0)),
                    (string)($color['image'] ?? ''),
                    $cpos++,
                ]);
            }
        }
    }
}

/** Orders: upsert the row, then resync the line items (snapshots). */
function db_write_order(array $doc): void {
    $p = db_prefix();
    $id = (string)$doc['_id'];
    $customer = (array)($doc['customer'] ?? []);
    $status = (string)($doc['status'] ?? 'pending');
    if (!in_array($status, ['pending', 'processing', 'completed', 'cancelled'], true)) {
        $status = 'pending';
    }
    $coupon = $doc['couponCode'] ?? null;
    $stmt = db()->prepare(
        "INSERT INTO `{$p}orders`
            (`id`, `order_number`, `status`, `subtotal`, `discount`, `coupon_code`,
             `shipping_fee`, `total`, `customer_name`, `customer_address`,
             `customer_phone`, `created_at`, `updated_at`)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            `order_number` = VALUES(`order_number`), `status` = VALUES(`status`),
            `subtotal` = VALUES(`subtotal`), `discount` = VALUES(`discount`),
            `coupon_code` = VALUES(`coupon_code`),
            `shipping_fee` = VALUES(`shipping_fee`), `total` = VALUES(`total`),
            `customer_name` = VALUES(`customer_name`),
            `customer_address` = VALUES(`customer_address`),
            `customer_phone` = VALUES(`customer_phone`),
            `updated_at` = VALUES(`updated_at`)"
    );
    $stmt->execute([
        $id,
        (string)($doc['orderNumber'] ?? ''),
        $status,
        (float)($doc['subtotal'] ?? 0),
        (float)($doc['discount'] ?? 0),
        ($coupon === null || $coupon === '') ? null : (string)$coupon,
        (float)($doc['shippingFee'] ?? 0),
        (float)($doc['total'] ?? 0),
        (string)($customer['name'] ?? ''),
        (string)($customer['address'] ?? ''),
        (string)($customer['phone'] ?? ''),
        db_created_at($doc),
        db_datetime_col($doc['updatedAt'] ?? null),
    ]);

    if (array_key_exists('items', $doc)) {
        $stmt = db()->prepare("DELETE FROM `{$p}order_items` WHERE `order_id` = ?");
        $stmt->execute([$id]);
        $stmt = db()->prepare(
            "INSERT INTO `{$p}order_items`
                (`order_id`, `product_id`, `name`, `image`, `price`, `cost_price`,
                 `qty`, `model`, `color`, `position`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $pos = 0;
        foreach (array_values((array)($doc['items'] ?? [])) as $item) {
            $stmt->execute([
                $id,
                (string)($item['productId'] ?? ''),
                (string)($item['name'] ?? ''),
                (string)($item['image'] ?? ''),
                (float)($item['price'] ?? 0),
                (float)($item['costPrice'] ?? 0),
                (int)($item['qty'] ?? 1),
                isset($item['model']) && $item['model'] !== '' ? (string)$item['model'] : null,
                isset($item['color']) && $item['color'] !== '' ? (string)$item['color'] : null,
                $pos++,
            ]);
        }
    }
}

/** Product reviews. */
function db_write_review(array $doc): void {
    $p = db_prefix();
    $stmt = db()->prepare(
        "INSERT INTO `{$p}reviews` (`id`, `product_id`, `user_name`, `rating`, `comment`, `created_at`)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            `product_id` = VALUES(`product_id`), `user_name` = VALUES(`user_name`),
            `rating` = VALUES(`rating`), `comment` = VALUES(`comment`)"
    );
    $stmt->execute([
        (string)$doc['_id'],
        (string)($doc['productId'] ?? ''),
        (string)($doc['userName'] ?? ''),
        max(0, min(255, (int)($doc['rating'] ?? 0))),
        (string)($doc['comment'] ?? ''),
        db_created_at($doc),
    ]);
}

/** Discount coupons. */
function db_write_coupon(array $doc): void {
    $p = db_prefix();
    $type = (string)($doc['type'] ?? 'percent');
    if (!in_array($type, ['percent', 'fixed'], true)) {
        $type = 'percent';
    }
    $stmt = db()->prepare(
        "INSERT INTO `{$p}coupons`
            (`id`, `code`, `type`, `value`, `min_order`, `expires_at`, `active`, `created_at`)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            `code` = VALUES(`code`), `type` = VALUES(`type`), `value` = VALUES(`value`),
            `min_order` = VALUES(`min_order`), `expires_at` = VALUES(`expires_at`),
            `active` = VALUES(`active`)"
    );
    $stmt->execute([
        (string)$doc['_id'],
        (string)($doc['code'] ?? ''),
        $type,
        (float)($doc['value'] ?? 0),
        (float)($doc['minOrder'] ?? 0),
        db_datetime_col($doc['expiresAt'] ?? null),
        !empty($doc['active']) ? 1 : 0,
        db_created_at($doc),
    ]);
}

/** Homepage ticker ads. */
function db_write_ad(array $doc): void {
    $p = db_prefix();
    $type = (string)($doc['type'] ?? 'image');
    if (!in_array($type, ['image', 'video'], true)) {
        $type = 'image';
    }
    $stmt = db()->prepare(
        "INSERT INTO `{$p}ads`
            (`id`, `title`, `media_url`, `type`, `link_url`, `sort_order`, `active`, `created_at`)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            `title` = VALUES(`title`), `media_url` = VALUES(`media_url`),
            `type` = VALUES(`type`), `link_url` = VALUES(`link_url`),
            `sort_order` = VALUES(`sort_order`), `active` = VALUES(`active`)"
    );
    $stmt->execute([
        (string)$doc['_id'],
        (string)($doc['title'] ?? ''),
        (string)($doc['mediaUrl'] ?? ''),
        $type,
        (string)($doc['linkUrl'] ?? ''),
        (int)($doc['order'] ?? 0),
        !empty($doc['active']) ? 1 : 0,
        db_created_at($doc),
    ]);
}

/** Settings: upsert the single row, then resync the category list. */
function db_write_settings(array $doc): void {
    $p = db_prefix();
    $shipping = (array)($doc['shipping'] ?? []);
    $currency = (array)($doc['currency'] ?? []);
    $stmt = db()->prepare(
        "INSERT INTO `{$p}settings`
            (`id`, `whatsapp`, `shipping_enabled`, `shipping_flat_rate`,
             `shipping_free_over`, `currency_symbol`, `currency_code`, `updated_at`)
         VALUES (1, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            `whatsapp` = VALUES(`whatsapp`),
            `shipping_enabled` = VALUES(`shipping_enabled`),
            `shipping_flat_rate` = VALUES(`shipping_flat_rate`),
            `shipping_free_over` = VALUES(`shipping_free_over`),
            `currency_symbol` = VALUES(`currency_symbol`),
            `currency_code` = VALUES(`currency_code`),
            `updated_at` = VALUES(`updated_at`)"
    );
    $stmt->execute([
        (string)($doc['whatsapp'] ?? ''),
        !empty($shipping['enabled']) ? 1 : 0,
        (float)($shipping['flatRate'] ?? 0),
        (float)($shipping['freeOver'] ?? 0),
        (string)($currency['symbol'] ?? '$'),
        (string)($currency['code'] ?? 'USD'),
        db_datetime_col($doc['updatedAt'] ?? null) ?? gmdate('Y-m-d H:i:s'),
    ]);

    if (array_key_exists('categories', $doc)) {
        db()->exec("DELETE FROM `{$p}categories`");
        $stmt = db()->prepare("INSERT INTO `{$p}categories` (`name`, `position`) VALUES (?, ?)");
        $pos = 0;
        foreach (array_values((array)($doc['categories'] ?? [])) as $name) {
            $name = trim((string)$name);
            if ($name === '') {
                continue;
            }
            $stmt->execute([$name, $pos++]);
        }
    }
}

/** Admin accounts. */
function db_write_user(array $doc): void {
    $p = db_prefix();
    $stmt = db()->prepare(
        "INSERT INTO `{$p}users` (`id`, `username`, `password_hash`, `created_at`)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            `username` = VALUES(`username`), `password_hash` = VALUES(`password_hash`)"
    );
    $stmt->execute([
        (string)$doc['_id'],
        (string)($doc['username'] ?? ''),
        (string)($doc['passwordHash'] ?? ''),
        db_created_at($doc),
    ]);
}

/** Sort documents in PHP by a Mongo-style sort spec (field => 1|-1). */
function db_sort_docs(array $rows, array $sort): array {
    usort($rows, function ($a, $b) use ($sort) {
        foreach ($sort as $field => $dir) {
            $cmp = db_compare(db_get_path($a, (string)$field), db_get_path($b, (string)$field));
            if ($cmp !== 0) {
                return ((int)$dir < 0) ? -$cmp : $cmp;
            }
        }
        return 0;
    });
    return $rows;
}

// ---------------------------------------------------------------- Store API
/** Query documents and return them as plain arrays (BSON types converted). */
function mongo_find(string $collection, array $filter = [], array $options = []): array {
    $matched = [];
    foreach (db_load_docs($collection, $filter) as $row) {
        if (db_matches($row, $filter)) {
            $matched[] = $row;
        }
    }
    if (!empty($options['sort']) && is_array($options['sort'])) {
        $matched = db_sort_docs($matched, $options['sort']);
    }
    $skip = max(0, (int)($options['skip'] ?? 0));
    if ($skip > 0) {
        $matched = array_slice($matched, $skip);
    }
    if (!empty($options['limit'])) {
        $matched = array_slice($matched, 0, (int)$options['limit']);
    }
    return array_map('bson_clean', $matched);
}

/** Query a single document or null. */
function mongo_find_one(string $collection, array $filter = [], array $options = []): ?array {
    $options['limit'] = 1;
    $rows = mongo_find($collection, $filter, $options);
    return $rows[0] ?? null;
}

/** Insert a document. A 24-hex id is generated when not provided. Returns the doc. */
function mongo_insert(string $collection, array $document): array {
    if (!isset($document['_id'])) {
        $document['_id'] = $collection === 'settings' ? db_settings_id() : new_id();
    }
    $document['_id'] = (string)db_scalar($document['_id']);
    db_replace($collection, $document['_id'], $document);
    return $document;
}

/** Update matching documents. Returns number of modified/upserted documents. */
function mongo_update(string $collection, array $filter, array $update, array $options = []): int {
    $multi = !empty($options['multi']);
    $upsert = !empty($options['upsert']);

    $rows = mongo_find($collection, $filter, ['limit' => $multi ? 0 : 1]);
    $changed = 0;
    foreach ($rows as $row) {
        db_replace($collection, (string)$row['_id'], db_apply_update($row, $update));
        $changed++;
    }

    if ($changed === 0 && $upsert) {
        $base = [];
        foreach ($filter as $key => $value) {
            if (!is_string($key) || $key === '' || $key[0] === '$') {
                continue;
            }
            if (is_scalar($value) || $value instanceof \MongoDB\BSON\ObjectId) {
                $base[$key] = db_scalar($value);
            }
        }
        mongo_insert($collection, db_apply_update($base, $update));
        $changed = 1;
    }
    return $changed;
}

/** Delete matching documents (child rows cascade). Returns rows removed. */
function mongo_delete(string $collection, array $filter): int {
    $rows = mongo_find($collection, $filter);
    if ($rows === []) {
        return 0;
    }
    $p = db_prefix();
    if ($collection === 'settings') {
        db()->exec("DELETE FROM `{$p}categories`");
        return (int)db()->exec("DELETE FROM `{$p}settings` WHERE `id` = 1");
    }
    $ids = array_map(fn($r) => (string)$r['_id'], $rows);
    $table = db_table($collection);
    $stmt = db()->prepare("DELETE FROM `$table` WHERE `id` IN (" . db_placeholders($ids) . ")");
    $stmt->execute($ids);
    return $stmt->rowCount();
}

/** Count documents matching the filter. */
function mongo_count(string $collection, array $filter = []): int {
    if ($filter === []) {
        $table = db_table($collection);
        return (int)db()->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    }
    return count(mongo_find($collection, $filter));
}

/** Recursively convert BSON values to JSON-friendly values. */
function bson_clean($value) {
    if ($value instanceof \MongoDB\BSON\ObjectId) {
        return (string)$value;
    }
    if ($value instanceof \MongoDB\BSON\UTCDateTime) {
        return $value->toDateTime()->format(\DateTimeInterface::ATOM);
    }
    if ($value instanceof \MongoDB\BSON\Regex) {
        return $value->getPattern();
    }
    if (is_object($value)) {
        $out = [];
        foreach (get_object_vars($value) as $k => $v) {
            $out[$k] = bson_clean($v);
        }
        return $out;
    }
    if (is_array($value)) {
        return array_map('bson_clean', $value);
    }
    return $value;
}

/** Validate a 24-hex id string; return it (lowercase) or null. */
function object_id(string $id): ?string {
    $id = strtolower(trim($id));
    return preg_match('/^[a-f0-9]{24}$/', $id) ? $id : null;
}

// ---------------------------------------------------------------- Auth
/** Create a signed admin token (payload.HMAC-SHA256). */
function create_token(array $user): string {
    global $CONFIG;
    $payload = json_encode([
        'sub' => (string)$user['_id'],
        'name' => $user['username'],
        'exp' => time() + 604800, // 7 days
    ]);
    return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=')
        . '.' . hash_hmac('sha256', $payload, $CONFIG['jwtSecret']);
}

/** Verify the Bearer token and return the admin user document, or send 401. */
function require_admin(): array {
    global $CONFIG;
    $header = bearer_token();
    if ($header === null || !str_starts_with($header, 'Bearer ')) {
        fail('Unauthorized', 401);
    }
    $token = trim(substr($header, 7));
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        fail('Unauthorized', 401);
    }
    [$payloadB64, $signature] = $parts;
    $payloadJson = base64_decode(strtr($payloadB64, '-_', '+/'), true);
    if ($payloadJson === false || !hash_equals(hash_hmac('sha256', $payloadJson, $CONFIG['jwtSecret']), $signature)) {
        fail('Unauthorized', 401);
    }
    $payload = json_decode($payloadJson, true);
    if (!is_array($payload) || ($payload['exp'] ?? 0) < time()) {
        fail('Token expired', 401);
    }
    $id = object_id((string)$payload['sub']);
    $user = $id ? mongo_find_one('users', ['_id' => $id]) : null;
    if (!$user) {
        fail('Unauthorized', 401);
    }
    return $user;
}

// ---------------------------------------------------------------- Store helpers (shared lib)
require __DIR__ . '/lib/store.php';
