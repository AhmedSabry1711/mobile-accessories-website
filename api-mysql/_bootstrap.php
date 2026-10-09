<?php
/**
 * Mobi Boost — API bootstrap (MySQL / PDO edition).
 *
 * Drop-in replacement for the MongoDB version of this file. It exposes the very
 * same helper surface used by every endpoint (mongo_find, mongo_find_one,
 * mongo_insert, mongo_update, mongo_delete, mongo_count, object_id, bson_clean,
 * require_admin, ...) but persists each "collection" as JSON rows in MySQL via
 * PDO — so the shop runs on standard Hostinger hosting without ext-mongodb.
 *
 * Collections map to tables:
 *   products, reviews, orders, coupons, ads, settings, users
 * Each table is (id CHAR(24) PK, doc LONGTEXT JSON, created_at DATETIME NULL).
 * Filters/sort/pagination are evaluated in PHP (the datasets are small); _id
 * lookups are pushed to SQL. See api-mysql/README.md for the trade-offs.
 */
declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');

/** @var array $CONFIG */
$CONFIG = require __DIR__ . '/config.php';

// Value-type polyfills — no-op when the real mongodb extension is loaded.
require __DIR__ . '/lib/mongo_compat.php';

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

/** Encode an associative document for the `doc` column. */
function db_json_encode(array $doc): string {
    $json = json_encode(db_encode_value($doc), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json === false ? '{}' : $json;
}

/** Best-effort createdAt value for the `created_at` column. */
function db_created_at(array $doc): string {
    $created = $doc['createdAt'] ?? null;
    if ($created instanceof \MongoDB\BSON\UTCDateTime) {
        return $created->toDateTime()->format('Y-m-d H:i:s');
    }
    if (is_string($created) && $created !== '') {
        $ts = strtotime($created);
        if ($ts !== false) {
            return gmdate('Y-m-d H:i:s', $ts);
        }
    }
    return gmdate('Y-m-d H:i:s');
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

/** Decode a row's JSON document into an array. */
function db_doc_to_array(string $json): array {
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

/**
 * Load candidate documents for a filter. _id equality is pushed to SQL; every
 * other filter is evaluated in PHP by the caller (db_matches).
 */
function db_load_docs(string $collection, array $filter): array {
    $table = db_table($collection);
    if (isset($filter['_id']) && !is_array($filter['_id'])) {
        $stmt = db()->prepare("SELECT doc FROM `$table` WHERE id = ?");
        $stmt->execute([(string)db_scalar($filter['_id'])]);
    } else {
        $stmt = db()->query("SELECT doc FROM `$table`");
    }
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $json) {
        $rows[] = db_doc_to_array((string)$json);
    }
    return $rows;
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

/** Persist a document back to its row. */
function db_replace(string $collection, string $id, array $doc): void {
    $table = db_table($collection);
    $stmt = db()->prepare("UPDATE `$table` SET doc = ?, created_at = COALESCE(created_at, ?) WHERE id = ?");
    $stmt->execute([db_json_encode($doc), db_created_at($doc), $id]);
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
        $document['_id'] = new_id();
    }
    $document['_id'] = (string)db_scalar($document['_id']);
    $table = db_table($collection);
    $stmt = db()->prepare("INSERT INTO `$table` (id, doc, created_at) VALUES (?, ?, ?)");
    $stmt->execute([$document['_id'], db_json_encode($document), db_created_at($document)]);
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

/** Delete matching documents. Returns number of deleted documents. */
function mongo_delete(string $collection, array $filter): int {
    $rows = mongo_find($collection, $filter);
    if ($rows === []) {
        return 0;
    }
    $ids = array_map(fn($r) => (string)$r['_id'], $rows);
    $table = db_table($collection);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("DELETE FROM `$table` WHERE id IN ($placeholders)");
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
