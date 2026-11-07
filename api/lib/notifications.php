<?php
/**
 * Mobi Boost — outgoing notifications.
 *
 * New orders are pushed to the shop owner's WhatsApp through CallMeBot:
 *   https://www.callmebot.com/blog/free-api-whatsapp-messages/
 *
 * Credentials must never land in Git: this repository is public. They are read
 * from the CALLMEBOT_* environment variables or from api/config.private.php
 * (git-ignored and excluded from deploy.ps1). When neither is configured the
 * notifier quietly does nothing, so a missing key can never block a sale.
 *
 * Delivery is best-effort: any network/API error is swallowed. The order is
 * already saved and the stock already reserved when this runs, and the shopper
 * must not see a failure because WhatsApp was slow.
 */
declare(strict_types=1);

/** Resolve the CallMeBot settings once per request. */
function whatsapp_config(): array {
    global $CONFIG;
    static $resolved = null;
    if ($resolved !== null) {
        return $resolved;
    }

    $cfg = (array)($CONFIG['whatsapp'] ?? []);

    $privateFile = __DIR__ . '/../config.private.php';
    if (is_file($privateFile)) {
        $private = require $privateFile;
        if (is_array($private)) {
            // Accept both a flat file and a ['whatsapp' => [...]] block.
            $wa = isset($private['whatsapp']) && is_array($private['whatsapp'])
                ? $private['whatsapp']
                : $private;
            foreach (['enabled', 'phone', 'apiKey'] as $key) {
                if (array_key_exists($key, $wa)) {
                    $cfg[$key] = $wa[$key];
                }
            }
        }
    }

    $resolved = [
        'enabled' => !array_key_exists('enabled', $cfg) || !empty($cfg['enabled']),
        'phone' => preg_replace('/\D+/', '', (string)($cfg['phone'] ?? '')),
        'apiKey' => trim((string)($cfg['apiKey'] ?? '')),
    ];
    return $resolved;
}

/** Format a money amount as "123.45 $" (or the configured currency symbol). */
function notify_money(float $amount): string {
    global $CONFIG;
    $symbol = (string)($CONFIG['currencySymbol'] ?? '$');
    return number_format($amount, 2, '.', '') . ' ' . $symbol;
}

/** Build the Arabic WhatsApp message for a freshly placed order. */
function whatsapp_order_message(array $order): string {
    $customer = (array)($order['customer'] ?? []);
    $items = array_values((array)($order['items'] ?? []));

    $lines = [];
    $lines[] = '🛒 طلب جديد — Mobi Boost';
    $lines[] = '';
    $lines[] = 'رقم الطلب: ' . (string)($order['orderNumber'] ?? '');
    $lines[] = 'العميل: ' . (string)($customer['name'] ?? '');
    $lines[] = 'الموبايل: ' . (string)($customer['phone'] ?? '');
    $lines[] = 'العنوان: ' . (string)($customer['address'] ?? '');
    $lines[] = '';
    $lines[] = 'المنتجات:';
    foreach ($items as $item) {
        $label = (string)($item['name'] ?? '');
        $variant = trim(
            (string)($item['model'] ?? '') . (($item['model'] ?? '') && ($item['color'] ?? '') ? ' / ' : '') . (string)($item['color'] ?? ''),
            " /"
        );
        $qty = (int)($item['qty'] ?? 0);
        $unit = (float)($item['price'] ?? 0);
        $line = '• ' . $qty . '× ' . $label;
        if ($variant !== '') {
            $line .= ' (' . $variant . ')';
        }
        $line .= ' — ' . notify_money($qty * $unit);
        $lines[] = $line;
    }
    $lines[] = '';

    $discount = (float)($order['discount'] ?? 0);
    if ($discount > 0) {
        $coupon = (string)($order['couponCode'] ?? '');
        $lines[] = 'الخصم' . ($coupon !== '' ? " ($coupon)" : '') . ': -' . notify_money($discount);
    }
    $shipping = (float)($order['shippingFee'] ?? 0);
    $lines[] = 'الشحن: ' . ($shipping > 0 ? notify_money($shipping) : 'مجاني');
    $lines[] = 'الإجمالي: ' . notify_money((float)($order['total'] ?? 0));

    return implode("\n", $lines);
}

/**
 * Send one WhatsApp message. Returns true when the API accepted it.
 * Never throws: a notification failure must not break checkout.
 */
function whatsapp_send(string $text): bool {
    $config = whatsapp_config();
    if (!$config['enabled'] || $config['phone'] === '' || $config['apiKey'] === '') {
        return false;
    }

    $url = 'https://api.callmebot.com/whatsapp.php?' . http_build_query([
        'phone' => $config['phone'],
        'text' => $text,
        'apikey' => $config['apiKey'],
    ]);

    try {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 6,
                CURLOPT_USERAGENT => 'MobiBoost/1.0',
            ]);
            $body = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            // CallMeBot answers 200 with a short confirmation, or 4xx/5xx text.
            return $body !== false && $status >= 200 && $status < 300;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 6,
                'ignore_errors' => true,
                'user_agent' => 'MobiBoost/1.0',
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        return $body !== false;
    } catch (Throwable $e) {
        error_log('whatsapp notification failed: ' . $e->getMessage());
        return false;
    }
}

/** Notify the owner about a new order. Silent on purpose. */
function notify_order_whatsapp(array $order): bool {
    try {
        return whatsapp_send(whatsapp_order_message($order));
    } catch (Throwable $e) {
        error_log('whatsapp notification failed: ' . $e->getMessage());
        return false;
    }
}
