<?php
/**
 * Mobi Boost — one-shot admin-auth diagnostic (for the Hostinger install).
 *
 * Usage:
 *   1. copy this file into  public_html/api/  as  diag.php
 *   2. open  https://<your-domain>/api/diag.php?go=1
 *   3. read the report, then DELETE it from the server.
 *
 * It prints connection facts, the shape of the admin row, and replays the exact
 * token round-trip that the admin panel performs — so it says plainly whether the
 * problem is the admin row's `id`, the JWT secret, or the Authorization header.
 * It never prints the DB password or the password hash.
 */
declare(strict_types=1);

if (($_GET['go'] ?? '') !== '1') {
    http_response_code(403);
    exit("Add ?go=1 to the URL, e.g. /api/diag.php?go=1\n");
}

require __DIR__ . '/_bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

function row(string $label, $value): void {
    if (is_bool($value)) {
        $value = $value ? 'yes' : 'no';
    }
    printf("  %-26s %s\n", $label, (string)$value);
}

$CONFIG = $GLOBALS['CONFIG'];
$m = $CONFIG['mysql'];
$p = db_prefix();

echo "Mobi Boost — admin auth diagnostic\n";
echo str_repeat('=', 66), "\n\n";

// ---------------------------------------------------------------- 1. config
echo "1. config\n";
row('db host', $m['host']);
row('db name', $m['database']);
row('db user', $m['username']);
row('table prefix', $m['tablePrefix']);
row('password set', (string)$m['password'] !== '');
row('jwtSecret is the default', $GLOBALS['CONFIG']['jwtSecret'] === 'REPLACE_WITH_A_LONG_RANDOM_SECRET_STRING');
echo "\n";

// ---- the admin row ----------------------------------------------------------
echo "-- mb_users rows --\n";
$rows = db()->query("SELECT `id`, `username`, `created_at` FROM `{$p}users`")->fetchAll();
if (!$rows) {
    echo "  (table is EMPTY — there is no admin account at all;\n";
    echo "   every login will fail with 'Invalid credentials')\n";
} else {
    foreach ($rows as $r) {
        $id = (string)$r['id'];
        $ok = strlen($id) === 24 && ctype_xdigit($id);
        printf("  username : %s\n", (string)$r['username']);
        printf("  id       : %s\n", $id === '' ? '(empty string)' : $id);
        printf("  id chars : %d  %s\n", strlen($id), $ok ? 'OK' : '<< WRONG: must be exactly 24 hex chars (0-9 a-f)');
        printf("  created  : %s\n\n", (string)($r['created_at'] ?? '(null)'));
    }
}

// ---- end-to-end token round-trip -----------------------------------------
echo "-- token round-trip (what the admin panel does) --\n";
$user = mongo_find_one('users', ['username' => 'admin']);
if (!$user) {
    echo "  no account called 'admin' -> login returns 401 'Invalid credentials'\n";
} else {
    row('password_verify(admin123)', password_verify('admin123', (string)$user['passwordHash']));
    $token = create_token($user);
    $sub   = (string)$user['_id'];
    row('token sub (_id)', $sub === '' ? '(empty)' : $sub);
    $id = object_id($sub);
    row('object_id(sub)', $id ?? 'NULL  <<< this is what makes every authenticated request 401');
    row('re-lookup by sub', ($id !== null && mongo_find_one('users', ['_id' => $id])) ? 'found' : 'NOT FOUND  <<< require_admin() sends 401');
    row('token length', strlen($token));
}

// ---- what the server hands PHP -------------------------------------------
echo "\n-- request headers --\n";
row('HTTP_AUTHORIZATION', (string)($_SERVER['HTTP_AUTHORIZATION'] ?? 'ABSENT'));
row('REDIRECT_HTTP_AUTHORIZATION', (string)($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? 'ABSENT'));
row('getallheaders()', function_exists('getallheaders') ? 'available' : 'missing');
if (function_exists('getallheaders')) {
    foreach (getallheaders() as $name => $value) {
        if (stripos((string)$name, 'auth') !== false) {
            row('  header ' . $name, (string)$value);
        }
    }
}
row('bearer_token()', bearer_token() ?? 'none');

echo "\nDone. Delete this file now:  public_html/api/diag.php\n";
