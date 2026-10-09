<?php
/**
 * Mobi Boost — Admin settings (shipping rules, WhatsApp, categories).
 *
 * GET /api/admin/settings.php   current settings
 * PUT /api/admin/settings.php   update settings
 */
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_admin();
    ok(store_settings());
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    require_admin();

    $data = json_input();
    $errors = [];

    $whatsapp = trim((string)($data['whatsapp'] ?? ''));
    if ($whatsapp !== '' && !preg_match('/^[+]?[\d\s\-().]{6,20}$/', $whatsapp)) {
        $errors['whatsapp'] = 'Enter a valid phone number';
    }

    $shipping = (array)($data['shipping'] ?? []);
    $shippingRules = [
        'enabled' => !empty($shipping['enabled']),
        'flatRate' => (float)($shipping['flatRate'] ?? 0),
        'freeOver' => (float)($shipping['freeOver'] ?? 0),
    ];
    if ($shippingRules['flatRate'] < 0) {
        $errors['flatRate'] = 'Flat rate cannot be negative';
    }
    if ($shippingRules['freeOver'] < 0) {
        $errors['freeOver'] = 'Free-shipping threshold cannot be negative';
    }

    $currency = (array)($data['currency'] ?? []);
    $currencyRules = [
        'symbol' => trim((string)($currency['symbol'] ?? '$')) ?: '$',
        'code' => strtoupper(trim((string)($currency['code'] ?? 'USD'))) ?: 'USD',
    ];

    $categories = array_values(array_filter(array_map('trim', (array)($data['categories'] ?? [])), fn($c) => $c !== ''));
    if (count($categories) > 30) {
        $errors['categories'] = 'Maximum 30 categories';
    }

    if ($errors) {
        fail('Validation failed', 422, $errors);
    }

    save_settings([
        'whatsapp' => $whatsapp,
        'shipping' => $shippingRules,
        'currency' => $currencyRules,
        'categories' => $categories,
    ]);

    ok(store_settings());
}

fail('Method not allowed', 405);
