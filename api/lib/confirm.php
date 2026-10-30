<?php
/**
 * Mobi Boost — order confirmation links (printed as a QR on the shipping label).
 *
 * The QR contains a per-order code built from the order id plus a token derived
 * from a random per-order salt and the server secret (jwtSecret). The raw token
 * is never stored — the database only keeps the salt — so a database leak does
 * not hand out working confirmation links. Reprinting a label yields the same
 * code; rotating the salt (or the server secret) invalidates old labels.
 */
declare(strict_types=1);

/** 128-bit token -> 22 URL-safe base64 characters. */
const ORDER_CONFIRM_TOKEN_BYTES = 16;
const ORDER_CONFIRM_CODE_PATTERN = '/^([a-f0-9]{24})([A-Za-z0-9_-]{22})$/';

/** The secret part of the confirmation code. */
function order_confirm_token(string $orderId, string $salt): string {
    global $CONFIG;
    $secret = (string)($CONFIG['jwtSecret'] ?? '');
    $mac = hash_hmac('sha256', 'mobi-boost:order-confirm:v1:' . $orderId . ':' . $salt, $secret, true);
    return rtrim(strtr(base64_encode(substr($mac, 0, ORDER_CONFIRM_TOKEN_BYTES)), '+/', '-_'), '=');
}

/** The value that travels in the QR link: <24-hex order id><22-char token>. */
function order_confirm_code(string $orderId, string $salt): string {
    return $orderId . order_confirm_token($orderId, $salt);
}

/**
 * Split a confirmation code into its parts.
 *
 * @return array{id: string, token: string}|null
 */
function order_confirm_parse(string $code): ?array {
    if (!preg_match(ORDER_CONFIRM_CODE_PATTERN, $code, $m)) {
        return null;
    }
    return ['id' => $m[1], 'token' => $m[2]];
}

/**
 * Read the random per-order salt; optionally create it (used when a label is
 * printed for an order that was placed before this feature existed).
 */
function order_confirm_salt(string $orderId, bool $create = false): ?string {
    $ready = order_columns_ready();
    if (!$ready['confirm_salt']) {
        return null;
    }
    $p = db_prefix();
    $read = db()->prepare("SELECT `confirm_salt` FROM `{$p}orders` WHERE `id` = ?");
    $read->execute([$orderId]);
    $salt = $read->fetchColumn();
    if (is_string($salt) && $salt !== '') {
        return $salt;
    }
    if (!$create) {
        return null;
    }

    $salt = bin2hex(random_bytes(16));
    $write = db()->prepare(
        "UPDATE `{$p}orders` SET `confirm_salt` = ?
         WHERE `id` = ? AND (`confirm_salt` IS NULL OR `confirm_salt` = '')"
    );
    $write->execute([$salt, $orderId]);
    if ($write->rowCount() === 1) {
        return $salt;
    }

    // Another request created it first — return the stored value.
    $read->execute([$orderId]);
    $salt = $read->fetchColumn();
    return is_string($salt) && $salt !== '' ? $salt : null;
}

/** Constant-time token check for an order id. */
function order_confirm_matches(string $orderId, string $token): bool {
    $salt = order_confirm_salt($orderId);
    if ($salt === null) {
        return false;
    }
    return hash_equals(order_confirm_token($orderId, $salt), $token);
}

/** Site-absolute URL when APP_URL is configured; null otherwise. */
function order_confirm_absolute_url(string $path): ?string {
    global $CONFIG;
    $base = trim((string)($CONFIG['appUrl'] ?? ''));
    return $base !== '' ? rtrim($base, '/') . $path : null;
}

/**
 * Public site base URL, in priority order:
 *   1. config.php → appUrl (set this in production, e.g. https://mobiboost.com)
 *   2. the browser Origin of the admin request that asked for the label
 *   3. the Referer (admin page URL) — the dev proxy strips no referrer and the
 *      SPA usually runs on a different port than /api during development
 *   4. the current request host (works when the SPA and /api share a domain)
 * A trailing slash is never included.
 */
function order_confirm_site_url(): string {
    $configured = trim((string)order_confirm_absolute_url(''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $pattern = '#^(https?://[a-z0-9.\-]+(?::[0-9]+)?)(?:/|$)#i';
    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $header) {
        $value = trim((string)($_SERVER[$header] ?? ''));
        if ($value !== '' && preg_match($pattern, $value, $m)) {
            return $m[1];
        }
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (preg_match('#^[a-z0-9.\-]+(:[0-9]+)?$#i', $host)) {
        return ($https ? 'https://' : 'http://') . $host;
    }
    return ($https ? 'https://' : 'http://') . 'localhost';
}

/** Full confirmation URL for a QR code: <site>/confirm/<code>. */
function order_confirm_url(string $code): string {
    return order_confirm_site_url() . '/confirm/' . rawurlencode($code);
}

/**
 * Minimal public payload for the confirmation page: no customer name, phone,
 * address or cost data — only what the shopper needs to recognise the order.
 */
function order_confirm_payload(array $order): array {
    $settings = store_settings();
    $items = [];
    foreach ((array)($order['items'] ?? []) as $item) {
        $items[] = [
            'name' => (string)($item['name'] ?? ''),
            'model' => isset($item['model']) && $item['model'] !== null ? (string)$item['model'] : null,
            'color' => isset($item['color']) && $item['color'] !== null ? (string)$item['color'] : null,
            'qty' => (int)($item['qty'] ?? 0),
        ];
    }
    return [
        'orderNumber' => (string)($order['orderNumber'] ?? ''),
        'status' => (string)($order['status'] ?? 'pending'),
        'createdAt' => $order['createdAt'] ?? null,
        'confirmedAt' => $order['confirmedAt'] ?? null,
        'items' => $items,
        'itemCount' => array_sum(array_column($items, 'qty')),
        'total' => (float)($order['total'] ?? 0),
        'currency' => [
            'symbol' => (string)($settings['currency']['symbol'] ?? '$'),
            'code' => (string)($settings['currency']['code'] ?? 'USD'),
        ],
    ];
}
